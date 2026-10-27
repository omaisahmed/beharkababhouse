<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\Product;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SettingsService
{
    /** @var array<string, mixed>|null */
    protected ?array $loaded = null;

    public function defaults(): array
    {
        return config('pos.defaults', []);
    }

    public function all(): array
    {
        if ($this->loaded !== null) {
            return $this->loaded;
        }

        $defaults = $this->defaults();

        if (! Schema::hasTable('app_settings')) {
            return $this->loaded = $defaults;
        }

        $stored = AppSetting::query()->get();

        foreach ($stored as $setting) {
            $defaults[$setting->key] = $this->castFromDatabase($setting);
        }

        return $this->loaded = $defaults;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $all = $this->all();

        return array_key_exists($key, $all) ? $all[$key] : $default;
    }

    public function string(string $key, string $default = ''): string
    {
        $value = $this->get($key, $default);

        return $value === null ? $default : (string) $value;
    }

    public function bool(string $key, bool $default = false): bool
    {
        $value = $this->get($key, $default);

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $default;
    }

    public function int(string $key, int $default = 0): int
    {
        $value = $this->get($key, $default);

        return is_numeric($value) ? (int) $value : $default;
    }

    public function money(int $minor, bool $withSymbol = true): string
    {
        return Money::format($minor, $this->string('currency.symbol', 'Rs'), $withSymbol);
    }

    public function currencySymbol(): string
    {
        return $this->string('currency.symbol', 'Rs');
    }

    public function restaurantName(): string
    {
        return $this->string('restaurant.name', 'Behar Kabab House');
    }

    public function timezone(): string
    {
        $timezone = $this->string('timezone', config('app.timezone', 'UTC'));

        return in_array($timezone, timezone_identifiers_list(), true) ? $timezone : 'UTC';
    }

    public function taxRateBpForProduct(?Product $product = null): int
    {
        if ($product && $product->tax_rate_bp !== null) {
            return $product->tax_rate_bp;
        }

        return $this->bool('tax.enabled') ? $this->int('tax.rate_bp') : 0;
    }

    public function set(string $key, mixed $value, ?User $user = null): AppSetting
    {
        $type = match (true) {
            is_bool($value) => 'bool',
            is_int($value) => 'int',
            is_array($value) => 'json',
            default => 'string',
        };

        $stored = match (true) {
            is_bool($value) => $value ? '1' : '0',
            is_array($value) => json_encode($value, JSON_UNESCAPED_UNICODE),
            $value === null => null,
            default => (string) $value,
        };

        $setting = AppSetting::query()->updateOrCreate(
            ['key' => $key],
            [
                'value' => $stored,
                'group' => str_contains($key, '.') ? explode('.', $key)[0] : 'general',
                'type' => $type,
                'updated_by' => $user?->id,
            ]
        );

        $this->loaded = null;
        Cache::forget('app_settings');

        return $setting;
    }

    public function setMany(array $values, ?User $user = null): void
    {
        DB::transaction(function () use ($values, $user) {
            foreach ($values as $key => $value) {
                $this->set($key, $value, $user);
            }
        });

        $this->loaded = null;
    }

    public function ensureDefaults(?User $user = null): void
    {
        if (! Schema::hasTable('app_settings')) {
            return;
        }

        foreach ($this->defaults() as $key => $default) {
            if (! AppSetting::query()->where('key', $key)->exists()) {
                $this->set($key, $default, $user);
            }
        }

        $this->loaded = null;
    }

    public function hasTax(): bool
    {
        return $this->bool('tax.enabled') && $this->int('tax.rate_bp') > 0;
    }

    public function hasServiceCharge(): bool
    {
        return $this->bool('service_charge.enabled') && $this->int('service_charge.rate_bp') > 0;
    }

    protected function castFromDatabase(AppSetting $setting): mixed
    {
        return match ($setting->type) {
            'bool' => filter_var($setting->value, FILTER_VALIDATE_BOOLEAN),
            'int' => (int) $setting->value,
            'json' => $setting->value ? json_decode($setting->value, true) : null,
            default => $setting->value,
        };
    }
}
