@php
    $settings = app(\App\Services\SettingsService::class);
    $restaurant = $settings->restaurantName();
    $logo = $settings->string('restaurant.logo_path');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Test receipt</title>
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
        <a href="{{ route('settings.edit') }}" class="btn btn-ghost">Settings</a>
        <button type="button" class="btn btn-primary" onclick="window.print()"><x-icon name="printer" class="w-4 h-4" /> Print test</button>
    </div>

    <div class="receipt-paper size-{{ in_array($width, [58, 80], true) ? $width : 80 }}" style="margin:0 auto;">
        <div class="receipt-center">
            @if ($logo && $settings->bool('receipt.show_logo', true))<img src="{{ route('media', ['path' => $logo]) }}" alt="" class="receipt-logo">@endif
            <div class="receipt-title">{{ $restaurant }}</div>
            @if ($settings->string('restaurant.address'))<div>{{ $settings->string('restaurant.address') }}</div>@endif
            @if ($settings->string('restaurant.phone'))<div>{{ $settings->string('restaurant.phone') }}</div>@endif
            <div>{{ $settings->bool('receipt.show_tax_id', true) && $settings->string('restaurant.tax_id') ? 'Tax ID: '.$settings->string('restaurant.tax_id') : '' }}</div>
        </div>
        <div class="receipt-rule"></div>
        <div class="receipt-center" style="font-weight:800;font-size:15px;">*** TEST RECEIPT ***</div>
        <div class="receipt-rule"></div>
        <table class="receipt-table receipt-items">
            <thead><tr><th>Item</th><th class="r">Qty</th><th class="r">Amount</th></tr></thead>
            <tbody>
                <tr><td>Chicken Tikka</td><td class="r">2</td><td class="r">{{ $settings->money(90000) }}</td></tr>
                <tr><td>Garlic Naan</td><td class="r">3</td><td class="r">{{ $settings->money(12000) }}</td></tr>
                <tr><td>Fresh Lime</td><td class="r">2</td><td class="r">{{ $settings->money(16000) }}</td></tr>
            </tbody>
        </table>
        <div class="receipt-rule"></div>
        <div style="display:flex;justify-content:space-between;"><span>Subtotal</span><span>{{ $settings->money(118000) }}</span></div>
        <div style="display:flex;justify-content:space-between;"><span>Tax</span><span>{{ $settings->money(0) }}</span></div>
        <div class="receipt-total" style="display:flex;justify-content:space-between;"><span>Total</span><span>{{ $settings->money(118000) }}</span></div>
        <div style="display:flex;justify-content:space-between;"><span>Cash</span><span>{{ $settings->money(120000) }}</span></div>
        <div style="display:flex;justify-content:space-between;"><span>Change</span><span>{{ $settings->money(2000) }}</span></div>
        <div class="receipt-rule"></div>
        <div class="receipt-center receipt-footer">{{ $settings->string('receipt.footer', 'Thank you for dining with us.') }}</div>
        <div class="receipt-center" style="margin-top:8px;">{{ $width }} mm test print</div>
    </div>
</body>
</html>
