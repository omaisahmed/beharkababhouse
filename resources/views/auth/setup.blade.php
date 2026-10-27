@extends('layouts.auth')

@section('title', 'First-time setup')

@section('content')
    <div style="display:flex;align-items:center;gap:8px;margin-bottom:12px;">
        <span class="badge badge-orange"><x-icon name="sparkles" class="w-3 h-3" /> First run</span>
        <span class="tiny muted">No data leaves this computer</span>
    </div>

    <h1 class="auth-title">Set up your register</h1>
    <p class="auth-sub">Create the administrator account for this installation. You can add cashiers and managers later.</p>

    <form method="POST" action="{{ route('setup.store') }}" class="stack" style="gap:13px;">
        @csrf

        <div class="field">
            <label class="label" for="restaurant_name">Restaurant name</label>
            <input id="restaurant_name" name="restaurant_name" class="input" value="{{ old('restaurant_name', 'Behar Kabab House') }}" required maxlength="120">
            @error('restaurant_name') <div class="error-text">{{ $message }}</div> @enderror
        </div>

        <div class="field">
            <label class="label" for="name">Administrator name</label>
            <input id="name" name="name" class="input" value="{{ old('name') }}" required maxlength="120" placeholder="Full name">
            @error('name') <div class="error-text">{{ $message }}</div> @enderror
        </div>

        <div class="field">
            <label class="label" for="email">Email address</label>
            <input id="email" name="email" type="email" class="input" value="{{ old('email') }}" required autocomplete="username" placeholder="admin@restaurant.local">
            <div class="hint">Used to sign in. A local address is fine; email is not sent anywhere.</div>
            @error('email') <div class="error-text">{{ $message }}</div> @enderror
        </div>

        <div class="field">
            <label class="label" for="password">Password</label>
            <input id="password" name="password" type="password" class="input" required autocomplete="new-password" minlength="10">
            @error('password') <div class="error-text">{{ $message }}</div> @enderror
        </div>

        <div class="field">
            <label class="label" for="password_confirmation">Confirm password</label>
            <input id="password_confirmation" name="password_confirmation" type="password" class="input" required autocomplete="new-password" minlength="10">
        </div>

        <div class="hint">Use at least 10 characters. Store this password somewhere safe — there is no remote password recovery.</div>

        <button type="submit" class="btn btn-primary btn-lg" style="width:100%;">
            <x-icon name="check" class="w-4 h-4" /> Create register
        </button>
    </form>
@endsection
