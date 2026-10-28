<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\TableStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\InventoryItem;
use App\Models\Modifier;
use App\Models\ModifierGroup;
use App\Models\Order;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Recipe;
use App\Models\RecipeIngredient;
use App\Models\RestaurantTable;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Services\AuditService;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class WorkflowSmokeTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Product $product;

    protected InventoryItem $inventory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        $this->admin = User::query()->create([
            'name' => 'Workflow Admin',
            'email' => 'workflow@example.com',
            'password' => 'password',
            'role' => UserRole::Admin,
            'is_active' => true,
        ]);

        app(SettingsService::class)->ensureDefaults($this->admin);

        $category = Category::query()->create([
            'name' => 'Kebabs',
            'slug' => 'kebabs',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->product = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Chicken Kebab',
            'sku' => 'KEB-001',
            'price_minor' => 50000,
            'cost_minor' => 20000,
            'is_taxable' => false,
            'is_available' => true,
            'sort_order' => 1,
        ]);

        $this->inventory = InventoryItem::query()->create([
            'name' => 'Chicken',
            'sku' => 'INV-CHK',
            'unit' => 'kg',
            'stock_milli' => 10000,
            'minimum_stock_milli' => 1000,
            'cost_per_unit_minor' => 80000,
            'is_active' => true,
        ]);

        $recipe = Recipe::query()->create([
            'product_id' => $this->product->id,
            'name' => 'Chicken Kebab recipe',
            'yield_quantity' => 1,
            'is_active' => true,
        ]);

        RecipeIngredient::query()->create([
            'recipe_id' => $recipe->id,
            'inventory_item_id' => $this->inventory->id,
            'quantity_milli' => 500,
        ]);
    }

    public function test_settings_can_be_saved_with_nested_fields(): void
    {
        $payload = [
            'restaurant' => [
                'name' => 'Behar Kabab House',
                'legal_name' => 'Behar Foods',
                'address' => 'Main Road',
                'phone' => '03001234567',
                'email' => 'hello@example.com',
                'website' => 'https://example.com',
                'tax_id' => 'NTN-123',
            ],
            'currency' => ['code' => 'PKR', 'symbol' => 'Rs', 'decimals' => 2],
            'timezone' => 'Asia/Karachi',
            'locale' => 'en',
            'tax' => ['enabled' => '1', 'rate' => '5', 'inclusive' => '1', 'label' => 'GST'],
            'service_charge' => ['enabled' => '1', 'rate' => '10', 'label' => 'Service'],
            'discount' => ['cashier_max_percent' => 5, 'manager_max_percent' => 50],
            'rounding' => ['mode' => 'half_up'],
            'receipt' => [
                'width_mm' => 80,
                'header' => 'Welcome',
                'footer' => 'Thank you',
                'show_logo' => '1',
                'show_tax_id' => '1',
                'printer_name' => 'Receipt printer',
                'copies' => 2,
            ],
            'kitchen' => ['enabled' => '1', 'printer_name' => 'Kitchen printer', 'warning_minutes' => 10, 'critical_minutes' => 20],
            'tables' => ['enabled' => '1'],
            'backup' => ['directory' => '', 'reminder_days' => 7, 'auto_reminder' => '1'],
            'orders' => ['require_shift' => '1', 'default_type' => 'takeaway', 'show_kitchen_after_checkout' => '1'],
            'app' => ['theme' => 'dark'],
        ];

        $this->actingAs($this->admin)
            ->put(route('settings.update'), $payload)
            ->assertRedirect(route('settings.edit'))
            ->assertSessionHasNoErrors();

        $settings = app(SettingsService::class);
        $settings->all();

        $this->assertSame(500, $settings->int('tax.rate_bp'));
        $this->assertSame(1000, $settings->int('service_charge.rate_bp'));
        $this->assertTrue($settings->bool('tax.enabled'));
        $this->assertSame('Behar Kabab House', $settings->restaurantName());
        $this->assertSame(2, $settings->int('receipt.copies'));
    }

    public function test_cash_sale_receipt_inventory_and_shift_close(): void
    {
        $this->actingAs($this->admin)
            ->post(route('shifts.store'), ['opening_cash' => '1000.00'])
            ->assertRedirect(route('shifts.index'));

        $payload = [
            'client_uuid' => (string) Str::uuid(),
            'order_type' => 'takeaway',
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 2],
            ],
            'payment' => [
                'method' => 'cash',
                'amount' => '1000.00',
                'tendered' => '1200.00',
            ],
        ];

        $response = $this->actingAs($this->admin)->post(route('pos.checkout'), $payload);
        $response->assertSessionHasNoErrors();

        $order = Order::query()->firstOrFail();

        $this->assertSame(100000, $order->total_minor);
        $this->assertSame(20000, $order->payments()->firstOrFail()->change_minor);
        $this->assertSame(9000, $this->inventory->fresh()->stock_milli);

        $this->actingAs($this->admin)->get(route('orders.show', $order))->assertOk();
        $this->actingAs($this->admin)->get(route('orders.receipt', $order))->assertOk();
        $this->actingAs($this->admin)->get(route('orders.kitchen-ticket', $order))->assertOk();

        $shift = $this->admin->openShift();
        $this->assertNotNull($shift);

        $this->actingAs($this->admin)->get(route('shifts.show', $shift))->assertOk();

        $this->actingAs($this->admin)
            ->post(route('shifts.close', $shift), ['counted_cash' => '2000.00'])
            ->assertRedirect();

        $shift->refresh();

        $this->assertFalse($shift->isOpen());
        $this->assertSame(0, $shift->difference_minor);
    }

    public function test_inventory_item_creation_rolls_back_if_opening_stock_recording_fails(): void
    {
        $audit = \Mockery::mock(AuditService::class);
        $audit->shouldReceive('log')->once()->andThrow(new \RuntimeException('Audit log failed.'));
        $this->app->instance(AuditService::class, $audit);
        $this->withoutExceptionHandling();

        try {
            $this->actingAs($this->admin)->post(route('inventory.items.store'), [
                'name' => 'Olive oil',
                'sku' => 'INV-OIL',
                'unit' => 'l',
                'minimum_stock' => '0',
                'cost_per_unit' => '10.00',
                'opening_stock' => '2',
                'opening_unit' => 'l',
            ]);
            $this->fail('The failed audit should abort inventory creation.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Audit log failed.', $exception->getMessage());
        }

        $this->assertDatabaseMissing('inventory_items', ['sku' => 'INV-OIL']);
        $this->assertDatabaseMissing('stock_movements', ['reason' => 'Opening stock entered when the item was created.']);
    }

    public function test_inventory_creation_and_restock_update_stock_and_cost(): void
    {
        $this->actingAs($this->admin)->post(route('inventory.items.store'), [
            'name' => 'Olive oil',
            'sku' => 'INV-OIL',
            'unit' => 'l',
            'minimum_stock' => '0',
            'cost_per_unit' => '10.00',
            'opening_stock' => '2',
            'opening_unit' => 'l',
        ])->assertRedirect();

        $item = InventoryItem::query()->where('sku', 'INV-OIL')->firstOrFail();
        $this->assertSame(2000, $item->stock_milli);
        $this->assertSame(1000, $item->cost_per_unit_minor);

        $this->actingAs($this->admin)->post(route('inventory.restock', $item), [
            'quantity' => '1.5',
            'unit' => 'l',
            'unit_cost' => '12.00',
            'reason' => 'Supplier delivery',
        ])->assertRedirect();

        $item->refresh();
        $this->assertSame(3500, $item->stock_milli);
        $this->assertSame(1200, $item->cost_per_unit_minor);
        $this->assertSame(2, StockMovement::query()->where('inventory_item_id', $item->id)->count());
    }

    public function test_order_completion_rolls_back_status_and_table_if_audit_log_fails(): void
    {
        $table = RestaurantTable::query()->create([
            'name' => 'Table 1',
            'capacity' => 4,
            'status' => TableStatus::Occupied,
            'is_active' => true,
        ]);
        $order = Order::query()->create([
            'order_number' => 'ORD-ATOMIC-1',
            'sequence_date' => today(),
            'daily_sequence' => 1,
            'order_type' => OrderType::DineIn,
            'status' => OrderStatus::Ready,
            'user_id' => $this->admin->id,
            'restaurant_table_id' => $table->id,
        ]);

        $audit = \Mockery::mock(AuditService::class);
        $audit->shouldReceive('log')->once()->andThrow(new \RuntimeException('Audit log failed.'));
        $this->app->instance(AuditService::class, $audit);
        $this->withoutExceptionHandling();

        try {
            $this->actingAs($this->admin)->post(route('orders.status', $order), ['status' => 'completed']);
            $this->fail('The failed audit should abort order completion.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Audit log failed.', $exception->getMessage());
        }

        $this->assertSame(OrderStatus::Ready, $order->fresh()->status);
        $this->assertSame(TableStatus::Occupied, $table->fresh()->status);
    }

    public function test_order_completion_releases_its_table(): void
    {
        $table = RestaurantTable::query()->create([
            'name' => 'Table 2',
            'capacity' => 4,
            'status' => TableStatus::Occupied,
            'is_active' => true,
        ]);
        $order = Order::query()->create([
            'order_number' => 'ORD-COMPLETE-1',
            'sequence_date' => today(),
            'daily_sequence' => 2,
            'order_type' => OrderType::DineIn,
            'status' => OrderStatus::Ready,
            'user_id' => $this->admin->id,
            'restaurant_table_id' => $table->id,
        ]);

        $this->actingAs($this->admin)
            ->post(route('orders.status', $order), ['status' => 'completed'])
            ->assertRedirect();

        $this->assertSame(OrderStatus::Completed, $order->fresh()->status);
        $this->assertSame(TableStatus::Available, $table->fresh()->status);
    }

    public function test_management_pages_and_updates_render_with_existing_data(): void
    {
        $this->actingAs($this->admin)
            ->post(route('modifier-groups.store'), [
                'name' => 'Spice level',
                'min_selections' => 0,
                'max_selections' => 1,
                'is_active' => '1',
                'modifiers' => [
                    ['name' => 'Mild', 'price' => '0', 'is_active' => '1'],
                    ['name' => 'Hot', 'price' => '50', 'is_active' => '1'],
                ],
            ])
            ->assertRedirect(route('modifier-groups.index'))
            ->assertSessionHasNoErrors();

        $group = ModifierGroup::query()->where('name', 'Spice level')->firstOrFail();
        $this->assertSame(2, Modifier::query()->where('modifier_group_id', $group->id)->count());

        $this->actingAs($this->admin)
            ->put(route('products.update', $this->product), [
                'category_id' => $this->product->category_id,
                'name' => 'Chicken Kebab',
                'sku' => 'KEB-001',
                'price' => '600.00',
                'is_taxable' => '1',
                'is_available' => '1',
                'modifier_groups' => [$group->id],
                'variants' => [
                    ['name' => 'Regular', 'price_delta' => '0', 'is_default' => '1', 'is_active' => '1'],
                    ['name' => 'Large', 'price_delta' => '100', 'is_active' => '1'],
                ],
            ])
            ->assertRedirect(route('products.index'))
            ->assertSessionHasNoErrors();

        $this->product->refresh();
        $this->assertSame(60000, $this->product->price_minor);
        $this->assertCount(2, $this->product->variants);

        $this->actingAs($this->admin)
            ->post(route('suppliers.store'), [
                'name' => 'Fresh Foods',
                'phone' => '03001234567',
                'is_active' => '1',
            ])
            ->assertRedirect(route('suppliers.index'))
            ->assertSessionHasNoErrors();

        $supplier = Supplier::query()->where('name', 'Fresh Foods')->firstOrFail();

        $this->actingAs($this->admin)
            ->post(route('purchases.store'), [
                'supplier_id' => $supplier->id,
                'purchased_at' => now()->toDateString(),
                'payment_status' => 'paid',
                'paid' => '1600.00',
                'items' => [
                    [
                        'inventory_item_id' => $this->inventory->id,
                        'quantity' => '2',
                        'unit' => 'kg',
                        'unit_cost' => '800.00',
                    ],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $purchase = Purchase::query()->firstOrFail();
        $this->assertSame(160000, $purchase->total_minor);
        $this->assertSame(12000, $this->inventory->fresh()->stock_milli);

        $this->actingAs($this->admin)
            ->post(route('users.store'), [
                'name' => 'Cashier One',
                'email' => 'cashier@example.com',
                'password' => 'password',
                'password_confirmation' => 'password',
                'role' => UserRole::Cashier->value,
                'is_active' => '1',
            ])
            ->assertRedirect(route('users.index'))
            ->assertSessionHasNoErrors();

        $cashier = User::query()->where('email', 'cashier@example.com')->firstOrFail();

        $pages = [
            route('products.edit', $this->product),
            route('modifier-groups.edit', $group),
            route('suppliers.edit', $supplier),
            route('purchases.show', $purchase),
            route('recipes.edit', $this->product),
            route('users.edit', $cashier),
            route('categories.index'),
            route('tables.index'),
            route('expenses.index'),
            route('stock-movements.index'),
            route('reports.inventory'),
        ];

        foreach ($pages as $url) {
            $this->actingAs($this->admin)->get($url)->assertOk();
        }
    }
}
