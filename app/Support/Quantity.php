<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Ingredient quantities are stored as integer milli-units (1/1000 of the
 * inventory item's canonical unit) so that recipe and stock arithmetic is
 * deterministic and free of floating point drift.
 */
final class Quantity
{
    public const SCALE = 1000;

    public static function parse(string|int|float|null $value): int
    {
        if ($value === null || $value === '') {
            return 0;
        }

        if (is_int($value)) {
            return $value * self::SCALE;
        }

        if (is_float($value)) {
            return (int) round($value * self::SCALE);
        }

        $value = trim($value);
        $negative = str_starts_with($value, '-');
        $value = ltrim($value, '+-');
        $value = str_replace([',', ' '], '', $value);

        if ($value === '' || ! preg_match('/^\d*(\.\d*)?$/', $value)) {
            throw new InvalidArgumentException('Invalid quantity.');
        }

        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        $whole = $whole === '' ? '0' : $whole;
        $fraction = str_pad($fraction, 4, '0');

        $milli = ((int) $whole * self::SCALE) + (int) substr($fraction, 0, 3);

        if ((int) $fraction[3] >= 5) {
            $milli++;
        }

        return $negative ? -$milli : $milli;
    }

    public static function format(int $milli, int $precision = 3): string
    {
        $absolute = abs($milli);
        $whole = intdiv($absolute, self::SCALE);
        $fraction = $absolute % self::SCALE;
        $value = $fraction === 0
            ? (string) $whole
            : rtrim(number_format($whole).'.'.str_pad((string) $fraction, 3, '0', STR_PAD_LEFT), '0');

        if ($precision >= 0 && str_contains($value, '.')) {
            $value = rtrim($value, '0');
            $value = rtrim($value, '.');
        }

        return ($milli < 0 ? '-' : '').$value;
    }

    public static function toDecimal(int $milli): string
    {
        $absolute = abs($milli);

        return ($milli < 0 ? '-' : '').intdiv($absolute, self::SCALE).'.'.str_pad((string) ($absolute % self::SCALE), 3, '0', STR_PAD_LEFT);
    }

    public static function multiply(int $milli, int $quantity): int
    {
        return $milli * $quantity;
    }
}
