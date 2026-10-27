<?php

namespace App\Models;

use App\Enums\StockMovementType;
use App\Support\Money;
use App\Support\Units;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'inventory_item_id',
        'type',
        'quantity_milli',
        'unit_cost_minor',
        'total_cost_minor',
        'reason',
        'reference_type',
        'reference_id',
        'idempotency_key',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'type' => StockMovementType::class,
            'quantity_milli' => 'integer',
            'unit_cost_minor' => 'integer',
            'total_cost_minor' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function quantityLabel(): string
    {
        return Units::stockLabel($this->quantity_milli, $this->inventoryItem?->unit ?? 'piece');
    }

    public function totalCostLabel(): string
    {
        return $this->total_cost_minor === null ? '—' : Money::format($this->total_cost_minor);
    }
}
