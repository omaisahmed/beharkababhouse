@extends('layouts.app')

@section('title', $product->exists ? 'Edit product' : 'New product')

@php
    use App\Support\Money;

    $editing = $product->exists;
    $variantRows = old('variants', collect($variants)->map(fn ($variant) => [
        'id' => $variant->id,
        'name' => $variant->name,
        'price_delta' => Money::toDecimal($variant->price_delta_minor),
        'is_default' => $variant->is_default,
        'is_active' => $variant->is_active,
    ])->all());
    $selected = old('modifier_groups', $selectedGroups);
    $showInlineErrors = true;
@endphp

@section('content')
    <form method="POST" action="{{ $editing ? route('products.update', $product) : route('products.store') }}" enctype="multipart/form-data">
        @csrf
        @if ($editing) @method('PUT') @endif

        <div class="page-head">
            <div>
                <div class="tiny" style="margin-bottom:5px;"><a href="{{ route('products.index') }}" style="color:var(--muted);">Products</a> / {{ $editing ? $product->name : 'New' }}</div>
                <h1 class="page-title">{{ $editing ? 'Edit product' : 'New product' }}</h1>
                <p class="page-subtitle">Prices are entered in {{ app(\App\Services\SettingsService::class)->currencySymbol() }}. Tax is applied per line at checkout.</p>
            </div>
            <div class="page-actions">
                <a href="{{ route('products.index') }}" class="btn btn-ghost">Cancel</a>
                <button type="submit" class="btn btn-primary">{{ $editing ? 'Save changes' : 'Create product' }}</button>
            </div>
        </div>

        <div class="grid-2" style="align-items:start;">
            <div class="stack" style="display:grid;gap:14px;">
                <div class="panel">
                    <div class="panel-head"><div class="panel-title">Basics</div></div>
                    <div class="panel-body">
                        <div class="form-grid">
                            <div class="field" style="grid-column:span 2;"><label class="label">Product name</label><input name="name" class="input" required maxlength="120" value="{{ old('name', $product->name) }}">@error('name')<div class="field-error">{{ $message }}</div>@enderror</div>
                            <div class="field"><label class="label">Category</label>
                                <select name="category_id" class="select" required>
                                    <option value="">Choose…</option>
                                    @foreach ($categories as $category)
                                        <option value="{{ $category->id }}" @selected(old('category_id', $product->category_id) == $category->id)>{{ $category->name }}</option>
                                    @endforeach
                                </select>
                                @error('category_id')<div class="field-error">{{ $message }}</div>@enderror
                            </div>
                            <div class="field"><label class="label">SKU</label><input name="sku" class="input" maxlength="60" value="{{ old('sku', $product->sku) }}">@error('sku')<div class="field-error">{{ $message }}</div>@enderror</div>
                        </div>
                        <div class="field" style="margin-top:12px;"><label class="label">Description</label><textarea name="description" class="textarea" rows="3" maxlength="2000">{{ old('description', $product->description) }}</textarea></div>
                    </div>
                </div>

                <div class="panel">
                    <div class="panel-head"><div><div class="panel-title">Pricing</div><div class="panel-sub">Base price before variants and modifiers</div></div></div>
                    <div class="panel-body">
                        <div class="form-grid">
                            <div class="field"><label class="label">Selling price</label><input name="price" class="input" inputmode="decimal" required value="{{ old('price', $editing ? Money::toDecimal($product->price_minor) : '') }}" placeholder="0.00">@error('price')<div class="field-error">{{ $message }}</div>@enderror</div>
                            <div class="field"><label class="label">Cost price (optional)</label><input name="cost" class="input" inputmode="decimal" value="{{ old('cost', $product->cost_minor !== null ? Money::toDecimal($product->cost_minor) : '') }}" placeholder="0.00">@error('cost')<div class="field-error">{{ $message }}</div>@enderror</div>
                            <div class="field"><label class="label">Tax rate %</label><input name="tax_rate" class="input" inputmode="decimal" value="{{ old('tax_rate', $product->tax_rate_bp !== null ? number_format($product->tax_rate_bp / 100, 2, '.', '') : '') }}" placeholder="e.g. 16">@error('tax_rate')<div class="field-error">{{ $message }}</div>@enderror</div>
                            <div class="field"><label class="label">Kitchen station</label><input name="kitchen_station" class="input" maxlength="60" value="{{ old('kitchen_station', $product->kitchen_station) }}" placeholder="Grill, Curry, Bar…"></div>
                        </div>
                        <div class="form-grid" style="margin-top:12px;">
                            <div class="field"><label class="label">Prep minutes</label><input name="preparation_minutes" type="number" min="0" max="240" class="input" value="{{ old('preparation_minutes', $product->preparation_minutes) }}"></div>
                            <div class="field"><label class="label">Sort order</label><input name="sort_order" type="number" min="0" max="9999" class="input" value="{{ old('sort_order', $product->sort_order ?? 0) }}"></div>
                        </div>
                        <label class="check" style="margin-top:12px;"><input type="checkbox" name="is_taxable" value="1" @checked(old('is_taxable', $product->is_taxable ?? true))><span>Apply tax to this item</span></label>
                    </div>
                </div>

                <div class="panel">
                    <div class="panel-head">
                        <div><div class="panel-title">Variants</div><div class="panel-sub">Optional sizes or portions. The delta is added to the base price.</div></div>
                        <button type="button" class="btn btn-ghost btn-sm" id="add-variant">Add variant</button>
                    </div>
                    <div class="panel-body">
                        <div id="variant-rows" style="display:grid;gap:10px;"></div>
                        <div class="tiny" id="variant-empty" style="display:none;">No variants. The base price is used.</div>
                    </div>
                </div>
            </div>

            <div class="stack" style="display:grid;gap:14px;">
                <div class="panel">
                    <div class="panel-head"><div class="panel-title">Availability</div></div>
                    <div class="panel-body">
                        <label class="check"><input type="checkbox" name="is_available" value="1" @checked(old('is_available', $product->is_available ?? true))><span>Available for sale on POS</span></label>
                        <div class="alert alert-warning" style="margin-top:12px;font-size:11px;">Unavailable items stay on the menu but cashiers cannot add them to an order.</div>
                    </div>
                </div>

                <div class="panel">
                    <div class="panel-head"><div class="panel-title">Image</div></div>
                    <div class="panel-body">
                        <div style="width:100%;aspect-ratio:4/3;border-radius:12px;border:1px dashed var(--border);display:grid;place-items:center;overflow:hidden;background:var(--surface-2);">
                            <img id="image-preview" src="{{ $product->image_path ? route('media', ['path' => $product->image_path]) : '' }}" alt="" style="width:100%;height:100%;object-fit:cover;{{ $product->image_path ? '' : 'display:none;' }}">
                            <x-icon name="products" id="image-placeholder" class="w-8 h-8" style="{{ $product->image_path ? 'display:none;' : 'color:var(--muted);' }}" />
                        </div>
                        <input type="file" name="image" id="image-input" accept="image/*" class="input" style="margin-top:10px;">
                        @if ($product->image_path)
                            <label class="check" style="margin-top:8px;"><input type="checkbox" name="remove_image" value="1"><span>Remove current image</span></label>
                        @endif
                    </div>
                </div>

                <div class="panel">
                    <div class="panel-head"><div><div class="panel-title">Modifier groups</div><div class="panel-sub">Add-ons cashiers can attach</div></div></div>
                    <div class="panel-body">
                        @forelse ($modifierGroups as $group)
                            <label class="check" style="padding:7px 0;">
                                <input type="checkbox" name="modifier_groups[]" value="{{ $group->id }}" @checked(in_array($group->id, $selected))>
                                <span>{{ $group->name }} <span class="tiny">{{ $group->is_required ? '· required' : '' }}</span></span>
                            </label>
                        @empty
                            <div class="tiny">No active modifier groups. Create them under Menu → Modifiers.</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </form>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const rows = document.getElementById('variant-rows');
        const empty = document.getElementById('variant-empty');
        let index = 0;

        const refreshEmpty = () => {
            empty.style.display = rows.children.length ? 'none' : 'block';
        };

        const addRow = (variant = {}) => {
            const i = index++;
            const row = document.createElement('div');
            row.className = 'variant-row';
            row.style.cssText = 'display:grid;grid-template-columns:1fr 120px auto auto auto;gap:8px;align-items:center;';
            row.innerHTML = `
                <input type="hidden" name="variants[${i}][id]" value="${variant.id ?? ''}">
                <input name="variants[${i}][name]" class="input" placeholder="Variant name" maxlength="60" value="${variant.name ?? ''}">
                <input name="variants[${i}][price_delta]" class="input" inputmode="decimal" placeholder="+0.00" value="${variant.price_delta ?? ''}">
                <label class="check" title="Default"><input type="checkbox" name="variants[${i}][is_default]" value="1" ${variant.is_default ? 'checked' : ''}><span>Default</span></label>
                <label class="check" title="Active"><input type="checkbox" name="variants[${i}][is_active]" value="1" ${variant.is_active !== false ? 'checked' : ''}><span>Active</span></label>
                <button type="button" class="icon-btn" data-remove-variant><x-icon name="x" class="w-3.5 h-3.5" /></button>
            `;
            row.querySelector('[data-remove-variant]').addEventListener('click', () => {
                row.remove();
                refreshEmpty();
            });
            rows.appendChild(row);
            refreshEmpty();
        };

        @json($variantRows).forEach((variant) => addRow(variant));
        refreshEmpty();

        document.getElementById('add-variant').addEventListener('click', () => addRow());

        const imageInput = document.getElementById('image-input');
        imageInput.addEventListener('change', () => {
            const file = imageInput.files && imageInput.files[0];
            if (!file) return;
            const preview = document.getElementById('image-preview');
            preview.src = URL.createObjectURL(file);
            preview.style.display = 'block';
            const placeholder = document.getElementById('image-placeholder');
            if (placeholder) placeholder.style.display = 'none';
        });
    });
</script>
@endpush
