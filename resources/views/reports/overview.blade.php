@extends('layouts.app')

@section('title', 'Reports')

@php
    $money = app(\App\Services\SettingsService::class);
    $maxDaily = max(1, collect($series)->max('total_minor'));
@endphp

@section('content')
    <div class="page-head">
        <div>
            <h1 class="page-title">Reports</h1>
            <p class="page-subtitle">Sales, profit, payments, and stock at a glance.</p>
        </div>
    </div>

    @include('reports._filters')

    <div class="stat-grid">
        <div class="stat"><div class="stat-label">Net sales</div><div class="stat-value">{{ $money->money($summary['net_sales_minor']) }}</div><div class="stat-meta">After {{ $money->money($summary['refunds_minor']) }} refunds</div></div>
        <div class="stat"><div class="stat-label">Orders</div><div class="stat-value">{{ number_format($summary['order_count']) }}</div><div class="stat-meta">{{ number_format($summary['items_sold']) }} items sold</div></div>
        <div class="stat"><div class="stat-label">Average ticket</div><div class="stat-value">{{ $money->money($summary['average_ticket_minor']) }}</div></div>
        <div class="stat"><div class="stat-label">Gross profit</div><div class="stat-value" style="color:{{ $summary['gross_profit_minor'] >= 0 ? 'var(--success)' : 'var(--danger)' }};">{{ $money->money($summary['gross_profit_minor']) }}</div><div class="stat-meta">COGS {{ $money->money($summary['cogs_minor']) }}</div></div>
    </div>

    <div class="grid-2" style="margin-top:18px;align-items:start;">
        <div class="panel">
            <div class="panel-head"><div><div class="panel-title">Daily sales</div><div class="panel-sub">Net of refunds</div></div></div>
            <div class="panel-body">
                @if (count($series) === 0)
                    <div class="tiny">No sales in this period.</div>
                @else
                    <div style="display:grid;gap:7px;">
                        @foreach ($series as $day)
                            <div style="display:grid;grid-template-columns:64px 1fr 96px;gap:10px;align-items:center;">
                                <span class="tiny">{{ $day['label'] }}</span>
                                <div style="height:8px;border-radius:999px;background:var(--surface-2);overflow:hidden;">
                                    <div style="height:100%;width:{{ $day['total_minor'] > 0 ? max(1, round($day['total_minor'] / $maxDaily * 100)) : 0 }}%;background:linear-gradient(90deg,var(--brand),var(--brand-strong));"></div>
                                </div>
                                <span class="tiny" style="text-align:right;font-weight:600;">{{ $money->money($day['total_minor']) }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        <div class="panel">
            <div class="panel-head"><div><div class="panel-title">Payment mix</div><div class="panel-sub">Completed payments, net of refunds</div></div></div>
            <div class="panel-body">
                @foreach ($payments as $payment)
                    <div class="summary-row"><span>{{ $payment['label'] }} <span class="tiny">· {{ $payment['count'] }}×</span></span><strong>{{ $money->money($payment['net_minor']) }}</strong></div>
                @endforeach
                <div class="summary-total"><span>Total collected</span><strong>{{ $money->money(collect($payments)->sum('net_minor')) }}</strong></div>
            </div>
        </div>
    </div>

    <div class="grid-2" style="margin-top:18px;align-items:start;">
        <div class="panel">
            <div class="panel-head"><div><div class="panel-title">Top products</div><div class="panel-sub">By quantity sold</div></div><a href="{{ route('reports.products', request()->only(['from', 'to', 'preset'])) }}" class="btn btn-ghost btn-sm">All products</a></div>
            <div class="table-wrap">
                <table class="data-table">
                    <thead><tr><th>Product</th><th class="r">Qty</th><th class="r">Revenue</th></tr></thead>
                    <tbody>
                        @forelse ($topProducts as $product)
                            <tr><td>{{ $product['name'] }}</td><td class="r">{{ $product['quantity'] }}</td><td class="r">{{ $money->money($product['revenue_minor']) }}</td></tr>
                        @empty
                            <tr><td colspan="3" class="tiny">No product sales.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="stack" style="display:grid;gap:14px;">
            <div class="panel">
                <div class="panel-head"><div class="panel-title">Categories</div></div>
                <div class="panel-body">
                    @forelse ($categories as $category)
                        <div class="summary-row"><span>{{ $category['name'] }} <span class="tiny">· {{ $category['quantity'] }} items</span></span><strong>{{ $money->money($category['revenue_minor']) }}</strong></div>
                    @empty
                        <div class="tiny">No category data.</div>
                    @endforelse
                </div>
            </div>

            <div class="panel">
                <div class="panel-head"><div class="panel-title">Inventory health</div><a href="{{ route('inventory.index') }}" class="btn btn-ghost btn-sm">Open stock</a></div>
                <div class="panel-body">
                    <div class="summary-row"><span>Tracked items</span><strong>{{ $inventory['item_count'] }}</strong></div>
                    <div class="summary-row"><span>Low stock</span><strong style="color:var(--warning);">{{ $inventory['low_stock_count'] }}</strong></div>
                    <div class="summary-row"><span>Out of stock</span><strong style="color:var(--danger);">{{ $inventory['out_of_stock_count'] }}</strong></div>
                    <div class="summary-total"><span>Stock value</span><strong>{{ $money->money($inventory['stock_value_minor']) }}</strong></div>
                </div>
            </div>
        </div>
    </div>

    <div class="panel" style="margin-top:18px;">
        <div class="panel-head"><div><div class="panel-title">Staff performance</div><div class="panel-sub">Orders and sales by cashier</div></div></div>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>Cashier</th><th>Role</th><th class="r">Orders</th><th class="r">Sales</th></tr></thead>
                <tbody>
                    @forelse ($staff as $member)
                        <tr><td>{{ $member['name'] }}</td><td class="tiny">{{ ucfirst($member['role']) }}</td><td class="r">{{ $member['order_count'] }}</td><td class="r">{{ $money->money($member['total_minor']) }}</td></tr>
                    @empty
                        <tr><td colspan="4" class="tiny">No staff sales in this period.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
