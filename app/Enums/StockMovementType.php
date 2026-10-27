<?php

namespace App\Enums;

enum StockMovementType: string
{
    case Purchase = 'purchase';
    case Consumption = 'consumption';
    case Wastage = 'wastage';
    case Damage = 'damage';
    case Adjustment = 'adjustment';
    case Return = 'return';
    case Reversal = 'reversal';
    case Opening = 'opening';

    public function label(): string
    {
        return match ($this) {
            self::Purchase => 'Purchase',
            self::Consumption => 'Recipe consumption',
            self::Wastage => 'Wastage',
            self::Damage => 'Damaged stock',
            self::Adjustment => 'Manual adjustment',
            self::Return => 'Returned stock',
            self::Reversal => 'Reversal',
            self::Opening => 'Opening stock',
        };
    }

    public function increasesStock(): bool
    {
        return in_array($this, [self::Purchase, self::Return, self::Opening], true)
            || $this === self::Adjustment;
    }
}
