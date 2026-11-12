<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\ResolvesApiKeyContext;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('submit_session_metrics')]
#[Description('Agent token usage for the session (input/output). Sent once at the very end, after both runs are finished and case suggestions are submitted.')]
class SubmitSessionMetricsTool extends Tool
{
    use ResolvesApiKeyContext;

    public function handle(Request $request): Response
    {
        $session = $this->sessionOrFail($this->requiredInt($request, 'session_id'));

        $data = $request->validate([
            'tokens_in' => ['nullable', 'integer', 'min:0'],
            'tokens_out' => ['nullable', 'integer', 'min:0'],
        ]);

        $update = [];
        foreach (['tokens_in', 'tokens_out'] as $field) {
            if (array_key_exists($field, $data)) {
                $update[$field] = $data[$field];
            }
        }

        if ($update !== []) {
            $session->update($update);
        }

        return Response::json([
            'session_id' => $session->id,
            'tokens_in' => $session->refresh()->tokens_in,
            'tokens_out' => $session->refresh()->tokens_out,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'session_id' => Type::integer()->required(),
            'tokens_in' => Type::integer()->description('Agent input tokens for the whole session (including cache reads)'),
            'tokens_out' => Type::integer()->description('Agent output tokens for the whole session'),
        ];
    }
}
