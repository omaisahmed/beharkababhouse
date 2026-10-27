<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\StockMovementType;
use App\Models\InventoryItem;
use App\Models\Order;
use App\Models\Product;
use App\Models\Refund;
use App\Models\StockMovement;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RefundService
{
    public function __construct(
        protected OrderNumberService $numbers,
        protected AuditService $audit,
        protected SettingsService $settings,
    ) {}

    public function refund(Order $order, User $user, int $amountMinor, PaymentMethod $method, string $reason, bool $restock = false): Refund
    {
        if ($order->isCancelled()) {
            throw ValidationException::withMessages(['refund' => 'Cancelled orders cannot be refunded.']);
        }

        if (! $order->isPaid()) {
            throw ValidationException::withMessages(['refund' => 'This order has not been paid.']);
        }

        $refundable = max(0, $order->paid_minor - $order->refunded_minor);

        if ($refundable <= 0) {
            throw ValidationException::withMessages(['refund' => 'This order has already been fully refunded.']);
        }

        if ($amountMinor <= 0) {
            throw ValidationException::withMessages(['amount' => 'Enter a refund amount greater than zero.']);
        }

        if ($amountMinor > $refundable) {
            throw ValidationException::withMessages([
                'amount' => 'The refund cannot exceed the remaining paid amount of '.$this->settings->money($refundable).'.',
            ]);
        }

        $shift = $user->openShift();

        if ($method === PaymentMethod::Cash && $this->settings->bool('orders.require_shift', true) && ! $shift) {
            throw ValidationException::withMessages(['shift' => 'Open a shift before issuing a cash refund.']);
        }

        $isFull = $amountMinor >= $refundable;

        return DB::transaction(function () use ($order, $user, $amountMinor, $method, $reason, $restock, $shift, $isFull) {
            $ratioBp = $isFull ? 10000 : intdiv($amountMinor * 10000, max(1, $order->paid_minor));

            $subtotal = $isFull ? $order->net_minor : Money::percent($order->net_minor, $ratioBp);
            $tax = $isFull ? $order->tax_minor : Money::percent($order->tax_minor, $ratioBp);
            $service = $isFull ? $order->service_charge_minor : Money::percent($order->service_charge_minor, $ratioBp);
            $delivery = $isFull ? $order->delivery_fee_minor : Money::percent($order->delivery_fee_minor, $ratioBp);

            $refund = Refund::query()->create([
                'client_uuid' => (string) Str::uuid(),
                'refund_number' => $this->numbers->nextRefundNumber(),
                'order_id' => $order->id,
                'payment_id' => $order->payments()->latest('paid_at')->value('id'),
                'cashier_shift_id' => $shift?->id,
                'user_id' => $user->id,
                'method' => $method,
                'amount_minor' => $amountMinor,
                'subtotal_minor' => $subtotal,
                'tax_minor' => $tax,
                'service_charge_minor' => $service,
                'delivery_fee_minor' => $delivery,
                'reason' => $reason,
            ]);

            $newRefunded = $order->refunded_minor + $amountMinor;

            $order->forceFill([
                'refunded_minor' => $newRefunded,
                'payment_status' => $newRefunded >= $order->paid_minor
                    ? PaymentStatus::Refunded
                    : PaymentStatus::PartiallyRefunded,
            ])->save();

            if ($method === PaymentMethod::Cash && $shift) {
                $shift->increment('cash_refunds_minor', $amountMinor);
            }

            if ($restock && $isFull) {
                $this->restockOrder($order, $user, $refund);
            }

            $this->audit->log(
                'order.refunded',
                'Refunded '.$this->settings->money($amountMinor).' on '.$order->order_number.' via '.$method->label().'.',
                $order,
                [
                    'refund_id' => $refund->id,
                    'amount_minor' => $amountMinor,
                    'method' => $method->value,
                    'restocked' => $restock && $isFull,
                    'reason' => $reason,
                ],
                $user,
            );

            return $refund->fresh();
        });
    }

    public function void(Order $order, User $user, string $reason): Order
    {
        if ($order->isCancelled()) {
            throw ValidationException::withMessages(['order' => 'This order is already cancelled.']);
        }

        return DB::transaction(function () use ($order, $user, $reason) {
            $refundable = max(0, $order->paid_minor - $order->refunded_minor);

            if ($order->isPaid() && $refundable > 0) {
                $method = $order->payments()->latest('paid_at')->first()?->method ?? PaymentMethod::Cash;
                $this->refund($order, $user, $refundable, $method, 'Voided: '.$reason, true);
            } elseif ($order->inventory_deducted_at) {
                $this->restockOrder($order->fresh(), $user);
            }

            $order->forceFill([
                'status' => OrderStatus::Cancelled,
                'cancelled_at' => now(),
                'cancelled_by' => $user->id,
                'cancellation_reason' => $reason,
            ])->save();

            app(PosService::class)->releaseTable($order->table);

            $this->audit->log(
                'order.voided',
                'Voided order '.$order->order_number.': '.$reason,
                $order,
                ['reason' => $reason, 'paid_minor' => $order->paid_minor, 'refunded_minor' => $order->refunded_minor],
                $user,
            );

            return $order->fresh(['items.modifiers', 'payments', 'refunds']);
        });
    }

    protected function restockOrder(Order $order, User $user, ?Refund $refund = null): void
    {
        if (! $order->inventory_deducted_at) {
            return;
        }

        $order->loadMissing('items');

        foreach ($order->items as $item) {
            $product = Product::query()
                ->with(['recipe.ingredients.inventoryItem'])
                ->find($item->product_id);

            if (! $product || ! $product->recipe || ! $product->recipe->is_active) {
                continue;
            }

            $yield = max(1, (int) $product->recipe->yield_quantity);

            foreach ($product->recipe->ingredients as $ingredient) {
                $quantity = Money::divideRounded($ingredient->quantity_milli * $item->quantity, $yield);

                if ($quantity <= 0) {
                    continue;
                }

                $inventory = $ingredient->inventoryItem;

                if (! $inventory) {
                    continue;
                }

                $key = 'refund:'.($refund?->id ?? 'void').':line:'.$item->id.':inv:'.$inventory->id;

                if (StockMovement::query()->where('idempotency_key', $key)->exists()) {
                    continue;
                }

                $cost = Money::divideRounded($quantity * $inventory->cost_per_unit_minor, 1000);

                StockMovement::query()->create([
                    'inventory_item_id' => $inventory->id,
                    'type' => StockMovementType::Return,
                    'quantity_milli' => $quantity,
                    'unit_cost_minor' => $inventory->cost_per_unit_minor,
                    'total_cost_minor' => $cost,
                    'reason' => 'Restock from '.($refund ? 'refund '.$refund->refund_number : 'void '.$order->order_number),
                    'reference_type' => $order->getMorphClass(),
                    'reference_id' => $order->id,
                    'idempotency_key' => $key,
                    'user_id' => $user->id,
                ]);

                InventoryItem::query()->whereKey($inventory->id)->increment('stock_milli', $quantity);
            }
        }

        if ($refund) {
            $refund->forceFill(['restocked_at' => now()])->save();
        }
    }
}
