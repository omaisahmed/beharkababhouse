@extends('layouts.app')

@section('title', 'Purchase '.$purchase->purchase_number)

@php $money = app(\App\Services\SettingsService::class); @endphp

@section('content')
    <div class="page-head">
        <div>
            <div class="tiny" style="margin-bottom:5px;"><a href="{{ route('purchases.index') }}" style="color:var(--muted);">Purchases</a> / {{ $purchase->purchase_number }}</div>
            <h1 class="page-title">{{ $purchase->purchase_number }}</h1>
            <p class="page-subtitle">{{ $purchase->purchased_at?->format('l, d F Y') }} · {{ $purchase->supplier?->name ?? 'No supplier' }} · {{ $purchase->user?->name }}</p>
        </div>
        <div class="page-actions">
            @if (auth()->user()?->isAdmin())
                <form method="POST" action="{{ route('purchases.destroy', $purchase) }}" data-confirm="Reverse this purchase? Stock will be removed and the invoice deleted.">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-ghost" style="color:var(--danger);">Reverse purchase</button>
                </form>
            @endif
            <a href="{{ route('purchases.index') }}" class="btn btn-ghost">Back</a>
        </div>
    </div>

    <div class="stat-grid">
        <div class="stat"><div class="stat-label">Total</div><div class="stat-value">{{ $money->money($purchase->total_minor) }}</div><div class="stat-meta">{{ $purchase->items->count() }} line items</div></div>
        <div class="stat"><div class="stat-label">Paid</div><div class="stat-value">{{ $money->money($purchase->paid_minor) }}</div><div class="stat-meta">{{ ucfirst($purchase->payment_status) }}</div></div>
        <div class="stat"><div class="stat-label">Balance</div><div class="stat-value" style="color:{{ $purchase->total_minor - $purchase->paid_minor > 0 ? 'var(--warning)' : 'var(--success)' }};">{{ $money->money($purchase->total_minor - $purchase->paid_minor) }}</div></div>
        <div class="stat"><div class="stat-label">Received</div><div class="stat-value" style="font-size:18px;">{{ $purchase->received_at?->format('d M, H:i') ?? '—' }}</div></div>
    </div>

    <div class="grid-2" style="margin-top:18px;align-items:start;">
        <div class="panel">
            <div class="panel-head"><div class="panel-title">Items received</div></div>
            <div class="table-wrap">
                <table class="data-table">
                    <thead><tr><th>Item</th><th class="r">Quantity</th><th class="r">Unit cost</th><th class="r">Line total</th></tr></thead>
                    <tbody>
                        @foreach ($purchase->items as $line)
                            <tr>
                                <td><div style="font-weight:600;">{{ $line->inventoryItem?->name ?? 'Deleted item' }}</div><div class="tiny">Entered as {{ $line->input_unit }}</div></td>
                                <td class="r">{{ $line->quantityLabel() }}</td>
                                <td class="r">{{ $money->money($line->unit_cost_minor) }}</td>
                                <td class="r">{{ $money->money($line->total_minor) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="panel-body" style="border-top:1px solid var(--border);">
                <div class="summary-total"><span>Total</span><strong>{{ $money->money($purchase->total_minor) }}</strong></div>
            </div>
        </div>

        <div class="panel">
            <div class="panel-head"><div class="panel-title">Details</div></div>
            <div class="panel-body">
                <div class="summary-row"><span>Supplier</span><strong>{{ $purchase->supplier?->name ?? '—' }}</strong></div>
                <div class="summary-row"><span>Reference</span><strong>{{ $purchase->reference ?: '—' }}</strong></div>
                <div class="summary-row"><span>Recorded by</span><strong>{{ $purchase->user?->name ?? '—' }}</strong></div>
                <div class="summary-row"><span>Payment</span><strong>{{ ucfirst($purchase->payment_status) }}</strong></div>
                @if ($purchase->notes)
                    <div style="margin-top:12px;"><div class="label">Notes</div><p style="font-size:12.5px;margin-top:5px;">{{ $purchase->notes }}</p></div>
                @endif
            </div>
        </div>
    </div>
@endsection
