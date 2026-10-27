<?php

namespace App\Models;

use App\Support\Money;
use App\Support\Quantity;
use App\Support\Units;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class InventoryItem extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'supplier_id',
        'name',
        'sku',
        'unit',
        'stock_milli',
        'minimum_stock_milli',
        'cost_per_unit_minor',
        'is_active',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'stock_milli' => 'integer',
            'minimum_stock_milli' => 'integer',
            'cost_per_unit_minor' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function recipeIngredients(): HasMany
    {
        return $this->hasMany(RecipeIngredient::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function purchaseItems(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function isLowStock(): bool
    {
        return $this->minimum_stock_milli > 0 && $this->stock_milli <= $this->minimum_stock_milli;
    }

    public function isOutOfStock(): bool
    {
        return $this->stock_milli <= 0;
    }

    public function stockLabel(): string
    {
        return Units::stockLabel($this->stock_milli, $this->unit);
    }

    public function minimumStockLabel(): string
    {
        return Units::stockLabel($this->minimum_stock_milli, $this->unit);
    }

    public function costLabel(): string
    {
        return Money::format($this->cost_per_unit_minor).' / '.Units::label($this->unit);
    }

    public function stockValueMinor(): int
    {
        return Money::divideRounded($this->stock_milli * $this->cost_per_unit_minor, Quantity::SCALE);
    }

    public function stockValueLabel(): string
    {
        return Money::format($this->stockValueMinor());
    }
}
