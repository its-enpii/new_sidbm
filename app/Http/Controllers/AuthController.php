<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class AuthController
{
    public function showLogin(): Response
    {
        return Inertia::render('Auth/Login');
    }

    public function login(LoginRequest $request): RedirectResponse
    {
        $identifier = (string) $request->validated('identifier');
        $password = (string) $request->validated('password');
        $remember = (bool) $request->boolean('remember');

        $user = User::query()
            ->where(function ($query) use ($identifier): void {
                $query->where('username', $identifier)
                    ->orWhere('email', $identifier);
            })
            ->where('status', 'active')
            ->first();

        if ($user === null || ! Hash::check($password, (string) $user->password)) {
            throw ValidationException::withMessages([
                'identifier' => 'Kredensial yang diberikan tidak valid.',
            ]);
        }

        if ($user->tenant_id === null) {
            $membership = $user->memberships()->where('status', 'active')->first();
            $user->forceFill(['tenant_id' => $membership?->tenant_id])->save();
        }

        Auth::login($user, $remember);
        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->save();

        $home = $user->is_superadmin === true ? route('admin.dashboard') : route('dashboard');

        return redirect()->intended($home);
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
