<?php

use App\Http\Controllers\AboutController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\SetupController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\KitchenController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\ModifierGroupController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\ReceiptController;
use App\Http\Controllers\RecipeController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\ShiftController;
use App\Http\Controllers\StockMovementController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\TableController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('branding/logo', [MediaController::class, 'branding'])->name('branding.logo');

Route::middleware('guest')->group(function () {
    Route::get('setup', [SetupController::class, 'create'])->name('setup.create');
    Route::post('setup', [SetupController::class, 'store'])->name('setup.store');
    Route::get('login', [LoginController::class, 'create'])->name('login');
    Route::post('login', [LoginController::class, 'store'])->name('login.store');
});

Route::post('logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');

    Route::get('media/{path}', MediaController::class)->where('path', '.*')->name('media');

    Route::get('password', [PasswordController::class, 'edit'])->name('password.edit');
    Route::put('password', [PasswordController::class, 'update'])->name('password.update');

    Route::get('pos', [PosController::class, 'index'])->name('pos.index');
    Route::post('pos/checkout', [PosController::class, 'checkout'])->name('pos.checkout');
    Route::post('pos/hold', [PosController::class, 'hold'])->name('pos.hold');
    Route::get('pos/held', [PosController::class, 'held'])->name('pos.held');
    Route::delete('pos/held/{order}', [PosController::class, 'discard'])->name('pos.held.discard');
    Route::get('pos/resume/{order}', [PosController::class, 'resume'])->name('pos.resume');

    Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('orders/{order}', [OrderController::class, 'show'])->name('orders.show');
    Route::post('orders/{order}/status', [OrderController::class, 'updateStatus'])->name('orders.status');
    Route::post('orders/{order}/void', [OrderController::class, 'void'])->name('orders.void');
    Route::post('orders/{order}/refund', [OrderController::class, 'refund'])->name('orders.refund');
    Route::get('orders/{order}/receipt', [ReceiptController::class, 'show'])->name('orders.receipt');
    Route::get('orders/{order}/kitchen-ticket', [ReceiptController::class, 'kitchen'])->name('orders.kitchen-ticket');
    Route::get('receipts/test', [ReceiptController::class, 'test'])->name('receipts.test');

    Route::get('kitchen', [KitchenController::class, 'index'])->name('kitchen.index');

    Route::get('tables', [TableController::class, 'index'])->name('tables.index');
    Route::post('tables', [TableController::class, 'store'])->name('tables.store');
    Route::put('tables/{table}', [TableController::class, 'update'])->name('tables.update');
    Route::post('tables/{table}/status', [TableController::class, 'updateStatus'])->name('tables.status');
    Route::delete('tables/{table}', [TableController::class, 'destroy'])->name('tables.destroy');

    Route::get('products/export', [ProductController::class, 'export'])->name('products.export');
    Route::post('products/import', [ProductController::class, 'import'])->name('products.import');
    Route::post('products/{product}/toggle', [ProductController::class, 'toggle'])->name('products.toggle');
    Route::resource('categories', CategoryController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::resource('products', ProductController::class)->except(['show']);
    Route::resource('modifier-groups', ModifierGroupController::class)->except(['show']);

    Route::get('inventory', [InventoryController::class, 'index'])->name('inventory.index');
    Route::post('inventory/items', [InventoryController::class, 'store'])->name('inventory.items.store');
    Route::put('inventory/items/{item}', [InventoryController::class, 'update'])->name('inventory.items.update');
    Route::post('inventory/items/{item}/adjust', [InventoryController::class, 'adjust'])->name('inventory.adjust');
    Route::delete('inventory/items/{item}', [InventoryController::class, 'destroy'])->name('inventory.items.destroy');
    Route::post('inventory/items/{item}/restock', [InventoryController::class, 'restock'])->name('inventory.restock');

    Route::get('recipes', [RecipeController::class, 'index'])->name('recipes.index');
    Route::get('recipes/{product}/edit', [RecipeController::class, 'edit'])->name('recipes.edit');
    Route::put('recipes/{product}', [RecipeController::class, 'update'])->name('recipes.update');

    Route::get('stock-movements', [StockMovementController::class, 'index'])->name('stock-movements.index');
    Route::post('stock-movements', [StockMovementController::class, 'store'])->name('stock-movements.store');

    Route::resource('suppliers', SupplierController::class)->except(['show']);
    Route::resource('purchases', PurchaseController::class)->only(['index', 'create', 'store', 'show', 'destroy']);

    Route::get('expenses', [ExpenseController::class, 'index'])->name('expenses.index');
    Route::post('expenses', [ExpenseController::class, 'store'])->name('expenses.store');
    Route::delete('expenses/{expense}', [ExpenseController::class, 'destroy'])->name('expenses.destroy');
    Route::post('expense-categories', [ExpenseController::class, 'storeCategory'])->name('expense-categories.store');
    Route::put('expense-categories/{expenseCategory}', [ExpenseController::class, 'updateCategory'])->name('expense-categories.update');
    Route::delete('expense-categories/{expenseCategory}', [ExpenseController::class, 'destroyCategory'])->name('expense-categories.destroy');

    Route::get('shifts', [ShiftController::class, 'index'])->name('shifts.index');
    Route::post('shifts', [ShiftController::class, 'store'])->name('shifts.store');
    Route::get('shifts/{shift}', [ShiftController::class, 'show'])->name('shifts.show');
    Route::post('shifts/{shift}/close', [ShiftController::class, 'close'])->name('shifts.close');
    Route::post('shifts/{shift}/cash', [ShiftController::class, 'cashMovement'])->name('shifts.cash');

    Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('reports/sales', [ReportController::class, 'sales'])->name('reports.sales');
    Route::get('reports/products', [ReportController::class, 'products'])->name('reports.products');
    Route::get('reports/payments', [ReportController::class, 'payments'])->name('reports.payments');
    Route::get('reports/staff', [ReportController::class, 'staff'])->name('reports.staff');
    Route::get('reports/inventory', [ReportController::class, 'inventory'])->name('reports.inventory');
    Route::get('reports/export', [ReportController::class, 'export'])->name('reports.export');

    Route::resource('users', UserController::class)->except(['show']);
    Route::post('users/{user}/toggle', [UserController::class, 'toggle'])->name('users.toggle');
    Route::put('users/{user}/password', [UserController::class, 'resetPassword'])->name('users.password');

    Route::get('roles', RoleController::class)->name('roles.index');

    Route::get('settings', [SettingsController::class, 'edit'])->name('settings.edit');
    Route::put('settings', [SettingsController::class, 'update'])->name('settings.update');

    Route::get('backups', [BackupController::class, 'index'])->name('backups.index');
    Route::post('backups', [BackupController::class, 'create'])->name('backups.create');
    Route::get('backups/{backup}/download', [BackupController::class, 'download'])->name('backups.download');
    Route::post('backups/{backup}/restore', [BackupController::class, 'restore'])->name('backups.restore');
    Route::delete('backups/{backup}', [BackupController::class, 'destroy'])->name('backups.destroy');

    Route::get('audit', [AuditLogController::class, 'index'])->name('audit.index');
    Route::get('about', AboutController::class)->name('about');
});
