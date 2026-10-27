@extends('layouts.app')

@section('title', 'Orders')

@php
    $money = app(\App\Services\SettingsService::class);
    $statuses = \App\Enums\OrderStatus::cases();
    $types = \App\Enums\OrderType::cases();
    $paymentStatuses = \App\Enums\PaymentStatus::cases();
@endphp

@section('content')
    <div class="page-head">
        <div>
            <h1 class="page-title">Orders</h1>
            <p class="page-subtitle">Every sale, held check, refund, and kitchen ticket in one place.</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('pos.index') }}" class="btn btn-primary"><x-icon name="plus" class="w-4 h-4" /> New order</a>
        </div>
    </div>

    <div class="stat-grid">
        <div class="stat"><div class="stat-label">Orders today</div><div class="stat-value">{{ $stats['today_count'] }}</div><div class="stat-meta">All statuses</div></div>
        <div class="stat accent"><div class="stat-label">Sales today</div><div class="stat-value">{{ $money->money($stats['today_sales']) }}</div><div class="stat-meta">Paid orders</div></div>
        <div class="stat"><div class="stat-label">Open checks</div><div class="stat-value">{{ $stats['open_count'] }}</div><div class="stat-meta">Held, preparing, and served</div></div>
        <div class="stat"><div class="stat-label">Refunded today</div><div class="stat-value">{{ $money->money($stats['refunded_today']) }}</div><div class="stat-meta">Across today’s orders</div></div>
    </div>

    <div class="panel" style="margin-top:18px;">
        <div class="panel-head" style="flex-wrap:wrap;gap:10px;">
            <form method="GET" action="{{ route('orders.index') }}" style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;width:100%;">
                <div class="pos-search" style="max-width:260px;">
                    <x-icon name="search" />
                    <input name="q" value="{{ request('q') }}" class="input input-sm" placeholder="Order, customer, phone…">
                </div>
                <select name="status" class="select input-sm" style="width:auto;">
                    <option value="">All statuses</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>
                    @endforeach
                </select>
                <select name="type" class="select input-sm" style="width:auto;">
                    <option value="">All types</option>
                    @foreach ($types as $type)
                        <option value="{{ $type->value }}" @selected(request('type') === $type->value)>{{ $type->label() }}</option>
                    @endforeach
                </select>
                <select name="payment" class="select input-sm" style="width:auto;">
                    <option value="">Payment</option>
                    @foreach ($paymentStatuses as $status)
                        <option value="{{ $status->value }}" @selected(request('payment') === $status->value)>{{ $status->label() }}</option>
                    @endforeach
                </select>
                <input type="date" name="from" value="{{ request('from') }}" class="input input-sm" style="width:auto;">
                <input type="date" name="to" value="{{ request('to') }}" class="input input-sm" style="width:auto;">
                <button class="btn btn-ghost btn-sm" type="submit"><x-icon name="filter" class="w-4 h-4" /> Filter</button>
                @if (request()->hasAny(['q', 'status', 'type', 'payment', 'from', 'to']))
                    <a href="{{ route('orders.index') }}" class="btn btn-ghost btn-sm">Reset</a>
                @endif
            </form>
        </div>

        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr><th>Order</th><th>Type</th><th>Customer</th><th>Status</th><th>Payment</th><th>Cashier</th><th class="r">Total</th><th></th></tr>
                </thead>
                <tbody>
                    @forelse ($orders as $order)
                        <tr>
                            <td>
                                <a href="{{ route('orders.show', $order) }}" style="font-weight:650;">{{ $order->order_number }}</a>
                                <div class="tiny">{{ $order->created_at?->format('d M Y · H:i') }} · {{ $order->items_count }} lines</div>
                            </td>
                            <td>
                                {{ $order->order_type?->label() }}
                                @if ($order->table)<div class="tiny">{{ $order->table->name }}</div>@endif
                            </td>
                            <td>
                                {{ $order->customer_name ?: 'Walk-in' }}
                                @if ($order->customer_phone)<div class="tiny">{{ $order->customer_phone }}</div>@endif
                            </td>
                            <td>
                                <span class="badge {{ $order->status === \App\Enums\OrderStatus::Cancelled ? 'badge-rose' : ($order->status === \App\Enums\OrderStatus::Completed ? 'badge-emerald' : ($order->status === \App\Enums\OrderStatus::Held || $order->status === \App\Enums\OrderStatus::Draft ? 'badge-slate' : 'badge-amber')) }}">{{ $order->status?->label() }}</span>
                            </td>
                            <td>
                                <span class="badge {{ $order->payment_status === \App\Enums\PaymentStatus::Paid ? 'badge-emerald' : ($order->payment_status === \App\Enums\PaymentStatus::Unpaid ? 'badge-slate' : 'badge-orange') }}">{{ $order->payment_status?->label() }}</span>
                            </td>
                            <td>{{ $order->user?->name }}</td>
                            <td class="r">{{ $money->money($order->total_minor) }}</td>
                            <td class="r" style="white-space:nowrap;">
                                <a href="{{ route('orders.show', $order) }}" class="btn btn-ghost btn-sm">View</a>
                                @if ($order->payment_status !== \App\Enums\PaymentStatus::Unpaid)
                                    <a href="{{ route('orders.receipt', $order) }}" target="_blank" class="btn btn-ghost btn-sm"><x-icon name="printer" class="w-3.5 h-3.5" /></a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8">
                                <div class="empty" style="padding:50px 10px;">
                                    <div class="empty-icon"><x-icon name="orders" class="w-6 h-6" /></div>
                                    <div class="empty-title">No orders found</div>
                                    <div class="empty-text">Adjust the filters or start a new order.</div>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div style="margin-top:14px;">{{ $orders->links() }}</div>
@endsection
