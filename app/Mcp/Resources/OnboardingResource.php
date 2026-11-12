<?php

namespace App\Mcp\Resources;

use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\MimeType;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Uri;
use Laravel\Mcp\Server\Resource;

// Onboarding template: how a freshly connected project builds its two agent
// skills (femida-write-cases, femida-run-session). The content ships with the server,
// so every project re-reads the current version instead of stale copies.
#[Name('onboarding')]
#[Uri('femida://onboarding')]
#[MimeType('text/markdown')]
#[Description('First-time project onboarding: adaptable skeletons of the two agent skills (femida-write-cases, femida-run-session), the MCP client and Playwright driver scripts, a femida.yaml reference and pitfalls from real runs. Read this before building your project\'s Femida skills.')]
class OnboardingResource extends Resource
{
    public function handle(): Response
    {
        $path = dirname(__DIR__, 3).'/resources/mcp/onboarding.md';

        return Response::text((string) file_get_contents($path));
    }
}
