@extends('layouts.app')

@section('title', 'Stock movements')

@php
    $money = app(\App\Services\SettingsService::class);
@endphp

@section('content')
    <div class="page-head">
        <div>
            <h1 class="page-title">Stock movements</h1>
            <p class="page-subtitle">Every change to stock, with reason and cashier.</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('inventory.index') }}" class="btn btn-ghost">Stock levels</a>
            <button type="button" class="btn btn-primary" onclick="document.getElementById('movement-dialog').showModal()"><x-icon name="plus" class="w-4 h-4" /> Record movement</button>
        </div>
    </div>

    <div class="panel">
        <form method="GET" action="{{ route('stock-movements.index') }}" class="panel-body" style="display:flex;gap:9px;flex-wrap:wrap;align-items:flex-end;border-bottom:1px solid var(--border);">
            <div class="field" style="min-width:200px;"><label class="label">Item</label><select name="item" class="select"><option value="">All items</option>@foreach ($items as $item)<option value="{{ $item->id }}" @selected(request('item') == $item->id)>{{ $item->name }}</option>@endforeach</select></div>
            <div class="field" style="min-width:180px;"><label class="label">Type</label><select name="type" class="select"><option value="">All types</option>@foreach (\App\Enums\StockMovementType::cases() as $type)<option value="{{ $type->value }}" @selected(request('type') === $type->value)>{{ $type->label() }}</option>@endforeach</select></div>
            <div class="field"><label class="label">From</label><input type="date" name="from" value="{{ request('from') }}" class="input"></div>
            <div class="field"><label class="label">To</label><input type="date" name="to" value="{{ request('to') }}" class="input"></div>
            <button type="submit" class="btn btn-ghost">Filter</button>
            @if (request()->hasAny(['item', 'type', 'from', 'to']))<a href="{{ route('stock-movements.index') }}" class="btn btn-ghost">Clear</a>@endif
        </form>

        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>When</th><th>Item</th><th>Type</th><th class="r">Quantity</th><th class="r">Unit cost</th><th class="r">Total</th><th>Reason</th><th>By</th></tr></thead>
                <tbody>
                    @forelse ($movements as $movement)
                        <tr>
                            <td class="tiny">{{ $movement->created_at?->format('d M Y, H:i') }}</td>
                            <td><div style="font-weight:600;">{{ $movement->inventoryItem?->name ?? 'Deleted item' }}</div></td>
                            <td><span class="badge badge-neutral">{{ $movement->type?->label() ?? '—' }}</span></td>
                            <td class="r" style="font-weight:650;color:{{ $movement->quantity_milli >= 0 ? 'var(--success)' : 'var(--danger)' }};">{{ $movement->quantityLabel() }}</td>
                            <td class="r tiny">{{ $money->money($movement->unit_cost_minor) }}</td>
                            <td class="r tiny">{{ $movement->totalCostLabel() }}</td>
                            <td class="tiny">{{ $movement->reason }}</td>
                            <td class="tiny">{{ $movement->user?->name ?? 'System' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8"><div class="empty" style="padding:50px 20px;"><div class="empty-icon"><x-icon name="movements" class="w-7 h-7" /></div><div class="empty-title">No movements</div><div class="empty-text">Stock changes will appear here.</div></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($movements->hasPages())<div class="panel-body">{{ $movements->links() }}</div>@endif
    </div>
@endsection

@push('modals')
    <dialog class="modal" id="movement-dialog">
        <form method="POST" action="{{ route('stock-movements.store') }}">
            @csrf
            <div class="modal-head"><div class="modal-title">Record movement</div><button type="button" class="close-x" data-close><x-icon name="x" class="w-4 h-4" /></button></div>
            <div class="modal-body">
                <div class="field"><label class="label">Item</label>
                    <select name="inventory_item_id" id="movement-item" class="select" required>
                        <option value="">Choose item…</option>
                        @foreach ($items as $item)
                            <option value="{{ $item->id }}" data-units='@json(\App\Support\Units::inputUnitsFor($item->unit))'>{{ $item->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-grid" style="margin-top:12px;">
                    <div class="field"><label class="label">Type</label>
                        <select name="type" class="select" required>
                            <option value="adjustment">Manual adjustment</option>
                            <option value="wastage">Wastage</option>
                            <option value="damage">Damage</option>
                            <option value="return">Returned stock</option>
                            <option value="opening">Opening stock</option>
                        </select>
                    </div>
                    <div class="field"><label class="label">Quantity</label><input name="quantity" class="input" inputmode="decimal" required placeholder="0"></div>
                    <div class="field"><label class="label">Unit</label><select name="unit" id="movement-unit" class="select"></select></div>
                </div>
                <div class="field" style="margin-top:12px;"><label class="label">Reason</label><input name="reason" class="input" required minlength="3" maxlength="255"></div>
                <div class="alert alert-warning" style="margin-top:12px;font-size:11px;">Purchases must be recorded from the Purchases screen so supplier balances stay accurate.</div>
            </div>
            <div class="modal-foot"><button type="button" class="btn btn-ghost" data-close>Cancel</button><button type="submit" class="btn btn-primary">Record movement</button></div>
        </form>
    </dialog>
@endpush

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const item = document.getElementById('movement-item');
        const unit = document.getElementById('movement-unit');

        const fill = () => {
            unit.innerHTML = '';
            const selected = item.selectedOptions[0];
            const units = selected && selected.dataset.units ? JSON.parse(selected.dataset.units) : {};
            Object.entries(units).forEach(([value, label]) => {
                const option = document.createElement('option');
                option.value = value;
                option.textContent = label;
                unit.appendChild(option);
            });
        };

        item.addEventListener('change', fill);
        fill();
    });
</script>
@endpush
