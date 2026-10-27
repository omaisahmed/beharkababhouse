<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Controlled unit conversions. Stock is stored in the item's canonical unit:
 * kg, L, or piece. Grams and millilitres are accepted at input boundaries and
 * converted to milli-units of the canonical unit.
 */
final class Units
{
    public const CANONICAL = ['kg', 'l', 'piece'];

    public const ALL = ['kg', 'g', 'l', 'ml', 'piece'];

    /**
     * How many milli-units of the canonical unit one whole input unit equals.
     */
    public static function milliFactor(string $inputUnit, string $canonicalUnit): int
    {
        $inputUnit = strtolower(trim($inputUnit));
        $canonicalUnit = strtolower(trim($canonicalUnit));

        if (! in_array($inputUnit, self::ALL, true) || ! in_array($canonicalUnit, self::CANONICAL, true)) {
            throw new InvalidArgumentException('Unsupported unit.');
        }

        return match (true) {
            $inputUnit === $canonicalUnit => 1000,
            $inputUnit === 'g' && $canonicalUnit === 'kg' => 1,
            $inputUnit === 'kg' && $canonicalUnit === 'kg' => 1000,
            $inputUnit === 'ml' && $canonicalUnit === 'l' => 1,
            $inputUnit === 'l' && $canonicalUnit === 'l' => 1000,
            $inputUnit === 'piece' && $canonicalUnit === 'piece' => 1000,
            default => throw new InvalidArgumentException("Cannot convert {$inputUnit} to {$canonicalUnit}."),
        };
    }

    public static function toMilli(string|int|float $quantity, string $inputUnit, string $canonicalUnit): int
    {
        $parsed = Quantity::parse($quantity);

        return intdiv($parsed * self::milliFactor($inputUnit, $canonicalUnit), 1000);
    }

    public static function inputUnitsFor(string $canonicalUnit): array
    {
        return match ($canonicalUnit) {
            'kg' => ['kg' => 'kg', 'g' => 'g'],
            'l' => ['l' => 'L', 'ml' => 'mL'],
            'piece' => ['piece' => 'piece'],
            default => [],
        };
    }

    public static function label(string $unit): string
    {
        return match ($unit) {
            'kg' => 'kg',
            'g' => 'g',
            'l' => 'L',
            'ml' => 'mL',
            'piece' => 'piece',
            default => $unit,
        };
    }

    public static function stockLabel(int $milli, string $unit): string
    {
        return Quantity::format($milli).' '.self::label($unit);
    }
}
