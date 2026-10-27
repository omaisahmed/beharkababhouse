@extends('layouts.app')

@section('title', 'Product report')

@php $money = app(\App\Services\SettingsService::class); @endphp

@section('content')
    <div class="page-head">
        <div>
            <h1 class="page-title">Product report</h1>
            <p class="page-subtitle">Best sellers and category revenue for the selected period.</p>
        </div>
    </div>

    @include('reports._filters')

    <div class="stat-grid">
        <div class="stat"><div class="stat-label">Items sold</div><div class="stat-value">{{ number_format($summary['items_sold']) }}</div></div>
        <div class="stat"><div class="stat-label">Net sales</div><div class="stat-value">{{ $money->money($summary['net_sales_minor']) }}</div></div>
        <div class="stat"><div class="stat-label">Orders</div><div class="stat-value">{{ number_format($summary['order_count']) }}</div></div>
    </div>

    <div class="grid-2" style="margin-top:18px;align-items:start;">
        <div class="panel">
            <div class="panel-head"><div class="panel-title">Products by quantity</div></div>
            <div class="table-wrap">
                <table class="data-table">
                    <thead><tr><th>#</th><th>Product</th><th class="r">Qty</th><th class="r">Revenue</th></tr></thead>
                    <tbody>
                        @forelse ($topProducts as $index => $product)
                            <tr><td class="tiny">{{ $index + 1 }}</td><td>{{ $product['name'] }}</td><td class="r">{{ $product['quantity'] }}</td><td class="r">{{ $money->money($product['revenue_minor']) }}</td></tr>
                        @empty
                            <tr><td colspan="4" class="tiny">No product sales in this period.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="panel">
            <div class="panel-head"><div class="panel-title">Categories</div></div>
            <div class="table-wrap">
                <table class="data-table">
                    <thead><tr><th>Category</th><th class="r">Qty</th><th class="r">Revenue</th></tr></thead>
                    <tbody>
                        @forelse ($categories as $category)
                            <tr><td>{{ $category['name'] }}</td><td class="r">{{ $category['quantity'] }}</td><td class="r">{{ $money->money($category['revenue_minor']) }}</td></tr>
                        @empty
                            <tr><td colspan="3" class="tiny">No category data.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
