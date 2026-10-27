<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('manage-users');

        $query = User::query();

        if ($search = trim((string) $request->input('q'))) {
            $query->where(function ($builder) use ($search) {
                $builder->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($role = $request->input('role')) {
            $query->where('role', $role);
        }

        if ($request->has('active') && $request->input('active') !== '') {
            $query->where('is_active', $request->boolean('active'));
        }

        $users = $query->orderBy('name')->paginate(20)->withQueryString();
        $stats = [
            'total' => User::query()->count(),
            'active' => User::query()->active()->count(),
            'admins' => User::query()->where('role', UserRole::Admin->value)->count(),
        ];

        return view('users.index', [
            'users' => $users,
            'roles' => UserRole::cases(),
            'stats' => $stats,
        ]);
    }

    public function create(): View
    {
        $this->authorize('manage-users');

        return view('users.form', [
            'user' => new User(['role' => UserRole::Cashier, 'is_active' => true]),
            'roles' => UserRole::cases(),
        ]);
    }

    public function store(Request $request, AuditService $audit): RedirectResponse
    {
        $this->authorize('manage-users');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:160', 'unique:users,email'],
            'role' => ['required', Rule::enum(UserRole::class)],
            'password' => ['required', 'confirmed', Password::min(8)],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data['is_active'] = $request->boolean('is_active');
        $user = User::query()->create($data);

        $audit->log('user.created', 'Created user '.$user->name.' ('.$user->role->label().').', $user);

        return redirect()->route('users.index')->with('success', 'User created.');
    }

    public function edit(User $user): View
    {
        $this->authorize('manage-users');

        return view('users.form', [
            'user' => $user,
            'roles' => UserRole::cases(),
        ]);
    }

    public function update(Request $request, User $user, AuditService $audit): RedirectResponse
    {
        $this->authorize('manage-users');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:160', Rule::unique('users', 'email')->ignore($user->id)],
            'role' => ['required', Rule::enum(UserRole::class)],
            'password' => ['nullable', 'confirmed', Password::min(8)],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data['is_active'] = $request->boolean('is_active');

        if ($user->isAdmin() && (! $data['is_active'] || $data['role'] !== UserRole::Admin->value) && $this->activeAdminCount() <= 1) {
            return back()->withErrors(['role' => 'The last active administrator cannot be demoted or deactivated.'])->withInput();
        }

        if ($password = ($data['password'] ?? null)) {
            $data['password'] = $password;
        } else {
            unset($data['password']);
        }

        $user->update($data);
        $audit->log('user.updated', 'Updated user '.$user->name.'.', $user, ['role' => $user->role->value, 'is_active' => $user->is_active]);

        return redirect()->route('users.index')->with('success', 'User updated.');
    }

    public function resetPassword(Request $request, User $user, AuditService $audit): RedirectResponse
    {
        $this->authorize('manage-users');

        $data = $request->validate([
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $user->update(['password' => $data['password']]);
        $audit->log('user.password_reset', 'Reset password for '.$user->name.'.', $user);

        return back()->with('success', 'Password updated.');
    }

    public function toggle(Request $request, User $user, AuditService $audit): RedirectResponse
    {
        $this->authorize('manage-users');

        if ($user->is($request->user()) && $user->is_active) {
            return back()->with('error', 'You cannot deactivate your own account.');
        }

        if ($user->isAdmin() && $user->is_active && $this->activeAdminCount() <= 1) {
            return back()->with('error', 'The last active administrator cannot be deactivated.');
        }

        $user->forceFill(['is_active' => ! $user->is_active])->save();
        $audit->log('user.status', ($user->is_active ? 'Activated ' : 'Deactivated ').$user->name.'.', $user);

        return back()->with('success', $user->name.' is now '.($user->is_active ? 'active' : 'inactive').'.');
    }

    public function destroy(Request $request, User $user, AuditService $audit): RedirectResponse
    {
        $this->authorize('manage-users');

        if ($user->is($request->user())) {
            return back()->with('error', 'You cannot delete your own account.');
        }

        if ($user->isAdmin() && $this->activeAdminCount() <= 1) {
            return back()->with('error', 'The last active administrator cannot be deleted.');
        }

        $audit->log('user.deleted', 'Deleted user '.$user->name.'.', null, ['email' => $user->email]);
        $user->delete();

        return redirect()->route('users.index')->with('success', 'User removed.');
    }

    protected function activeAdminCount(): int
    {
        return User::query()->where('role', UserRole::Admin->value)->where('is_active', true)->count();
    }
}
