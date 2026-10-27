<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\TableStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class RestaurantTable extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'zone',
        'capacity',
        'status',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'status' => TableStatus::class,
            'capacity' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function activeOrder()
    {
        return $this->hasOne(Order::class)
            ->whereIn('status', [
                OrderStatus::Held->value,
                OrderStatus::Confirmed->value,
                OrderStatus::Preparing->value,
                OrderStatus::Ready->value,
                OrderStatus::Served->value,
            ])
            ->latestOfMany();
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('zone')->orderBy('name');
    }
}
