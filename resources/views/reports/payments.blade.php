@extends('layouts.app')

@section('title', 'Payment report')

@php $money = app(\App\Services\SettingsService::class); @endphp

@section('content')
    <div class="page-head">
        <div>
            <h1 class="page-title">Payment report</h1>
            <p class="page-subtitle">Collection by payment method, net of refunds.</p>
        </div>
    </div>

    @include('reports._filters')

    <div class="stat-grid">
        <div class="stat"><div class="stat-label">Collected</div><div class="stat-value">{{ $money->money($summary['total_minor']) }}</div></div>
        <div class="stat"><div class="stat-label">Refunded</div><div class="stat-value" style="color:var(--danger);">{{ $money->money($summary['refunds_minor']) }}</div></div>
        <div class="stat"><div class="stat-label">Net sales</div><div class="stat-value">{{ $money->money($summary['net_sales_minor']) }}</div></div>
    </div>

    <div class="panel" style="margin-top:18px;">
        <div class="panel-head"><div class="panel-title">By method</div></div>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>Method</th><th class="r">Payments</th><th class="r">Collected</th><th class="r">Refunds</th><th class="r">Refunded</th><th class="r">Net</th></tr></thead>
                <tbody>
                    @foreach ($payments as $payment)
                        <tr>
                            <td>{{ $payment['label'] }}</td>
                            <td class="r">{{ $payment['count'] }}</td>
                            <td class="r">{{ $money->money($payment['total_minor']) }}</td>
                            <td class="r">{{ $payment['refund_count'] }}</td>
                            <td class="r tiny" style="color:{{ $payment['refund_minor'] > 0 ? 'var(--danger)' : 'inherit' }};">{{ $money->money($payment['refund_minor']) }}</td>
                            <td class="r" style="font-weight:650;">{{ $money->money($payment['net_minor']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection
