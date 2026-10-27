<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
    protected $fillable = [
        'expense_category_id',
        'supplier_id',
        'user_id',
        'description',
        'amount_minor',
        'expense_date',
        'payment_method',
        'reference',
        'notes',
        'attachment_path',
    ];

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'expense_date' => 'date',
            'payment_method' => PaymentMethod::class,
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function amountLabel(): string
    {
        return Money::format($this->amount_minor);
    }
}
