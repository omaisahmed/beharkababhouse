<?php

namespace App\Services;

use App\Enums\StockMovementType;
use App\Models\InventoryItem;
use App\Models\Purchase;
use App\Models\StockMovement;
use App\Models\User;
use App\Support\Money;
use App\Support\Units;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryService
{
    public function __construct(protected AuditService $audit) {}

    public function adjust(InventoryItem $item, int $quantityMilli, StockMovementType $type, string $reason, User $user, ?int $unitCostMinor = null): StockMovement
    {
        if ($quantityMilli === 0) {
            throw ValidationException::withMessages(['quantity' => 'Enter a quantity other than zero.']);
        }

        if (in_array($type, [StockMovementType::Wastage, StockMovementType::Damage], true)) {
            $quantityMilli = -abs($quantityMilli);
        }

        if ($type === StockMovementType::Opening && $quantityMilli < 0) {
            throw ValidationException::withMessages(['quantity' => 'Opening stock must be a positive quantity.']);
        }

        return DB::transaction(function () use ($item, $quantityMilli, $type, $reason, $user, $unitCostMinor) {
            $cost = $unitCostMinor ?? $item->cost_per_unit_minor;

            $movement = StockMovement::query()->create([
                'inventory_item_id' => $item->id,
                'type' => $type,
                'quantity_milli' => $quantityMilli,
                'unit_cost_minor' => $cost,
                'total_cost_minor' => Money::divideRounded(abs($quantityMilli) * $cost, 1000),
                'reason' => $reason,
                'user_id' => $user->id,
            ]);

            $item->stock_milli += $quantityMilli;

            if ($type === StockMovementType::Opening) {
                $item->cost_per_unit_minor = $cost;
            }

            $item->save();

            $this->audit->log(
                'inventory.adjusted',
                $type->label().' of '.Units::stockLabel(abs($quantityMilli), $item->unit).' for '.$item->name.': '.$reason,
                $item,
                ['quantity_milli' => $quantityMilli, 'type' => $type->value, 'reason' => $reason],
                $user,
            );

            return $movement;
        });
    }

    public function purchase(array $data, User $user): Purchase
    {
        return DB::transaction(function () use ($data, $user) {
            $purchase = Purchase::query()->create([
                'purchase_number' => app(OrderNumberService::class)->nextPurchaseNumber(),
                'supplier_id' => $data['supplier_id'] ?? null,
                'user_id' => $user->id,
                'purchased_at' => $data['purchased_at'] ?? today(),
                'payment_status' => $data['payment_status'] ?? 'unpaid',
                'total_minor' => 0,
                'paid_minor' => 0,
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'received_at' => now(),
            ]);

            $total = 0;
            $paid = 0;

            foreach ($data['items'] as $row) {
                $item = InventoryItem::query()->findOrFail($row['inventory_item_id']);
                $inputUnit = $row['input_unit'] ?? $row['unit'] ?? $item->unit;
                $quantityMilli = Units::toMilli($row['quantity'], $inputUnit, $item->unit);
                $costPerUnit = Money::parse($row['unit_cost'] ?? 0);
                $lineTotal = Money::divideRounded($quantityMilli * $costPerUnit, 1000);

                if ($quantityMilli <= 0) {
                    throw ValidationException::withMessages(['items' => 'Purchase quantities must be greater than zero.']);
                }

                $purchase->items()->create([
                    'inventory_item_id' => $item->id,
                    'quantity_milli' => $quantityMilli,
                    'input_unit' => $inputUnit,
                    'unit_cost_minor' => $costPerUnit,
                    'total_minor' => $lineTotal,
                ]);

                $item->stock_milli += $quantityMilli;
                $item->cost_per_unit_minor = $costPerUnit;
                $item->save();

                StockMovement::query()->create([
                    'inventory_item_id' => $item->id,
                    'type' => StockMovementType::Purchase,
                    'quantity_milli' => $quantityMilli,
                    'unit_cost_minor' => $costPerUnit,
                    'total_cost_minor' => $lineTotal,
                    'reason' => 'Purchase '.$purchase->purchase_number,
                    'reference_type' => $purchase->getMorphClass(),
                    'reference_id' => $purchase->id,
                    'idempotency_key' => 'purchase:'.$purchase->id.':item:'.$item->id,
                    'user_id' => $user->id,
                ]);

                $total += $lineTotal;
            }

            if (($data['payment_status'] ?? 'unpaid') === 'paid') {
                $paid = $total;
            } elseif (($data['payment_status'] ?? 'unpaid') === 'partial') {
                $paid = min($total, Money::parse($data['paid'] ?? 0));
            }

            $purchase->forceFill([
                'total_minor' => $total,
                'paid_minor' => $paid,
                'payment_status' => $paid >= $total ? 'paid' : ($paid > 0 ? 'partial' : 'unpaid'),
            ])->save();

            $this->audit->log(
                'purchase.created',
                'Recorded purchase '.$purchase->purchase_number.' for '.Money::format($total).'.',
                $purchase,
                ['total_minor' => $total, 'items' => count($data['items'])],
                $user,
            );

            return $purchase->fresh(['items.inventoryItem', 'supplier']);
        });
    }
}
