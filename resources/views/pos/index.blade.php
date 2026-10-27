@extends('layouts.app')

@section('title', 'New order')

@php
    $settings = app(\App\Services\SettingsService::class);
    $requireShift = $settings->bool('orders.require_shift', true);
    $currency = $settings->currencySymbol();
    $posConfig = [
        'currency' => $currency,
        'taxEnabled' => $settings->bool('tax.enabled'),
        'taxRate' => $settings->int('tax.rate_bp'),
        'taxInclusive' => $settings->bool('tax.inclusive'),
        'taxLabel' => $settings->string('tax.label', 'Tax'),
        'serviceEnabled' => $settings->hasServiceCharge(),
        'serviceRate' => $settings->int('service_charge.rate_bp'),
        'serviceLabel' => $settings->string('service_charge.label', 'Service charge'),
        'cashierCap' => $settings->int('discount.cashier_max_percent', 0),
        'isManager' => auth()->user()->isAdmin() || auth()->user()->isManager(),
        'requiresShift' => $requireShift,
        'hasShift' => (bool) $shift,
        'tablesEnabled' => $settings->bool('tables.enabled', true),
        'defaultType' => $settings->string('orders.default_type', 'takeaway'),
    ];
@endphp

@section('content')
    <div class="pos-layout">
        <aside class="pos-categories" aria-label="Menu categories">
            <button type="button" class="cat-btn active" data-category="all">
                <x-icon name="grid" class="w-4 h-4" /> All items
            </button>
            @foreach ($categories as $category)
                <button type="button" class="cat-btn" data-category="{{ $category->id }}">
                    <x-icon name="tag" class="w-4 h-4" /> {{ $category->name }}
                    <span style="margin-left:auto;font-size:10px;color:var(--muted);">{{ $category->active_products_count }}</span>
                </button>
            @endforeach
        </aside>

        <section class="pos-main">
            <div class="pos-toolbar">
                <div class="pos-search">
                    <x-icon name="search" />
                    <input id="pos-search" class="input input-sm" type="search" placeholder="Search the menu…" autocomplete="off">
                </div>
                <a href="{{ route('pos.held') }}" class="btn btn-ghost btn-sm">
                    <x-icon name="clock" class="w-4 h-4" /> Held
                    @if ($heldCount > 0)<span class="badge badge-amber">{{ $heldCount }}</span>@endif
                </a>
            </div>

            @if ($requireShift && ! $shift)
                <div class="alert alert-warning">
                    <x-icon name="shift" class="w-4 h-4 shrink-0" />
                    <div><strong>No open shift.</strong> <a href="{{ route('shifts.index') }}" style="text-decoration:underline;">Open a shift</a> before completing sales.</div>
                </div>
            @endif

            <div class="product-grid" id="product-grid">
                @foreach ($products as $product)
                    <button type="button" class="product-card" data-product-id="{{ $product['id'] }}">
                        <div class="product-thumb">
                            @if ($product['image'])
                                <img src="{{ $product['image'] }}" alt="" loading="lazy">
                            @else
                                {{ mb_substr($product['name'], 0, 2) }}
                            @endif
                            @if (! empty($product['variants']))
                                <span class="product-flag">{{ count($product['variants']) }} sizes</span>
                            @endif
                        </div>
                        <div class="product-info">
                            <div class="product-name">{{ $product['name'] }}</div>
                            <div class="product-meta">
                                <span>{{ $settings->money($product['price_minor']) }}</span>
                                @if (! empty($product['modifier_groups']))
                                    <span class="tiny" style="color:var(--muted);font-weight:500;">+ extras</span>
                                @endif
                            </div>
                        </div>
                    </button>
                @endforeach
            </div>
            <div id="no-products" class="empty" style="display:none;">
                <div class="empty-icon"><x-icon name="search" class="w-6 h-6" /></div>
                <div class="empty-title">No menu items found</div>
                <div class="empty-text">Try a different category or search term.</div>
            </div>
        </section>

        <aside class="pos-cart">
            <div class="cart-head" style="flex-direction:column;align-items:stretch;gap:9px;">
                <div style="display:flex;align-items:center;gap:8px;">
                    <div style="font-weight:750;font-size:13.5px;">Current order</div>
                    <span id="cart-count-badge" class="badge badge-slate" style="margin-left:auto;">0 items</span>
                    <button type="button" id="order-options" class="icon-btn" title="Discount and delivery options"><x-icon name="filter" class="w-4 h-4" /></button>
                    <button type="button" id="clear-cart" class="icon-btn" title="Clear order" style="display:none;"><x-icon name="trash" class="w-4 h-4" /></button>
                </div>
                <div class="order-type-tabs" id="order-type-tabs">
                    <button type="button" class="order-type-tab" data-type="dine_in">Dine-in</button>
                    <button type="button" class="order-type-tab" data-type="takeaway">Takeaway</button>
                    <button type="button" class="order-type-tab" data-type="delivery">Delivery</button>
                </div>
                <div id="table-row" style="display:none;">
                    <select id="table-select" class="select input-sm">
                        <option value="">Select a table…</option>
                        @foreach ($tables as $table)
                            <option value="{{ $table['id'] }}" @selected((int) $preselectedTable === (int) $table['id'])>{{ $table['name'] }}@if ($table['zone']) · {{ $table['zone'] }}@endif</option>
                        @endforeach
                    </select>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px;">
                    <input id="customer-name" class="input input-sm" placeholder="Customer name" maxlength="120">
                    <input id="customer-phone" class="input input-sm" placeholder="Phone" maxlength="40">
                </div>
                <input id="customer-address" class="input input-sm" placeholder="Delivery address" maxlength="500" style="display:none;">
            </div>

            <div class="cart-items" id="cart-items">
                <div class="pos-empty" id="cart-empty">
                    <x-icon name="pos" />
                    <div style="font-weight:650;color:var(--text);">Start a new order</div>
                    <div class="tiny" style="margin-top:4px;">Tap a menu item to add it to the cart.</div>
                </div>
            </div>

            <div class="cart-summary">
                <div class="summary-row"><span>Subtotal</span><strong id="sum-subtotal">{{ $currency }} 0.00</strong></div>
                <div class="summary-row" id="sum-discount-row" style="display:none;"><span>Discount</span><strong id="sum-discount" style="color:var(--danger);">− {{ $currency }} 0.00</strong></div>
                <div class="summary-row" id="sum-service-row" style="display:none;"><span id="sum-service-label">Service charge</span><strong id="sum-service">{{ $currency }} 0.00</strong></div>
                <div class="summary-row" id="sum-delivery-row" style="display:none;"><span>Delivery</span><strong id="sum-delivery">{{ $currency }} 0.00</strong></div>
                <div class="summary-row" id="sum-tax-row" style="display:none;"><span id="sum-tax-label">Tax</span><strong id="sum-tax">{{ $currency }} 0.00</strong></div>
                <div class="summary-total"><span>Total</span><strong id="sum-total">{{ $currency }} 0.00</strong></div>
            </div>

            <div class="cart-actions">
                <button type="button" id="hold-order" class="btn btn-ghost" disabled><x-icon name="clock" class="w-4 h-4" /> Hold</button>
                <button type="button" id="checkout-order" class="btn btn-primary" disabled><x-icon name="cash" class="w-4 h-4" /> Charge</button>
            </div>
        </aside>
    </div>
@endsection

@push('modals')
    <dialog class="modal" id="item-dialog">
        <form method="dialog" id="item-form">
            <div class="modal-head">
                <div>
                    <div class="modal-title" id="item-dialog-title">Add item</div>
                    <div class="tiny" id="item-dialog-price"></div>
                </div>
                <button type="button" class="close-x" data-close><x-icon name="x" class="w-4 h-4" /></button>
            </div>
            <div class="modal-body" id="item-options"></div>
            <div class="modal-foot">
                <button type="button" class="btn btn-ghost" data-close>Cancel</button>
                <button type="submit" class="btn btn-primary">Add to order</button>
            </div>
        </form>
    </dialog>

    <dialog class="modal" id="checkout-dialog">
        <form method="dialog" id="checkout-form">
            <div class="modal-head">
                <div>
                    <div class="modal-title">Take payment</div>
                    <div class="tiny" id="checkout-summary"></div>
                </div>
                <button type="button" class="close-x" data-close><x-icon name="x" class="w-4 h-4" /></button>
            </div>
            <div class="modal-body">
                <div id="checkout-errors" class="alert alert-danger" style="display:none;margin-bottom:12px;"></div>

                <div class="field">
                    <label class="label">Payment method</label>
                    <div class="pill-tabs" id="payment-methods">
                        <button type="button" class="pill-tab active" data-method="cash"><x-icon name="cash" class="w-4 h-4" /> Cash</button>
                        <button type="button" class="pill-tab" data-method="card"><x-icon name="card" class="w-4 h-4" /> Card</button>
                        <button type="button" class="pill-tab" data-method="bank_transfer">Bank</button>
                        <button type="button" class="pill-tab" data-method="other">Other</button>
                    </div>
                </div>

                <div class="form-grid" style="margin-top:14px;">
                    <div class="field">
                        <label class="label" for="pay-tendered">Cash received</label>
                        <input id="pay-tendered" class="input" inputmode="decimal" placeholder="0.00">
                        <div class="tiny">Total due: <strong id="pay-total"></strong></div>
                    </div>
                    <div class="field">
                        <label class="label" for="pay-change">Change</label>
                        <input id="pay-change" class="input" readonly value="{{ $currency }} 0.00">
                    </div>
                </div>

                <div class="field" id="pay-reference-field" style="display:none;margin-top:12px;">
                    <label class="label" for="pay-reference">Reference</label>
                    <input id="pay-reference" class="input" maxlength="120" placeholder="Card / transfer reference">
                </div>

                <div class="field" style="margin-top:12px;">
                    <label class="label" for="order-notes">Order notes</label>
                    <textarea id="order-notes" class="textarea" rows="2" maxlength="1000" placeholder="Kitchen or service notes"></textarea>
                </div>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn btn-ghost" data-close>Cancel</button>
                <button type="submit" class="btn btn-primary" id="pay-submit"><x-icon name="check" class="w-4 h-4" /> Complete payment</button>
            </div>
        </form>
    </dialog>

    <dialog class="modal" id="order-dialog">
        <form method="dialog" id="order-details-form">
            <div class="modal-head">
                <div class="modal-title">Order options</div>
                <button type="button" class="close-x" data-close><x-icon name="x" class="w-4 h-4" /></button>
            </div>
            <div class="modal-body">
                <div class="form-grid">
                    <div class="field">
                        <label class="label">Discount</label>
                        <select id="discount-type" class="select">
                            <option value="">No discount</option>
                            <option value="percent">Percentage (%)</option>
                            <option value="fixed">Fixed amount</option>
                        </select>
                    </div>
                    <div class="field">
                        <label class="label">Value</label>
                        <input id="discount-value" class="input" inputmode="decimal" placeholder="0">
                    </div>
                </div>
                <div class="field" style="margin-top:12px;">
                    <label class="label">Delivery fee</label>
                    <input id="delivery-fee" class="input" inputmode="decimal" placeholder="0.00">
                </div>
                <div class="field" style="margin-top:12px;">
                    <label class="label">Delivery instructions</label>
                    <input id="delivery-instructions" class="input" maxlength="500" placeholder="Gate, landmark, instructions">
                </div>
                <div class="tiny" id="discount-cap-note" style="margin-top:10px;"></div>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn btn-ghost" data-close>Cancel</button>
                <button type="submit" class="btn btn-primary">Apply</button>
            </div>
        </form>
    </dialog>
@endpush

@push('scripts')
<script>
(() => {
    const DATA = @json($products);
    const TABLES = @json($tables);
    const RESUME = @json($resume);
    const CONFIG = @json($posConfig);
    const ENDPOINTS = {
        checkout: @json(route('pos.checkout')),
        hold: @json(route('pos.hold')),
    };

    const state = {
        cart: [],
        type: CONFIG.defaultType || 'takeaway',
        discountType: '',
        discountValue: '',
        deliveryFee: '',
        deliveryInstructions: '',
        paymentMethod: 'cash',
        activeCategory: 'all',
        search: '',
        editingKey: null,
        submitting: false,
    };

    const products = new Map(DATA.map((product) => [product.id, product]));
    const el = (id) => document.getElementById(id);
    const esc = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char]));
    const money = (minor) => window.bkh.formatMoney(minor, CONFIG.currency);

    function uuid() {
        if (window.crypto?.randomUUID) return window.crypto.randomUUID();
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
            const r = Math.random() * 16 | 0;
            return (c === 'x' ? r : (r & 0x3 | 0x8)).toString(16);
        });
    }

    function parseAmount(value) {
        const cleaned = String(value ?? '').replace(/[^0-9.\-]/g, '');
        const number = Number.parseFloat(cleaned);
        return Number.isFinite(number) ? Math.round(number * 100) : 0;
    }

    function productFor(id) {
        return products.get(Number(id));
    }

    function variantFor(product, id) {
        return (product?.variants || []).find((variant) => Number(variant.id) === Number(id)) || null;
    }

    function modifiersFor(product, ids) {
        const all = (product?.modifier_groups || []).flatMap((group) => group.modifiers.map((modifier) => ({...modifier, group})));
        return (ids || []).map((id) => all.find((modifier) => modifier.id === Number(id))).filter(Boolean);
    }

    function lineUnitTotal(product, variant, modifiers) {
        return product.price_minor + (variant?.price_delta_minor || 0) + modifiers.reduce((sum, modifier) => sum + modifier.price_minor, 0);
    }

    function lineTotal(line) {
        const product = productFor(line.productId);
        if (!product) return 0;
        return lineUnitTotal(product, variantFor(product, line.variantId), modifiersFor(product, line.modifierIds)) * line.quantity;
    }

    function cartKey(productId, variantId, modifierIds) {
        return [productId, variantId || 0, [...(modifierIds || [])].sort((a, b) => a - b).join('.')].join(':');
    }

    function addLine(product, options = {}) {
        const key = cartKey(product.id, options.variantId, options.modifierIds);
        const existing = state.cart.find((line) => line.key === key && line.notes === (options.notes || ''));

        if (existing) {
            existing.quantity = Math.min(99, existing.quantity + (options.quantity || 1));
        } else {
            state.cart.push({
                key,
                productId: product.id,
                variantId: options.variantId || null,
                quantity: options.quantity || 1,
                modifierIds: options.modifierIds || [],
                notes: options.notes || '',
            });
        }

        renderCart();
    }

    function calcTotals() {
        const lines = state.cart.map((line) => {
            const product = productFor(line.productId);
            const variant = variantFor(product, line.variantId);
            const modifiers = modifiersFor(product, line.modifierIds);
            const total = lineUnitTotal(product, variant, modifiers) * line.quantity;
            return {line, product, variant, total, rate: product.is_taxable ? product.tax_rate_bp : 0};
        });

        const gross = lines.reduce((sum, item) => sum + item.total, 0);
        let discount = 0;

        if (state.discountType === 'percent') {
            const bp = Math.max(0, Math.min(10000, Math.round(Number.parseFloat(state.discountValue || '0') * 100) || 0));
            discount = Math.round(gross * bp / 10000);
        } else if (state.discountType === 'fixed') {
            discount = Math.min(gross, Math.max(0, parseAmount(state.discountValue)));
        }

        if (!CONFIG.isManager && state.discountType === 'percent') {
            const bp = Math.round(Number.parseFloat(state.discountValue || '0') * 100) || 0;
            if (bp > CONFIG.cashierCap * 100) discount = Math.round(gross * CONFIG.cashierCap / 100);
        }

        let remaining = discount;
        const shares = lines.map((item) => {
            if (!gross || !discount) return 0;
            const share = Math.min(item.total, Math.floor(discount * item.total / gross));
            remaining -= share;
            return share;
        });
        let index = 0;
        while (remaining > 0 && lines.length) {
            const capacity = lines[index].total - shares[index];
            if (capacity > 0) {
                const add = Math.min(capacity, remaining);
                shares[index] += add;
                remaining -= add;
            }
            index = (index + 1) % lines.length;
        }

        let net = 0;
        let tax = 0;
        for (let i = 0; i < lines.length; i++) {
            const lineNet = Math.max(0, lines[i].total - shares[i]);
            net += lineNet;
            if (lines[i].rate > 0) {
                const inclusive = CONFIG.taxInclusive;
                tax += inclusive ? Math.round(lineNet * lines[i].rate / (10000 + lines[i].rate)) : Math.round(lineNet * lines[i].rate / 10000);
            }
        }

        const service = CONFIG.serviceEnabled ? Math.round(net * CONFIG.serviceRate / 10000) : 0;
        const delivery = state.type === 'delivery' ? Math.max(0, parseAmount(state.deliveryFee)) : 0;
        const total = net + service + delivery + (CONFIG.taxInclusive ? 0 : tax);

        return {gross, discount, net, tax, service, delivery, total, shares};
    }

    function renderCart() {
        const container = el('cart-items');
        const empty = el('cart-empty');
        const totals = calcTotals();

        if (!state.cart.length) {
            container.innerHTML = '';
            container.appendChild(empty);
            empty.style.display = '';
        } else {
            empty.style.display = 'none';
            container.innerHTML = state.cart.map((line, index) => {
                const product = productFor(line.productId);
                const variant = variantFor(product, line.variantId);
                const modifiers = modifiersFor(product, line.modifierIds);
                const meta = [variant?.name, ...modifiers.map((modifier) => modifier.name)].filter(Boolean).join(' · ');
                return `
                    <div class="cart-line" data-index="${index}">
                        <div class="cart-line-top">
                            <div style="min-width:0;">
                                <div class="cart-line-name">${esc(product?.name || 'Item')}</div>
                                ${meta ? `<div class="cart-line-meta">${esc(meta)}</div>` : ''}
                            </div>
                            <div class="cart-line-price">${money(lineTotal(line))}</div>
                        </div>
                        <div style="display:flex;align-items:center;gap:6px;">
                            <div class="qty-control">
                                <button type="button" class="qty-btn" data-qty="-1" aria-label="Decrease quantity"><x-icon name="minus" class="w-3.5 h-3.5" /></button>
                                <span class="qty-value">${line.quantity}</span>
                                <button type="button" class="qty-btn" data-qty="1" aria-label="Increase quantity"><x-icon name="plus" class="w-3.5 h-3.5" /></button>
                            </div>
                            <div class="cart-line-actions">
                                <button type="button" class="icon-btn" data-edit title="Edit item"><x-icon name="edit" class="w-3.5 h-3.5" /></button>
                                <button type="button" class="icon-btn" data-remove title="Remove item" style="color:var(--danger);"><x-icon name="trash" class="w-3.5 h-3.5" /></button>
                            </div>
                        </div>
                        <input class="cart-notes-input" data-note placeholder="Add a note for the kitchen…" maxlength="300" value="${esc(line.notes)}">
                    </div>`;
            }).join('');
        }

        const count = state.cart.reduce((sum, line) => sum + line.quantity, 0);
        el('cart-count-badge').textContent = count + (count === 1 ? ' item' : ' items');
        el('clear-cart').style.display = state.cart.length ? '' : 'none';
        el('sum-subtotal').textContent = money(totals.gross);
        el('sum-discount-row').style.display = totals.discount > 0 ? '' : 'none';
        el('sum-discount').textContent = '− ' + money(totals.discount);
        el('sum-tax-row').style.display = totals.tax > 0 ? '' : 'none';
        el('sum-tax').textContent = money(totals.tax);
        el('sum-tax-label').textContent = CONFIG.taxLabel + (CONFIG.taxInclusive ? ' (incl.)' : '');
        el('sum-service-row').style.display = totals.service > 0 ? '' : 'none';
        el('sum-service').textContent = money(totals.service);
        el('sum-service-label').textContent = CONFIG.serviceLabel;
        el('sum-delivery-row').style.display = totals.delivery > 0 ? '' : 'none';
        el('sum-delivery').textContent = money(totals.delivery);
        el('sum-total').textContent = money(totals.total);

        const blocked = !state.cart.length || (CONFIG.requiresShift && !CONFIG.hasShift);
        el('checkout-order').disabled = blocked;
        el('hold-order').disabled = !state.cart.length;
        el('checkout-order').title = blocked && CONFIG.requiresShift && !CONFIG.hasShift ? 'Open a shift first' : '';
    }

    function renderBody() {
        document.querySelectorAll('[data-type]').forEach((button) => button.classList.toggle('active', button.dataset.type === state.type));
        el('table-row').style.display = state.type === 'dine_in' && CONFIG.tablesEnabled ? '' : 'none';
        el('customer-address').style.display = state.type === 'delivery' ? '' : 'none';
        el('sum-delivery-row').style.display = state.type === 'delivery' && parseAmount(state.deliveryFee) > 0 ? '' : 'none';
    }

    function filterProducts() {
        const term = state.search.toLowerCase().trim();
        let visible = 0;
        document.querySelectorAll('.product-card').forEach((card) => {
            const product = productFor(card.dataset.productId);
            const categoryMatch = state.activeCategory === 'all' || Number(product.category_id) === Number(state.activeCategory);
            const searchMatch = !term || `${product.name} ${product.sku || ''}`.toLowerCase().includes(term);
            const show = categoryMatch && searchMatch;
            card.style.display = show ? '' : 'none';
            if (show) visible++;
        });
        el('no-products').style.display = visible ? 'none' : '';
    }

    function openItemDialog(product, editingIndex = null) {
        state.editingKey = editingIndex;
        const editLine = editingIndex !== null ? state.cart[editingIndex] : null;
        const selectedModifiers = new Set(editLine?.modifierIds || []);
        const selectedVariant = editLine?.variantId || (product.variants.find((variant) => variant.is_default)?.id ?? product.variants[0]?.id ?? null);

        el('item-dialog-title').textContent = product.name;
        el('item-dialog-price').textContent = money(product.price_minor);

        let html = '';

        if (product.variants.length) {
            html += `<div class="field"><label class="label">Size / variant</label><div style="display:flex;gap:8px;flex-wrap:wrap;">`;
            product.variants.forEach((variant) => {
                html += `<label class="check" style="border:1px solid var(--border);padding:8px 10px;border-radius:9px;">
                    <input type="radio" name="variant" value="${variant.id}" ${Number(variant.id) === Number(selectedVariant) ? 'checked' : ''}>
                    <span>${esc(variant.name)}${variant.price_delta_minor ? ' · ' + (variant.price_delta_minor > 0 ? '+' : '') + money(variant.price_delta_minor) : ''}</span>
                </label>`;
            });
            html += `</div></div>`;
        }

        product.modifier_groups.forEach((group) => {
            const max = Math.max(1, group.max_selections || 1);
            const inputType = max === 1 ? 'radio' : 'checkbox';
            const required = group.is_required || group.min_selections > 0;
            html += `<div class="field" data-group="${group.id}" data-min="${group.min_selections || (group.is_required ? 1 : 0)}" data-max="${max}">
                <label class="label">${esc(group.name)} ${required ? '<span style="color:var(--danger);">*</span>' : ''} <span class="tiny" style="font-weight:400;">${group.min_selections > 0 ? 'choose at least ' + group.min_selections : (required ? 'required' : 'optional')}${max > 1 ? ' · up to ' + max : ''}</span></label>
                <div style="display:grid;gap:6px;">`;
            group.modifiers.forEach((modifier) => {
                const checked = selectedModifiers.has(modifier.id);
                html += `<label class="check" style="justify-content:space-between;">
                    <span style="display:flex;align-items:center;gap:8px;"><input type="${inputType}" name="modifier-${group.id}" value="${modifier.id}" ${checked ? 'checked' : ''}> ${esc(modifier.name)}</span>
                    <span class="tiny">${modifier.price_minor ? '+' + money(modifier.price_minor) : 'Free'}</span>
                </label>`;
            });
            html += `</div><div class="error-text" data-group-error style="display:none;"></div></div>`;
        });

        html += `<div class="field" style="margin-top:12px;"><label class="label">Item note</label><input id="item-note" class="input" maxlength="300" placeholder="No onions, extra spicy…" value="${esc(editLine?.notes || '')}"></div>`;

        el('item-options').innerHTML = html;
        el('item-dialog').showModal();
    }

    function commitItemDialog(event) {
        event.preventDefault();
        const product = productFor(state.cart[state.editingKey ?? -1]?.productId) || productFor(el('item-dialog').dataset.productId);
        const id = Number(el('item-dialog').dataset.productId);
        const target = products.get(id);
        if (!target) return;

        const selectedVariant = document.querySelector('input[name="variant"]:checked')?.value || null;
        const modifierIds = [];
        let valid = true;

        document.querySelectorAll('[data-group]').forEach((groupEl) => {
            const checked = [...groupEl.querySelectorAll('input:checked')].map((input) => Number(input.value));
            const min = Number(groupEl.dataset.min || 0);
            const max = Number(groupEl.dataset.max || 1);
            const error = groupEl.querySelector('[data-group-error]');
            if (checked.length < min || checked.length > max) {
                error.style.display = '';
                error.textContent = `Select ${min === max ? min : 'between ' + min + ' and ' + max} option(s).`;
                valid = false;
            } else {
                error.style.display = 'none';
            }
            modifierIds.push(...checked);
        });

        if (!valid) return;

        const note = el('item-note')?.value.trim() || '';
        const quantity = state.editingKey !== null ? state.cart[state.editingKey].quantity : 1;

        if (state.editingKey !== null) {
            state.cart[state.editingKey] = {
                key: cartKey(target.id, selectedVariant, modifierIds),
                productId: target.id,
                variantId: selectedVariant ? Number(selectedVariant) : null,
                quantity,
                modifierIds,
                notes: note,
            };
        } else {
            addLine(target, {variantId: selectedVariant ? Number(selectedVariant) : null, modifierIds, notes: note});
        }

        state.editingKey = null;
        el('item-dialog').close();
        renderCart();
    }

    function openItem(index) {
        const line = state.cart[index];
        const product = productFor(line.productId);
        if (!product) return;
        openItemDialog(product, index);
        el('item-dialog').dataset.productId = product.id;
    }

    function fillCheckout() {
        const totals = calcTotals();
        const totalLabel = money(totals.total);
        el('pay-total').textContent = totalLabel;
        el('checkout-summary').textContent = `${state.cart.reduce((sum, line) => sum + line.quantity, 0)} items · ${totalLabel}`;
        el('pay-tendered').value = (totals.total / 100).toFixed(2);
        updateChange();
        el('checkout-errors').style.display = 'none';
        el('checkout-dialog').showModal();
    }

    function updateChange() {
        const total = calcTotals().total;
        const tendered = parseAmount(el('pay-tendered').value);
        const change = Math.max(0, tendered - total);
        el('pay-change').value = money(change);
    }

    function paymentPayload() {
        const totals = calcTotals();
        return {
            client_uuid: uuid(),
            resume_order: RESUME?.order_number || null,
            order_type: state.type,
            table_id: state.type === 'dine_in' ? (Number(el('table-select').value) || null) : null,
            customer_name: el('customer-name').value.trim() || null,
            customer_phone: el('customer-phone').value.trim() || null,
            customer_address: state.type === 'delivery' ? (el('customer-address').value.trim() || null) : null,
            delivery_instructions: state.type === 'delivery' ? (state.deliveryInstructions || null) : null,
            delivery_fee: state.type === 'delivery' ? (state.deliveryFee || null) : null,
            notes: el('order-notes').value.trim() || null,
            discount_type: state.discountType || null,
            discount_value: state.discountValue || null,
            items: state.cart.map((line) => ({
                product_id: line.productId,
                variant_id: line.variantId,
                quantity: line.quantity,
                modifier_ids: line.modifierIds,
                notes: line.notes || null,
            })),
            payment: {
                method: state.paymentMethod,
                amount: (totals.total / 100).toFixed(2),
                tendered: state.paymentMethod === 'cash' ? (parseAmount(el('pay-tendered').value) / 100).toFixed(2) : null,
                reference: el('pay-reference').value.trim() || null,
            },
        };
    }

    async function submitAction(url, payload) {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            },
            body: JSON.stringify(payload),
        });

        const data = await response.json().catch(() => ({}));

        if (!response.ok) {
            const errors = data.errors ? Object.values(data.errors).flat() : [data.message || 'The request could not be completed.'];
            throw new Error(errors.join(' '));
        }

        return data;
    }

    async function checkout() {
        if (state.submitting) return;
        state.submitting = true;
        const button = el('pay-submit');
        button.disabled = true;
        const original = button.innerHTML;
        button.textContent = 'Processing…';

        try {
            const data = await submitAction(ENDPOINTS.checkout, paymentPayload());
            window.location.href = data.receipt_url || data.redirect;
        } catch (error) {
            const box = el('checkout-errors');
            box.style.display = '';
            box.textContent = error.message;
            state.submitting = false;
            button.disabled = false;
            button.innerHTML = original;
        }
    }

    async function holdOrder() {
        if (!state.cart.length || state.submitting) return;
        state.submitting = true;
        const button = el('hold-order');
        button.disabled = true;
        const payload = paymentPayload();
        payload.payment = {method: 'cash', amount: '0.00'};

        try {
            const data = await submitAction(ENDPOINTS.hold, payload);
            window.location.href = data.redirect || @json(route('pos.held'));
        } catch (error) {
            window.alert(error.message);
            state.submitting = false;
            button.disabled = false;
        }
    }

    function clearCart() {
        if (!window.confirm('Clear all items from this order?')) return;
        state.cart = [];
        state.discountType = '';
        state.discountValue = '';
        state.deliveryFee = '';
        state.deliveryInstructions = '';
        el('discount-type').value = '';
        el('discount-value').value = '';
        el('delivery-fee').value = '';
        el('delivery-instructions').value = '';
        el('customer-name').value = '';
        el('customer-phone').value = '';
        el('customer-address').value = '';
        el('table-select').value = '';
        renderCart();
    }

    function init() {
        el('order-type-tabs').addEventListener('click', (event) => {
            const button = event.target.closest('[data-type]');
            if (!button) return;
            state.type = button.dataset.type;
            renderBody();
            renderCart();
        });

        document.querySelectorAll('[data-category]').forEach((button) => {
            button.addEventListener('click', () => {
                state.activeCategory = button.dataset.category;
                document.querySelectorAll('[data-category]').forEach((item) => item.classList.toggle('active', item === button));
                filterProducts();
            });
        });

        el('pos-search').addEventListener('input', (event) => {
            state.search = event.target.value;
            filterProducts();
        });

        el('product-grid').addEventListener('click', (event) => {
            const card = event.target.closest('[data-product-id]');
            if (!card) return;
            const product = productFor(card.dataset.productId);
            if (!product) return;
            if (product.variants.length || product.modifier_groups.length) {
                el('item-dialog').dataset.productId = product.id;
                el('item-dialog').showModal();
                openItemDialog(product);
            } else {
                addLine(product);
            }
        });

        el('cart-items').addEventListener('click', (event) => {
            const line = event.target.closest('.cart-line');
            if (!line) return;
            const index = Number(line.dataset.index);
            if (event.target.closest('[data-qty]')) {
                const delta = Number(event.target.closest('[data-qty]').dataset.qty);
                state.cart[index].quantity = Math.max(1, Math.min(99, state.cart[index].quantity + delta));
                renderCart();
            } else if (event.target.closest('[data-remove]')) {
                state.cart.splice(index, 1);
                renderCart();
            } else if (event.target.closest('[data-edit]')) {
                openItem(index);
            }
        });

        el('cart-items').addEventListener('input', (event) => {
            if (!event.target.matches('[data-note]')) return;
            const index = Number(event.target.closest('.cart-line').dataset.index);
            state.cart[index].notes = event.target.value;
            event.target.dataset.changed = '1';
        });

        el('cart-items').addEventListener('change', (event) => {
            if (event.target.matches('[data-note]')) renderCart();
        });

        el('clear-cart').addEventListener('click', clearCart);
        el('order-options').addEventListener('click', () => {
            el('discount-type').value = state.discountType;
            el('discount-value').value = state.discountValue;
            el('delivery-fee').value = state.deliveryFee;
            el('delivery-instructions').value = state.deliveryInstructions;
            el('order-dialog').showModal();
        });

        el('item-form').addEventListener('submit', commitItemDialog);

        el('checkout-order').addEventListener('click', () => {
            if (state.type === 'dine_in' && CONFIG.tablesEnabled && !el('table-select').value) {
                window.alert('Select a table for this dine-in order.');
                return;
            }
            fillCheckout();
        });

        el('hold-order').addEventListener('click', holdOrder);
        el('pay-submit').addEventListener('click', (event) => {
            event.preventDefault();
            checkout();
        });

        el('checkout-dialog').addEventListener('close', () => {
            state.submitting = false;
            el('pay-submit').disabled = false;
            el('pay-submit').innerHTML = '<svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m4 12 5 5L20 6"/></svg> Complete payment';
        });

        el('pay-tendered').addEventListener('input', updateChange);

        document.querySelectorAll('[data-method]').forEach((button) => {
            button.addEventListener('click', () => {
                state.paymentMethod = button.dataset.method;
                document.querySelectorAll('[data-method]').forEach((item) => item.classList.toggle('active', item === button));
                const cash = state.paymentMethod === 'cash';
                el('pay-tendered').closest('.field').style.display = cash ? '' : 'none';
                el('pay-change').closest('.field').style.display = cash ? '' : 'none';
                el('pay-reference-field').style.display = cash ? 'none' : '';
            });
        });

        el('order-details-form').addEventListener('submit', (event) => {
            event.preventDefault();
            state.discountType = el('discount-type').value;
            state.discountValue = el('discount-value').value;
            state.deliveryFee = el('delivery-fee').value;
            state.deliveryInstructions = el('delivery-instructions').value;
            el('order-dialog').close();
            renderCart();
        });

        el('discount-cap-note').textContent = CONFIG.isManager
            ? 'Managers can apply any discount up to 100%.'
            : `Your discount limit is ${CONFIG.cashierCap}%.`;

        const totals = calcTotals();
        el('sum-total').textContent = money(totals.total);

        document.querySelector(`[data-type="${state.type}"]`)?.click();

        if (RESUME) {
            (RESUME.items || []).forEach((item) => {
                const product = productFor(item.product_id);
                if (!product) return;
                state.cart.push({
                    key: cartKey(product.id, item.variant_id, item.modifier_ids || []),
                    productId: product.id,
                    variantId: item.variant_id || null,
                    quantity: item.quantity,
                    modifierIds: item.modifier_ids || [],
                    notes: item.notes || '',
                });
            });
            el('customer-name').value = RESUME.customer_name || '';
            el('customer-phone').value = RESUME.customer_phone || '';
            el('customer-address').value = RESUME.customer_address || '';
            el('delivery-instructions').value = RESUME.delivery_instructions || '';
            el('order-notes').value = RESUME.notes || '';
            if (RESUME.discount_type) {
                state.discountType = RESUME.discount_type;
                state.discountValue = RESUME.discount_value || '';
                el('discount-type').value = RESUME.discount_type;
                el('discount-value').value = RESUME.discount_value || '';
            }
            if (RESUME.table_id) el('table-select').value = RESUME.table_id;
        } else {
            el('table-select').value = @json($preselectedTable ?? '');
        }

        renderCart();
        renderBody();
    }

    document.addEventListener('DOMContentLoaded', init);
})();
</script>
@endpush
