@php
    $tabs = [
        'reports.index' => 'Overview',
        'reports.sales' => 'Sales',
        'reports.products' => 'Products',
        'reports.payments' => 'Payments',
        'reports.staff' => 'Staff',
        'reports.inventory' => 'Inventory',
    ];
    $preset = request('preset', 'month');
@endphp

<div class="panel" style="margin-bottom:18px;">
    <div class="panel-body" style="display:flex;gap:7px;flex-wrap:wrap;border-bottom:1px solid var(--border);">
        @foreach ($tabs as $route => $label)
            <a href="{{ route($route, request()->only(['from', 'to', 'preset'])) }}" class="btn btn-sm {{ request()->routeIs($route) ? 'btn-primary' : 'btn-ghost' }}">{{ $label }}</a>
        @endforeach
    </div>
    @isset($from)
        <form method="GET" action="{{ url()->current() }}" class="panel-body" style="display:flex;gap:9px;flex-wrap:wrap;align-items:flex-end;">
            <div class="field" style="min-width:160px;">
                <label class="label">Period</label>
                <select name="preset" class="select" onchange="this.form.submit()">
                    @foreach (['today' => 'Today', 'yesterday' => 'Yesterday', 'week' => 'This week', 'month' => 'This month', 'year' => 'This year', 'custom' => 'Custom range'] as $value => $label)
                        <option value="{{ $value }}" @selected($preset === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field"><label class="label">From</label><input type="date" name="from" value="{{ $from->toDateString() }}" class="input"></div>
            <div class="field"><label class="label">To</label><input type="date" name="to" value="{{ $to->toDateString() }}" class="input"></div>
            <button type="submit" class="btn btn-ghost">Apply</button>
            <div style="margin-left:auto;display:flex;gap:7px;">
                <span class="chip">{{ $from->format('d M Y') }} → {{ $to->format('d M Y') }}</span>
                @if (request()->routeIs('reports.index', 'reports.sales'))
                    <a href="{{ route('reports.export', request()->only(['from', 'to', 'preset'])) }}" class="btn btn-ghost btn-sm"><x-icon name="download" class="w-3.5 h-3.5" /> CSV</a>
                @endif
            </div>
        </form>
    @endisset
</div>
