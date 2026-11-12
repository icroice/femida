<?php

namespace App\Mcp\Tools;

use App\Enums\RunSide;
use App\Mcp\Concerns\ResolvesApiKeyContext;
use App\Services\SessionFlowService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('start_run')]
#[Description('Start a session run on one side (after = head, before = base). Same commit — idempotent: the existing run is returned (backfilling holes left after a case reset). A different commit is an error: create a new session for a new commit.')]
class StartRunTool extends Tool
{
    use ResolvesApiKeyContext;

    public function __construct(private readonly SessionFlowService $flow) {}

    public function handle(Request $request): Response
    {
        $session = $this->sessionOrFail($this->requiredInt($request, 'session_id'));

        $data = $request->validate([
            'side' => ['required', 'in:before,after'],
            'commit' => ['required', 'string', 'between:7,40'],
        ]);

        // contract violations surface as a ValidationException from
        // SessionFlowService and reach the agent as-is; no abort() here
        ['run' => $run, 'created' => $created] = $this->flow->startRun(
            $session,
            RunSide::from($data['side']),
            $data['commit'],
        );

        return Response::json([
            'run_id' => $run->id,
            'session_id' => $session->id,
            'side' => $run->side->value,
            'commit' => $run->commit,
            'status' => $run->status->value,
            'created' => $created,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'session_id' => Type::integer()->required(),
            'side' => Type::string()->enum(['before', 'after'])->required(),
            'commit' => Type::string()->required()->min(7)->max(40)->description('Commit the side runs on (must match the existing run of that side)'),
        ];
    }
}
