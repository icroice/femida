<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Project Bearer key: fem_…, sha256 hash in the DB.
 * The key grants developer-level rights within a single project.
 */
class AuthenticateApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();
        abort_unless($token !== null && $token !== '', 401, 'API key is required');

        $key = ApiKey::findByToken($token);
        // one message for revoked and access-lost keys — the reason is not disclosed
        abort_unless($key !== null && $key->isUsable(), 401, 'Invalid or revoked API key');

        // last_used is updated at most once a minute to avoid a DB write per request
        if (! $key->last_used_at || $key->last_used_at->lt(now()->subMinute())) {
            $key->forceFill(['last_used_at' => now()])->saveQuietly();
        }

        $request->attributes->set('apiKey', $key);
        $request->attributes->set('apiProject', $key->project);
        $request->attributes->set('apiUser', $key->owner);

        return $next($request);
    }
}
