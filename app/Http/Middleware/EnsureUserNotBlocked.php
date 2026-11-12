<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocking revokes access immediately: the session and the remember cookie
 * are torn down on any following request, including recaller auto-login.
 */
class EnsureUserNotBlocked
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && $user->isBlocked()) {
            Auth::guard('web')->logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson()) {
                abort(403, __('auth.blocked'));
            }

            return redirect()->route('login')->with('status', __('auth.blocked'));
        }

        return $next($request);
    }
}
