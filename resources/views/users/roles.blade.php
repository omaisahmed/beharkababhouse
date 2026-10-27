@extends('layouts.app')

@section('title', 'Roles & permissions')

@section('content')
    <div class="page-head">
        <div>
            <h1 class="page-title">Roles & permissions</h1>
            <p class="page-subtitle">What each built-in role can do. Roles are fixed in this version.</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('users.index') }}" class="btn btn-ghost">Users</a>
        </div>
    </div>

    <div class="panel">
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Capability</th>
                        @foreach ($roles as $role)
                            <th class="r">{{ $role->label() }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($capabilities as $label => $ability)
                        <tr>
                            <td style="font-weight:600;">{{ $label }}</td>
                            @foreach ($roles as $role)
                                <td class="r">
                                    @if ($matrix[$role->value][$ability])
                                        <span class="badge badge-emerald">Yes</span>
                                    @else
                                        <span class="tiny">—</span>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="alert alert-warning" style="margin-top:16px;">
        <x-icon name="alert" class="w-4 h-4 shrink-0" />
        <span>Cashiers can take orders and open their own shift. Managers can run the menu, inventory, reports, refunds, and voids. Administrators can also manage users, settings, backups, and the audit log.</span>
    </div>
@endsection
