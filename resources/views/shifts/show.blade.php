@extends('layouts.app')

@section('title', 'Shift · '.$shift->opened_at?->format('d M Y'))

@php
    $money = app(\App\Services\SettingsService::class);
    $orders = $shift->orders;
    $sales = $orders->where('status', '!=', \App\Enums\OrderStatus::Cancelled);
    $payments = $shift->payments->where('status', 'completed');
@endphp

@section('content')
    <div class="page-head">
        <div>
            <div class="tiny" style="margin-bottom:5px;"><a href="{{ route('shifts.index') }}" style="color:var(--muted);">Shifts</a> / {{ $shift->opened_at?->format('d M Y') }}</div>
            <h1 class="page-title">Shift · {{ $shift->user?->name }}</h1>
            <p class="page-subtitle">{{ $shift->opened_at?->format('l, d F Y · H:i') }} → {{ $shift->closed_at?->format('H:i') ?? 'in progress' }}</p>
        </div>
        <div class="page-actions">
            @if ($shift->isOpen())
                <button type="button" class="btn btn-ghost" onclick="document.getElementById('movement-dialog').showModal()">Cash in / out</button>
                <button type="button" class="btn btn-primary" onclick="document.getElementById('close-dialog').showModal()">Close shift</button>
            @else
                <span class="badge badge-neutral">Closed</span>
            @endif
        </div>
    </div>

    <div class="stat-grid">
        <div class="stat"><div class="stat-label">Opening cash</div><div class="stat-value">{{ $money->money($shift->opening_cash_minor) }}</div></div>
        <div class="stat"><div class="stat-label">{{ $shift->isOpen() ? 'Expected in drawer' : 'Expected cash' }}</div><div class="stat-value" style="color:var(--brand-strong);">{{ $money->money($expected) }}</div><div class="stat-meta">Opening + cash sales − refunds + in − out</div></div>
        <div class="stat"><div class="stat-label">Counted</div><div class="stat-value">{{ $shift->counted_cash_minor !== null ? $money->money($shift->counted_cash_minor) : '—' }}</div></div>
        <div class="stat"><div class="stat-label">Variance</div><div class="stat-value" style="color:{{ ($shift->difference_minor ?? 0) === 0 ? 'var(--text)' : (($shift->difference_minor ?? 0) > 0 ? 'var(--warning)' : 'var(--danger)') }};">{{ $shift->difference_minor !== null ? $money->money($shift->difference_minor) : '—' }}</div></div>
    </div>

    <div class="grid-2" style="margin-top:18px;align-items:start;">
        <div class="stack" style="display:grid;gap:14px;">
            <div class="panel">
                <div class="panel-head"><div><div class="panel-title">Cash reconciliation</div><div class="panel-sub">All figures in restaurant currency</div></div></div>
                <div class="panel-body">
                    <div class="summary-row"><span>Opening cash</span><strong>{{ $money->money($shift->opening_cash_minor) }}</strong></div>
                    <div class="summary-row"><span>Cash sales</span><strong style="color:var(--success);">+ {{ $money->money($shift->cash_sales_minor) }}</strong></div>
                    <div class="summary-row"><span>Cash refunds</span><strong style="color:var(--danger);">− {{ $money->money($shift->cash_refunds_minor) }}</strong></div>
                    <div class="summary-row"><span>Cash in</span><strong>+ {{ $money->money($shift->cash_in_minor) }}</strong></div>
                    <div class="summary-row"><span>Cash out</span><strong>− {{ $money->money($shift->cash_out_minor) }}</strong></div>
                    <div class="summary-total"><span>Expected</span><strong>{{ $money->money($expected) }}</strong></div>
                    @if ($shift->counted_cash_minor !== null)
                        <div class="summary-row" style="margin-top:8px;"><span>Counted cash</span><strong>{{ $money->money($shift->counted_cash_minor) }}</strong></div>
                        <div class="summary-row"><span>Difference</span><strong style="color:{{ ($shift->difference_minor ?? 0) === 0 ? 'inherit' : (($shift->difference_minor ?? 0) > 0 ? 'var(--warning)' : 'var(--danger)') }};">{{ $money->money($shift->difference_minor ?? 0) }}</strong></div>
                    @endif
                </div>
            </div>

            <div class="panel">
                <div class="panel-head"><div class="panel-title">Cash movements</div></div>
                <div class="panel-body">
                    @forelse ($shift->cashMovements as $movement)
                        <div style="display:flex;gap:10px;padding:8px 0;border-bottom:1px solid var(--border);">
                            <div style="flex:1;"><div style="font-weight:600;font-size:12.5px;">{{ $movement->type?->label() }}</div><div class="tiny">{{ $movement->reason }} · {{ $movement->user?->name }}</div></div>
                            <strong style="color:{{ $movement->type === \App\Enums\CashMovementType::In ? 'var(--success)' : 'var(--danger)' }};">{{ $movement->type === \App\Enums\CashMovementType::In ? '+' : '−' }} {{ $money->money($movement->amount_minor) }}</strong>
                        </div>
                    @empty
                        <div class="tiny">No cash movements recorded.</div>
                    @endforelse
                </div>
            </div>

            @if ($shift->opening_notes || $shift->closing_notes)
                <div class="panel">
                    <div class="panel-head"><div class="panel-title">Notes</div></div>
                    <div class="panel-body">
                        @if ($shift->opening_notes)<div class="summary-row"><span>Opening</span><strong style="text-align:right;max-width:70%;font-weight:500;">{{ $shift->opening_notes }}</strong></div>@endif
                        @if ($shift->closing_notes)<div class="summary-row"><span>Closing</span><strong style="text-align:right;max-width:70%;font-weight:500;">{{ $shift->closing_notes }}</strong></div>@endif
                    </div>
                </div>
            @endif
        </div>

        <div class="stack" style="display:grid;gap:14px;">
            <div class="panel">
                <div class="panel-head"><div><div class="panel-title">Orders</div><div class="panel-sub">{{ $sales->count() }} completed · {{ $orders->count() - $sales->count() }} cancelled</div></div></div>
                <div class="table-wrap" style="max-height:340px;overflow:auto;">
                    <table class="data-table">
                        <thead><tr><th>Order</th><th>Time</th><th class="r">Total</th></tr></thead>
                        <tbody>
                            @forelse ($orders->sortByDesc('created_at') as $order)
                                <tr>
                                    <td><a href="{{ route('orders.show', $order) }}" style="font-weight:600;color:var(--brand-strong);">{{ $order->order_number }}</a><div class="tiny">{{ $order->order_type?->label() }}</div></td>
                                    <td class="tiny">{{ $order->created_at?->format('H:i') }}</td>
                                    <td class="r">{{ $money->money($order->total_minor) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="tiny">No orders in this shift.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="panel">
                <div class="panel-head"><div class="panel-title">Payments</div></div>
                <div class="panel-body">
                    @forelse ($payments as $payment)
                        <div style="display:flex;gap:10px;padding:8px 0;border-bottom:1px solid var(--border);">
                            <div style="flex:1;"><div style="font-weight:600;font-size:12.5px;">{{ $payment->method?->label() }}</div><div class="tiny">{{ $payment->paid_at?->format('H:i') }} @if ($payment->order)· {{ $payment->order->order_number }}@endif</div></div>
                            <strong>{{ $money->money($payment->amount_minor) }}</strong>
                        </div>
                    @empty
                        <div class="tiny">No payments in this shift.</div>
                    @endforelse
                </div>
            </div>

            @if ($shift->refunds->isNotEmpty())
                <div class="panel">
                    <div class="panel-head"><div class="panel-title">Refunds</div></div>
                    <div class="panel-body">
                        @foreach ($shift->refunds as $refund)
                            <div style="display:flex;gap:10px;padding:8px 0;border-bottom:1px solid var(--border);">
                                <div style="flex:1;"><div style="font-weight:600;font-size:12.5px;">{{ $refund->refund_number }}</div><div class="tiny">{{ $refund->reason }}</div></div>
                                <strong style="color:var(--danger);">− {{ $money->money($refund->amount_minor) }}</strong>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>
@endsection

@push('modals')
    @if ($shift->isOpen())
        <dialog class="modal" id="movement-dialog">
            <form method="POST" action="{{ route('shifts.cash', $shift) }}">
                @csrf
                <div class="modal-head"><div class="modal-title">Cash movement</div><button type="button" class="close-x" data-close><x-icon name="x" class="w-4 h-4" /></button></div>
                <div class="modal-body">
                    <div class="form-grid">
                        <div class="field"><label class="label">Type</label><select name="type" class="select"><option value="in">Cash in</option><option value="out">Cash out</option></select></div>
                        <div class="field"><label class="label">Amount</label><input name="amount" class="input" inputmode="decimal" required placeholder="0.00"></div>
                    </div>
                    <div class="field" style="margin-top:12px;"><label class="label">Reason</label><input name="reason" class="input" required maxlength="255"></div>
                </div>
                <div class="modal-foot"><button type="button" class="btn btn-ghost" data-close>Cancel</button><button type="submit" class="btn btn-primary">Record movement</button></div>
            </form>
        </dialog>

        <dialog class="modal" id="close-dialog">
            <form method="POST" action="{{ route('shifts.close', $shift) }}">
                @csrf
                <div class="modal-head"><div class="modal-title">Close shift</div><button type="button" class="close-x" data-close><x-icon name="x" class="w-4 h-4" /></button></div>
                <div class="modal-body">
                    <div class="alert alert-warning" style="margin-bottom:14px;"><x-icon name="alert" class="w-4 h-4 shrink-0" /><span>Expected cash in drawer is <strong>{{ $money->money($expected) }}</strong>.</span></div>
                    <div class="field"><label class="label">Counted cash</label><input name="counted_cash" class="input" inputmode="decimal" required placeholder="0.00"></div>
                    <div class="field" style="margin-top:12px;"><label class="label">Closing notes (optional)</label><textarea name="closing_notes" class="textarea" rows="2" maxlength="1000"></textarea></div>
                </div>
                <div class="modal-foot"><button type="button" class="btn btn-ghost" data-close>Cancel</button><button type="submit" class="btn btn-danger">Close shift</button></div>
            </form>
        </dialog>
    @endif
@endpush
