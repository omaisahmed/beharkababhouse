<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Modifier extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'modifier_group_id',
        'name',
        'price_minor',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'price_minor' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(ModifierGroup::class, 'modifier_group_id');
    }
}
