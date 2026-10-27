<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $fillable = [
        'client_uuid',
        'order_number',
        'sequence_date',
        'daily_sequence',
        'order_type',
        'status',
        'payment_status',
        'user_id',
        'cashier_shift_id',
        'restaurant_table_id',
        'customer_name',
        'customer_phone',
        'customer_address',
        'delivery_instructions',
        'notes',
        'gross_minor',
        'item_discount_minor',
        'subtotal_minor',
        'order_discount_minor',
        'discount_minor',
        'net_minor',
        'service_charge_minor',
        'delivery_fee_minor',
        'tax_minor',
        'total_minor',
        'paid_minor',
        'refunded_minor',
        'cogs_minor',
        'tax_rate_bp',
        'tax_inclusive',
        'tax_label',
        'service_charge_label',
        'kitchen_required',
        'inventory_deducted_at',
        'held_at',
        'confirmed_at',
        'completed_at',
        'cancelled_at',
        'cancelled_by',
        'cancellation_reason',
    ];

    protected function casts(): array
    {
        return [
            'order_type' => OrderType::class,
            'status' => OrderStatus::class,
            'payment_status' => PaymentStatus::class,
            'sequence_date' => 'date',
            'tax_inclusive' => 'boolean',
            'kitchen_required' => 'boolean',
            'inventory_deducted_at' => 'datetime',
            'held_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'gross_minor' => 'integer',
            'item_discount_minor' => 'integer',
            'subtotal_minor' => 'integer',
            'order_discount_minor' => 'integer',
            'discount_minor' => 'integer',
            'net_minor' => 'integer',
            'service_charge_minor' => 'integer',
            'delivery_fee_minor' => 'integer',
            'tax_minor' => 'integer',
            'total_minor' => 'integer',
            'paid_minor' => 'integer',
            'refunded_minor' => 'integer',
            'cogs_minor' => 'integer',
            'tax_rate_bp' => 'integer',
            'daily_sequence' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(CashierShift::class, 'cashier_shift_id');
    }

    public function table(): BelongsTo
    {
        return $this->belongsTo(RestaurantTable::class, 'restaurant_table_id');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class)->orderBy('sort_order');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    public function scopeSold(Builder $query): Builder
    {
        return $query
            ->where('status', '!=', OrderStatus::Cancelled->value)
            ->whereIn('payment_status', [
                PaymentStatus::Paid->value,
                PaymentStatus::PartiallyRefunded->value,
                PaymentStatus::Refunded->value,
            ]);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', [
            OrderStatus::Draft->value,
            OrderStatus::Held->value,
            OrderStatus::Confirmed->value,
            OrderStatus::Preparing->value,
            OrderStatus::Ready->value,
            OrderStatus::Served->value,
        ]);
    }

    public function scopeBetween(Builder $query, $from, $to): Builder
    {
        return $query->whereBetween('orders.created_at', [$from, $to]);
    }

    public function scopeToday(Builder $query): Builder
    {
        return $query->whereDate('created_at', today());
    }

    public function isPaid(): bool
    {
        return $this->payment_status->isPaid();
    }

    public function isCancelled(): bool
    {
        return $this->status === OrderStatus::Cancelled;
    }

    public function isHeld(): bool
    {
        return $this->status === OrderStatus::Held || $this->status === OrderStatus::Draft;
    }

    public function amountDueMinor(): int
    {
        return max(0, $this->total_minor - $this->paid_minor);
    }

    public function netPaidMinor(): int
    {
        return $this->paid_minor - $this->refunded_minor;
    }

    public function netSalesMinor(): int
    {
        return max(0, $this->net_minor - $this->refunds->sum('subtotal_minor'));
    }

    public function requiresKitchen(): bool
    {
        return $this->kitchen_required;
    }

    public function getRouteKeyName(): string
    {
        return 'order_number';
    }
}
