<?php

namespace App\Models;

use App\Enums\ShiftStatus;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashierShift extends Model
{
    protected $fillable = [
        'user_id',
        'status',
        'opening_cash_minor',
        'cash_sales_minor',
        'cash_refunds_minor',
        'cash_in_minor',
        'cash_out_minor',
        'expected_cash_minor',
        'counted_cash_minor',
        'difference_minor',
        'opening_notes',
        'closing_notes',
        'opened_at',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ShiftStatus::class,
            'opening_cash_minor' => 'integer',
            'cash_sales_minor' => 'integer',
            'cash_refunds_minor' => 'integer',
            'cash_in_minor' => 'integer',
            'cash_out_minor' => 'integer',
            'expected_cash_minor' => 'integer',
            'counted_cash_minor' => 'integer',
            'difference_minor' => 'integer',
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    public function cashMovements(): HasMany
    {
        return $this->hasMany(CashMovement::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function scopeOpen($query)
    {
        return $query->where('status', ShiftStatus::Open->value);
    }

    public function isOpen(): bool
    {
        return $this->status === ShiftStatus::Open;
    }

    public function expectedCashMinor(): int
    {
        return $this->opening_cash_minor
            + $this->cash_sales_minor
            - $this->cash_refunds_minor
            + $this->cash_in_minor
            - $this->cash_out_minor;
    }

    public function expectedCashLabel(): string
    {
        return Money::format($this->expectedCashMinor());
    }
}
