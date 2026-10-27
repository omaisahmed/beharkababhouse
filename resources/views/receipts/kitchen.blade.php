@php
    $settings = app(\App\Services\SettingsService::class);
    $restaurant = $settings->restaurantName();
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Kitchen ticket {{ $order->order_number }}</title>
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
        <button type="button" class="btn btn-primary" onclick="window.print()"><x-icon name="printer" class="w-4 h-4" /> Print ticket</button>
    </div>

    <div class="receipt-paper size-{{ in_array($width, [58, 80], true) ? $width : 80 }}" style="margin:0 auto;">
        <div class="receipt-center">
            <div class="receipt-title">KITCHEN</div>
            <div>{{ $restaurant }}</div>
            <div style="font-size:16px;font-weight:800;margin-top:5px;">{{ $order->order_number }}</div>
            <div>{{ $order->created_at?->format('d/m/Y H:i') }}</div>
        </div>

        <div class="receipt-rule"></div>

        <div style="display:flex;justify-content:space-between;font-weight:700;font-size:14px;">
            <span>{{ strtoupper($order->order_type?->label() ?? 'ORDER') }}</span>
            <span>{{ $order->table?->name ?: '' }}</span>
        </div>
        @if ($order->customer_name)<div>Customer: {{ $order->customer_name }}</div>@endif
        @if ($order->notes)<div style="font-weight:700;">Note: {{ $order->notes }}</div>@endif

        <div class="receipt-rule"></div>

        @foreach ($order->items as $item)
            <div style="margin-bottom:9px;">
                <div style="display:flex;gap:8px;font-size:15px;font-weight:800;">
                    <span style="min-width:28px;">{{ $item->quantity }}×</span>
                    <span>{{ $item->product_name }}</span>
                </div>
                @if ($item->variant_name)<div style="margin-left:36px;">{{ $item->variant_name }}</div>@endif
                @foreach ($item->modifiers as $modifier)
                    <div style="margin-left:36px;">+ {{ $modifier->name }}</div>
                @endforeach
                @if ($item->notes)<div style="margin-left:36px;font-weight:700;">* {{ $item->notes }}</div>@endif
            </div>
        @endforeach

        <div class="receipt-rule"></div>

        <div class="receipt-center receipt-muted">
            Printed {{ now()->format('d/m/Y H:i:s') }}
        </div>
    </div>
</body>
</html>
