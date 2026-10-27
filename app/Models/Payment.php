<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $fillable = [
        'order_id',
        'cashier_shift_id',
        'user_id',
        'method',
        'status',
        'amount_minor',
        'tendered_minor',
        'change_minor',
        'reference',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'method' => PaymentMethod::class,
            'amount_minor' => 'integer',
            'tendered_minor' => 'integer',
            'change_minor' => 'integer',
            'paid_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
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
