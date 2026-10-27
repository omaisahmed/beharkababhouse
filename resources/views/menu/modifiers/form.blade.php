@extends('layouts.app')

@section('title', $group->exists ? 'Edit modifier group' : 'New modifier group')

@php
    use App\Support\Money;

    $editing = $group->exists;
    $modifierRows = old('modifiers', collect($modifiers)->map(fn ($modifier) => [
        'id' => $modifier->id,
        'name' => $modifier->name,
        'price' => Money::toDecimal($modifier->price_minor),
        'is_active' => $modifier->is_active,
    ])->all());
    $showInlineErrors = true;
@endphp

@section('content')
    <form method="POST" action="{{ $editing ? route('modifier-groups.update', $group) : route('modifier-groups.store') }}">
        @csrf
        @if ($editing) @method('PUT') @endif

        <div class="page-head">
            <div>
                <div class="tiny" style="margin-bottom:5px;"><a href="{{ route('modifier-groups.index') }}" style="color:var(--muted);">Modifiers</a> / {{ $editing ? $group->name : 'New' }}</div>
                <h1 class="page-title">{{ $editing ? 'Edit modifier group' : 'New modifier group' }}</h1>
                <p class="page-subtitle">Attach this group to products to offer add-ons at the point of sale.</p>
            </div>
            <div class="page-actions">
                <a href="{{ route('modifier-groups.index') }}" class="btn btn-ghost">Cancel</a>
                <button type="submit" class="btn btn-primary">{{ $editing ? 'Save changes' : 'Create group' }}</button>
            </div>
        </div>

        <div class="grid-2" style="align-items:start;">
            <div class="panel">
                <div class="panel-head"><div class="panel-title">Group details</div></div>
                <div class="panel-body">
                    <div class="field"><label class="label">Name</label><input name="name" class="input" required maxlength="80" value="{{ old('name', $group->name) }}" placeholder="Spice level">@error('name')<div class="field-error">{{ $message }}</div>@enderror</div>
                    <div class="field" style="margin-top:12px;"><label class="label">Description</label><textarea name="description" class="textarea" rows="2" maxlength="500">{{ old('description', $group->description) }}</textarea></div>
                    <div class="form-grid" style="margin-top:12px;">
                        <div class="field"><label class="label">Minimum selections</label><input name="min_selections" type="number" min="0" max="20" class="input" required value="{{ old('min_selections', $group->min_selections ?? 0) }}">@error('min_selections')<div class="field-error">{{ $message }}</div>@enderror</div>
                        <div class="field"><label class="label">Maximum selections</label><input name="max_selections" type="number" min="1" max="20" class="input" required value="{{ old('max_selections', $group->max_selections ?? 1) }}">@error('max_selections')<div class="field-error">{{ $message }}</div>@enderror</div>
                    </div>
                    <div class="form-grid" style="margin-top:12px;">
                        <div class="field"><label class="label">Sort order</label><input name="sort_order" type="number" min="0" max="9999" class="input" value="{{ old('sort_order', $group->sort_order ?? 0) }}"></div>
                        <div class="field" style="display:flex;align-items:flex-end;">
                            <label class="check"><input type="checkbox" name="is_required" value="1" @checked(old('is_required', $group->is_required ?? false))><span>Required before adding to cart</span></label>
                        </div>
                    </div>
                    <label class="check" style="margin-top:12px;"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $group->is_active ?? true))><span>Group is active</span></label>
                </div>
            </div>

            <div class="panel">
                <div class="panel-head">
                    <div><div class="panel-title">Options</div><div class="panel-sub">Price is added to the item when selected.</div></div>
                    <button type="button" class="btn btn-ghost btn-sm" id="add-modifier">Add option</button>
                </div>
                <div class="panel-body">
                    <div id="modifier-rows" style="display:grid;gap:10px;"></div>
                    <div class="tiny" id="modifier-empty" style="display:none;">No options yet. Add at least one.</div>
                </div>
            </div>
        </div>
    </form>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const rows = document.getElementById('modifier-rows');
        const empty = document.getElementById('modifier-empty');
        let index = 0;

        const refreshEmpty = () => {
            empty.style.display = rows.children.length ? 'none' : 'block';
        };

        const addRow = (modifier = {}) => {
            const i = index++;
            const row = document.createElement('div');
            row.style.cssText = 'display:grid;grid-template-columns:1fr 120px auto auto;gap:8px;align-items:center;';
            row.innerHTML = `
                <input type="hidden" name="modifiers[${i}][id]" value="${modifier.id ?? ''}">
                <input name="modifiers[${i}][name]" class="input" placeholder="Option name" maxlength="80" value="${modifier.name ?? ''}">
                <input name="modifiers[${i}][price]" class="input" inputmode="decimal" placeholder="+0.00" value="${modifier.price ?? ''}">
                <label class="check"><input type="checkbox" name="modifiers[${i}][is_active]" value="1" ${modifier.is_active !== false ? 'checked' : ''}><span>Active</span></label>
                <button type="button" class="icon-btn" data-remove><x-icon name="x" class="w-3.5 h-3.5" /></button>
            `;
            row.querySelector('[data-remove]').addEventListener('click', () => {
                row.remove();
                refreshEmpty();
            });
            rows.appendChild(row);
            refreshEmpty();
        };

        @json($modifierRows).forEach((modifier) => addRow(modifier));
        refreshEmpty();

        document.getElementById('add-modifier').addEventListener('click', () => addRow());
    });
</script>
@endpush
