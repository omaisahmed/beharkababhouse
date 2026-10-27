@extends('layouts.app')

@section('title', 'Inventory')

@php
    $money = app(\App\Services\SettingsService::class);
@endphp

@section('content')
    <div class="page-head">
        <div>
            <h1 class="page-title">Stock levels</h1>
            <p class="page-subtitle">Ingredients and supplies tracked in kg, litres, and pieces.</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('stock-movements.index') }}" class="btn btn-ghost">Movement history</a>
            <button type="button" class="btn btn-primary" onclick="document.getElementById('item-dialog').showModal()"><x-icon name="plus" class="w-4 h-4" /> New item</button>
        </div>
    </div>

    <div class="stat-grid">
        <div class="stat"><div class="stat-label">Items</div><div class="stat-value">{{ $summary['items'] }}</div><div class="stat-meta">Active inventory records</div></div>
        <div class="stat"><div class="stat-label">Low stock</div><div class="stat-value" style="color:var(--warning);">{{ $summary['low'] }}</div><div class="stat-meta">At or below minimum</div></div>
        <div class="stat"><div class="stat-label">Out of stock</div><div class="stat-value" style="color:var(--danger);">{{ $summary['out'] }}</div><div class="stat-meta">Needs restocking</div></div>
        <div class="stat"><div class="stat-label">Stock value</div><div class="stat-value">{{ $money->money($summary['value']) }}</div><div class="stat-meta">At latest unit cost</div></div>
    </div>

    <div class="panel" style="margin-top:18px;">
        <form method="GET" action="{{ route('inventory.index') }}" class="panel-body" style="display:flex;gap:9px;flex-wrap:wrap;align-items:center;border-bottom:1px solid var(--border);">
            <input name="q" value="{{ request('q') }}" class="input" placeholder="Search item or SKU…" style="max-width:260px;">
            <label class="check"><input type="checkbox" name="low" value="1" @checked(request()->boolean('low'))><span>Low stock only</span></label>
            <button type="submit" class="btn btn-ghost">Filter</button>
            @if (request()->hasAny(['q', 'low']))<a href="{{ route('inventory.index') }}" class="btn btn-ghost">Clear</a>@endif
        </form>

        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>Item</th><th>Supplier</th><th class="r">In stock</th><th class="r">Minimum</th><th class="r">Unit cost</th><th class="r">Value</th><th>Status</th><th class="r"></th></tr></thead>
                <tbody>
                    @forelse ($items as $item)
                        <tr>
                            <td><div style="font-weight:650;">{{ $item->name }}</div><div class="tiny">{{ $item->sku ?: 'No SKU' }} · tracked in {{ \App\Support\Units::label($item->unit) }}</div></td>
                            <td class="tiny">{{ $item->supplier?->name ?? '—' }}</td>
                            <td class="r" style="font-weight:650;">{{ $item->stockLabel() }}</td>
                            <td class="r tiny">{{ $item->minimum_stock_milli > 0 ? $item->minimumStockLabel() : '—' }}</td>
                            <td class="r tiny">{{ $item->costLabel() }}</td>
                            <td class="r tiny">{{ $money->money($item->stockValueMinor()) }}</td>
                            <td>
                                @if ($item->isOutOfStock())
                                    <span class="badge badge-rose">Out</span>
                                @elseif ($item->isLowStock())
                                    <span class="badge badge-amber">Low</span>
                                @else
                                    <span class="badge badge-emerald">OK</span>
                                @endif
                            </td>
                            <td class="r">
                                <div style="display:flex;gap:5px;justify-content:flex-end;">
                                    <button type="button" class="btn btn-ghost btn-sm" data-restock="{{ $item->id }}" data-name="{{ $item->name }}" data-units='@json(\App\Support\Units::inputUnitsFor($item->unit))'>Restock</button>
                                    <details style="position:relative;">
                                        <summary class="btn btn-ghost btn-sm" style="list-style:none;cursor:pointer;">More</summary>
                                        <div class="dropdown" style="right:0;left:auto;min-width:180px;">
                                            <button type="button" class="dropdown-item" data-adjust="{{ $item->id }}" data-name="{{ $item->name }}" data-units='@json(\App\Support\Units::inputUnitsFor($item->unit))'>Adjust / wastage</button>
                                            <button type="button" class="dropdown-item" data-edit-item="{{ $item->id }}" data-name="{{ $item->name }}" data-sku="{{ $item->sku }}" data-supplier="{{ $item->supplier_id }}" data-minimum="{{ \App\Support\Quantity::format($item->minimum_stock_milli) }}" data-cost="{{ \App\Support\Money::toDecimal($item->cost_per_unit_minor) }}" data-active="{{ $item->is_active ? 1 : 0 }}" data-notes="{{ $item->notes }}">Edit item</button>
                                            <div class="dropdown-sep"></div>
                                            <form method="POST" action="{{ route('inventory.items.destroy', $item) }}" data-confirm="Remove {{ $item->name }} from inventory?">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="dropdown-item danger">Remove item</button>
                                            </form>
                                        </div>
                                    </details>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8"><div class="empty" style="padding:50px 20px;"><div class="empty-icon"><x-icon name="inventory" class="w-7 h-7" /></div><div class="empty-title">No inventory items</div><div class="empty-text">Add the ingredients you buy and track.</div></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($items->hasPages())<div class="panel-body">{{ $items->links() }}</div>@endif
    </div>
@endsection

@push('modals')
    <dialog class="modal" id="item-dialog">
        <form method="POST" action="{{ route('inventory.items.store') }}">
            @csrf
            <div class="modal-head"><div class="modal-title">New inventory item</div><button type="button" class="close-x" data-close><x-icon name="x" class="w-4 h-4" /></button></div>
            <div class="modal-body">
                <div class="form-grid">
                    <div class="field"><label class="label">Name</label><input name="name" class="input" required maxlength="120" placeholder="Chicken breast"></div>
                    <div class="field"><label class="label">SKU</label><input name="sku" class="input" maxlength="60"></div>
                    <div class="field"><label class="label">Supplier</label><select name="supplier_id" class="select"><option value="">None</option>@foreach ($suppliers as $supplier)<option value="{{ $supplier->id }}">{{ $supplier->name }}</option>@endforeach</select></div>
                    <div class="field"><label class="label">Tracked unit</label><select name="unit" class="select" required><option value="kg">kg</option><option value="l">L</option><option value="piece">piece</option></select></div>
                    <div class="field"><label class="label">Minimum stock</label><input name="minimum_stock" class="input" inputmode="decimal" placeholder="0" value="0"></div>
                    <div class="field"><label class="label">Cost per unit</label><input name="cost_per_unit" class="input" inputmode="decimal" placeholder="0.00"></div>
                    <div class="field"><label class="label">Opening stock</label><input name="opening_stock" class="input" inputmode="decimal" placeholder="0"></div>
                    <div class="field"><label class="label">Opening unit</label><select name="opening_unit" class="select"><option value="kg">kg</option><option value="g">g</option><option value="l">L</option><option value="ml">mL</option><option value="piece">piece</option></select></div>
                </div>
                <div class="field" style="margin-top:12px;"><label class="label">Notes</label><textarea name="notes" class="textarea" rows="2" maxlength="1000"></textarea></div>
            </div>
            <div class="modal-foot"><button type="button" class="btn btn-ghost" data-close>Cancel</button><button type="submit" class="btn btn-primary">Create item</button></div>
        </form>
    </dialog>

    <dialog class="modal" id="item-edit-dialog">
        <form method="POST" id="item-edit-form">
            @csrf
            @method('PUT')
            <div class="modal-head"><div class="modal-title">Edit inventory item</div><button type="button" class="close-x" data-close><x-icon name="x" class="w-4 h-4" /></button></div>
            <div class="modal-body">
                <div class="form-grid">
                    <div class="field"><label class="label">Name</label><input name="name" id="item-name" class="input" required maxlength="120"></div>
                    <div class="field"><label class="label">SKU</label><input name="sku" id="item-sku" class="input" maxlength="60"></div>
                    <div class="field"><label class="label">Supplier</label><select name="supplier_id" id="item-supplier" class="select"><option value="">None</option>@foreach ($suppliers as $supplier)<option value="{{ $supplier->id }}">{{ $supplier->name }}</option>@endforeach</select></div>
                    <div class="field"><label class="label">Minimum stock</label><input name="minimum_stock" id="item-minimum" class="input" inputmode="decimal"></div>
                    <div class="field"><label class="label">Cost per unit</label><input name="cost_per_unit" id="item-cost" class="input" inputmode="decimal"></div>
                </div>
                <div class="field" style="margin-top:12px;"><label class="label">Notes</label><textarea name="notes" id="item-notes" class="textarea" rows="2" maxlength="1000"></textarea></div>
                <label class="check" style="margin-top:12px;"><input type="checkbox" name="is_active" value="1" id="item-active"><span>Item is active</span></label>
            </div>
            <div class="modal-foot"><button type="button" class="btn btn-ghost" data-close>Cancel</button><button type="submit" class="btn btn-primary">Save changes</button></div>
        </form>
    </dialog>

    <dialog class="modal" id="restock-dialog">
        <form method="POST" id="restock-form">
            @csrf
            <div class="modal-head"><div class="modal-title">Restock <span id="restock-name"></span></div><button type="button" class="close-x" data-close><x-icon name="x" class="w-4 h-4" /></button></div>
            <div class="modal-body">
                <div class="form-grid">
                    <div class="field"><label class="label">Quantity received</label><input name="quantity" class="input" inputmode="decimal" required placeholder="0"></div>
                    <div class="field"><label class="label">Unit</label><select name="unit" id="restock-unit" class="select"></select></div>
                    <div class="field" style="grid-column:span 2;"><label class="label">Unit cost</label><input name="unit_cost" class="input" inputmode="decimal" required placeholder="0.00"></div>
                </div>
                <div class="field" style="margin-top:12px;"><label class="label">Reference (optional)</label><input name="reason" class="input" maxlength="255" placeholder="Invoice, supplier…"></div>
            </div>
            <div class="modal-foot"><button type="button" class="btn btn-ghost" data-close>Cancel</button><button type="submit" class="btn btn-primary">Receive stock</button></div>
        </form>
    </dialog>

    <dialog class="modal" id="adjust-dialog">
        <form method="POST" id="adjust-form">
            @csrf
            <div class="modal-head"><div class="modal-title">Adjust <span id="adjust-name"></span></div><button type="button" class="close-x" data-close><x-icon name="x" class="w-4 h-4" /></button></div>
            <div class="modal-body">
                <div class="form-grid">
                    <div class="field"><label class="label">Type</label><select name="type" class="select"><option value="wastage">Wastage</option><option value="damage">Damage</option><option value="adjustment">Adjustment</option></select></div>
                    <div class="field"><label class="label">Quantity</label><input name="quantity" class="input" inputmode="decimal" required placeholder="0"></div>
                    <div class="field"><label class="label">Unit</label><select name="unit" id="adjust-unit" class="select"></select></div>
                </div>
                <div class="field" style="margin-top:12px;"><label class="label">Reason</label><input name="reason" class="input" required minlength="3" maxlength="255"></div>
            </div>
            <div class="modal-foot"><button type="button" class="btn btn-ghost" data-close>Cancel</button><button type="submit" class="btn btn-danger">Record adjustment</button></div>
        </form>
    </dialog>
@endpush

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const fillUnits = (select, units) => {
            select.innerHTML = '';
            Object.entries(units).forEach(([value, label]) => {
                const option = document.createElement('option');
                option.value = value;
                option.textContent = label;
                select.appendChild(option);
            });
        };

        document.querySelectorAll('[data-edit-item]').forEach((button) => {
            button.addEventListener('click', () => {
                document.getElementById('item-edit-form').action = @json(url('/inventory/items')) + '/' + button.dataset.editItem;
                document.getElementById('item-name').value = button.dataset.name || '';
                document.getElementById('item-sku').value = button.dataset.sku || '';
                document.getElementById('item-supplier').value = button.dataset.supplier || '';
                document.getElementById('item-minimum').value = button.dataset.minimum || '';
                document.getElementById('item-cost').value = button.dataset.cost || '';
                document.getElementById('item-notes').value = button.dataset.notes || '';
                document.getElementById('item-active').checked = button.dataset.active === '1';
                document.getElementById('item-edit-dialog').showModal();
            });
        });

        document.querySelectorAll('[data-restock]').forEach((button) => {
            button.addEventListener('click', () => {
                document.getElementById('restock-form').action = @json(url('/inventory/items')) + '/' + button.dataset.restock + '/restock';
                document.getElementById('restock-name').textContent = button.dataset.name;
                fillUnits(document.getElementById('restock-unit'), JSON.parse(button.dataset.units));
                document.getElementById('restock-dialog').showModal();
            });
        });

        document.querySelectorAll('[data-adjust]').forEach((button) => {
            button.addEventListener('click', () => {
                document.getElementById('adjust-form').action = @json(url('/inventory/items')) + '/' + button.dataset.adjust + '/adjust';
                document.getElementById('adjust-name').textContent = button.dataset.name;
                fillUnits(document.getElementById('adjust-unit'), JSON.parse(button.dataset.units));
                document.getElementById('adjust-dialog').showModal();
            });
        });
    });
</script>
@endpush
