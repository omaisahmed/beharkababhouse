@extends('layouts.app')

@section('title', 'Inventory report')

@php $money = app(\App\Services\SettingsService::class); @endphp

@section('content')
    <div class="page-head">
        <div>
            <h1 class="page-title">Inventory report</h1>
            <p class="page-subtitle">Current stock snapshot and value.</p>
        </div>
    </div>

    @include('reports._filters')

    <div class="stat-grid">
        <div class="stat"><div class="stat-label">Items</div><div class="stat-value">{{ $inventory['item_count'] }}</div></div>
        <div class="stat"><div class="stat-label">Low stock</div><div class="stat-value" style="color:var(--warning);">{{ $inventory['low_stock_count'] }}</div></div>
        <div class="stat"><div class="stat-label">Out of stock</div><div class="stat-value" style="color:var(--danger);">{{ $inventory['out_of_stock_count'] }}</div></div>
        <div class="stat"><div class="stat-label">Stock value</div><div class="stat-value">{{ $money->money($inventory['stock_value_minor']) }}</div></div>
    </div>

    <div class="panel" style="margin-top:18px;">
        <div class="panel-head"><div class="panel-title">Current items</div><a href="{{ route('inventory.index') }}" class="btn btn-ghost btn-sm">Manage stock</a></div>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>Item</th><th>Unit</th><th class="r">In stock</th><th class="r">Minimum</th><th class="r">Unit cost</th><th class="r">Value</th><th>Status</th></tr></thead>
                <tbody>
                    @forelse ($inventory['items'] as $item)
                        <tr>
                            <td><div style="font-weight:600;">{{ $item->name }}</div><div class="tiny">{{ $item->sku ?: 'No SKU' }}</div></td>
                            <td class="tiny">{{ \App\Support\Units::label($item->unit) }}</td>
                            <td class="r">{{ $item->stockLabel() }}</td>
                            <td class="r tiny">{{ $item->minimum_stock_milli > 0 ? $item->minimumStockLabel() : '—' }}</td>
                            <td class="r tiny">{{ $item->costLabel() }}</td>
                            <td class="r">{{ $money->money($item->stockValueMinor()) }}</td>
                            <td>
                                @if ($item->isOutOfStock())
                                    <span class="badge badge-rose">Out</span>
                                @elseif ($item->isLowStock())
                                    <span class="badge badge-amber">Low</span>
                                @else
                                    <span class="badge badge-emerald">OK</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="tiny">No inventory items.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
