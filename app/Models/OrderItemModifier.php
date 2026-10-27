<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItemModifier extends Model
{
    protected $fillable = [
        'order_item_id',
        'modifier_id',
        'modifier_group_id',
        'group_name',
        'name',
        'price_minor',
        'quantity',
    ];

    protected function casts(): array
    {
        return [
            'price_minor' => 'integer',
            'quantity' => 'integer',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class, 'order_item_id');
    }
}
