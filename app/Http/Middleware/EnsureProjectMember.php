<?php

namespace App\Http\Middleware;

use App\Models\Project;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Project context: non-members get a 404 (existence is not disclosed).
 */
class EnsureProjectMember
{
    public function handle(Request $request, Closure $next): Response
    {
        $project = $request->route('project');

        abort_unless($project instanceof Project && $request->user()?->isMember($project), 404);

        return $next($request);
    }
}
