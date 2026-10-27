@extends('layouts.app')

@section('title', 'Sales report')

@php $money = app(\App\Services\SettingsService::class); @endphp

@section('content')
    <div class="page-head">
        <div>
            <h1 class="page-title">Sales report</h1>
            <p class="page-subtitle">Daily net sales, gross profit, and payment collection.</p>
        </div>
    </div>

    @include('reports._filters')

    <div class="stat-grid">
        <div class="stat"><div class="stat-label">Gross sales</div><div class="stat-value">{{ $money->money($summary['total_minor']) }}</div></div>
        <div class="stat"><div class="stat-label">Refunds</div><div class="stat-value" style="color:var(--danger);">{{ $money->money($summary['refunds_minor']) }}</div><div class="stat-meta">{{ $summary['refund_count'] }} refunds</div></div>
        <div class="stat"><div class="stat-label">Net sales</div><div class="stat-value">{{ $money->money($summary['net_sales_minor']) }}</div></div>
        <div class="stat"><div class="stat-label">Cost of goods</div><div class="stat-value">{{ $money->money($summary['cogs_minor']) }}</div></div>
        <div class="stat"><div class="stat-label">Gross profit</div><div class="stat-value" style="color:{{ $summary['gross_profit_minor'] >= 0 ? 'var(--success)' : 'var(--danger)' }};">{{ $money->money($summary['gross_profit_minor']) }}</div></div>
        <div class="stat"><div class="stat-label">Average ticket</div><div class="stat-value">{{ $money->money($summary['average_ticket_minor']) }}</div><div class="stat-meta">{{ $summary['order_count'] }} orders</div></div>
    </div>

    <div class="grid-2" style="margin-top:18px;align-items:start;">
        <div class="panel">
            <div class="panel-head"><div class="panel-title">Daily breakdown</div></div>
            <div class="table-wrap" style="max-height:520px;overflow:auto;">
                <table class="data-table">
                    <thead><tr><th>Date</th><th class="r">Orders</th><th class="r">Net sales</th><th class="r">COGS</th><th class="r">Profit</th></tr></thead>
                    <tbody>
                        @foreach ($series as $day)
                            <tr>
                                <td class="tiny">{{ $day['label'] }}</td>
                                <td class="r">{{ $day['orders'] }}</td>
                                <td class="r">{{ $money->money($day['total_minor']) }}</td>
                                <td class="r tiny">{{ $money->money($day['cogs_minor']) }}</td>
                                <td class="r" style="color:{{ $day['profit_minor'] >= 0 ? 'var(--success)' : 'var(--danger)' }};">{{ $money->money($day['profit_minor']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="stack" style="display:grid;gap:14px;">
            <div class="panel">
                <div class="panel-head"><div class="panel-title">Sales composition</div></div>
                <div class="panel-body">
                    <div class="summary-row"><span>Gross sales</span><strong>{{ $money->money($summary['gross_minor']) }}</strong></div>
                    <div class="summary-row"><span>Discounts</span><strong style="color:var(--danger);">− {{ $money->money($summary['discount_minor']) }}</strong></div>
                    <div class="summary-row"><span>Service charge</span><strong>{{ $money->money($summary['service_charge_minor']) }}</strong></div>
                    <div class="summary-row"><span>Delivery fees</span><strong>{{ $money->money($summary['delivery_fee_minor']) }}</strong></div>
                    <div class="summary-row"><span>Tax</span><strong>{{ $money->money($summary['tax_minor']) }}</strong></div>
                    <div class="summary-total"><span>Gross total</span><strong>{{ $money->money($summary['total_minor']) }}</strong></div>
                </div>
            </div>
            <div class="panel">
                <div class="panel-head"><div class="panel-title">Payments</div></div>
                <div class="panel-body">
                    @foreach ($payments as $payment)
                        <div class="summary-row"><span>{{ $payment['label'] }} <span class="tiny">· {{ $payment['count'] }}×</span></span><strong>{{ $money->money($payment['net_minor']) }}</strong></div>
                    @endforeach
                    <div class="summary-total"><span>Net collected</span><strong>{{ $money->money(collect($payments)->sum('net_minor')) }}</strong></div>
                </div>
            </div>
        </div>
    </div>
@endsection
