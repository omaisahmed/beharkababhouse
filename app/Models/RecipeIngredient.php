<?php

namespace App\Models;

use App\Support\Units;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecipeIngredient extends Model
{
    protected $fillable = [
        'recipe_id',
        'inventory_item_id',
        'quantity_milli',
    ];

    protected function casts(): array
    {
        return ['quantity_milli' => 'integer'];
    }

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    public function quantityLabel(): string
    {
        return Units::stockLabel($this->quantity_milli, $this->inventoryItem?->unit ?? 'piece');
    }
}
