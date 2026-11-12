<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\ResolvesApiKeyContext;
use App\Support\SessionPayload;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('get_session')]
#[Description('The whole session: cases, suggestions, verdicts and steps with before/after screenshot pairs. Per-case coverage (expected/filled/passed per side) and pending_case_ids — which cases to run: a repeat run takes only what is still unfilled.')]
class GetSessionTool extends Tool
{
    use ResolvesApiKeyContext;

    public function handle(Request $request): Response
    {
        $session = $this->sessionOrFail($this->requiredInt($request, 'session_id'));

        return Response::json(['data' => SessionPayload::make($session)]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'session_id' => Type::integer()->required(),
        ];
    }
}
