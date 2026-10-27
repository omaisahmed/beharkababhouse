@extends('layouts.app')

@section('title', 'Held orders')

@php $money = app(\App\Services\SettingsService::class); @endphp

@section('content')
    <div class="page-head">
        <div>
            <h1 class="page-title">Held orders</h1>
            <p class="page-subtitle">Parked checks waiting to be resumed or discarded.</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('pos.index') }}" class="btn btn-primary"><x-icon name="plus" class="w-4 h-4" /> New order</a>
        </div>
    </div>

    <div class="panel">
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr><th>Order</th><th>Type</th><th>Table</th><th>Items</th><th>Cashier</th><th>Held</th><th class="r">Total</th><th></th></tr>
                </thead>
                <tbody>
                    @forelse ($orders as $order)
                        <tr>
                            <td>
                                <div style="font-weight:650;">{{ $order->order_number }}</div>
                                <div class="tiny">{{ $order->customer_name ?: 'Walk-in customer' }}</div>
                            </td>
                            <td>{{ $order->order_type?->label() }}</td>
                            <td>{{ $order->table?->name ?: '—' }}</td>
                            <td>{{ $order->items->sum('quantity') }} items</td>
                            <td>{{ $order->user?->name }}</td>
                            <td>{{ $order->held_at?->diffForHumans() }}</td>
                            <td class="r">{{ $money->money($order->total_minor) }}</td>
                            <td class="r" style="white-space:nowrap;">
                                <a href="{{ route('pos.resume', $order) }}" class="btn btn-primary btn-sm"><x-icon name="undo" class="w-3.5 h-3.5" /> Resume</a>
                                <form method="POST" action="{{ route('pos.held.discard', $order) }}" style="display:inline;" data-confirm="Discard {{ $order->order_number }}? This cannot be undone.">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-ghost btn-sm" style="color:var(--danger);"><x-icon name="trash" class="w-3.5 h-3.5" /></button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8">
                                <div class="empty" style="padding:47px 10px;">
                                    <div class="empty-icon"><x-icon name="clock" class="w-6 h-6" /></div>
                                    <div class="empty-title">No held orders</div>
                                    <div class="empty-text">Parked orders will appear here for quick resuming.</div>
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
