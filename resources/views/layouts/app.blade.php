@php
    $settings = app(\App\Services\SettingsService::class);
    $currentUser = auth()->user();
    $currentShift = null;
    $navHeld = 0;
    $navKitchen = 0;
    $navLowStock = 0;
    $lastBackup = null;
    try {
        $currentShift = $currentUser?->openShift();
        $navHeld = \App\Models\Order::query()->whereIn('status', ['held', 'draft'])->count();
        $navKitchen = \App\Models\Order::query()->whereIn('status', ['confirmed', 'preparing', 'ready'])->count();
        $navLowStock = \App\Models\InventoryItem::query()->where('minimum_stock_milli', '>', 0)->whereColumn('stock_milli', '<=', 'minimum_stock_milli')->count();
        $lastBackup = \App\Models\BackupHistory::query()->latest()->first();
    } catch (\Throwable) {
        //
    }
    $backupDays = (int) $settings->int('backup.reminder_days', 7);
    $backupDue = $settings->bool('backup.auto_reminder', true)
        && (! $lastBackup || ($lastBackup->created_at && $lastBackup->created_at->diffInDays(now()) >= $backupDays));
    $logoPath = $settings->string('restaurant.logo_path');
    $restaurantName = $settings->restaurantName();
    $currentTitle = trim($__env->yieldContent('title', 'Dashboard'));
@endphp

<!DOCTYPE html>
<html lang="en" data-theme="{{ $settings->string('app.theme', 'dark') }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="color-scheme" content="dark light">
    <title>{{ $currentTitle }} · {{ $restaurantName }}</title>
    <script>
        (function () {
            try {
                var saved = localStorage.getItem('bkh-theme');
                if (saved === 'light' || saved === 'dark') {
                    document.documentElement.dataset.theme = saved;
                }
            } catch (e) {}
        })();
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="app-shell" data-timezone="{{ $settings->timezone() }}">
    <aside class="sidebar" id="app-sidebar" aria-label="Primary navigation">
        <div class="sidebar-inner">
            <div class="brand-block">
                <div class="brand-mark">
                    @if ($logoPath)
                        <img src="{{ route('media', ['path' => $logoPath]) }}" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:14px;">
                    @else
                        <span style="font-weight:800;letter-spacing:-.04em;font-size:18px;">BK</span>
                    @endif
                </div>
                <div>
                    <div class="brand-name">{{ $restaurantName }}</div>
                    <div class="brand-sub">Point of sale</div>
                </div>
            </div>

            <nav class="nav-scroll">
                <div class="nav-group">
                    <div class="nav-group-label">Operations</div>
                    <a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                        <x-icon name="dashboard" /> Dashboard
                    </a>
                    <a href="{{ route('pos.index') }}" class="nav-link {{ request()->routeIs('pos.index') ? 'active' : '' }}">
                        <x-icon name="pos" /> New order
                    </a>
                    <a href="{{ route('pos.held') }}" class="nav-link {{ request()->routeIs('pos.held') ? 'active' : '' }}">
                        <x-icon name="clock" /> Held orders
                        @if ($navHeld > 0)<span class="nav-count">{{ $navHeld }}</span>@endif
                    </a>
                    <a href="{{ route('orders.index') }}" class="nav-link {{ request()->routeIs('orders.*') ? 'active' : '' }}">
                        <x-icon name="receipt" /> Orders
                    </a>
                    @if ($settings->bool('kitchen.enabled', true))
                        <a href="{{ route('kitchen.index') }}" class="nav-link {{ request()->routeIs('kitchen.*') ? 'active' : '' }}">
                            <x-icon name="kitchen" /> Kitchen
                            @if ($navKitchen > 0)<span class="nav-count">{{ $navKitchen }}</span>@endif
                        </a>
                    @endif
                    @if ($settings->bool('tables.enabled', true))
                        <a href="{{ route('tables.index') }}" class="nav-link {{ request()->routeIs('tables.*') ? 'active' : '' }}">
                            <x-icon name="tables" /> Tables
                        </a>
                    @endif
                </div>

                @can('manage-menu')
                    <div class="nav-group">
                        <div class="nav-group-label">Menu</div>
                        <a href="{{ route('categories.index') }}" class="nav-link {{ request()->routeIs('categories.*') ? 'active' : '' }}">
                            <x-icon name="categories" /> Categories
                        </a>
                        <a href="{{ route('products.index') }}" class="nav-link {{ request()->routeIs('products.*') ? 'active' : '' }}">
                            <x-icon name="products" /> Products
                        </a>
                        <a href="{{ route('modifier-groups.index') }}" class="nav-link {{ request()->routeIs('modifier-groups.*') ? 'active' : '' }}">
                            <x-icon name="modifiers" /> Modifiers
                        </a>
                    </div>
                @endcan

                @can('manage-inventory')
                    <div class="nav-group">
                        <div class="nav-group-label">Inventory</div>
                        <a href="{{ route('inventory.index') }}" class="nav-link {{ request()->routeIs('inventory.*') ? 'active' : '' }}">
                            <x-icon name="inventory" /> Stock levels
                            @if ($navLowStock > 0)<span class="nav-count">{{ $navLowStock }}</span>@endif
                        </a>
                        <a href="{{ route('recipes.index') }}" class="nav-link {{ request()->routeIs('recipes.*') ? 'active' : '' }}">
                            <x-icon name="recipe" /> Recipes
                        </a>
                        <a href="{{ route('stock-movements.index') }}" class="nav-link {{ request()->routeIs('stock-movements.*') ? 'active' : '' }}">
                            <x-icon name="movements" /> Stock movements
                        </a>
                        <a href="{{ route('suppliers.index') }}" class="nav-link {{ request()->routeIs('suppliers.*') ? 'active' : '' }}">
                            <x-icon name="supplier" /> Suppliers
                        </a>
                        <a href="{{ route('purchases.index') }}" class="nav-link {{ request()->routeIs('purchases.*') ? 'active' : '' }}">
                            <x-icon name="purchase" /> Purchases
                        </a>
                    </div>
                @endcan

                <div class="nav-group">
                    <div class="nav-group-label">Finance</div>
                    <a href="{{ route('shifts.index') }}" class="nav-link {{ request()->routeIs('shifts.*') ? 'active' : '' }}">
                        <x-icon name="shift" /> Shifts
                    </a>
                    @can('manage-expenses')
                        <a href="{{ route('expenses.index') }}" class="nav-link {{ request()->routeIs('expenses.*') ? 'active' : '' }}">
                            <x-icon name="expenses" /> Expenses
                        </a>
                    @endcan
                    @can('view-reports')
                        <a href="{{ route('reports.index') }}" class="nav-link {{ request()->routeIs('reports.*') ? 'active' : '' }}">
                            <x-icon name="reports" /> Reports
                        </a>
                    @endcan
                </div>

                @if (auth()->user()?->isAdmin() || auth()->user()?->isManager())
                    <div class="nav-group">
                        <div class="nav-group-label">Administration</div>
                        @can('manage-users')
                            <a href="{{ route('users.index') }}" class="nav-link {{ request()->routeIs('users.*') ? 'active' : '' }}">
                                <x-icon name="users" /> Users
                            </a>
                            <a href="{{ route('roles.index') }}" class="nav-link {{ request()->routeIs('roles.*') ? 'active' : '' }}">
                                <x-icon name="roles" /> Roles & permissions
                            </a>
                        @endcan
                        @can('manage-settings')
                            <a href="{{ route('settings.edit') }}" class="nav-link {{ request()->routeIs('settings.*') ? 'active' : '' }}">
                                <x-icon name="settings" /> Settings
                            </a>
                        @endcan
                        @can('manage-backups')
                            <a href="{{ route('backups.index') }}" class="nav-link {{ request()->routeIs('backups.*') ? 'active' : '' }}">
                                <x-icon name="backup" /> Backup & restore
                                @if ($backupDue)<span class="nav-count">!</span>@endif
                            </a>
                        @endcan
                        @can('view-audit')
                            <a href="{{ route('audit.index') }}" class="nav-link {{ request()->routeIs('audit.*') ? 'active' : '' }}">
                                <x-icon name="audit" /> Audit log
                            </a>
                        @endcan
                    </div>
                @endif

                <div class="nav-group">
                    <a href="{{ route('about') }}" class="nav-link {{ request()->routeIs('about') ? 'active' : '' }}">
                        <x-icon name="info" /> About
                    </a>
                </div>
            </nav>

            <div class="sidebar-footer">
                <div style="display:flex;align-items:center;gap:7px;margin-bottom:5px;">
                    <span style="width:7px;height:7px;border-radius:999px;background:#4cc494;display:inline-block;"></span>
                    Local &amp; offline
                </div>
                Version {{ $settings->string('app.version', '0.1.0') }}
            </div>
        </div>
    </aside>

    <div class="main-shell">
        <header class="topbar">
            <button type="button" class="icon-btn mobile-nav-toggle" id="nav-toggle" aria-label="Open navigation">
                <x-icon name="hamburger" class="w-4 h-4" />
            </button>
            <div class="topbar-title">{{ $restaurantName }}</div>
            <div class="topbar-spacer"></div>

            <span class="chip" title="The application works without an internet connection">
                <span class="dot"></span> Offline ready
            </span>

            <span class="chip" id="live-clock" title="Local restaurant time">
                <x-icon name="clock" class="w-3.5 h-3.5" />
                <span>{{ now()->format('D, d M · H:i') }}</span>
            </span>

            @if ($settings->bool('orders.require_shift', true))
                @if ($currentShift)
                    <a href="{{ route('shifts.index') }}" class="chip" style="color:var(--success);" title="Open shift started {{ $currentShift->opened_at?->format('H:i') }}">
                        <span class="dot"></span> Shift open
                    </a>
                @else
                    <a href="{{ route('shifts.index') }}" class="chip warn" title="Open a shift before taking orders">
                        <span class="dot"></span> Shift closed
                    </a>
                @endif
            @endif

            <button type="button" class="icon-btn" id="theme-toggle" aria-label="Toggle color theme" title="Toggle theme">
                <x-icon name="moon" class="w-4 h-4 theme-icon-dark" />
                <x-icon name="sun" class="w-4 h-4 theme-icon-light" style="display:none;" />
            </button>

            <details class="user-menu">
                <summary class="user-summary">
                    <span class="user-avatar">{{ strtoupper(substr($currentUser?->name ?? 'U', 0, 1)) }}</span>
                    <span class="user-meta">
                        <span class="user-name">{{ $currentUser?->name }}</span>
                        <span class="user-role">{{ $currentUser?->role?->label() }}</span>
                    </span>
                    <x-icon name="chevron-down" class="w-3.5 h-3.5" style="color:var(--muted);" />
                </summary>
                <div class="dropdown">
                    <a href="{{ route('password.edit') }}" class="dropdown-item">
                        <x-icon name="lock" class="w-4 h-4" /> Change password
                    </a>
                    @can('manage-settings')
                        <a href="{{ route('settings.edit') }}" class="dropdown-item">
                            <x-icon name="settings" class="w-4 h-4" /> Settings
                        </a>
                    @endcan
                    <div class="dropdown-sep"></div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="dropdown-item danger">
                            <x-icon name="logout" class="w-4 h-4" /> Sign out
                        </button>
                    </form>
                </div>
            </details>
        </header>

        <main class="content">
            @if (session('success'))
                <div class="flash-stack" data-flash>
                    <div class="alert alert-success"><x-icon name="check" class="w-4 h-4 shrink-0" /> <span>{{ session('success') }}</span></div>
                </div>
            @endif
            @if (session('status'))
                <div class="flash-stack" data-flash>
                    <div class="alert alert-success"><x-icon name="check" class="w-4 h-4 shrink-0" /> <span>{{ session('status') }}</span></div>
                </div>
            @endif
            @if (session('error'))
                <div class="flash-stack" data-flash>
                    <div class="alert alert-danger"><x-icon name="alert" class="w-4 h-4 shrink-0" /> <span>{{ session('error') }}</span></div>
                </div>
            @endif
            @if (session('warning'))
                <div class="flash-stack" data-flash>
                    <div class="alert alert-warning"><x-icon name="alert" class="w-4 h-4 shrink-0" /> <span>{{ session('warning') }}</span></div>
                </div>
            @endif
            @if ($errors->any() && ! isset($showInlineErrors))
                <div class="flash-stack" data-flash>
                    <div class="alert alert-danger">
                        <x-icon name="alert" class="w-4 h-4 shrink-0" />
                        <div>
                            <strong>Please review the highlighted fields.</strong>
                            <div class="tiny" style="margin-top:4px;">{{ $errors->first() }}</div>
                        </div>
                    </div>
                </div>
            @endif

            @yield('content')
        </main>
    </div>

    <div id="nav-scrim" class="nav-scrim"></div>

    @stack('modals')
    @stack('scripts')
</body>
</html>
