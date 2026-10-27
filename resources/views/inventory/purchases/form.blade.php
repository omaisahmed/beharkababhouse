@extends('layouts.app')

@section('title', 'New purchase')

@php
    $showInlineErrors = true;
    $lineItems = $items->map(fn ($item) => [
        'id' => $item->id,
        'name' => $item->name,
        'unit' => $item->unit,
        'units' => \App\Support\Units::inputUnitsFor($item->unit),
    ])->values();
@endphp

@section('content')
    <form method="POST" action="{{ route('purchases.store') }}" id="purchase-form">
        @csrf

        <div class="page-head">
            <div>
                <div class="tiny" style="margin-bottom:5px;"><a href="{{ route('purchases.index') }}" style="color:var(--muted);">Purchases</a> / New</div>
                <h1 class="page-title">New purchase</h1>
                <p class="page-subtitle">Stock is added as soon as the purchase is saved.</p>
            </div>
            <div class="page-actions">
                <a href="{{ route('purchases.index') }}" class="btn btn-ghost">Cancel</a>
                <button type="submit" class="btn btn-primary">Record purchase</button>
            </div>
        </div>

        <div class="grid-2" style="align-items:start;">
            <div class="stack" style="display:grid;gap:14px;">
                <div class="panel">
                    <div class="panel-head"><div class="panel-title">Purchase details</div></div>
                    <div class="panel-body">
                        <div class="form-grid">
                            <div class="field"><label class="label">Supplier</label><select name="supplier_id" class="select"><option value="">No supplier</option>@foreach ($suppliers as $supplier)<option value="{{ $supplier->id }}" @selected(old('supplier_id') == $supplier->id)>{{ $supplier->name }}</option>@endforeach</select></div>
                            <div class="field"><label class="label">Purchase date</label><input type="date" name="purchased_at" class="input" required max="{{ today()->toDateString() }}" value="{{ old('purchased_at', today()->toDateString()) }}"></div>
                            <div class="field"><label class="label">Invoice reference</label><input name="reference" class="input" maxlength="120" value="{{ old('reference') }}"></div>
                            <div class="field"><label class="label">Payment status</label><select name="payment_status" class="select" required><option value="unpaid" @selected(old('payment_status') === 'unpaid')>Unpaid</option><option value="partial" @selected(old('payment_status') === 'partial')>Partially paid</option><option value="paid" @selected(old('payment_status') === 'paid')>Paid</option></select></div>
                            <div class="field"><label class="label">Amount paid</label><input name="paid" class="input" inputmode="decimal" value="{{ old('paid', '0') }}" placeholder="0.00"><div class="hint">Ignored when status is paid (full total is recorded).</div></div>
                        </div>
                        <div class="field" style="margin-top:12px;"><label class="label">Notes</label><textarea name="notes" class="textarea" rows="2" maxlength="1000">{{ old('notes') }}</textarea></div>
                    </div>
                </div>

                <div class="panel">
                    <div class="panel-head">
                        <div><div class="panel-title">Line items</div><div class="panel-sub">Enter what arrived from the supplier</div></div>
                        <button type="button" class="btn btn-ghost btn-sm" id="add-line">Add line</button>
                    </div>
                    <div class="panel-body">
                        <div id="line-rows" style="display:grid;gap:10px;"></div>
                        <div class="tiny" id="line-empty" style="display:none;">Add at least one line item.</div>
                    </div>
                </div>
            </div>

            <div class="panel">
                <div class="panel-head"><div class="panel-title">Summary</div></div>
                <div class="panel-body">
                    <div class="summary-row"><span>Lines</span><strong id="summary-lines">0</strong></div>
                    <div class="summary-row"><span>Estimated total</span><strong id="summary-total">{{ app(\App\Services\SettingsService::class)->money(0) }}</strong></div>
                    <div class="summary-total"><span>Balance</span><strong id="summary-balance">{{ app(\App\Services\SettingsService::class)->money(0) }}</strong></div>
                    <div class="alert alert-warning" style="margin-top:14px;font-size:11px;">The server recalculates totals using exact milli-unit conversions.</div>
                </div>
            </div>
        </div>
    </form>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const items = @json($lineItems);
        const rows = document.getElementById('line-rows');
        const empty = document.getElementById('line-empty');
        let index = 0;

        const refreshEmpty = () => { empty.style.display = rows.children.length ? 'none' : 'block'; };

        const itemOptions = () => items.map((item) =>
            `<option value="${item.id}" data-units='${JSON.stringify(item.units)}'>${item.name}</option>`
        ).join('');

        const fillUnits = (select, units) => {
            select.innerHTML = '';
            Object.entries(units).forEach(([value, label]) => {
                const option = document.createElement('option');
                option.value = value;
                option.textContent = label;
                select.appendChild(option);
            });
        };

        const recalc = () => {
            let total = 0;
            rows.querySelectorAll('[data-line]').forEach((row) => {
                const qty = parseFloat(row.querySelector('[data-qty]').value) || 0;
                const cost = parseFloat(row.querySelector('[data-cost]').value) || 0;
                total += qty * cost;
            });
            document.getElementById('summary-lines').textContent = rows.children.length;
            const formatted = total.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            document.getElementById('summary-total').textContent = @json(app(\App\Services\SettingsService::class)->currencySymbol()) + ' ' + formatted;
            document.getElementById('summary-balance').textContent = @json(app(\App\Services\SettingsService::class)->currencySymbol()) + ' ' + formatted;
        };

        const addLine = () => {
            const i = index++;
            const row = document.createElement('div');
            row.dataset.line = '1';
            row.style.cssText = 'display:grid;grid-template-columns:1fr 90px 80px 110px auto;gap:8px;align-items:center;';
            row.innerHTML = `
                <select name="items[${i}][inventory_item_id]" class="select" required>
                    <option value="">Choose item…</option>
                    ${itemOptions()}
                </select>
                <input name="items[${i}][quantity]" data-qty class="input" inputmode="decimal" placeholder="Qty" required>
                <select name="items[${i}][unit]" class="select"></select>
                <input name="items[${i}][unit_cost]" data-cost class="input" inputmode="decimal" placeholder="Unit cost" required>
                <button type="button" class="icon-btn" data-remove><x-icon name="x" class="w-3.5 h-3.5" /></button>
            `;

            const itemSelect = row.querySelector('select[name$="[inventory_item_id]"]');
            const unitSelect = row.querySelector('select[name$="[unit]"]');
            itemSelect.addEventListener('change', () => {
                const selected = itemSelect.selectedOptions[0];
                fillUnits(unitSelect, selected && selected.dataset.units ? JSON.parse(selected.dataset.units) : {});
            });
            row.querySelector('[data-qty]').addEventListener('input', recalc);
            row.querySelector('[data-cost]').addEventListener('input', recalc);
            row.querySelector('[data-remove]').addEventListener('click', () => {
                row.remove();
                refreshEmpty();
                recalc();
            });

            rows.appendChild(row);
            refreshEmpty();
            recalc();
        };

        document.getElementById('add-line').addEventListener('click', addLine);
        addLine();
    });
</script>
@endpush
