<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Manager = 'manager';
    case Cashier = 'cashier';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrator',
            self::Manager => 'Manager',
            self::Cashier => 'Cashier',
        };
    }

    public function isAdmin(): bool
    {
        return $this === self::Admin;
    }

    public function isManager(): bool
    {
        return $this === self::Manager;
    }

    public function isCashier(): bool
    {
        return $this === self::Cashier;
    }

    public function canManageUsers(): bool
    {
        return $this === self::Admin;
    }

    public function canManageSettings(): bool
    {
        return $this === self::Admin;
    }

    public function canViewAudit(): bool
    {
        return $this === self::Admin;
    }

    public function canProcessRefunds(): bool
    {
        return in_array($this, [self::Admin, self::Manager], true);
    }

    public function canVoidOrders(): bool
    {
        return in_array($this, [self::Admin, self::Manager], true);
    }

    public function canManageBackups(): bool
    {
        return in_array($this, [self::Admin, self::Manager], true);
    }

    public function canViewReports(): bool
    {
        return in_array($this, [self::Admin, self::Manager], true);
    }

    public function canManageInventory(): bool
    {
        return in_array($this, [self::Admin, self::Manager], true);
    }

    public function canManageExpenses(): bool
    {
        return in_array($this, [self::Admin, self::Manager], true);
    }

    public function canManageMenu(): bool
    {
        return in_array($this, [self::Admin, self::Manager], true);
    }

    public function canManageShifts(): bool
    {
        return in_array($this, [self::Admin, self::Manager], true);
    }
}
