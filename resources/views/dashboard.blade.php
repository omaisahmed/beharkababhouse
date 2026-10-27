@extends('layouts.app')

@section('title', 'Dashboard')

@php
    $money = app(\App\Services\SettingsService::class);
    $peak = max(1, collect($series)->max('total_minor') ?? 0);
@endphp

@section('content')
    <div class="page-head">
        <div>
            <h1 class="page-title">Good {{ now()->hour < 12 ? 'morning' : (now()->hour < 17 ? 'afternoon' : 'evening') }}, {{ auth()->user()->name }}</h1>
            <p class="page-subtitle">{{ now()->format('l, d F Y') }} · Here is how the counter is doing today.</p>
        </div>
        <div class="page-actions">
            @if ($shift)
                <a href="{{ route('shifts.show', $shift) }}" class="btn btn-ghost"><x-icon name="shift" class="w-4 h-4" /> Shift open</a>
            @else
                <a href="{{ route('shifts.index') }}" class="btn btn-ghost"><x-icon name="shift" class="w-4 h-4" /> Open a shift</a>
            @endif
            <a href="{{ route('pos.index') }}" class="btn btn-primary"><x-icon name="plus" class="w-4 h-4" /> New order</a>
        </div>
    </div>

    @if ($backupDue)
        <div class="alert alert-warning" style="margin-bottom:18px;">
            <x-icon name="backup" class="w-4 h-4 shrink-0" />
            <div>
                <strong>A recent backup is recommended.</strong>
                <div class="tiny" style="margin-top:3px;">Your last backup was {{ $lastBackup?->created_at?->diffForHumans() ?? 'never' }}. <a href="{{ route('backups.index') }}" style="text-decoration:underline;">Create a backup now</a>.</div>
            </div>
        </div>
    @endif

    <div class="stat-grid">
        <div class="stat accent">
            <div class="stat-label">Sales today</div>
            <div class="stat-value">{{ $money->money($summary['total_minor']) }}</div>
            <div class="stat-meta">{{ $summary['order_count'] }} paid orders · avg {{ $money->money($summary['average_ticket_minor']) }}</div>
        </div>
        <div class="stat">
            <div class="stat-label">Net after refunds</div>
            <div class="stat-value">{{ $money->money($summary['net_sales_minor']) }}</div>
            <div class="stat-meta">{{ $summary['items_sold'] }} items sold</div>
        </div>
        <div class="stat">
            <div class="stat-label">Open orders</div>
            <div class="stat-value">{{ $openOrders }}</div>
            <div class="stat-meta">{{ $heldOrders }} held · {{ $kitchenOrders }} in kitchen</div>
        </div>
        <div class="stat">
            <div class="stat-label">Gross profit</div>
            <div class="stat-value">{{ $money->money($summary['gross_profit_minor']) }}</div>
            <div class="stat-meta">COGS {{ $money->money($summary['cogs_minor']) }}</div>
        </div>
    </div>

    <div class="grid-2" style="margin-top:18px;align-items:start;">
        <div class="panel">
            <div class="panel-head">
                <div>
                    <div class="panel-title">Last 7 days</div>
                    <div class="panel-sub">Sales excluding refunds</div>
                </div>
                <a href="{{ route('reports.sales') }}" class="btn btn-ghost btn-sm">Full report <x-icon name="chevron-right" class="w-3.5 h-3.5" /></a>
            </div>
            <div class="panel-body">
                <div class="chart" style="height:190px;">
                    @foreach ($series as $day)
                        <div class="chart-bar" style="height:{{ max(4, (int) round(($day['total_minor'] / $peak) * 150)) }}px;" title="{{ $day['label'] }}: {{ $money->money($day['total_minor']) }} · {{ $day['orders'] }} orders">
                            <span class="chart-label">{{ $day['label'] }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="panel">
            <div class="panel-head">
                <div>
                    <div class="panel-title">Recent orders</div>
                    <div class="panel-sub">Latest activity at the counter</div>
                </div>
                <a href="{{ route('orders.index') }}" class="btn btn-ghost btn-sm">All orders</a>
            </div>
            <div class="table-wrap">
                <table class="data-table">
                    <thead><tr><th>Order</th><th>Type</th><th>Status</th><th class="r">Total</th></tr></thead>
                    <tbody>
                        @forelse ($recentOrders as $order)
                            <tr>
                                <td><a href="{{ route('orders.show', $order) }}" style="font-weight:600;">{{ $order->order_number }}</a><div class="tiny">{{ $order->created_at?->format('H:i') }} · {{ $order->user?->name }}</div></td>
                                <td>{{ $order->order_type?->label() }}</td>
                                <td><span class="badge {{ $order->status === \App\Enums\OrderStatus::Cancelled ? 'badge-rose' : ($order->status === \App\Enums\OrderStatus::Completed ? 'badge-emerald' : 'badge-amber') }}">{{ $order->status?->label() }}</span></td>
                                <td class="r">{{ $money->money($order->total_minor) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4"><div class="empty" style="padding:30px 10px;"><div class="empty-title">No orders yet</div><div class="empty-text">Start a new order from the POS.</div></div></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="grid-2" style="margin-top:18px;align-items:start;">
        <div class="panel">
            <div class="panel-head"><div><div class="panel-title">Top products today</div><div class="panel-sub">By quantity sold</div></div></div>
            <div class="panel-body">
                @forelse ($topProducts as $product)
                    <div style="display:flex;align-items:center;gap:12px;padding:9px 0;border-bottom:1px solid var(--border);">
                        <div style="flex:1;min-width:0;">
                            <div style="font-weight:600;font-size:13px;">{{ $product['name'] }}</div>
                            <div class="tiny">{{ $product['quantity'] }} sold</div>
                        </div>
                        <strong style="font-size:12.5px;">{{ $money->money($product['revenue_minor']) }}</strong>
                    </div>
                @empty
                    <div class="empty" style="padding:26px 10px;"><div class="empty-title">No sales yet today</div></div>
                @endforelse
            </div>
        </div>

        <div class="panel">
            <div class="panel-head">
                <div><div class="panel-title">Low stock</div><div class="panel-sub">Items at or below minimum</div></div>
                @can('manage-inventory')<a href="{{ route('inventory.index', ['low' => 1]) }}" class="btn btn-ghost btn-sm">Manage</a>@endcan
            </div>
            <div class="panel-body">
                @forelse ($lowStock as $item)
                    <div style="display:flex;align-items:center;gap:12px;padding:9px 0;border-bottom:1px solid var(--border);">
                        <x-icon name="inventory" class="w-4 h-4" style="color:var(--warning);" />
                        <div style="flex:1;min-width:0;"><div style="font-weight:600;font-size:13px;">{{ $item->name }}</div><div class="tiny">Minimum {{ $item->minimumStockLabel() }}</div></div>
                        <span class="badge {{ $item->stock_milli <= 0 ? 'badge-rose' : 'badge-amber' }}">{{ $item->stockLabel() }}</span>
                    </div>
                @empty
                    <div class="empty" style="padding:26px 10px;"><div class="empty-title">Stock levels look healthy</div></div>
                @endforelse
            </div>
        </div>
    </div>
@endsection
