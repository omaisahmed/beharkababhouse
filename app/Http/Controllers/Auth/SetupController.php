<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\InstallationService;
use App\Services\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class SetupController extends Controller
{
    public function create(): View
    {
        return view('auth.setup');
    }

    public function store(Request $request, InstallationService $installer, SettingsService $settings): RedirectResponse
    {
        $data = $request->validate([
            'restaurant_name' => ['required', 'string', 'max:120'],
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:190', 'unique:users,email'],
            'password' => ['required', 'string', 'min:10', 'max:200', 'confirmed'],
        ]);

        $user = $installer->createAdministrator([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
        ]);

        $settings->setMany([
            'restaurant.name' => trim($data['restaurant_name']),
            'app.version' => config('nativephp.version', '0.1.0'),
        ], $user);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard')->with('success', 'Register configured. Welcome to '.$data['restaurant_name'].'.');
    }
}
