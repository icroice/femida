<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Dev-only pages (the styleguide): available in local and testing
 * environments, a plain 404 anywhere else.
 */
class EnsureLocalEnv
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(in_array(app()->environment(), ['local', 'testing'], true), 404);

        return $next($request);
    }
}
