<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PageSmokeTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        $this->admin = User::query()->create([
            'name' => 'Smoke Admin',
            'email' => 'smoke@example.com',
            'password' => 'password',
            'role' => UserRole::Admin,
            'is_active' => true,
        ]);

        app(SettingsService::class)->ensureDefaults($this->admin);
    }

    public function test_setup_is_skipped_once_an_administrator_exists(): void
    {
        $this->get(route('setup.create'))->assertRedirect(route('dashboard'));
    }

    public function test_shifts_page_renders_with_an_open_shift(): void
    {
        $this->actingAs($this->admin)
            ->post(route('shifts.store'), ['opening_cash' => '0.00'])
            ->assertRedirect(route('shifts.index'));

        $this->actingAs($this->admin)
            ->get(route('shifts.index'))
            ->assertOk()
            ->assertSee('Shift history');
    }

    public function test_authenticated_pages_render(): void
    {
        $routes = [
            'dashboard',
            'about',
            'audit.index',
            'backups.index',
            'categories.index',
            'expenses.index',
            'inventory.index',
            'kitchen.index',
            'modifier-groups.index',
            'modifier-groups.create',
            'orders.index',
            'pos.index',
            'pos.held',
            'products.index',
            'products.create',
            'purchases.index',
            'purchases.create',
            'receipts.test',
            'recipes.index',
            'reports.index',
            'reports.sales',
            'reports.products',
            'reports.payments',
            'reports.staff',
            'reports.inventory',
            'roles.index',
            'settings.edit',
            'shifts.index',
            'stock-movements.index',
            'suppliers.index',
            'suppliers.create',
            'tables.index',
            'users.index',
            'users.create',
        ];

        foreach ($routes as $name) {
            $this->actingAs($this->admin)->get(route($name))->assertOk()->assertSee('</html>', false);
        }
    }
}
