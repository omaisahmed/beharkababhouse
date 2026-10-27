<?php

namespace App\Providers;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->configureSqlite();
        $this->configureAuthorization();
    }

    protected function configureSqlite(): void
    {
        if (! in_array(config('database.default'), ['sqlite', 'nativephp'], true)) {
            return;
        }

        try {
            DB::statement('PRAGMA foreign_keys = ON');
            DB::statement('PRAGMA busy_timeout = 5000');

            $database = DB::connection()->getDatabaseName();

            if ($database !== ':memory:') {
                DB::statement('PRAGMA journal_mode = WAL');
            }

            DB::statement('PRAGMA synchronous = NORMAL');
        } catch (\Throwable) {
            // The connection may not be available during early boot or install.
        }
    }

    protected function configureAuthorization(): void
    {
        Gate::define('manage-users', fn (User $user) => $user->role->canManageUsers());
        Gate::define('manage-settings', fn (User $user) => $user->role->canManageSettings());
        Gate::define('view-audit', fn (User $user) => $user->role->canViewAudit());
        Gate::define('manage-menu', fn (User $user) => $user->role->canManageMenu());
        Gate::define('manage-inventory', fn (User $user) => $user->role->canManageInventory());
        Gate::define('manage-expenses', fn (User $user) => $user->role->canManageExpenses());
        Gate::define('view-reports', fn (User $user) => $user->role->canViewReports());
        Gate::define('process-refunds', fn (User $user) => $user->role->canProcessRefunds());
        Gate::define('void-orders', fn (User $user) => $user->role->canVoidOrders());
        Gate::define('manage-backups', fn (User $user) => $user->role->canManageBackups());
        Gate::define('manage-shifts', fn (User $user) => $user->role->canManageShifts());
        Gate::define('apply-discounts', fn (User $user) => $user->hasRole(UserRole::Admin, UserRole::Manager));
        Gate::define('manage-tables', fn (User $user) => $user->hasRole(UserRole::Admin, UserRole::Manager));
        Gate::define('use-pos', fn (User $user) => true);
    }
}
