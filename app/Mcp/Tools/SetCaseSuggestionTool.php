<?php

namespace App\Mcp\Tools;

use App\Enums\Verdict;
use App\Mcp\Concerns\ResolvesApiKeyContext;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('set_case_suggestion')]
#[Description('Agent suggestion for a case (pass/fail/blocked) + notes on before/after differences. The verdict is set by a human.')]
class SetCaseSuggestionTool extends Tool
{
    use ResolvesApiKeyContext;

    public function handle(Request $request): Response
    {
        [$session, $sessionCase] = $this->sessionCaseOrFail(
            $this->requiredInt($request, 'session_id'),
            $this->requiredInt($request, 'case_id')
        );

        $data = $request->validate([
            'suggestion' => ['required', 'in:pass,fail,blocked'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $sessionCase->update([
            'agent_suggestion' => Verdict::from($data['suggestion']),
            'agent_notes' => $data['notes'] ?? $sessionCase->agent_notes,
        ]);

        return Response::json([
            'session_id' => $session->id,
            'case_id' => $sessionCase->case_id,
            'agent_suggestion' => $sessionCase->refresh()->agent_suggestion->value,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'session_id' => Type::integer()->required(),
            'case_id' => Type::integer()->required(),
            'suggestion' => Type::string()->enum(['pass', 'fail', 'blocked'])->required(),
            'notes' => Type::string()->max(5000)->description('How "after" differs from "before", step numbers with the problem'),
        ];
    }
}
