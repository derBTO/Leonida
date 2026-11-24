<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /** Aktueller Benutzer oder null. */
    public function me(Request $request): JsonResponse
    {
        return response()->json(['user' => $request->user()?->toPublicArray()]);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'username' => ['required', 'string', 'max:50'],
            'password' => ['required', 'string', 'max:200'],
        ]);
        [$perUser, $perUserDecay] = config('leonida.login_limits.per_user_ip');
        [$perIp, $perIpDecay] = config('leonida.login_limits.per_ip');
        $userKey = 'login:'.mb_strtolower($data['username']).'|'.$request->ip();
        $ipKey = 'login-ip:'.$request->ip();
        foreach ([[$userKey, $perUser], [$ipKey, $perIp]] as [$key, $max]) {
            if (RateLimiter::tooManyAttempts($key, $max)) {
                $seconds = RateLimiter::availableIn($key);
                throw ValidationException::withMessages([
                    'username' => "Zu viele Anmeldeversuche. Bitte in $seconds Sekunden erneut versuchen.",
                ])->status(429);
            }
        }

        $user = User::findByUsername($data['username']);
        $valid = $user && $user->is_active && Hash::check($data['password'], $user->password);
        if (! $valid) {
            RateLimiter::hit($userKey, $perUserDecay);
            RateLimiter::hit($ipKey, $perIpDecay);
            logger()->notice('Fehlgeschlagene Anmeldung', ['username' => $data['username'], 'ip' => $request->ip()]);
            throw ValidationException::withMessages(['username' => 'Benutzername oder Passwort ist falsch.']);
        }

        RateLimiter::clear($userKey);
        Auth::login($user);
        $request->session()->regenerate();
        if (Hash::needsRehash($user->password)) {
            $user->password = $data['password'];
        }
        $user->last_login_at = now();
        $user->save();
        AuditLogger::log('login', 'user', $user->id, $user->username);

        return response()->json(['user' => $user->toPublicArray()]);
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['user' => null]);
    }

    public function changePassword(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'confirmed', 'max:200', Password::min(config('leonida.password_min_length'))],
        ]);
        if (! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages(['current_password' => 'Das aktuelle Passwort stimmt nicht.']);
        }
        if (Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['password' => 'Das neue Passwort muss sich vom alten unterscheiden.']);
        }
        $user->password = $data['password'];
        $user->must_change_password = false;
        $user->password_changed_at = now();
        $user->save();
        $request->session()->regenerate();
        AuditLogger::log('password', 'user', $user->id, $user->username);

        return response()->json(['user' => $user->toPublicArray()]);
    }
}
