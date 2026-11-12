<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Until the first user exists, everything redirects to the setup wizard.
 * Once the admin is created, /setup is closed forever.
 */
class EnsureSetupCompleted
{
    public function handle(Request $request, Closure $next): Response
    {
        $hasUsers = User::query()->exists();
        $isSetupRoute = $request->routeIs('setup.*');

        if (! $hasUsers && ! $isSetupRoute) {
            return redirect()->route('setup.index');
        }

        if ($hasUsers && $isSetupRoute) {
            return redirect()->route('login');
        }

        return $next($request);
    }
}
