@extends('layouts.app')

@section('title', 'Suppliers')

@section('content')
    <div class="page-head">
        <div>
            <h1 class="page-title">Suppliers</h1>
            <p class="page-subtitle">Vendors used for inventory purchases.</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('purchases.index') }}" class="btn btn-ghost">Purchases</a>
            <a href="{{ route('suppliers.create') }}" class="btn btn-primary"><x-icon name="plus" class="w-4 h-4" /> New supplier</a>
        </div>
    </div>

    <div class="panel">
        <form method="GET" action="{{ route('suppliers.index') }}" class="panel-body" style="display:flex;gap:9px;flex-wrap:wrap;align-items:center;border-bottom:1px solid var(--border);">
            <input name="q" value="{{ request('q') }}" class="input" placeholder="Search supplier, contact, phone…" style="max-width:300px;">
            <select name="active" class="select" style="max-width:160px;">
                <option value="">Any status</option>
                <option value="1" @selected(request('active') === '1')>Active</option>
                <option value="0" @selected(request('active') === '0')>Inactive</option>
            </select>
            <button type="submit" class="btn btn-ghost">Filter</button>
            @if (request()->hasAny(['q', 'active']))<a href="{{ route('suppliers.index') }}" class="btn btn-ghost">Clear</a>@endif
        </form>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>Supplier</th><th>Contact</th><th>Phone</th><th>Email</th><th class="r">Items</th><th class="r">Purchases</th><th>Status</th><th class="r"></th></tr></thead>
                <tbody>
                    @forelse ($suppliers as $supplier)
                        <tr>
                            <td><div style="font-weight:650;">{{ $supplier->name }}</div>@if ($supplier->address)<div class="tiny">{{ \Illuminate\Support\Str::limit($supplier->address, 60) }}</div>@endif</td>
                            <td class="tiny">{{ $supplier->contact_name ?: '—' }}</td>
                            <td class="tiny">{{ $supplier->phone ?: '—' }}</td>
                            <td class="tiny">{{ $supplier->email ?: '—' }}</td>
                            <td class="r">{{ $supplier->inventory_items_count }}</td>
                            <td class="r">{{ $supplier->purchases_count }}</td>
                            <td><span class="badge {{ $supplier->is_active ? 'badge-emerald' : 'badge-neutral' }}">{{ $supplier->is_active ? 'Active' : 'Inactive' }}</span></td>
                            <td class="r">
                                <div style="display:flex;gap:6px;justify-content:flex-end;">
                                    <a href="{{ route('suppliers.edit', $supplier) }}" class="btn btn-ghost btn-sm">Edit</a>
                                    <form method="POST" action="{{ route('suppliers.destroy', $supplier) }}" data-confirm="Remove {{ $supplier->name }}?">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-ghost btn-sm" style="color:var(--danger);" @disabled($supplier->purchases_count > 0)>Remove</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8"><div class="empty" style="padding:50px 20px;"><div class="empty-icon"><x-icon name="supplier" class="w-7 h-7" /></div><div class="empty-title">No suppliers</div><div class="empty-text">Add vendors to record purchases and track costs.</div></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($suppliers->hasPages())<div class="panel-body">{{ $suppliers->links() }}</div>@endif
    </div>
@endsection
