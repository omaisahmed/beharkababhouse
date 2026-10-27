@extends('layouts.auth')

@section('title', 'Sign in')

@section('content')
    <h1 class="auth-title">Welcome back</h1>
    <p class="auth-sub">Sign in to open the counter. Use your assigned staff account.</p>

    <form method="POST" action="{{ route('login.store') }}" class="stack" style="gap:14px;">
        @csrf

        <div class="field">
            <label class="label" for="email">Email address</label>
            <input id="email" name="email" type="email" class="input" value="{{ old('email') }}" required autofocus autocomplete="username" placeholder="you@restaurant.local">
            @error('email') <div class="error-text">{{ $message }}</div> @enderror
        </div>

        <div class="field">
            <label class="label" for="password">Password</label>
            <input id="password" name="password" type="password" class="input" required autocomplete="current-password" placeholder="Enter your password">
            @error('password') <div class="error-text">{{ $message }}</div> @enderror
        </div>

        <label class="check">
            <input type="checkbox" name="remember" value="1" @checked(old('remember'))>
            <span>Keep me signed in on this workstation</span>
        </label>

        <button type="submit" class="btn btn-primary btn-lg" style="width:100%;">
            <x-icon name="lock" class="w-4 h-4" /> Sign in
        </button>
    </form>

    <div style="margin-top:18px;display:flex;gap:8px;align-items:flex-start;color:var(--muted);font-size:11.5px;line-height:1.5;">
        <x-icon name="shield" class="w-4 h-4 shrink-0" style="color:var(--success);" />
        <span>All sales, stock movements, and account actions are recorded in the local audit log.</span>
    </div>
@endsection
