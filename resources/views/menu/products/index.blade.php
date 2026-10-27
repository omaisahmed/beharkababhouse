@extends('layouts.app')

@section('title', 'Products')

@php
    $money = app(\App\Services\SettingsService::class);
@endphp

@section('content')
    <div class="page-head">
        <div>
            <h1 class="page-title">Products</h1>
            <p class="page-subtitle">Menu items, pricing, availability, and variants.</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('products.export') }}" class="btn btn-ghost"><x-icon name="download" class="w-4 h-4" /> Export</a>
            <button type="button" class="btn btn-ghost" onclick="document.getElementById('import-dialog').showModal()"><x-icon name="upload" class="w-4 h-4" /> Import</button>
            @can('manage-menu')
                <a href="{{ route('products.create') }}" class="btn btn-primary"><x-icon name="plus" class="w-4 h-4" /> New product</a>
            @endcan
        </div>
    </div>

    <div class="stat-grid">
        <div class="stat"><div class="stat-label">Total products</div><div class="stat-value">{{ $stats['total'] }}</div><div class="stat-meta">{{ $stats['categories'] }} categories</div></div>
        <div class="stat"><div class="stat-label">Available</div><div class="stat-value" style="color:var(--success);">{{ $stats['available'] }}</div><div class="stat-meta">Orderable on POS</div></div>
        <div class="stat"><div class="stat-label">Unavailable</div><div class="stat-value" style="color:var(--danger);">{{ $stats['unavailable'] }}</div><div class="stat-meta">Hidden from cashiers</div></div>
    </div>

    <div class="panel" style="margin-top:18px;">
        <form method="GET" action="{{ route('products.index') }}" class="panel-body" style="display:flex;gap:9px;flex-wrap:wrap;align-items:center;border-bottom:1px solid var(--border);">
            <input name="q" value="{{ request('q') }}" class="input" placeholder="Search name or SKU…" style="max-width:260px;">
            <select name="category" class="select" style="max-width:200px;">
                <option value="">All categories</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected(request('category') == $category->id)>{{ $category->name }}</option>
                @endforeach
            </select>
            <select name="availability" class="select" style="max-width:170px;">
                <option value="">Any availability</option>
                <option value="available" @selected(request('availability') === 'available')>Available</option>
                <option value="unavailable" @selected(request('availability') === 'unavailable')>Unavailable</option>
            </select>
            <button type="submit" class="btn btn-ghost">Filter</button>
            @if (request()->hasAny(['q', 'category', 'availability']))
                <a href="{{ route('products.index') }}" class="btn btn-ghost">Clear</a>
            @endif
        </form>

        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th style="width:54px;"></th><th>Product</th><th>Category</th><th class="r">Price</th><th class="r">Cost</th><th class="r">Tax</th><th class="r">Variants</th><th>Status</th><th class="r"></th></tr></thead>
                <tbody>
                    @forelse ($products as $product)
                        <tr>
                            <td>
                                <div class="thumb">
                                    @if ($product->image_path)
                                        <img src="{{ route('media', ['path' => $product->image_path]) }}" alt="">
                                    @else
                                        <x-icon name="products" class="w-4 h-4" />
                                    @endif
                                </div>
                            </td>
                            <td>
                                <div style="font-weight:650;">{{ $product->name }}</div>
                                <div class="tiny">{{ $product->sku ?: 'No SKU' }}@if ($product->kitchen_station) · {{ $product->kitchen_station }}@endif</div>
                            </td>
                            <td class="tiny">{{ $product->category?->name ?? '—' }}</td>
                            <td class="r">{{ $money->money($product->price_minor) }}</td>
                            <td class="r tiny">{{ $product->cost_minor !== null ? $money->money($product->cost_minor) : '—' }}</td>
                            <td class="r tiny">{{ $product->is_taxable ? number_format(($product->tax_rate_bp ?? 0) / 100, 2).'%' : 'Exempt' }}</td>
                            <td class="r">{{ $product->variants_count }}</td>
                            <td>
                                <form method="POST" action="{{ route('products.toggle', $product) }}">
                                    @csrf
                                    <button type="submit" class="badge {{ $product->is_available ? 'badge-emerald' : 'badge-neutral' }}" style="border:0;cursor:pointer;">{{ $product->is_available ? 'Available' : 'Hidden' }}</button>
                                </form>
                            </td>
                            <td class="r">
                                <div style="display:flex;gap:6px;justify-content:flex-end;">
                                    <a href="{{ route('products.edit', $product) }}" class="btn btn-ghost btn-sm">Edit</a>
                                    <form method="POST" action="{{ route('products.destroy', $product) }}" data-confirm="Delete {{ $product->name }}? Past orders keep their snapshots.">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-ghost btn-sm" style="color:var(--danger);">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9"><div class="empty" style="padding:50px 20px;"><div class="empty-icon"><x-icon name="products" class="w-7 h-7" /></div><div class="empty-title">No products found</div><div class="empty-text">Adjust your filters or create a new product.</div></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($products->hasPages())
            <div class="panel-body">{{ $products->links() }}</div>
        @endif
    </div>
@endsection

@push('modals')
    <dialog class="modal" id="import-dialog">
        <form method="POST" action="{{ route('products.import') }}" enctype="multipart/form-data">
            @csrf
            <div class="modal-head"><div class="modal-title">Import menu CSV</div><button type="button" class="close-x" data-close><x-icon name="x" class="w-4 h-4" /></button></div>
            <div class="modal-body">
                <p class="tiny" style="margin-bottom:12px;">Export an existing menu first to get the exact column format. Products are matched by SKU when present.</p>
                <div class="field"><label class="label">CSV file</label><input type="file" name="file" accept=".csv,text/csv" class="input" required></div>
            </div>
            <div class="modal-foot"><button type="button" class="btn btn-ghost" data-close>Cancel</button><button type="submit" class="btn btn-primary">Import products</button></div>
        </form>
    </dialog>
@endpush
