<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id',
        'product_id',
        'category_id',
        'product_variant_id',
        'product_name',
        'sku',
        'category_name',
        'variant_name',
        'unit_price_minor',
        'quantity',
        'modifiers_total_minor',
        'line_total_minor',
        'discount_minor',
        'line_net_minor',
        'is_taxable',
        'notes',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'unit_price_minor' => 'integer',
            'quantity' => 'integer',
            'modifiers_total_minor' => 'integer',
            'line_total_minor' => 'integer',
            'discount_minor' => 'integer',
            'line_net_minor' => 'integer',
            'is_taxable' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function modifiers(): HasMany
    {
        return $this->hasMany(OrderItemModifier::class);
    }

    public function modifierSummary(): string
    {
        return $this->modifiers->pluck('name')->implode(', ');
    }
}
