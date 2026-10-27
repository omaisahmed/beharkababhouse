@extends('layouts.app')

@section('title', 'Change password')

@section('content')
    <div class="page-head">
        <div>
            <h1 class="page-title">Change password</h1>
            <div class="page-subtitle">Update the password used to sign in to this workstation.</div>
        </div>
    </div>

    <div style="max-width:560px;">
        <form method="POST" action="{{ route('password.update') }}" class="panel">
            @csrf
            @method('PUT')

            <div class="form-section">
                <div class="form-grid" style="grid-template-columns:1fr;">
                    <div class="field">
                        <label class="label" for="current_password">Current password</label>
                        <input id="current_password" name="current_password" type="password" class="input" required autocomplete="current-password">
                        @error('current_password') <div class="error-text">{{ $message }}</div> @enderror
                    </div>
                    <div class="field">
                        <label class="label" for="password">New password</label>
                        <input id="password" name="password" type="password" class="input" required autocomplete="new-password" minlength="10">
                        @error('password') <div class="error-text">{{ $message }}</div> @enderror
                    </div>
                    <div class="field">
                        <label class="label" for="password_confirmation">Confirm new password</label>
                        <input id="password_confirmation" name="password_confirmation" type="password" class="input" required autocomplete="new-password" minlength="10">
                    </div>
                </div>
            </div>

            <div class="form-section" style="display:flex;justify-content:flex-end;gap:8px;">
                <a href="{{ route('dashboard') }}" class="btn">Cancel</a>
                <button type="submit" class="btn btn-primary"><x-icon name="save" class="w-4 h-4" /> Update password</button>
            </div>
        </form>
    </div>
@endsection
