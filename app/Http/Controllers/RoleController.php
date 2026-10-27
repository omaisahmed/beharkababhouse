<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use Illuminate\View\View;

class RoleController extends Controller
{
    public function __invoke(): View
    {
        $this->authorize('manage-users');

        $capabilities = [
            'Use POS' => 'use-pos',
            'Apply discounts' => 'apply-discounts',
            'Manage tables' => 'manage-tables',
            'Manage shifts' => 'manage-shifts',
            'Void orders' => 'void-orders',
            'Process refunds' => 'process-refunds',
            'Manage menu' => 'manage-menu',
            'Manage inventory' => 'manage-inventory',
            'Manage expenses' => 'manage-expenses',
            'View reports' => 'view-reports',
            'Manage backups' => 'manage-backups',
            'Manage users' => 'manage-users',
            'Manage settings' => 'manage-settings',
            'View audit log' => 'view-audit',
        ];

        $roles = UserRole::cases();
        $matrix = [];

        foreach ($roles as $role) {
            foreach ($capabilities as $ability) {
                $matrix[$role->value][$ability] = match ($ability) {
                    'use-pos' => true,
                    'manage-users', 'manage-settings', 'view-audit' => $role->isAdmin(),
                    'apply-discounts', 'manage-tables' => $role->canManageMenu(),
                    'manage-shifts', 'void-orders', 'process-refunds', 'manage-backups', 'view-reports' => $role->canManageInventory(),
                    default => $role->canManageMenu(),
                };
            }
        }

        return view('users.roles', compact('roles', 'capabilities', 'matrix'));
    }
}
