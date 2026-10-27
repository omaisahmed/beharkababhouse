@extends('layouts.app')

@section('title', 'Staff report')

@php $money = app(\App\Services\SettingsService::class); @endphp

@section('content')
    <div class="page-head">
        <div>
            <h1 class="page-title">Staff report</h1>
            <p class="page-subtitle">Sales and order counts recorded by each cashier.</p>
        </div>
    </div>

    @include('reports._filters')

    <div class="panel">
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>Cashier</th><th>Role</th><th class="r">Orders</th><th class="r">Sales</th><th class="r">Average ticket</th></tr></thead>
                <tbody>
                    @forelse ($staff as $member)
                        <tr>
                            <td><div style="font-weight:650;">{{ $member['name'] }}</div></td>
                            <td class="tiny">{{ ucfirst($member['role']) }}</td>
                            <td class="r">{{ $member['order_count'] }}</td>
                            <td class="r">{{ $money->money($member['total_minor']) }}</td>
                            <td class="r tiny">{{ $money->money($member['order_count'] > 0 ? intdiv($member['total_minor'], $member['order_count']) : 0) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><div class="empty" style="padding:45px 20px;"><div class="empty-title">No staff sales</div><div class="empty-text">Recorded orders will appear here.</div></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
