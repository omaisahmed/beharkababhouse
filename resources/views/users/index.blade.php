@extends('layouts.app')

@section('title', 'Users')

@section('content')
    <div class="page-head">
        <div>
            <h1 class="page-title">Users</h1>
            <p class="page-subtitle">Staff accounts and role-based access.</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('roles.index') }}" class="btn btn-ghost">Roles & permissions</a>
            <a href="{{ route('users.create') }}" class="btn btn-primary"><x-icon name="plus" class="w-4 h-4" /> New user</a>
        </div>
    </div>

    <div class="stat-grid">
        <div class="stat"><div class="stat-label">Users</div><div class="stat-value">{{ $stats['total'] }}</div></div>
        <div class="stat"><div class="stat-label">Active</div><div class="stat-value" style="color:var(--success);">{{ $stats['active'] }}</div></div>
        <div class="stat"><div class="stat-label">Administrators</div><div class="stat-value">{{ $stats['admins'] }}</div></div>
    </div>

    <div class="panel" style="margin-top:18px;">
        <form method="GET" action="{{ route('users.index') }}" class="panel-body" style="display:flex;gap:9px;flex-wrap:wrap;align-items:center;border-bottom:1px solid var(--border);">
            <input name="q" value="{{ request('q') }}" class="input" placeholder="Search name or email…" style="max-width:260px;">
            <select name="role" class="select" style="max-width:160px;"><option value="">All roles</option>@foreach ($roles as $role)<option value="{{ $role->value }}" @selected(request('role') === $role->value)>{{ $role->label() }}</option>@endforeach</select>
            <select name="active" class="select" style="max-width:150px;"><option value="">Any status</option><option value="1" @selected(request('active') === '1')>Active</option><option value="0" @selected(request('active') === '0')>Inactive</option></select>
            <button type="submit" class="btn btn-ghost">Filter</button>
            @if (request()->hasAny(['q', 'role', 'active']))<a href="{{ route('users.index') }}" class="btn btn-ghost">Clear</a>@endif
        </form>

        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>User</th><th>Role</th><th>Last login</th><th>Status</th><th class="r"></th></tr></thead>
                <tbody>
                    @forelse ($users as $user)
                        <tr>
                            <td>
                                <div style="display:flex;align-items:center;gap:10px;">
                                    <span class="user-avatar">{{ strtoupper(substr($user->name, 0, 1)) }}</span>
                                    <div><div style="font-weight:650;">{{ $user->name }} @if ($user->is(auth()->user()))<span class="tiny">(you)</span>@endif</div><div class="tiny">{{ $user->email }}</div></div>
                                </div>
                            </td>
                            <td><span class="badge badge-neutral">{{ $user->role?->label() }}</span></td>
                            <td class="tiny">{{ $user->last_login_at?->format('d M Y, H:i') ?? 'Never' }}</td>
                            <td>
                                <form method="POST" action="{{ route('users.toggle', $user) }}">
                                    @csrf
                                    <button type="submit" class="badge {{ $user->is_active ? 'badge-emerald' : 'badge-rose' }}" style="border:0;cursor:pointer;">{{ $user->is_active ? 'Active' : 'Inactive' }}</button>
                                </form>
                            </td>
                            <td class="r">
                                <div style="display:flex;gap:5px;justify-content:flex-end;">
                                    <button type="button" class="btn btn-ghost btn-sm" data-reset-password="{{ $user->id }}" data-name="{{ $user->name }}">Password</button>
                                    <a href="{{ route('users.edit', $user) }}" class="btn btn-ghost btn-sm">Edit</a>
                                    <form method="POST" action="{{ route('users.destroy', $user) }}" data-confirm="Delete {{ $user->name }}?">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-ghost btn-sm" style="color:var(--danger);" @disabled($user->is(auth()->user()))>Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><div class="empty" style="padding:45px 20px;"><div class="empty-title">No users found</div></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($users->hasPages())<div class="panel-body">{{ $users->links() }}</div>@endif
    </div>
@endsection

@push('modals')
    <dialog class="modal" id="password-dialog">
        <form method="POST" id="password-form">
            @csrf
            @method('PUT')
            <div class="modal-head"><div class="modal-title">Reset password · <span id="password-name"></span></div><button type="button" class="close-x" data-close><x-icon name="x" class="w-4 h-4" /></button></div>
            <div class="modal-body">
                <div class="field"><label class="label">New password</label><input type="password" name="password" class="input" required minlength="8" autocomplete="new-password"><div class="hint">At least 8 characters.</div></div>
                <div class="field" style="margin-top:12px;"><label class="label">Confirm password</label><input type="password" name="password_confirmation" class="input" required minlength="8" autocomplete="new-password"></div>
            </div>
            <div class="modal-foot"><button type="button" class="btn btn-ghost" data-close>Cancel</button><button type="submit" class="btn btn-primary">Update password</button></div>
        </form>
    </dialog>
@endpush

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('[data-reset-password]').forEach((button) => {
            button.addEventListener('click', () => {
                document.getElementById('password-form').action = @json(url('/users')) + '/' + button.dataset.resetPassword + '/password';
                document.getElementById('password-name').textContent = button.dataset.name;
                document.getElementById('password-dialog').showModal();
            });
        });
    });
</script>
@endpush
