<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\InteractsWithApiKey;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MeController extends Controller
{
    use InteractsWithApiKey;

    /**
     * Key check and project info.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $key = $this->apiKey($request);
        $project = $this->apiProject($request);

        return response()->json([
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
                'slug' => $project->slug,
            ],
            'key' => [
                'name' => $key->name,
                'prefix' => $key->token_prefix,
            ],
            'owner' => $this->apiUser($request) !== null
                ? ['id' => $this->apiUser($request)->id, 'name' => $this->apiUser($request)->name]
                : null,
        ]);
    }
}
