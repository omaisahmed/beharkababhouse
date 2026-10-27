@extends('layouts.app')

@section('title', 'Purchases')

@php $money = app(\App\Services\SettingsService::class); @endphp

@section('content')
    <div class="page-head">
        <div>
            <h1 class="page-title">Purchases</h1>
            <p class="page-subtitle">Receive supplier stock and track what is still owed.</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('suppliers.index') }}" class="btn btn-ghost">Suppliers</a>
            <a href="{{ route('purchases.create') }}" class="btn btn-primary"><x-icon name="plus" class="w-4 h-4" /> New purchase</a>
        </div>
    </div>

    <div class="stat-grid">
        <div class="stat"><div class="stat-label">Total purchases</div><div class="stat-value">{{ $money->money($summary['total']) }}</div><div class="stat-meta">All recorded invoices</div></div>
        <div class="stat"><div class="stat-label">Outstanding</div><div class="stat-value" style="color:var(--warning);">{{ $money->money($summary['unpaid']) }}</div><div class="stat-meta">Unpaid or partially paid</div></div>
        <div class="stat"><div class="stat-label">Purchases</div><div class="stat-value">{{ $purchases->total() }}</div><div class="stat-meta">Matching filters</div></div>
    </div>

    <div class="panel" style="margin-top:18px;">
        <form method="GET" action="{{ route('purchases.index') }}" class="panel-body" style="display:flex;gap:9px;flex-wrap:wrap;align-items:flex-end;border-bottom:1px solid var(--border);">
            <div class="field" style="min-width:190px;"><label class="label">Supplier</label><select name="supplier" class="select"><option value="">All suppliers</option>@foreach ($suppliers as $supplier)<option value="{{ $supplier->id }}" @selected(request('supplier') == $supplier->id)>{{ $supplier->name }}</option>@endforeach</select></div>
            <div class="field" style="min-width:160px;"><label class="label">Payment</label><select name="status" class="select"><option value="">Any status</option><option value="unpaid" @selected(request('status') === 'unpaid')>Unpaid</option><option value="partial" @selected(request('status') === 'partial')>Partial</option><option value="paid" @selected(request('status') === 'paid')>Paid</option></select></div>
            <div class="field"><label class="label">From</label><input type="date" name="from" value="{{ request('from') }}" class="input"></div>
            <div class="field"><label class="label">To</label><input type="date" name="to" value="{{ request('to') }}" class="input"></div>
            <button type="submit" class="btn btn-ghost">Filter</button>
            @if (request()->hasAny(['supplier', 'status', 'from', 'to']))<a href="{{ route('purchases.index') }}" class="btn btn-ghost">Clear</a>@endif
        </form>

        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>Purchase</th><th>Supplier</th><th>Date</th><th class="r">Items</th><th class="r">Total</th><th class="r">Paid</th><th class="r">Balance</th><th>Status</th><th class="r"></th></tr></thead>
                <tbody>
                    @forelse ($purchases as $purchase)
                        <tr>
                            <td><a href="{{ route('purchases.show', $purchase) }}" style="font-weight:650;color:var(--brand-strong);">{{ $purchase->purchase_number }}</a><div class="tiny">{{ $purchase->reference ?: 'No reference' }}</div></td>
                            <td class="tiny">{{ $purchase->supplier?->name ?? '—' }}</td>
                            <td class="tiny">{{ $purchase->purchased_at?->format('d M Y') }}</td>
                            <td class="r">{{ $purchase->items_count }}</td>
                            <td class="r">{{ $money->money($purchase->total_minor) }}</td>
                            <td class="r tiny">{{ $money->money($purchase->paid_minor) }}</td>
                            <td class="r" style="color:{{ $purchase->total_minor - $purchase->paid_minor > 0 ? 'var(--warning)' : 'inherit' }};">{{ $money->money($purchase->total_minor - $purchase->paid_minor) }}</td>
                            <td><span class="badge {{ $purchase->payment_status === 'paid' ? 'badge-emerald' : ($purchase->payment_status === 'partial' ? 'badge-amber' : 'badge-rose') }}">{{ ucfirst($purchase->payment_status) }}</span></td>
                            <td class="r"><a href="{{ route('purchases.show', $purchase) }}" class="btn btn-ghost btn-sm">View</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="9"><div class="empty" style="padding:50px 20px;"><div class="empty-icon"><x-icon name="purchase" class="w-7 h-7" /></div><div class="empty-title">No purchases</div><div class="empty-text">Record your first supplier invoice to add stock.</div></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($purchases->hasPages())<div class="panel-body">{{ $purchases->links() }}</div>@endif
    </div>
@endsection
