@extends('layouts.app')

@section('title', $user->exists ? 'Edit user' : 'New user')

@php $showInlineErrors = true; @endphp

@section('content')
    <form method="POST" action="{{ $user->exists ? route('users.update', $user) : route('users.store') }}">
        @csrf
        @if ($user->exists) @method('PUT') @endif

        <div class="page-head">
            <div>
                <div class="tiny" style="margin-bottom:5px;"><a href="{{ route('users.index') }}" style="color:var(--muted);">Users</a> / {{ $user->exists ? $user->name : 'New' }}</div>
                <h1 class="page-title">{{ $user->exists ? 'Edit user' : 'New user' }}</h1>
            </div>
            <div class="page-actions">
                <a href="{{ route('users.index') }}" class="btn btn-ghost">Cancel</a>
                <button type="submit" class="btn btn-primary">{{ $user->exists ? 'Save changes' : 'Create user' }}</button>
            </div>
        </div>

        <div class="grid-2" style="align-items:start;">
            <div class="panel">
                <div class="panel-head"><div class="panel-title">Account</div></div>
                <div class="panel-body">
                    <div class="field"><label class="label">Full name</label><input name="name" class="input" required maxlength="120" value="{{ old('name', $user->name) }}">@error('name')<div class="field-error">{{ $message }}</div>@enderror</div>
                    <div class="field" style="margin-top:12px;"><label class="label">Email</label><input name="email" type="email" class="input" required maxlength="160" value="{{ old('email', $user->email) }}">@error('email')<div class="field-error">{{ $message }}</div>@enderror</div>
                    <div class="form-grid" style="margin-top:12px;">
                        <div class="field"><label class="label">Role</label><select name="role" class="select" required>@foreach ($roles as $role)<option value="{{ $role->value }}" @selected(old('role', $user->role?->value) === $role->value)>{{ $role->label() }}</option>@endforeach</select>@error('role')<div class="field-error">{{ $message }}</div>@enderror</div>
                    </div>
                    <label class="check" style="margin-top:12px;"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $user->is_active ?? true))><span>Account is active</span></label>
                </div>
            </div>

            <div class="panel">
                <div class="panel-head"><div class="panel-title">{{ $user->exists ? 'Change password' : 'Password' }}</div></div>
                <div class="panel-body">
                    <div class="field"><label class="label">New password</label><input type="password" name="password" class="input" minlength="8" autocomplete="new-password" @if (! $user->exists) required @endif>@error('password')<div class="field-error">{{ $message }}</div>@enderror<div class="hint">{{ $user->exists ? 'Leave blank to keep the current password.' : 'At least 8 characters.' }}</div></div>
                    <div class="field" style="margin-top:12px;"><label class="label">Confirm password</label><input type="password" name="password_confirmation" class="input" minlength="8" autocomplete="new-password"></div>
                </div>
            </div>
        </div>
    </form>
@endsection
