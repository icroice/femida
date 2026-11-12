<?php

namespace App\Mcp\Tools;

use App\Enums\Verdict;
use App\Mcp\Concerns\ResolvesApiKeyContext;
use App\Services\SessionFlowService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('set_verdict')]
#[Description('Record a human verdict for a case. Use only when the human has explicitly stated their decision; the author is the key owner.')]
class SetVerdictTool extends Tool
{
    use ResolvesApiKeyContext;

    public function handle(Request $request): Response
    {
        [$session, $sessionCase] = $this->sessionCaseOrFail(
            $this->requiredInt($request, 'session_id'),
            $this->requiredInt($request, 'case_id')
        );

        $data = $request->validate([
            'verdict' => ['required', 'in:pass,fail,blocked'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);

        $sessionCase->update([
            'verdict' => Verdict::from($data['verdict']),
            'verdict_comment' => $data['comment'] ?? $sessionCase->verdict_comment,
            'verdict_by' => $this->owner()?->id,
            'verdict_at' => now(),
        ]);

        $sessionCase->refresh()->load('verdictBy');

        // a reject verdict breaks the "passed" invariant — reevaluate the status
        app(SessionFlowService::class)->reevaluate($sessionCase->session->refresh());

        return Response::json([
            'session_id' => $session->id,
            'case_id' => $sessionCase->case_id,
            'verdict' => $sessionCase->verdict->value,
            'verdict_by' => $sessionCase->verdictBy?->name,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'session_id' => Type::integer()->required(),
            'case_id' => Type::integer()->required(),
            'verdict' => Type::string()->enum(['pass', 'fail', 'blocked'])->required(),
            'comment' => Type::string()->max(2000),
        ];
    }
}
