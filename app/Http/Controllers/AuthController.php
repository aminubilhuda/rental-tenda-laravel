<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate(['username' => ['required', 'string'], 'password' => ['required', 'string']]);
        $key = Str::lower($credentials['username']).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()->withErrors(['username' => 'Terlalu banyak percobaan. Coba lagi dalam satu menit.'])->onlyInput('username');
        }
        $user = User::query()->where('username', $credentials['username'])->first();
        if (! $user || ! $user->is_active || ! Auth::attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::hit($key, 60);

            return back()->withErrors(['username' => 'Username atau password tidak valid.'])->onlyInput('username');
        }
        RateLimiter::clear($key);
        $request->session()->regenerate();
        $user->update(['last_login_at' => now()]);
        ActivityLog::create(['user_id' => $user->id, 'action' => 'login', 'module' => 'auth', 'description' => "{$user->name} masuk.", 'ip_address' => $request->ip(), 'user_agent' => $request->userAgent(), 'created_at' => now()]);

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request)
    {
        ActivityLog::create(['user_id' => Auth::id(), 'action' => 'logout', 'module' => 'auth', 'description' => (Auth::user()?->name ?? 'User').' keluar.', 'ip_address' => $request->ip(), 'user_agent' => $request->userAgent(), 'created_at' => now()]);
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    public function password(Request $request)
    {
        $validated = $request->validate(['current_password' => ['required', 'current_password'], 'password' => ['required', 'confirmed', 'min:8']]);
        $request->user()->update(['password' => $validated['password']]);

        return back()->with('success', 'Password berhasil diperbarui.');
    }
}
