<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Für alle schreibenden Routen: Konto muss aktiv sein und das Initialpasswort geändert.
 */
class EnsureActiveAccount
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Bitte anmelden.'], 401);
        }
        if (! $user->is_active) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();

            return response()->json(['message' => 'Dieses Konto ist deaktiviert.'], 401);
        }
        if ($user->must_change_password) {
            return response()->json([
                'message' => 'Bitte zuerst das Passwort ändern.',
                'code' => 'password_change_required',
            ], 403);
        }

        return $next($request);
    }
}
