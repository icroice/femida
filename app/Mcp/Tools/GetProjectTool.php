<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\ResolvesApiKeyContext;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('get_project')]
#[Description('Validate the project key: project name, key owner. Start every exchange with this call.')]
class GetProjectTool extends Tool
{
    use ResolvesApiKeyContext;

    public function handle(Request $request): Response
    {
        $project = $this->project();

        return Response::json([
            'project' => ['id' => $project->id, 'name' => $project->name, 'slug' => $project->slug],
            'key' => ['name' => $this->apiKey()->name, 'prefix' => $this->apiKey()->token_prefix],
            'owner' => $this->owner() !== null
                ? ['id' => $this->owner()->id, 'name' => $this->owner()->name]
                : null,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
