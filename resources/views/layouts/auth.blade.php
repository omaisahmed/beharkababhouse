@php
    $settings = app(\App\Services\SettingsService::class);
    $restaurantName = $settings->restaurantName();
    $logoPath = $settings->string('restaurant.logo_path');
@endphp
<!DOCTYPE html>
<html lang="en" data-theme="{{ $settings->string('app.theme', 'dark') }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="color-scheme" content="dark light">
    <title>@yield('title', 'Sign in') · {{ $restaurantName }}</title>
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
<body>
    <div class="auth-shell">
        <section class="auth-visual">
            <div class="auth-brand">
                <div class="brand-mark" style="width:52px;height:52px;">
                    @if ($logoPath)
                        <img src="{{ route('branding.logo') }}" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:14px;">
                    @else
                        <span style="font-weight:800;letter-spacing:-.04em;font-size:20px;">BK</span>
                    @endif
                </div>
                <div>
                    <div class="brand-name" style="font-size:18px;">{{ $restaurantName }}</div>
                    <div class="brand-sub">Restaurant point of sale</div>
                </div>
            </div>

            <div class="auth-hero">
                <h1>Fast, reliable service.<br><span>Every single order.</span></h1>
                <p>A complete offline-first point of sale built for the counter, the kitchen, and the back office. Your data stays on this computer.</p>
                <div class="auth-points">
                    <span>Works without internet</span>
                    <span>Touch friendly</span>
                    <span>Thermal receipt printing</span>
                    <span>Local backups</span>
                </div>
            </div>

            <div style="position:relative;z-index:2;font-size:11px;color:#9e8a76;">
                {{ $settings->string('restaurant.website') ?: 'Local restaurant management' }}
            </div>
        </section>

        <section class="auth-form-side">
            <div class="auth-card">
                @if ($errors->any())
                    <div class="alert alert-danger" style="margin-bottom:18px;">
                        <x-icon name="alert" class="w-4 h-4 shrink-0" />
                        <div>
                            <strong>Please check the form.</strong>
                            <div class="tiny" style="margin-top:3px;">{{ $errors->first() }}</div>
                        </div>
                    </div>
                @endif

                @yield('content')

                <div class="auth-footer">
                    Behar Kabab House POS · Local installation<br>
                    Need help? Contact your system administrator.
                </div>
            </div>
        </section>
    </div>
</body>
</html>
