@extends('layouts.app')

@section('title', 'About')

@section('content')
    <div class="page-head">
        <div>
            <h1 class="page-title">About</h1>
            <p class="page-subtitle">Application and device information.</p>
        </div>
    </div>

    <div class="grid-2" style="align-items:start;">
        <div class="panel">
            <div class="panel-body">
                <div style="display:flex;align-items:center;gap:15px;">
                    <div class="brand-mark" style="width:64px;height:64px;">
                        @if ($settings->string('restaurant.logo_path'))
                            <img src="{{ route('media', ['path' => $settings->string('restaurant.logo_path')]) }}" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:14px;">
                        @else
                            <span style="font-weight:800;letter-spacing:-.04em;font-size:24px;">BK</span>
                        @endif
                    </div>
                    <div>
                        <div style="font-size:19px;font-weight:800;letter-spacing:-.02em;">{{ $restaurant }}</div>
                        <div class="tiny">Point of sale · Version {{ $version }}</div>
                    </div>
                </div>

                <div style="margin-top:18px;">
                    <div class="summary-row"><span>Application</span><strong>Behar POS</strong></div>
                    <div class="summary-row"><span>Laravel</span><strong>{{ $laravel }}</strong></div>
                    <div class="summary-row"><span>PHP</span><strong>{{ $php }}</strong></div>
                    <div class="summary-row"><span>SQLite</span><strong>{{ $sqlite }}</strong></div>
                    <div class="summary-row"><span>Database size</span><strong>{{ number_format($databaseSize / 1024, 0) }} KB</strong></div>
                    <div class="summary-row"><span>Free disk space</span><strong>{{ $storageFree > 0 ? number_format($storageFree / 1073741824, 1).' GB' : 'Unknown' }}</strong></div>
                    <div class="summary-row"><span>Last backup</span><strong>{{ $lastBackup?->created_at?->format('d M Y, H:i') ?? 'Never' }}</strong></div>
                </div>
            </div>
        </div>

        <div class="stack" style="display:grid;gap:14px;">
            <div class="panel">
                <div class="panel-head"><div class="panel-title">Offline by design</div></div>
                <div class="panel-body">
                    <p class="tiny" style="line-height:1.7;">All sales, menu, stock, and staff data are stored on this computer in a local SQLite database. The application does not need an internet connection to take orders or print receipts.</p>
                    <div class="alert alert-warning" style="margin-top:12px;font-size:11px;"><x-icon name="alert" class="w-4 h-4 shrink-0" /><span>Back up regularly. If this device fails, the only recovery is a backup file.</span></div>
                </div>
            </div>

            <div class="panel">
                <div class="panel-head"><div class="panel-title">Support</div></div>
                <div class="panel-body">
                    <p class="tiny" style="line-height:1.7;">For training, menu changes, or technical support, contact your system administrator. Keep the version number above handy when reporting an issue.</p>
                    @can('manage-settings')
                        <a href="{{ route('settings.edit') }}" class="btn btn-ghost btn-sm" style="margin-top:10px;">Open settings</a>
                    @endcan
                </div>
            </div>
        </div>
    </div>
@endsection
