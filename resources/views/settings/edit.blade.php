@extends('layouts.app')

@section('title', 'Settings')

@php
    $showInlineErrors = true;
    $logo = $settings->string('restaurant.logo_path');
    $taxRate = $settings->int('tax.rate_bp') / 100;
    $serviceRate = $settings->int('service_charge.rate_bp') / 100;
@endphp

@section('content')
    <form method="POST" action="{{ route('settings.update') }}" enctype="multipart/form-data">
        @csrf
        @method('PUT')

        <div class="page-head">
            <div>
                <h1 class="page-title">Settings</h1>
                <p class="page-subtitle">Restaurant profile, currency, receipts, and operational rules.</p>
            </div>
            <div class="page-actions">
                <a href="{{ route('receipts.test') }}" target="_blank" class="btn btn-ghost"><x-icon name="printer" class="w-4 h-4" /> Test receipt</a>
                <button type="submit" class="btn btn-primary">Save settings</button>
            </div>
        </div>

        <div class="grid-2" style="align-items:start;">
            <div class="stack" style="display:grid;gap:14px;">
                <div class="panel">
                    <div class="panel-head"><div><div class="panel-title">Restaurant profile</div><div class="panel-sub">Shown on receipts and in the app</div></div></div>
                    <div class="panel-body">
                        <div class="form-grid">
                            <div class="field" style="grid-column:span 2;"><label class="label">Restaurant name</label><input name="restaurant[name]" class="input" required maxlength="120" value="{{ old('restaurant.name', $values['restaurant.name'] ?? '') }}">@error('restaurant.name')<div class="field-error">{{ $message }}</div>@enderror</div>
                            <div class="field"><label class="label">Legal name</label><input name="restaurant[legal_name]" class="input" maxlength="120" value="{{ old('restaurant.legal_name', $values['restaurant.legal_name'] ?? '') }}"></div>
                            <div class="field"><label class="label">Tax / NTN ID</label><input name="restaurant[tax_id]" class="input" maxlength="80" value="{{ old('restaurant.tax_id', $values['restaurant.tax_id'] ?? '') }}"></div>
                            <div class="field"><label class="label">Phone</label><input name="restaurant[phone]" class="input" maxlength="40" value="{{ old('restaurant.phone', $values['restaurant.phone'] ?? '') }}"></div>
                            <div class="field"><label class="label">Email</label><input name="restaurant[email]" type="email" class="input" maxlength="120" value="{{ old('restaurant.email', $values['restaurant.email'] ?? '') }}">@error('restaurant.email')<div class="field-error">{{ $message }}</div>@enderror</div>
                            <div class="field" style="grid-column:span 2;"><label class="label">Website</label><input name="restaurant[website]" class="input" maxlength="160" value="{{ old('restaurant.website', $values['restaurant.website'] ?? '') }}"></div>
                            <div class="field" style="grid-column:span 2;"><label class="label">Address</label><textarea name="restaurant[address]" class="textarea" rows="2" maxlength="500">{{ old('restaurant.address', $values['restaurant.address'] ?? '') }}</textarea></div>
                        </div>
                    </div>
                </div>

                <div class="panel">
                    <div class="panel-head"><div class="panel-title">Branding</div></div>
                    <div class="panel-body">
                        <div style="display:flex;gap:14px;align-items:center;">
                            <div style="width:80px;height:80px;border-radius:14px;border:1px solid var(--border);display:grid;place-items:center;overflow:hidden;background:var(--surface-2);">
                                @if ($logo)
                                    <img src="{{ route('media', ['path' => $logo]) }}" alt="" style="width:100%;height:100%;object-fit:cover;">
                                @else
                                    <span style="font-weight:800;letter-spacing:-.04em;font-size:22px;">BK</span>
                                @endif
                            </div>
                            <div style="flex:1;">
                                <label class="label">Logo</label>
                                <input type="file" name="logo" class="input" accept="image/*">
                                <div class="tiny" style="margin-top:5px;">Square PNG or JPG, up to 2 MB.</div>
                            </div>
                        </div>
                        @if ($logo)
                            <label class="check" style="margin-top:10px;"><input type="checkbox" name="remove_logo" value="1"><span>Remove current logo</span></label>
                        @endif
                    </div>
                </div>

                <div class="panel">
                    <div class="panel-head"><div class="panel-title">Regional & currency</div></div>
                    <div class="panel-body">
                        <div class="form-grid">
                            <div class="field"><label class="label">Currency code</label><input name="currency[code]" class="input" required maxlength="8" value="{{ old('currency.code', $values['currency.code'] ?? 'PKR') }}"></div>
                            <div class="field"><label class="label">Currency symbol</label><input name="currency[symbol]" class="input" required maxlength="8" value="{{ old('currency.symbol', $values['currency.symbol'] ?? 'Rs') }}"></div>
                            <div class="field"><label class="label">Decimals</label><input name="currency[decimals]" type="number" min="0" max="3" class="input" required value="{{ old('currency.decimals', $values['currency.decimals'] ?? 2) }}"></div>
                            <div class="field"><label class="label">Locale</label><input name="locale" class="input" required maxlength="8" value="{{ old('locale', $values['locale'] ?? 'en') }}"></div>
                            <div class="field" style="grid-column:span 2;"><label class="label">Timezone</label><input name="timezone" class="input" required value="{{ old('timezone', $values['timezone'] ?? 'Asia/Karachi') }}">@error('timezone')<div class="field-error">{{ $message }}</div>@enderror<div class="hint">IANA timezone, e.g. Asia/Karachi.</div></div>
                        </div>
                    </div>
                </div>

                <div class="panel">
                    <div class="panel-head"><div class="panel-title">Receipt</div></div>
                    <div class="panel-body">
                        <div class="form-grid">
                            <div class="field"><label class="label">Paper width</label><select name="receipt[width_mm]" class="select">@foreach ($widths as $width => $label)<option value="{{ $width }}" @selected((int) old('receipt.width_mm', $values['receipt.width_mm'] ?? 80) === (int) $width)>{{ $label }}</option>@endforeach</select></div>
                            <div class="field"><label class="label">Copies</label><input name="receipt[copies]" type="number" min="1" max="5" class="input" required value="{{ old('receipt.copies', $values['receipt.copies'] ?? 1) }}"></div>
                            <div class="field" style="grid-column:span 2;"><label class="label">Printer name (optional)</label><input name="receipt[printer_name]" class="input" maxlength="120" value="{{ old('receipt.printer_name', $values['receipt.printer_name'] ?? '') }}"><div class="hint">Leave empty to use the Windows default / print dialog.</div></div>
                        </div>
                        <div class="field" style="margin-top:12px;"><label class="label">Header text</label><textarea name="receipt[header]" class="textarea" rows="2" maxlength="500">{{ old('receipt.header', $values['receipt.header'] ?? '') }}</textarea></div>
                        <div class="field" style="margin-top:12px;"><label class="label">Footer text</label><textarea name="receipt[footer]" class="textarea" rows="2" maxlength="500">{{ old('receipt.footer', $values['receipt.footer'] ?? '') }}</textarea></div>
                        <div style="display:flex;gap:18px;margin-top:12px;flex-wrap:wrap;">
                            <label class="check"><input type="checkbox" name="receipt[show_logo]" value="1" @checked(old('receipt.show_logo', $settings->bool('receipt.show_logo', true)))><span>Print logo</span></label>
                            <label class="check"><input type="checkbox" name="receipt[show_tax_id]" value="1" @checked(old('receipt.show_tax_id', $settings->bool('receipt.show_tax_id', true)))><span>Print tax ID</span></label>
                        </div>
                    </div>
                </div>

                <div class="panel">
                    <div class="panel-head"><div class="panel-title">Kitchen</div></div>
                    <div class="panel-body">
                        <label class="check"><input type="checkbox" name="kitchen[enabled]" value="1" @checked(old('kitchen.enabled', $settings->bool('kitchen.enabled', true)))><span>Send paid orders to the kitchen board</span></label>
                        <div class="form-grid" style="margin-top:12px;">
                            <div class="field"><label class="label">Warning after (minutes)</label><input name="kitchen[warning_minutes]" type="number" min="1" max="120" class="input" required value="{{ old('kitchen.warning_minutes', $values['kitchen.warning_minutes'] ?? 10) }}"></div>
                            <div class="field"><label class="label">Critical after (minutes)</label><input name="kitchen[critical_minutes]" type="number" min="2" max="180" class="input" required value="{{ old('kitchen.critical_minutes', $values['kitchen.critical_minutes'] ?? 20) }}">@error('kitchen.critical_minutes')<div class="field-error">{{ $message }}</div>@enderror</div>
                            <div class="field" style="grid-column:span 2;"><label class="label">Kitchen printer name</label><input name="kitchen[printer_name]" class="input" maxlength="120" value="{{ old('kitchen.printer_name', $values['kitchen.printer_name'] ?? '') }}"></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="stack" style="display:grid;gap:14px;">
                <div class="panel">
                    <div class="panel-head"><div class="panel-title">Tax</div></div>
                    <div class="panel-body">
                        <label class="check"><input type="checkbox" name="tax[enabled]" value="1" @checked(old('tax.enabled', $settings->bool('tax.enabled')))><span>Apply tax to taxable products</span></label>
                        <div class="form-grid" style="margin-top:12px;">
                            <div class="field"><label class="label">Default tax rate %</label><input name="tax[rate]" class="input" inputmode="decimal" value="{{ old('tax.rate', number_format($taxRate, 2, '.', '')) }}">@error('tax.rate')<div class="field-error">{{ $message }}</div>@enderror</div>
                            <div class="field"><label class="label">Label</label><input name="tax[label]" class="input" maxlength="40" value="{{ old('tax.label', $values['tax.label'] ?? 'Tax') }}"></div>
                        </div>
                        <label class="check" style="margin-top:12px;"><input type="checkbox" name="tax[inclusive]" value="1" @checked(old('tax.inclusive', $settings->bool('tax.inclusive')))><span>Prices include tax</span></label>
                    </div>
                </div>

                <div class="panel">
                    <div class="panel-head"><div class="panel-title">Service charge</div></div>
                    <div class="panel-body">
                        <label class="check"><input type="checkbox" name="service_charge[enabled]" value="1" @checked(old('service_charge.enabled', $settings->bool('service_charge.enabled')))><span>Add a service charge to orders</span></label>
                        <div class="form-grid" style="margin-top:12px;">
                            <div class="field"><label class="label">Rate %</label><input name="service_charge[rate]" class="input" inputmode="decimal" value="{{ old('service_charge.rate', number_format($serviceRate, 2, '.', '')) }}"></div>
                            <div class="field"><label class="label">Label</label><input name="service_charge[label]" class="input" maxlength="40" value="{{ old('service_charge.label', $values['service_charge.label'] ?? 'Service charge') }}"></div>
                        </div>
                    </div>
                </div>

                <div class="panel">
                    <div class="panel-head"><div class="panel-title">Discounts & rounding</div></div>
                    <div class="panel-body">
                        <div class="form-grid">
                            <div class="field"><label class="label">Cashier max %</label><input name="discount[cashier_max_percent]" type="number" min="0" max="100" class="input" required value="{{ old('discount.cashier_max_percent', $values['discount.cashier_max_percent'] ?? 0) }}"></div>
                            <div class="field"><label class="label">Manager max %</label><input name="discount[manager_max_percent]" type="number" min="0" max="100" class="input" required value="{{ old('discount.manager_max_percent', $values['discount.manager_max_percent'] ?? 100) }}"></div>
                            <div class="field" style="grid-column:span 2;"><label class="label">Rounding</label><select name="rounding[mode]" class="select"><option value="half_up" @selected(old('rounding.mode', $values['rounding.mode'] ?? 'half_up') === 'half_up')>Round to nearest</option><option value="none" @selected(old('rounding.mode', $values['rounding.mode'] ?? '') === 'none')>No rounding</option></select></div>
                        </div>
                    </div>
                </div>

                <div class="panel">
                    <div class="panel-head"><div class="panel-title">Orders & tables</div></div>
                    <div class="panel-body">
                        <label class="check"><input type="checkbox" name="orders[require_shift]" value="1" @checked(old('orders.require_shift', $settings->bool('orders.require_shift', true)))><span>Require an open shift before taking orders</span></label>
                        <label class="check" style="margin-top:9px;"><input type="checkbox" name="orders[show_kitchen_after_checkout]" value="1" @checked(old('orders.show_kitchen_after_checkout', $settings->bool('orders.show_kitchen_after_checkout', true)))><span>Open kitchen ticket after checkout</span></label>
                        <label class="check" style="margin-top:9px;"><input type="checkbox" name="tables[enabled]" value="1" @checked(old('tables.enabled', $settings->bool('tables.enabled', true)))><span>Enable table service</span></label>
                        <div class="field" style="margin-top:12px;"><label class="label">Default order type</label><select name="orders[default_type]" class="select">@foreach ($orderTypes as $value => $label)<option value="{{ $value }}" @selected(old('orders.default_type', $values['orders.default_type'] ?? 'takeaway') === $value)>{{ $label }}</option>@endforeach</select></div>
                    </div>
                </div>

                <div class="panel">
                    <div class="panel-head"><div class="panel-title">Backups</div><a href="{{ route('backups.index') }}" class="btn btn-ghost btn-sm">Manage</a></div>
                    <div class="panel-body">
                        <div class="field"><label class="label">Backup directory</label><input name="backup[directory]" class="input" maxlength="500" value="{{ old('backup.directory', $values['backup.directory'] ?? '') }}" placeholder="Use app default"><div class="hint">Leave empty for the application data folder.</div></div>
                        <div class="form-grid" style="margin-top:12px;">
                            <div class="field"><label class="label">Reminder every (days)</label><input name="backup[reminder_days]" type="number" min="1" max="365" class="input" required value="{{ old('backup.reminder_days', $values['backup.reminder_days'] ?? 7) }}"></div>
                        </div>
                        <label class="check" style="margin-top:12px;"><input type="checkbox" name="backup[auto_reminder]" value="1" @checked(old('backup.auto_reminder', $settings->bool('backup.auto_reminder', true)))><span>Show backup reminders</span></label>
                    </div>
                </div>

                <div class="panel">
                    <div class="panel-head"><div class="panel-title">Appearance</div></div>
                    <div class="panel-body">
                        <div class="field"><label class="label">Theme</label><select name="app[theme]" class="select"><option value="dark" @selected(old('app.theme', $values['app.theme'] ?? 'dark') === 'dark')>Dark</option><option value="light" @selected(old('app.theme', $values['app.theme'] ?? '') === 'light')>Light</option></select></div>
                        <div class="tiny" style="margin-top:8px;">The header toggle overrides this locally for the current device.</div>
                    </div>
                </div>
            </div>
        </div>
    </form>
@endsection
