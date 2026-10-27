<?php

namespace App\Models;

use App\Support\Money;
use App\Support\Units;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseItem extends Model
{
    protected $fillable = [
        'purchase_id',
        'inventory_item_id',
        'quantity_milli',
        'input_unit',
        'unit_cost_minor',
        'total_minor',
    ];

    protected function casts(): array
    {
        return [
            'quantity_milli' => 'integer',
            'unit_cost_minor' => 'integer',
            'total_minor' => 'integer',
        ];
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    public function quantityLabel(): string
    {
        return Units::stockLabel($this->quantity_milli, $this->inventoryItem?->unit ?? 'piece');
    }

    public function totalLabel(): string
    {
        return Money::format($this->total_minor);
    }
}
