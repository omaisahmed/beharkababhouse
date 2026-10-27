<?php

namespace App\Http\Controllers;

use App\Services\AuditService;
use App\Services\MediaService;
use App\Services\SettingsService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function edit(SettingsService $settings): View
    {
        $this->authorize('manage-settings');

        return view('settings.edit', [
            'settings' => $settings,
            'values' => $settings->all(),
            'widths' => config('pos.receipt_widths'),
            'orderTypes' => config('pos.order_types'),
        ]);
    }

    public function update(Request $request, SettingsService $settings, MediaService $media, AuditService $audit): RedirectResponse
    {
        $this->authorize('manage-settings');

        $data = $request->validate([
            'restaurant.name' => ['required', 'string', 'max:120'],
            'restaurant.legal_name' => ['nullable', 'string', 'max:120'],
            'restaurant.address' => ['nullable', 'string', 'max:500'],
            'restaurant.phone' => ['nullable', 'string', 'max:40'],
            'restaurant.email' => ['nullable', 'email', 'max:120'],
            'restaurant.website' => ['nullable', 'string', 'max:160'],
            'restaurant.tax_id' => ['nullable', 'string', 'max:80'],
            'currency.code' => ['required', 'string', 'max:8'],
            'currency.symbol' => ['required', 'string', 'max:8'],
            'currency.decimals' => ['required', 'integer', 'min:0', 'max:3'],
            'timezone' => ['required', 'string', 'timezone'],
            'locale' => ['required', 'string', 'max:8'],
            'tax.enabled' => ['nullable', 'boolean'],
            'tax.rate' => ['nullable', 'string', 'max:10'],
            'tax.inclusive' => ['nullable', 'boolean'],
            'tax.label' => ['nullable', 'string', 'max:40'],
            'service_charge.enabled' => ['nullable', 'boolean'],
            'service_charge.rate' => ['nullable', 'string', 'max:10'],
            'service_charge.label' => ['nullable', 'string', 'max:40'],
            'discount.cashier_max_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
            'discount.manager_max_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
            'rounding.mode' => ['required', Rule::in(['half_up', 'none'])],
            'receipt.width_mm' => ['required', 'integer', Rule::in(array_keys(config('pos.receipt_widths')))],
            'receipt.header' => ['nullable', 'string', 'max:500'],
            'receipt.footer' => ['nullable', 'string', 'max:500'],
            'receipt.show_logo' => ['nullable', 'boolean'],
            'receipt.show_tax_id' => ['nullable', 'boolean'],
            'receipt.printer_name' => ['nullable', 'string', 'max:120'],
            'receipt.copies' => ['required', 'integer', 'min:1', 'max:5'],
            'kitchen.enabled' => ['nullable', 'boolean'],
            'kitchen.printer_name' => ['nullable', 'string', 'max:120'],
            'kitchen.warning_minutes' => ['required', 'integer', 'min:1', 'max:120'],
            'kitchen.critical_minutes' => ['required', 'integer', 'min:2', 'max:180'],
            'tables.enabled' => ['nullable', 'boolean'],
            'backup.directory' => ['nullable', 'string', 'max:500'],
            'backup.reminder_days' => ['required', 'integer', 'min:1', 'max:365'],
            'backup.auto_reminder' => ['nullable', 'boolean'],
            'orders.require_shift' => ['nullable', 'boolean'],
            'orders.default_type' => ['required', Rule::in(array_keys(config('pos.order_types')))],
            'orders.show_kitchen_after_checkout' => ['nullable', 'boolean'],
            'app.theme' => ['required', Rule::in(['dark', 'light'])],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_logo' => ['nullable', 'boolean'],
        ]);

        $data = Arr::dot($data);

        try {
            $data['tax.rate_bp'] = max(0, min(10000, Money::parse($data['tax.rate'] ?? 0)));
            $data['service_charge.rate_bp'] = max(0, min(10000, Money::parse($data['service_charge.rate'] ?? 0)));
        } catch (\InvalidArgumentException) {
            throw ValidationException::withMessages(['tax.rate' => 'Enter a valid tax or service charge percentage.']);
        }

        foreach ([
            'tax.enabled', 'tax.inclusive', 'service_charge.enabled', 'receipt.show_logo',
            'receipt.show_tax_id', 'kitchen.enabled', 'tables.enabled', 'backup.auto_reminder',
            'orders.require_shift', 'orders.show_kitchen_after_checkout',
        ] as $key) {
            $data[$key] = $request->boolean($key);
        }

        $data['discount.cashier_max_percent'] = (int) ($data['discount.cashier_max_percent'] ?? 0);
        $data['discount.manager_max_percent'] = (int) ($data['discount.manager_max_percent'] ?? 100);
        $data['currency.decimals'] = (int) $data['currency.decimals'];
        $data['receipt.width_mm'] = (int) $data['receipt.width_mm'];
        $data['receipt.copies'] = (int) $data['receipt.copies'];
        $data['kitchen.warning_minutes'] = (int) $data['kitchen.warning_minutes'];
        $data['kitchen.critical_minutes'] = (int) $data['kitchen.critical_minutes'];
        $data['backup.reminder_days'] = (int) $data['backup.reminder_days'];
        $data['backup.directory'] = trim((string) ($data['backup.directory'] ?? ''));

        if ($data['kitchen.critical_minutes'] <= $data['kitchen.warning_minutes']) {
            throw ValidationException::withMessages(['kitchen.critical_minutes' => 'Critical time must be greater than the warning time.']);
        }

        if ($request->hasFile('logo')) {
            $old = $settings->string('restaurant.logo_path');
            $media->delete($old);
            $data['restaurant.logo_path'] = $media->store($request->file('logo'), 'branding');
        } elseif ($request->boolean('remove_logo')) {
            $media->delete($settings->string('restaurant.logo_path'));
            $data['restaurant.logo_path'] = null;
        }

        unset($data['tax.rate'], $data['service_charge.rate'], $data['logo'], $data['remove_logo']);

        $settings->setMany($data, $request->user());
        $audit->log('settings.updated', 'Updated restaurant settings.', null, ['keys' => array_keys($data)]);

        return redirect()->route('settings.edit')->with('success', 'Settings saved.');
    }
}
