<?php

namespace App\Http\Requests\Pos;

use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'client_uuid' => ['required', 'uuid'],
            'resume_order' => ['nullable', 'string', 'max:40'],
            'order_type' => ['required', Rule::in(['dine_in', 'takeaway', 'delivery'])],
            'table_id' => ['nullable', 'integer', 'exists:restaurant_tables,id'],
            'customer_name' => ['nullable', 'string', 'max:120'],
            'customer_phone' => ['nullable', 'string', 'max:40'],
            'customer_address' => ['nullable', 'string', 'max:500'],
            'delivery_instructions' => ['nullable', 'string', 'max:500'],
            'delivery_fee' => ['nullable', 'string', 'max:20'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'discount_type' => ['nullable', Rule::in(['fixed', 'percent'])],
            'discount_value' => ['nullable', 'string', 'max:20'],

            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:99'],
            'items.*.modifier_ids' => ['nullable', 'array', 'max:20'],
            'items.*.modifier_ids.*' => ['integer', 'exists:modifiers,id'],
            'items.*.notes' => ['nullable', 'string', 'max:300'],

            'payment' => ['required', 'array'],
            'payment.method' => ['required', Rule::enum(PaymentMethod::class)],
            'payment.amount' => ['required', 'string', 'max:20'],
            'payment.tendered' => ['nullable', 'string', 'max:20'],
            'payment.reference' => ['nullable', 'string', 'max:120'],
        ];
    }
}
