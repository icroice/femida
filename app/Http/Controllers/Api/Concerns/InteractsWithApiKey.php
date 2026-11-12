<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Models\ApiKey;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\Request;

trait InteractsWithApiKey
{
    protected function apiKey(Request $request): ApiKey
    {
        return $request->attributes->get('apiKey');
    }

    protected function apiProject(Request $request): Project
    {
        return $request->attributes->get('apiProject');
    }

    /**
     * The key owner: attribution of runs and verdicts.
     */
    protected function apiUser(Request $request): ?User
    {
        return $request->attributes->get('apiUser');
    }

    /**
     * Foreign entities hide behind a 404 so their existence is not disclosed.
     */
    protected function ensureProjectScope(Request $request, Project $project): void
    {
        abort_unless($project->is($this->apiProject($request)), 404);
    }
}
