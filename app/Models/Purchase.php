<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Purchase extends Model
{
    protected $fillable = [
        'purchase_number',
        'supplier_id',
        'user_id',
        'purchased_at',
        'payment_status',
        'total_minor',
        'paid_minor',
        'reference',
        'notes',
        'received_at',
    ];

    protected function casts(): array
    {
        return [
            'purchased_at' => 'date',
            'total_minor' => 'integer',
            'paid_minor' => 'integer',
            'received_at' => 'datetime',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
    }

    public function totalLabel(): string
    {
        return Money::format($this->total_minor);
    }
}
