<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class InstallationService
{
    public function isInstalled(): bool
    {
        if (! Schema::hasTable('users')) {
            return false;
        }

        return User::query()->exists();
    }

    public function createAdministrator(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $user = User::query()->create([
                'name' => $data['name'],
                'email' => strtolower(trim($data['email'])),
                'password' => Hash::make($data['password']),
                'role' => UserRole::Admin,
                'is_active' => true,
            ]);

            app(SettingsService::class)->ensureDefaults($user);

            app(AuditService::class)->log(
                'installation.completed',
                'Initial administrator account created and application configured.',
                $user,
                ['email' => $user->email],
                $user,
            );

            return $user;
        });
    }
}
