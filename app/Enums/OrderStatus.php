<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Draft = 'draft';
    case Held = 'held';
    case Confirmed = 'confirmed';
    case Preparing = 'preparing';
    case Ready = 'ready';
    case Served = 'served';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Held => 'Held',
            self::Confirmed => 'Confirmed',
            self::Preparing => 'Preparing',
            self::Ready => 'Ready',
            self::Served => 'Served',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'slate',
            self::Held => 'amber',
            self::Confirmed => 'blue',
            self::Preparing => 'orange',
            self::Ready => 'emerald',
            self::Served => 'teal',
            self::Completed => 'emerald',
            self::Cancelled => 'rose',
        };
    }

    public static function kitchenQueue(): array
    {
        return [self::Confirmed, self::Preparing, self::Ready];
    }

    public function isOpen(): bool
    {
        return in_array($this, [self::Draft, self::Held, self::Confirmed, self::Preparing, self::Ready, self::Served], true);
    }

    public function isKitchen(): bool
    {
        return in_array($this, self::kitchenQueue(), true);
    }
}
