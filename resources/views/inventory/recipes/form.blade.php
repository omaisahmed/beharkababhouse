@extends('layouts.app')

@section('title', 'Recipe · '.$product->name)

@php
    $existing = old('ingredients', $recipe->exists
        ? $recipe->ingredients->map(fn ($ingredient) => [
            'inventory_item_id' => $ingredient->inventory_item_id,
            'quantity' => \App\Support\Quantity::format($ingredient->quantity_milli),
            'unit' => $ingredient->inventoryItem?->unit,
        ])->all()
        : []);
    $showInlineErrors = true;
    $lineItems = $items->map(fn ($item) => [
        'id' => $item->id,
        'name' => $item->name,
        'unit' => $item->unit,
        'units' => \App\Support\Units::inputUnitsFor($item->unit),
    ])->values();
@endphp

@section('content')
    <form method="POST" action="{{ route('recipes.update', $product) }}">
        @csrf
        @method('PUT')

        <div class="page-head">
            <div>
                <div class="tiny" style="margin-bottom:5px;"><a href="{{ route('recipes.index') }}" style="color:var(--muted);">Recipes</a> / {{ $product->name }}</div>
                <h1 class="page-title">{{ $product->name }}</h1>
                <p class="page-subtitle">Ingredients are deducted when this product is sold. Quantities are per single item.</p>
            </div>
            <div class="page-actions">
                <a href="{{ route('recipes.index') }}" class="btn btn-ghost">Cancel</a>
                <button type="submit" class="btn btn-primary">Save recipe</button>
            </div>
        </div>

        <div class="grid-2" style="align-items:start;">
            <div class="panel">
                <div class="panel-head">
                    <div><div class="panel-title">Ingredients</div><div class="panel-sub">Add each stock item once</div></div>
                    <button type="button" class="btn btn-ghost btn-sm" id="add-ingredient">Add ingredient</button>
                </div>
                <div class="panel-body">
                    <div id="ingredient-rows" style="display:grid;gap:10px;"></div>
                    <div class="tiny" id="ingredient-empty" style="display:none;">No ingredients yet. Stock will not be deducted for this product.</div>
                </div>
            </div>

            <div class="panel">
                <div class="panel-head"><div class="panel-title">Recipe settings</div></div>
                <div class="panel-body">
                    <div class="field"><label class="label">Yield quantity</label><input name="yield_quantity" type="number" min="1" max="1000" class="input" required value="{{ old('yield_quantity', $recipe->yield_quantity ?? 1) }}"><div class="hint">How many items this recipe produces. Usually 1.</div></div>
                    <div class="field" style="margin-top:12px;"><label class="label">Notes</label><textarea name="notes" class="textarea" rows="3" maxlength="1000">{{ old('notes', $recipe->notes) }}</textarea></div>
                    <label class="check" style="margin-top:12px;"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $recipe->is_active ?? true))><span>Deduct stock when sold</span></label>
                    <div class="alert alert-warning" style="margin-top:14px;font-size:11px;">If stock runs out, the POS still allows the sale unless you mark the product unavailable. Use this to avoid blocking service.</div>
                </div>
            </div>
        </div>
    </form>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const items = @json($lineItems);
        const rows = document.getElementById('ingredient-rows');
        const empty = document.getElementById('ingredient-empty');
        let index = 0;

        const refreshEmpty = () => {
            empty.style.display = rows.children.length ? 'none' : 'block';
        };

        const itemOptions = (selected) => items.map((item) =>
            `<option value="${item.id}" data-unit="${item.unit}" data-units='${JSON.stringify(item.units)}' ${Number(selected) === item.id ? 'selected' : ''}>${item.name}</option>`
        ).join('');

        const fillUnits = (select, units, selected) => {
            select.innerHTML = '';
            Object.entries(units).forEach(([value, label]) => {
                const option = document.createElement('option');
                option.value = value;
                option.textContent = label;
                option.selected = selected === value;
                select.appendChild(option);
            });
        };

        const addRow = (ingredient = {}) => {
            const i = index++;
            const row = document.createElement('div');
            row.style.cssText = 'display:grid;grid-template-columns:1fr 110px 90px auto;gap:8px;align-items:center;';
            row.innerHTML = `
                <select name="ingredients[${i}][inventory_item_id]" class="select" required>
                    <option value="">Choose item…</option>
                    ${itemOptions(ingredient.inventory_item_id)}
                </select>
                <input name="ingredients[${i}][quantity]" class="input" inputmode="decimal" placeholder="Qty" value="${ingredient.quantity ?? ''}" required>
                <select name="ingredients[${i}][unit]" class="select"></select>
                <button type="button" class="icon-btn" data-remove><x-icon name="x" class="w-3.5 h-3.5" /></button>
            `;

            const itemSelect = row.querySelector('select[name$="[inventory_item_id]"]');
            const unitSelect = row.querySelector('select[name$="[unit]"]');

            const syncUnits = () => {
                const selected = itemSelect.selectedOptions[0];
                const units = selected && selected.dataset.units ? JSON.parse(selected.dataset.units) : {};
                fillUnits(unitSelect, units, ingredient.unit || selected?.dataset.unit);
                ingredient.unit = null;
            };

            itemSelect.addEventListener('change', syncUnits);
            syncUnits();

            row.querySelector('[data-remove]').addEventListener('click', () => {
                row.remove();
                refreshEmpty();
            });

            rows.appendChild(row);
            refreshEmpty();
        };

        @json($existing).forEach((ingredient) => addRow(ingredient));
        refreshEmpty();

        document.getElementById('add-ingredient').addEventListener('click', () => addRow());
    });
</script>
@endpush
