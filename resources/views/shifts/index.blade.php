@extends('layouts.app')

@section('title', 'Shifts')

@php
    $money = app(\App\Services\SettingsService::class);
@endphp

@section('content')
    <div class="page-head">
        <div>
            <h1 class="page-title">Cashier shifts</h1>
            <p class="page-subtitle">Track opening cash, sales, cash movements, and end-of-shift variance.</p>
        </div>
        <div class="page-actions">
            @if ($current)
                <button type="button" class="btn btn-ghost" onclick="document.getElementById('movement-dialog').showModal()">Cash in / out</button>
                <button type="button" class="btn btn-primary" onclick="document.getElementById('close-dialog').showModal()">Close shift</button>
            @else
                <button type="button" class="btn btn-primary" onclick="document.getElementById('open-dialog').showModal()"><x-icon name="plus" class="w-4 h-4" /> Open shift</button>
            @endif
        </div>
    </div>

    @if ($current)
        <div class="panel" style="margin-bottom:18px;border-color:rgba(16,185,129,.35);">
            <div class="panel-head">
                <div>
                    <div class="panel-title">Current shift · opened {{ $current->opened_at?->format('d M, H:i') }}</div>
                    <div class="panel-sub">{{ $current->user?->name }} · {{ $current->opened_at?->diffForHumans() }}</div>
                </div>
                <span class="badge badge-emerald">Open</span>
            </div>
            <div class="panel-body">
                <div class="money-grid">
                    <div class="money-cell"><div class="label">Opening cash</div><div class="value">{{ $money->money($current->opening_cash_minor) }}</div></div>
                    <div class="money-cell"><div class="label">Cash sales</div><div class="value" style="color:var(--success);">{{ $money->money($current->cash_sales_minor) }}</div></div>
                    <div class="money-cell"><div class="label">Cash refunds</div><div class="value" style="color:var(--danger);">{{ $money->money($current->cash_refunds_minor) }}</div></div>
                    <div class="money-cell"><div class="label">Cash in</div><div class="value">{{ $money->money($current->cash_in_minor) }}</div></div>
                    <div class="money-cell"><div class="label">Cash out</div><div class="value">{{ $money->money($current->cash_out_minor) }}</div></div>
                    <div class="money-cell"><div class="label">Expected in drawer</div><div class="value" style="color:var(--brand-strong);font-size:21px;">{{ $money->money($expected) }}</div></div>
                </div>
            </div>
        </div>
    @endif

    <div class="stat-grid">
        <div class="stat"><div class="stat-label">Cash sales today</div><div class="stat-value">{{ $money->money($stats['sales']) }}</div><div class="stat-meta">Across {{ $shifts->total() }} recorded shifts</div></div>
        <div class="stat"><div class="stat-label">Refunds today</div><div class="stat-value">{{ $money->money($stats['refunds']) }}</div><div class="stat-meta">Cash refunded</div></div>
        <div class="stat"><div class="stat-label">Cash movements</div><div class="stat-value">{{ $money->money($stats['cash_in'] - $stats['cash_out']) }}</div><div class="stat-meta">In {{ $money->money($stats['cash_in']) }} · Out {{ $money->money($stats['cash_out']) }}</div></div>
        <div class="stat"><div class="stat-label">Variance today</div><div class="stat-value" style="color:{{ $stats['variance'] === 0 ? 'var(--text)' : ($stats['variance'] > 0 ? 'var(--warning)' : 'var(--danger)') }};">{{ $money->money($stats['variance']) }}</div><div class="stat-meta">Counted vs expected</div></div>
    </div>

    <div class="panel" style="margin-top:18px;">
        <div class="panel-head"><div><div class="panel-title">Shift history</div><div class="panel-sub">Most recent first</div></div></div>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>Shift</th><th>Cashier</th><th>Opened</th><th>Closed</th><th class="r">Opening</th><th class="r">Expected</th><th class="r">Counted</th><th class="r">Variance</th><th></th></tr></thead>
                <tbody>
                    @forelse ($shifts as $shift)
                        <tr>
                            <td><span class="badge {{ $shift->isOpen() ? 'badge-emerald' : 'badge-neutral' }}">{{ $shift->isOpen() ? 'Open' : 'Closed' }}</span></td>
                            <td>{{ $shift->user?->name }}</td>
                            <td class="tiny">{{ $shift->opened_at?->format('d M Y, H:i') }}</td>
                            <td class="tiny">{{ $shift->closed_at?->format('d M Y, H:i') ?? '—' }}</td>
                            <td class="r">{{ $money->money($shift->opening_cash_minor) }}</td>
                            <td class="r">{{ $shift->expected_cash_minor !== null ? $money->money($shift->expected_cash_minor) : '—' }}</td>
                            <td class="r">{{ $shift->counted_cash_minor !== null ? $money->money($shift->counted_cash_minor) : '—' }}</td>
                            <td class="r" style="color:{{ ($shift->difference_minor ?? 0) === 0 ? 'inherit' : (($shift->difference_minor ?? 0) > 0 ? 'var(--warning)' : 'var(--danger)') }};">{{ $shift->difference_minor !== null ? $money->money($shift->difference_minor) : '—' }}</td>
                            <td class="r"><a href="{{ route('shifts.show', $shift) }}" class="btn btn-ghost btn-sm">Details</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="9"><div class="empty" style="padding:40px 20px;"><div class="empty-title">No shifts yet</div><div class="empty-text">Open a shift before taking cash orders.</div></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($shifts->hasPages())
            <div class="panel-body">{{ $shifts->links() }}</div>
        @endif
    </div>
@endsection

@push('modals')
    @unless ($current)
        <dialog class="modal" id="open-dialog">
            <form method="POST" action="{{ route('shifts.store') }}">
                @csrf
                <div class="modal-head"><div class="modal-title">Open shift</div><button type="button" class="close-x" data-close><x-icon name="x" class="w-4 h-4" /></button></div>
                <div class="modal-body">
                    <div class="field"><label class="label">Opening cash in drawer</label><input name="opening_cash" class="input" inputmode="decimal" required placeholder="0.00"></div>
                    <div class="field" style="margin-top:12px;"><label class="label">Notes (optional)</label><textarea name="notes" class="textarea" rows="2" maxlength="500"></textarea></div>
                </div>
                <div class="modal-foot"><button type="button" class="btn btn-ghost" data-close>Cancel</button><button type="submit" class="btn btn-primary">Open shift</button></div>
            </form>
        </dialog>
    @endunless

    @if ($current)
        <dialog class="modal" id="movement-dialog">
            <form method="POST" action="{{ route('shifts.cash', $current) }}">
                @csrf
                <div class="modal-head"><div class="modal-title">Cash movement</div><button type="button" class="close-x" data-close><x-icon name="x" class="w-4 h-4" /></button></div>
                <div class="modal-body">
                    <div class="form-grid">
                        <div class="field"><label class="label">Type</label><select name="type" class="select"><option value="in">Cash in</option><option value="out">Cash out</option></select></div>
                        <div class="field"><label class="label">Amount</label><input name="amount" class="input" inputmode="decimal" required placeholder="0.00"></div>
                    </div>
                    <div class="field" style="margin-top:12px;"><label class="label">Reason</label><input name="reason" class="input" required maxlength="255" placeholder="Petty cash, float top-up…"></div>
                </div>
                <div class="modal-foot"><button type="button" class="btn btn-ghost" data-close>Cancel</button><button type="submit" class="btn btn-primary">Record movement</button></div>
            </form>
        </dialog>

        <dialog class="modal" id="close-dialog">
            <form method="POST" action="{{ route('shifts.close', $current) }}">
                @csrf
                <div class="modal-head"><div class="modal-title">Close shift</div><button type="button" class="close-x" data-close><x-icon name="x" class="w-4 h-4" /></button></div>
                <div class="modal-body">
                    <div class="alert alert-warning" style="margin-bottom:14px;"><x-icon name="alert" class="w-4 h-4 shrink-0" /><span>Expected cash in drawer is <strong>{{ $money->money($expected) }}</strong>. Count the drawer before closing.</span></div>
                    <div class="field"><label class="label">Counted cash</label><input name="counted_cash" class="input" inputmode="decimal" required placeholder="0.00"></div>
                    <div class="field" style="margin-top:12px;"><label class="label">Closing notes (optional)</label><textarea name="closing_notes" class="textarea" rows="2" maxlength="1000"></textarea></div>
                </div>
                <div class="modal-foot"><button type="button" class="btn btn-ghost" data-close>Cancel</button><button type="submit" class="btn btn-danger">Close shift</button></div>
            </form>
        </dialog>
    @endif
@endpush
