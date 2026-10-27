<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Refund extends Model
{
    protected $fillable = [
        'client_uuid',
        'refund_number',
        'order_id',
        'payment_id',
        'cashier_shift_id',
        'user_id',
        'method',
        'amount_minor',
        'subtotal_minor',
        'tax_minor',
        'service_charge_minor',
        'delivery_fee_minor',
        'reason',
        'restocked_at',
    ];

    protected function casts(): array
    {
        return [
            'method' => PaymentMethod::class,
            'amount_minor' => 'integer',
            'subtotal_minor' => 'integer',
            'tax_minor' => 'integer',
            'service_charge_minor' => 'integer',
            'delivery_fee_minor' => 'integer',
            'restocked_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(CashierShift::class, 'cashier_shift_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
