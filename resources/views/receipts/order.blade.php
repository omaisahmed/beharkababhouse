@php
    $settings = app(\App\Services\SettingsService::class);
    $restaurant = $settings->restaurantName();
    $logo = $settings->string('restaurant.logo_path');
    $money = fn (int $minor) => $settings->money($minor);
    $paid = $order->payments->where('status', 'completed')->sum('amount_minor');
    $change = $order->payments->sum('change_minor');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Receipt {{ $order->order_number }}</title>
    @vite('resources/css/app.css')
    <style>
        @page { margin: 0; }
        @media print {
            .receipt-toolbar { display: none !important; }
            body { background: #fff !important; }
            .receipt-paper { box-shadow: none !important; margin: 0 !important; border: 0 !important; }
        }
    </style>
</head>
<body style="background:var(--bg);padding:24px;">
    <div class="receipt-toolbar" style="max-width:560px;margin:0 auto 14px;display:flex;gap:8px;justify-content:flex-end;">
        <a href="{{ url()->previous() }}" class="btn btn-ghost">Back</a>
        <button type="button" class="btn btn-primary" onclick="window.print()"><x-icon name="printer" class="w-4 h-4" /> Print receipt</button>
    </div>

    <div class="receipt-paper size-{{ in_array($width, [58, 80], true) ? $width : 80 }}" style="margin:0 auto;">
        <div class="receipt-center">
            @if ($logo && $settings->bool('receipt.show_logo', true))
                <img src="{{ route('media', ['path' => $logo]) }}" alt="" class="receipt-logo">
            @endif
            <div class="receipt-title">{{ $restaurant }}</div>
            @if ($settings->string('restaurant.address'))<div>{{ $settings->string('restaurant.address') }}</div>@endif
            @if ($settings->string('restaurant.phone'))<div>{{ $settings->string('restaurant.phone') }}</div>@endif
            @if ($settings->bool('receipt.show_tax_id', true) && $settings->string('restaurant.tax_id'))
                <div>Tax ID: {{ $settings->string('restaurant.tax_id') }}</div>
            @endif
        </div>

        <div class="receipt-rule"></div>

        @if ($settings->string('receipt.header'))
            <div class="receipt-center" style="margin-bottom:8px;">{!! nl2br(e($settings->string('receipt.header'))) !!}</div>
        @endif

        <div style="display:flex;justify-content:space-between;gap:8px;">
            <span>{{ $order->order_number }}</span>
            <span>{{ $order->created_at?->format('d/m/Y H:i') }}</span>
        </div>
        <div style="display:flex;justify-content:space-between;gap:8px;">
            <span>{{ $order->order_type?->label() }}@if ($order->table) · {{ $order->table->name }}@endif</span>
            <span>{{ $order->user?->name }}</span>
        </div>
        @if ($order->customer_name)
            <div>Customer: {{ $order->customer_name }}</div>
        @endif
        @if ($order->customer_phone)
            <div>Phone: {{ $order->customer_phone }}</div>
        @endif
        @if ($order->customer_address)
            <div>Address: {{ $order->customer_address }}</div>
        @endif

        <div class="receipt-rule"></div>

        <table class="receipt-table receipt-items">
            <thead>
                <tr><th>Item</th><th class="r">Qty</th><th class="r">Amount</th></tr>
            </thead>
            <tbody>
                @foreach ($order->items as $item)
                    <tr>
                        <td>
                            {{ $item->product_name }}
                            @if ($item->variant_name)<div class="receipt-muted">({{ $item->variant_name }})</div>@endif
                            @foreach ($item->modifiers as $modifier)
                                <div class="receipt-muted">+ {{ $modifier->name }}</div>
                            @endforeach
                            @if ($item->notes)<div class="receipt-muted">* {{ $item->notes }}</div>@endif
                        </td>
                        <td class="r">{{ $item->quantity }}</td>
                        <td class="r">{{ $money($item->line_total_minor) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="receipt-rule"></div>

        <div style="display:flex;justify-content:space-between;"><span>Subtotal</span><span>{{ $money($order->subtotal_minor) }}</span></div>
        @if ($order->discount_minor > 0)
            <div style="display:flex;justify-content:space-between;"><span>Discount</span><span>− {{ $money($order->discount_minor) }}</span></div>
        @endif
        @if ($order->service_charge_minor > 0)
            <div style="display:flex;justify-content:space-between;"><span>{{ $order->service_charge_label ?: 'Service charge' }}</span><span>{{ $money($order->service_charge_minor) }}</span></div>
        @endif
        @if ($order->delivery_fee_minor > 0)
            <div style="display:flex;justify-content:space-between;"><span>Delivery</span><span>{{ $money($order->delivery_fee_minor) }}</span></div>
        @endif
        @if ($order->tax_minor > 0)
            <div style="display:flex;justify-content:space-between;"><span>{{ $order->tax_label ?: 'Tax' }}{{ $order->tax_inclusive ? ' (incl.)' : '' }}</span><span>{{ $money($order->tax_minor) }}</span></div>
        @endif

        <div class="receipt-total" style="display:flex;justify-content:space-between;">
            <span>Total</span><span>{{ $money($order->total_minor) }}</span>
        </div>

        <div class="receipt-rule"></div>

        @foreach ($order->payments->where('status', 'completed') as $payment)
            <div style="display:flex;justify-content:space-between;"><span>{{ $payment->method?->label() }}</span><span>{{ $money($payment->amount_minor) }}</span></div>
            @if ($payment->method === \App\Enums\PaymentMethod::Cash && $payment->tendered_minor)
                <div style="display:flex;justify-content:space-between;"><span>Tendered</span><span>{{ $money($payment->tendered_minor) }}</span></div>
                <div style="display:flex;justify-content:space-between;"><span>Change</span><span>{{ $money($payment->change_minor) }}</span></div>
            @endif
        @endforeach

        @if ($order->refunds->isNotEmpty())
            <div class="receipt-rule"></div>
            @foreach ($order->refunds as $refund)
                <div style="display:flex;justify-content:space-between;"><span>Refund {{ $refund->refund_number }}</span><span>− {{ $money($refund->amount_minor) }}</span></div>
            @endforeach
        @endif

        <div class="receipt-rule"></div>

        <div class="receipt-center receipt-footer">
            {!! nl2br(e($settings->string('receipt.footer', 'Thank you for dining with us.'))) !!}
            <div style="margin-top:7px;">{{ $order->order_number }}</div>
            <div>{{ $order->created_at?->format('d/m/Y H:i:s') }}</div>
        </div>
    </div>

    @if ($autoprint)
        <script>
            window.addEventListener('load', () => {
                window.setTimeout(() => window.print(), 350);
            });
        </script>
    @endif
</body>
</html>
