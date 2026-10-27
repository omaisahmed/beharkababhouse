<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Integer minor-unit money arithmetic.
 *
 * One minor unit is 1/100 of the configured currency unit (e.g. 1 paisa).
 * No financial total is ever calculated using binary floating point.
 */
final class Money
{
    public const SCALE = 100;

    public static function parse(string|int|null $value): int
    {
        if ($value === null || $value === '') {
            return 0;
        }

        if (is_int($value)) {
            return $value;
        }

        $value = trim($value);
        $negative = str_starts_with($value, '-');
        $value = ltrim($value, '+-');
        $value = str_replace([',', ' ', "\u{00A0}"], '', $value);

        if ($value === '' || ! preg_match('/^\d*(\.\d*)?$/', $value)) {
            throw new InvalidArgumentException('Invalid monetary amount.');
        }

        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        $whole = $whole === '' ? '0' : ltrim($whole, '0');
        $whole = $whole === '' ? '0' : $whole;
        $fraction = str_pad($fraction, 3, '0');

        $minor = ((int) $whole * self::SCALE) + (int) substr($fraction, 0, 2);

        if ((int) $fraction[2] >= 5) {
            $minor++;
        }

        return $negative ? -$minor : $minor;
    }

    public static function format(int $minor, string $symbol = 'Rs', bool $withSymbol = true): string
    {
        $absolute = abs($minor);
        $whole = intdiv($absolute, self::SCALE);
        $cents = $absolute % self::SCALE;
        $formatted = number_format($whole).'.'.str_pad((string) $cents, 2, '0', STR_PAD_LEFT);

        if ($minor < 0) {
            $formatted = '-'.$formatted;
        }

        return $withSymbol ? $symbol.' '.$formatted : $formatted;
    }

    /**
     * Round half-up percentage calculation in basis points (1% = 100 bp).
     */
    public static function percent(int $amount, int $basisPoints): int
    {
        if ($basisPoints === 0 || $amount === 0) {
            return 0;
        }

        $numerator = $amount * $basisPoints;

        if ($numerator < 0) {
            return -intdiv(-$numerator + 5000, 10000);
        }

        return intdiv($numerator + 5000, 10000);
    }

    /**
     * Round half-up to the nearest whole minor unit after dividing by $divisor.
     */
    public static function divideRounded(int $amount, int $divisor): int
    {
        if ($divisor <= 0) {
            throw new InvalidArgumentException('Divisor must be positive.');
        }

        if ($amount < 0) {
            return -intdiv(-$amount + intdiv($divisor, 2), $divisor);
        }

        return intdiv($amount + intdiv($divisor, 2), $divisor);
    }

    /**
     * Calculate tax from a tax-exclusive amount.
     */
    public static function taxExclusive(int $netAmount, int $basisPoints): int
    {
        return self::percent($netAmount, $basisPoints);
    }

    /**
     * Extract tax from a tax-inclusive gross amount.
     */
    public static function taxInclusive(int $grossAmount, int $basisPoints): int
    {
        if ($basisPoints <= 0) {
            return 0;
        }

        $denominator = 10000 + $basisPoints;

        if ($grossAmount < 0) {
            return -intdiv((-$grossAmount * $basisPoints) + intdiv($denominator, 2), $denominator);
        }

        return intdiv(($grossAmount * $basisPoints) + intdiv($denominator, 2), $denominator);
    }

    public static function percentOf(int $amount, int $percent): int
    {
        return self::percent($amount, $percent * 100);
    }

    public static function toDecimal(int $minor): string
    {
        $absolute = abs($minor);
        $value = intdiv($absolute, self::SCALE).'.'.str_pad((string) ($absolute % self::SCALE), 2, '0', STR_PAD_LEFT);

        return ($minor < 0 ? '-' : '').$value;
    }
}
