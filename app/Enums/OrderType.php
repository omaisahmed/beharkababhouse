<?php

namespace App\Enums;

enum OrderType: string
{
    case DineIn = 'dine_in';
    case Takeaway = 'takeaway';
    case Delivery = 'delivery';

    public function label(): string
    {
        return match ($this) {
            self::DineIn => 'Dine-in',
            self::Takeaway => 'Takeaway',
            self::Delivery => 'Delivery',
        };
    }

    public function requiresTable(): bool
    {
        return $this === self::DineIn;
    }

    public function requiresDeliveryDetails(): bool
    {
        return $this === self::Delivery;
    }
}
