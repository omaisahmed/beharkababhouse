@extends('layouts.app')

@section('title', 'Order '.$order->order_number)

@php
    $money = app(\App\Services\SettingsService::class);
    $status = $order->status;
    $nextStatuses = match ($status) {
        \App\Enums\OrderStatus::Confirmed => ['preparing' => 'Start preparing'],
        \App\Enums\OrderStatus::Preparing => ['ready' => 'Mark ready'],
        \App\Enums\OrderStatus::Ready => ['served' => 'Mark served', 'completed' => 'Complete'],
        \App\Enums\OrderStatus::Served => ['completed' => 'Complete'],
        default => [],
    };
    $canUpdate = ! in_array($status, [\App\Enums\OrderStatus::Completed, \App\Enums\OrderStatus::Cancelled], true);
    $refundable = in_array($order->payment_status, [\App\Enums\PaymentStatus::Paid, \App\Enums\PaymentStatus::PartiallyRefunded], true);
@endphp

@section('content')
    <div class="page-head">
        <div>
            <div class="tiny" style="margin-bottom:5px;"><a href="{{ route('orders.index') }}" style="color:var(--muted);">Orders</a> / {{ $order->order_number }}</div>
            <h1 class="page-title">{{ $order->order_number }}</h1>
            <p class="page-subtitle">{{ $order->created_at?->format('l, d F Y · H:i') }} · {{ $order->order_type?->label() }}@if ($order->table) · {{ $order->table->name }}@endif · {{ $order->user?->name }}</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('pos.index', ['resume' => $order->order_number]) }}" class="btn btn-ghost" style="{{ $order->isHeld() ? '' : 'display:none;' }}"><x-icon name="undo" class="w-4 h-4" /> Resume</a>
            @if ($order->kitchen_required)
                <a href="{{ route('orders.kitchen-ticket', $order) }}" target="_blank" class="btn btn-ghost"><x-icon name="kitchen" class="w-4 h-4" /> Kitchen ticket</a>
            @endif
            <a href="{{ route('orders.receipt', $order) }}" target="_blank" class="btn btn-ghost"><x-icon name="printer" class="w-4 h-4" /> Receipt</a>
            @if ($canUpdate)
                @foreach ($nextStatuses as $value => $label)
                    <form method="POST" action="{{ route('orders.status', $order) }}">
                        @csrf
                        <input type="hidden" name="status" value="{{ $value }}">
                        <button class="btn btn-primary" type="submit">{{ $label }}</button>
                    </form>
                @endforeach
            @endif
        </div>
    </div>

    <div class="stat-grid">
        <div class="stat"><div class="stat-label">Status</div><div class="stat-value" style="font-size:20px;"><span class="badge {{ $status === \App\Enums\OrderStatus::Cancelled ? 'badge-rose' : ($status === \App\Enums\OrderStatus::Completed ? 'badge-emerald' : 'badge-amber') }}">{{ $status?->label() }}</span></div><div class="stat-meta">{{ $order->payment_status?->label() }}</div></div>
        <div class="stat"><div class="stat-label">Total</div><div class="stat-value">{{ $money->money($order->total_minor) }}</div><div class="stat-meta">Paid {{ $money->money($order->paid_minor) }} · Refunded {{ $money->money($order->refunded_minor) }}</div></div>
        <div class="stat"><div class="stat-label">Items</div><div class="stat-value">{{ $order->items->sum('quantity') }}</div><div class="stat-meta">{{ $order->items->count() }} line items</div></div>
        <div class="stat"><div class="stat-label">Shift</div><div class="stat-value" style="font-size:18px;">{{ $order->shift?->opened_at?->format('d M · H:i') ?? '—' }}</div><div class="stat-meta">{{ $order->shift?->status?->label() ?? 'No shift linked' }}</div></div>
    </div>

    <div class="grid-2" style="margin-top:18px;align-items:start;">
        <div class="panel">
            <div class="panel-head"><div><div class="panel-title">Order items</div><div class="panel-sub">Prices captured at the time of sale</div></div></div>
            <div class="table-wrap">
                <table class="data-table">
                    <thead><tr><th>Item</th><th class="r">Qty</th><th class="r">Unit</th><th class="r">Total</th></tr></thead>
                    <tbody>
                        @foreach ($order->items as $item)
                            <tr>
                                <td>
                                    <div style="font-weight:600;">{{ $item->product_name }}</div>
                                    @if ($item->variant_name)<div class="tiny">{{ $item->variant_name }}</div>@endif
                                    @foreach ($item->modifiers as $modifier)
                                        <div class="tiny">+ {{ $modifier->name }} @if ($modifier->price_minor) ({{ $money->money($modifier->price_minor) }}) @endif</div>
                                    @endforeach
                                    @if ($item->notes)<div class="tiny" style="color:var(--warning);">“{{ $item->notes }}”</div>@endif
                                </td>
                                <td class="r">{{ $item->quantity }}</td>
                                <td class="r">{{ $money->money($item->unit_price_minor) }}</td>
                                <td class="r">{{ $money->money($item->line_total_minor) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="panel-body" style="border-top:1px solid var(--border);">
                <div class="summary-row"><span>Subtotal</span><strong>{{ $money->money($order->subtotal_minor) }}</strong></div>
                @if ($order->discount_minor > 0)<div class="summary-row"><span>Discount</span><strong style="color:var(--danger);">− {{ $money->money($order->discount_minor) }}</strong></div>@endif
                @if ($order->service_charge_minor > 0)<div class="summary-row"><span>{{ $order->service_charge_label ?: 'Service charge' }}</span><strong>{{ $money->money($order->service_charge_minor) }}</strong></div>@endif
                @if ($order->delivery_fee_minor > 0)<div class="summary-row"><span>Delivery</span><strong>{{ $money->money($order->delivery_fee_minor) }}</strong></div>@endif
                @if ($order->tax_minor > 0)<div class="summary-row"><span>{{ $order->tax_label ?: 'Tax' }}</span><strong>{{ $money->money($order->tax_minor) }}</strong></div>@endif
                <div class="summary-total"><span>Total</span><strong>{{ $money->money($order->total_minor) }}</strong></div>
            </div>
        </div>

        <div class="stack" style="display:grid;gap:14px;">
            <div class="panel">
                <div class="panel-head"><div class="panel-title">Customer & delivery</div></div>
                <div class="panel-body">
                    <div class="summary-row"><span>Customer</span><strong>{{ $order->customer_name ?: 'Walk-in' }}</strong></div>
                    <div class="summary-row"><span>Phone</span><strong>{{ $order->customer_phone ?: '—' }}</strong></div>
                    <div class="summary-row"><span>Address</span><strong style="text-align:right;max-width:65%;">{{ $order->customer_address ?: '—' }}</strong></div>
                    @if ($order->delivery_instructions)<div class="summary-row"><span>Instructions</span><strong style="text-align:right;max-width:65%;">{{ $order->delivery_instructions }}</strong></div>@endif
                    @if ($order->notes)<div class="summary-row"><span>Notes</span><strong style="text-align:right;max-width:65%;">{{ $order->notes }}</strong></div>@endif
                    @if ($order->cancellation_reason)
                        <div class="alert alert-danger" style="margin-top:12px;">{{ $order->cancellation_reason }}</div>
                    @endif
                </div>
            </div>

            <div class="panel">
                <div class="panel-head"><div class="panel-title">Payments</div></div>
                <div class="panel-body">
                    @forelse ($order->payments as $payment)
                        <div style="display:flex;gap:10px;padding:8px 0;border-bottom:1px solid var(--border);">
                            <div style="flex:1;">
                                <div style="font-weight:600;font-size:12.5px;">{{ $payment->method?->label() }}</div>
                                <div class="tiny">{{ $payment->paid_at?->format('d M · H:i') }} · {{ $payment->user?->name }}</div>
                            </div>
                            <strong>{{ $money->money($payment->amount_minor) }}</strong>
                        </div>
                    @empty
                        <div class="tiny">No payment recorded.</div>
                    @endforelse

                    @if ($order->refunds->isNotEmpty())
                        <div style="margin-top:14px;font-weight:700;font-size:12px;">Refunds</div>
                        @foreach ($order->refunds as $refund)
                            <div style="display:flex;gap:10px;padding:8px 0;border-bottom:1px solid var(--border);">
                                <div style="flex:1;"><div style="font-weight:600;font-size:12.5px;">{{ $refund->refund_number }}</div><div class="tiny">{{ $refund->reason }}</div></div>
                                <strong style="color:var(--danger);">− {{ $money->money($refund->amount_minor) }}</strong>
                            </div>
                        @endforeach
                    @endif
                </div>

                @if ($refundable || ($order->payment_status->value !== 'unpaid' && auth()->user()->can('void-orders')))
                    <div class="panel-body" style="border-top:1px solid var(--border);display:flex;gap:8px;flex-wrap:wrap;">
                        @can('process-refunds')
                            @if ($refundable)
                                <button type="button" class="btn btn-ghost btn-sm" onclick="document.getElementById('refund-dialog').showModal()">Refund</button>
                            @endif
                        @endcan
                        @can('void-orders')
                            <button type="button" class="btn btn-ghost btn-sm" style="color:var(--danger);" onclick="document.getElementById('void-dialog').showModal()">Void order</button>
                        @endcan
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection

@push('modals')
    @can('process-refunds')
        <dialog class="modal" id="refund-dialog">
            <form method="POST" action="{{ route('orders.refund', $order) }}">
                @csrf
                <div class="modal-head"><div class="modal-title">Refund order</div><button type="button" class="close-x" data-close><x-icon name="x" class="w-4 h-4" /></button></div>
                <div class="modal-body">
                    <p class="tiny" style="margin-bottom:14px;">Order total {{ $money->money($order->total_minor) }} · Already refunded {{ $money->money($order->refunded_minor) }}.</p>
                    <div class="form-grid">
                        <div class="field"><label class="label">Amount</label><input name="amount" class="input" inputmode="decimal" required placeholder="0.00"></div>
                        <div class="field"><label class="label">Method</label><select name="method" class="select">@foreach (\App\Enums\PaymentMethod::cases() as $method)<option value="{{ $method->value }}">{{ $method->label() }}</option>@endforeach</select></div>
                    </div>
                    <div class="field" style="margin-top:12px;"><label class="label">Reason</label><textarea name="reason" class="textarea" rows="2" required maxlength="500"></textarea></div>
                    <label class="check" style="margin-top:12px;"><input type="checkbox" name="restock" value="1"><span>Return ingredients to stock</span></label>
                </div>
                <div class="modal-foot"><button type="button" class="btn btn-ghost" data-close>Cancel</button><button type="submit" class="btn btn-danger">Record refund</button></div>
            </form>
        </dialog>
    @endcan

    @can('void-orders')
        <dialog class="modal" id="void-dialog">
            <form method="POST" action="{{ route('orders.void', $order) }}" data-confirm="Void this order and reverse all payments?">
                @csrf
                <div class="modal-head"><div class="modal-title">Void order {{ $order->order_number }}</div><button type="button" class="close-x" data-close><x-icon name="x" class="w-4 h-4" /></button></div>
                <div class="modal-body">
                    <div class="alert alert-warning" style="margin-bottom:14px;"><x-icon name="alert" class="w-4 h-4 shrink-0" /><span>This cancels the order, reverses payments, and restocks ingredients. This cannot be undone.</span></div>
                    <div class="field"><label class="label">Reason</label><textarea name="reason" class="textarea" rows="3" required maxlength="500"></textarea></div>
                </div>
                <div class="modal-foot"><button type="button" class="btn btn-ghost" data-close>Cancel</button><button type="submit" class="btn btn-danger">Void order</button></div>
            </form>
        </dialog>
    @endcan
@endpush
