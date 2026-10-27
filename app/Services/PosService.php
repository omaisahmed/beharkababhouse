<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\StockMovementType;
use App\Enums\TableStatus;
use App\Enums\UserRole;
use App\Models\InventoryItem;
use App\Models\Order;
use App\Models\Product;
use App\Models\RestaurantTable;
use App\Models\StockMovement;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PosService
{
    public function __construct(
        protected SettingsService $settings,
        protected OrderNumberService $numbers,
        protected AuditService $audit,
    ) {}

    /**
     * @return array{lines: array<int, array<string, mixed>>, totals: array<string, int|bool|string>}
     */
    public function quote(array $items, ?string $discountType, ?string $discountValue, ?string $deliveryFee, User $user, string $orderType = 'takeaway'): array
    {
        $lines = $this->buildLines($items);
        $totals = $this->calculateTotals($lines, $discountType, $discountValue, $deliveryFee, $user);

        return ['lines' => $lines, 'totals' => $totals];
    }

    public function hold(array $data, User $user): Order
    {
        $resume = $this->findResumable($data['resume_order'] ?? null);

        if (! $resume && ($existing = Order::query()->where('client_uuid', $data['client_uuid'])->first())) {
            return $existing;
        }

        $orderType = OrderType::from($data['order_type']);
        $table = $this->resolveTable($orderType, $data['table_id'] ?? null, $resume);

        return DB::transaction(function () use ($data, $user, $resume, $orderType, $table) {
            $quoted = $this->quote(
                $data['items'],
                $data['discount_type'] ?? null,
                $data['discount_value'] ?? null,
                $data['delivery_fee'] ?? null,
                $user,
                $orderType->value,
            );

            $order = $resume ?? $this->newOrder($user);
            $this->fillOrder($order, $data, $orderType, $table, $quoted['totals'], $user);
            $order->status = OrderStatus::Held;
            $order->payment_status = PaymentStatus::Unpaid;
            $order->held_at = now();
            $order->kitchen_required = (bool) $this->settings->bool('kitchen.enabled', true);
            $order->save();
            $this->replaceItems($order, $quoted['lines'], $quoted['totals']['shares']);

            $this->occupyTable($table);

            $this->audit->log(
                $resume ? 'order.held_updated' : 'order.held',
                'Held order '.$order->order_number.' for '.$this->settings->money($order->total_minor).'.',
                $order,
                ['total_minor' => $order->total_minor, 'items' => count($quoted['lines'])],
                $user,
            );

            return $order->fresh(['items.modifiers', 'table']);
        });
    }

    public function checkout(array $data, User $user): Order
    {
        $resume = $this->findResumable($data['resume_order'] ?? null);

        if (! $resume && ($existing = Order::query()->where('client_uuid', $data['client_uuid'])->first())) {
            return $existing;
        }

        $orderType = OrderType::from($data['order_type']);
        $table = $this->resolveTable($orderType, $data['table_id'] ?? null, $resume);

        $shift = $this->settings->bool('orders.require_shift', true)
            ? $user->openShift()
            : $user->openShift();

        if ($this->settings->bool('orders.require_shift', true) && ! $shift) {
            throw ValidationException::withMessages([
                'shift' => 'Open a cashier shift before completing a sale.',
            ]);
        }

        return DB::transaction(function () use ($data, $user, $resume, $orderType, $table, $shift) {
            $quoted = $this->quote(
                $data['items'],
                $data['discount_type'] ?? null,
                $data['discount_value'] ?? null,
                $data['delivery_fee'] ?? null,
                $user,
                $orderType->value,
            );

            $totals = $quoted['totals'];
            $total = (int) $totals['total_minor'];

            if ($total <= 0) {
                throw ValidationException::withMessages(['cart' => 'The order total must be greater than zero.']);
            }

            $paymentInput = $data['payment'];
            $method = PaymentMethod::from($paymentInput['method']);
            $amount = Money::parse($paymentInput['amount'] ?? 0);

            if ($amount < $total) {
                throw ValidationException::withMessages([
                    'payment.amount' => 'The payment does not cover the order total of '.$this->settings->money($total).'.',
                ]);
            }

            $tendered = Money::parse($paymentInput['tendered'] ?? $amount);
            $change = 0;

            if ($method === PaymentMethod::Cash) {
                if ($tendered < $total) {
                    throw ValidationException::withMessages([
                        'payment.tendered' => 'Cash received is less than the order total.',
                    ]);
                }

                $change = $tendered - $total;
            }

            $order = $resume ?? $this->newOrder($user);
            $this->fillOrder($order, $data, $orderType, $table, $totals, $user);
            $order->payment_status = PaymentStatus::Paid;
            $order->paid_minor = $total;
            $order->held_at = null;
            $order->confirmed_at = now();
            $order->cashier_shift_id = $shift?->id;

            $kitchen = $this->settings->bool('kitchen.enabled', true);

            if ($kitchen) {
                $order->status = OrderStatus::Confirmed;
                $order->kitchen_required = true;
            } else {
                $order->status = OrderStatus::Completed;
                $order->completed_at = now();
                $order->kitchen_required = false;
            }

            $order->save();

            $this->replaceItems($order, $quoted['lines'], $quoted['totals']['shares']);

            $order->payments()->create([
                'cashier_shift_id' => $shift?->id,
                'user_id' => $user->id,
                'method' => $method,
                'status' => 'completed',
                'amount_minor' => $total,
                'tendered_minor' => $method === PaymentMethod::Cash ? $tendered : null,
                'change_minor' => $change,
                'reference' => $paymentInput['reference'] ?? null,
                'paid_at' => now(),
            ]);

            if ($method === PaymentMethod::Cash && $shift) {
                $shift->increment('cash_sales_minor', $total);
            }

            $this->deductInventory($order, $user);
            $this->occupyTable($table);

            $this->audit->log(
                'order.checkout',
                'Completed order '.$order->order_number.' for '.$this->settings->money($total).' via '.$method->label().'.',
                $order,
                ['total_minor' => $total, 'method' => $method->value, 'change_minor' => $change],
                $user,
            );

            return $order->fresh(['items.modifiers', 'payments', 'table', 'shift']);
        });
    }

    public function cancelHeld(Order $order, User $user, ?string $reason = null): void
    {
        DB::transaction(function () use ($order, $user, $reason) {
            if (! $order->isHeld()) {
                throw ValidationException::withMessages(['order' => 'Only held orders can be discarded.']);
            }

            $order->forceFill([
                'status' => OrderStatus::Cancelled,
                'cancelled_at' => now(),
                'cancelled_by' => $user->id,
                'cancellation_reason' => $reason ?: 'Held order discarded before payment.',
            ])->save();

            $this->releaseTable($order->table);

            $this->audit->log('order.discarded', 'Discarded held order '.$order->order_number.'.', $order, [], $user);
        });
    }

    protected function newOrder(User $user): Order
    {
        $number = $this->numbers->next();

        $order = new Order;
        $order->forceFill([
            'order_number' => $number['order_number'],
            'sequence_date' => $number['sequence_date'],
            'daily_sequence' => $number['daily_sequence'],
            'user_id' => $user->id,
            'cashier_shift_id' => $user->openShift()?->id,
        ]);

        return $order;
    }

    protected function fillOrder(Order $order, array $data, OrderType $orderType, ?RestaurantTable $table, array $totals, User $user): void
    {
        $order->fill([
            'client_uuid' => $order->client_uuid ?: $data['client_uuid'],
            'order_type' => $orderType,
            'restaurant_table_id' => $table?->id,
            'customer_name' => $data['customer_name'] ?? null,
            'customer_phone' => $data['customer_phone'] ?? null,
            'customer_address' => $data['customer_address'] ?? null,
            'delivery_instructions' => $data['delivery_instructions'] ?? null,
            'notes' => $data['notes'] ?? null,
            'gross_minor' => $totals['gross_minor'],
            'item_discount_minor' => $totals['item_discount_minor'],
            'subtotal_minor' => $totals['subtotal_minor'],
            'order_discount_minor' => $totals['order_discount_minor'],
            'discount_minor' => $totals['discount_minor'],
            'net_minor' => $totals['net_minor'],
            'service_charge_minor' => $totals['service_charge_minor'],
            'delivery_fee_minor' => $totals['delivery_fee_minor'],
            'tax_minor' => $totals['tax_minor'],
            'total_minor' => $totals['total_minor'],
            'tax_rate_bp' => $totals['tax_rate_bp'],
            'tax_inclusive' => $totals['tax_inclusive'],
            'tax_label' => $totals['tax_label'],
            'service_charge_label' => $totals['service_charge_label'],
            'cogs_minor' => $totals['cogs_minor'],
            'user_id' => $order->user_id ?: $user->id,
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function buildLines(array $items): array
    {
        $ids = collect($items)->pluck('product_id')->map(fn ($id) => (int) $id)->unique();

        $products = Product::query()
            ->with(['category', 'variants', 'modifierGroups.modifiers', 'recipe.ingredients.inventoryItem'])
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        $lines = [];

        foreach (array_values($items) as $index => $item) {
            $product = $products->get((int) $item['product_id']);

            if (! $product) {
                throw ValidationException::withMessages(['items' => 'A selected product no longer exists.']);
            }

            if (! $product->is_available) {
                throw ValidationException::withMessages([
                    'items' => $product->name.' is currently unavailable.',
                ]);
            }

            $quantity = (int) $item['quantity'];

            if ($quantity < 1 || $quantity > 99) {
                throw ValidationException::withMessages(['items' => 'Quantities must be between 1 and 99.']);
            }

            $variant = null;

            if (! empty($item['variant_id'])) {
                $variant = $product->variants->firstWhere('id', (int) $item['variant_id']);

                if (! $variant || ! $variant->is_active) {
                    throw ValidationException::withMessages(['items' => 'The selected size is not available for '.$product->name.'.']);
                }
            } elseif ($product->variants->where('is_active', true)->isNotEmpty()) {
                $activeVariants = $product->variants->where('is_active', true);
                $variant = $activeVariants->firstWhere('is_default', true) ?? $activeVariants->first();
            }

            $unitPrice = $product->price_minor + ($variant?->price_delta_minor ?? 0);

            if ($unitPrice < 0) {
                $unitPrice = 0;
            }

            $selectedIds = array_values(array_unique(array_map('intval', $item['modifier_ids'] ?? [])));
            $groups = $product->modifierGroups->where('is_active', true)->keyBy('id');
            $modifiers = collect();
            $counts = [];

            foreach ($selectedIds as $modifierId) {
                $modifier = $groups->flatMap(fn ($group) => $group->modifiers)->firstWhere('id', $modifierId);

                if (! $modifier || ! $modifier->is_active) {
                    throw ValidationException::withMessages([
                        'items' => 'One of the selected add-ons is no longer available for '.$product->name.'.',
                    ]);
                }

                $counts[$modifier->modifier_group_id] = ($counts[$modifier->modifier_group_id] ?? 0) + 1;
                $modifiers->push($modifier);
            }

            foreach ($groups as $group) {
                $selectedCount = $counts[$group->id] ?? 0;
                $minimum = max((int) $group->min_selections, $group->is_required ? 1 : 0);

                if ($selectedCount < $minimum) {
                    throw ValidationException::withMessages([
                        'items' => $product->name.' requires a selection for '.$group->name.'.',
                    ]);
                }

                if ($group->max_selections > 0 && $selectedCount > $group->max_selections) {
                    throw ValidationException::withMessages([
                        'items' => 'Too many selections for '.$group->name.' on '.$product->name.'.',
                    ]);
                }
            }

            $modifierTotal = (int) $modifiers->sum('price_minor') * $quantity;
            $lineTotal = ($unitPrice * $quantity) + $modifierTotal;
            $taxRate = $product->tax_rate_bp !== null
                ? (int) $product->tax_rate_bp
                : ($this->settings->bool('tax.enabled') ? $this->settings->int('tax.rate_bp') : 0);

            $recipe = $product->recipe && $product->recipe->is_active ? $product->recipe : null;
            $unitCost = 0;

            if ($recipe) {
                foreach ($recipe->ingredients as $ingredient) {
                    $unitCost += Money::divideRounded(
                        $ingredient->quantity_milli * ($ingredient->inventoryItem?->cost_per_unit_minor ?? 0),
                        1000,
                    );
                }
            } elseif ($product->cost_minor !== null) {
                $unitCost = (int) $product->cost_minor;
            }

            $lines[] = [
                'product_id' => $product->id,
                'category_id' => $product->category_id,
                'product_variant_id' => $variant?->id,
                'product_name' => $product->name,
                'sku' => $product->sku,
                'category_name' => $product->category?->name,
                'variant_name' => $variant?->name,
                'unit_price_minor' => $unitPrice,
                'quantity' => $quantity,
                'modifiers_total_minor' => $modifierTotal,
                'line_total_minor' => $lineTotal,
                'is_taxable' => (bool) $product->is_taxable,
                'tax_rate_bp' => $taxRate,
                'notes' => isset($item['notes']) ? trim((string) $item['notes']) : null,
                'sort_order' => $index,
                'modifiers' => $modifiers->map(fn ($modifier) => [
                    'modifier_id' => $modifier->id,
                    'modifier_group_id' => $modifier->modifier_group_id,
                    'group_name' => $groups->get($modifier->modifier_group_id)?->name ?? 'Options',
                    'name' => $modifier->name,
                    'price_minor' => (int) $modifier->price_minor,
                    'quantity' => 1,
                ])->all(),
                'cogs_minor' => $unitCost * $quantity,
                'recipe' => $recipe,
                'yield_quantity' => $recipe?->yield_quantity ?: 1,
            ];
        }

        return $lines;
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines
     * @return array<string, int|bool|string>
     */
    protected function calculateTotals(array $lines, ?string $discountType, ?string $discountValue, ?string $deliveryFee, User $user): array
    {
        $gross = (int) collect($lines)->sum('line_total_minor');
        $subtotal = $gross;
        $discount = $this->resolveDiscount($discountType, $discountValue, $subtotal, $user);
        $orderDiscount = $discount['amount_minor'];

        $shares = $this->allocateDiscount($lines, $orderDiscount);
        $tax = 0;
        $net = 0;
        $cogs = 0;
        $taxRate = $this->settings->bool('tax.enabled') ? $this->settings->int('tax.rate_bp') : 0;
        $inclusive = $this->settings->bool('tax.inclusive');

        foreach ($lines as $index => $line) {
            $lineNet = max(0, $line['line_total_minor'] - $shares[$index]);
            $net += $lineNet;
            $cogs += $line['cogs_minor'];
            $rate = $line['is_taxable'] ? (int) $line['tax_rate_bp'] : 0;

            if ($rate > 0) {
                $tax += $inclusive
                    ? Money::taxInclusive($lineNet, $rate)
                    : Money::taxExclusive($lineNet, $rate);
            }
        }

        $serviceCharge = $this->settings->hasServiceCharge()
            ? Money::percent($net, $this->settings->int('service_charge.rate_bp'))
            : 0;

        $delivery = max(0, Money::parse($deliveryFee));
        $total = $net + $serviceCharge + $delivery + ($inclusive ? 0 : $tax);

        return [
            'gross_minor' => $gross,
            'item_discount_minor' => 0,
            'subtotal_minor' => $subtotal,
            'order_discount_minor' => $orderDiscount,
            'discount_minor' => $orderDiscount,
            'net_minor' => $net,
            'service_charge_minor' => $serviceCharge,
            'delivery_fee_minor' => $delivery,
            'tax_minor' => $tax,
            'total_minor' => $total,
            'cogs_minor' => $cogs,
            'tax_rate_bp' => $taxRate,
            'tax_inclusive' => $inclusive,
            'tax_label' => $this->settings->string('tax.label', 'Tax'),
            'service_charge_label' => $this->settings->string('service_charge.label', 'Service charge'),
            'discount_type' => $discountType ?? '',
            'discount_value' => $discountValue ?? '',
            'discount_bp' => $discount['basis_points'],
            'shares' => $shares,
        ];
    }

    /**
     * @return array{amount_minor: int, basis_points: int}
     */
    protected function resolveDiscount(?string $type, ?string $value, int $subtotal, User $user): array
    {
        if (! $type || $value === null || trim($value) === '' || $subtotal <= 0) {
            return ['amount_minor' => 0, 'basis_points' => 0];
        }

        $isManager = $user->hasRole(UserRole::Admin, UserRole::Manager);
        $capPercent = $isManager
            ? 100
            : max(0, min(100, $this->settings->int('discount.cashier_max_percent', 0)));
        $capBp = $capPercent * 100;

        if ($type === 'fixed') {
            $amount = max(0, Money::parse($value));
            $amount = min($amount, $subtotal);
            $basisPoints = $subtotal > 0 ? intdiv($amount * 10000, $subtotal) : 0;

            if ($basisPoints > $capBp) {
                throw ValidationException::withMessages([
                    'discount_value' => 'This discount exceeds your allowed limit of '.$capPercent.'%.',
                ]);
            }

            return ['amount_minor' => $amount, 'basis_points' => $basisPoints];
        }

        $basisPoints = max(0, min(10000, Money::parse($value)));

        if ($basisPoints > $capBp) {
            throw ValidationException::withMessages([
                'discount_value' => 'This discount exceeds your allowed limit of '.$capPercent.'%.',
            ]);
        }

        return [
            'amount_minor' => Money::percent($subtotal, $basisPoints),
            'basis_points' => $basisPoints,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines
     * @return array<int, int>
     */
    protected function allocateDiscount(array $lines, int $discount): array
    {
        $count = count($lines);
        $shares = array_fill(0, $count, 0);

        if ($discount <= 0 || $count === 0) {
            return $shares;
        }

        $subtotal = max(1, (int) collect($lines)->sum('line_total_minor'));
        $remaining = $discount;

        foreach ($lines as $index => $line) {
            $share = intdiv($discount * $line['line_total_minor'], $subtotal);
            $share = min($share, $line['line_total_minor']);
            $shares[$index] = $share;
            $remaining -= $share;
        }

        $index = 0;

        while ($remaining > 0 && $count > 0) {
            $capacity = $lines[$index]['line_total_minor'] - $shares[$index];

            if ($capacity > 0) {
                $add = min($capacity, $remaining);
                $shares[$index] += $add;
                $remaining -= $add;
            }

            $index = ($index + 1) % $count;
        }

        return $shares;
    }

    protected function replaceItems(Order $order, array $lines, array $shares = []): void
    {
        $order->items()->delete();

        foreach ($lines as $index => $line) {
            $share = (int) ($shares[$index] ?? 0);

            $item = $order->items()->create([
                'product_id' => $line['product_id'],
                'category_id' => $line['category_id'],
                'product_variant_id' => $line['product_variant_id'],
                'product_name' => $line['product_name'],
                'sku' => $line['sku'],
                'category_name' => $line['category_name'],
                'variant_name' => $line['variant_name'],
                'unit_price_minor' => $line['unit_price_minor'],
                'quantity' => $line['quantity'],
                'modifiers_total_minor' => $line['modifiers_total_minor'],
                'line_total_minor' => $line['line_total_minor'],
                'discount_minor' => $share,
                'line_net_minor' => $line['line_total_minor'] - $share,
                'is_taxable' => $line['is_taxable'],
                'notes' => $line['notes'],
                'sort_order' => $line['sort_order'] ?? $index,
            ]);

            foreach ($line['modifiers'] as $modifier) {
                $item->modifiers()->create($modifier);
            }
        }
    }

    protected function deductInventory(Order $order, User $user): void
    {
        if ($order->inventory_deducted_at) {
            return;
        }

        $order->loadMissing('items');

        foreach ($order->items as $item) {
            $product = Product::query()
                ->with(['recipe.ingredients.inventoryItem'])
                ->find($item->product_id);

            if (! $product) {
                continue;
            }

            $recipe = $product->recipe && $product->recipe->is_active ? $product->recipe : null;

            if (! $recipe) {
                continue;
            }

            $yield = max(1, (int) $recipe->yield_quantity);

            foreach ($recipe->ingredients as $ingredient) {
                $quantity = Money::divideRounded($ingredient->quantity_milli * $item->quantity, $yield);

                if ($quantity <= 0) {
                    continue;
                }

                $inventory = $ingredient->inventoryItem;

                if (! $inventory) {
                    continue;
                }

                $key = 'order:'.$order->id.':line:'.$item->id.':inv:'.$inventory->id;

                if (StockMovement::query()->where('idempotency_key', $key)->exists()) {
                    continue;
                }

                $cost = Money::divideRounded($quantity * $inventory->cost_per_unit_minor, 1000);

                StockMovement::query()->create([
                    'inventory_item_id' => $inventory->id,
                    'type' => StockMovementType::Consumption,
                    'quantity_milli' => -$quantity,
                    'unit_cost_minor' => $inventory->cost_per_unit_minor,
                    'total_cost_minor' => $cost,
                    'reason' => 'Order '.$order->order_number.' · '.$item->quantity.'× '.$item->product_name,
                    'reference_type' => $order->getMorphClass(),
                    'reference_id' => $order->id,
                    'idempotency_key' => $key,
                    'user_id' => $user->id,
                ]);

                InventoryItem::query()->whereKey($inventory->id)->decrement('stock_milli', $quantity);
            }
        }

        $order->forceFill(['inventory_deducted_at' => now()])->save();
    }

    protected function resolveTable(OrderType $type, mixed $tableId, ?Order $resume): ?RestaurantTable
    {
        if (! $this->settings->bool('tables.enabled', true)) {
            return null;
        }

        if ($type !== OrderType::DineIn) {
            return null;
        }

        if (! $tableId) {
            throw ValidationException::withMessages(['table_id' => 'Select a table for dine-in orders.']);
        }

        $table = RestaurantTable::query()->whereKey($tableId)->where('is_active', true)->first();

        if (! $table) {
            throw ValidationException::withMessages(['table_id' => 'The selected table is not available.']);
        }

        $active = $table->activeOrder()->first();

        if ($active && (! $resume || $active->id !== $resume->id)) {
            throw ValidationException::withMessages([
                'table_id' => $table->name.' already has an open order ('.$active->order_number.').',
            ]);
        }

        if ($resume && $resume->restaurant_table_id && $resume->restaurant_table_id !== $table->id) {
            $this->releaseTable($resume->table);
        }

        return $table;
    }

    protected function occupyTable(?RestaurantTable $table): void
    {
        if ($table && $table->status !== TableStatus::Occupied) {
            $table->forceFill(['status' => TableStatus::Occupied])->save();
        }
    }

    public function releaseTable(?RestaurantTable $table): void
    {
        if (! $table) {
            return;
        }

        $hasOpen = Order::query()
            ->where('restaurant_table_id', $table->id)
            ->active()
            ->exists();

        if (! $hasOpen) {
            $table->forceFill(['status' => TableStatus::Available])->save();
        }
    }

    protected function findResumable(?string $orderNumber): ?Order
    {
        if (! $orderNumber) {
            return null;
        }

        $order = Order::query()->where('order_number', $orderNumber)->first();

        if (! $order || ! $order->isHeld()) {
            throw ValidationException::withMessages(['resume_order' => 'That held order is no longer available.']);
        }

        return $order;
    }
}
