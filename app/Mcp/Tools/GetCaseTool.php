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

#[Name('get_case')]
#[Description('A full test case: preconditions and "action → expectation" steps.')]
class GetCaseTool extends Tool
{
    use ResolvesApiKeyContext;

    public function handle(Request $request): Response
    {
        $case = $this->caseOrFail($this->requiredInt($request, 'case_id'));

        return Response::json([
            'id' => $case->id,
            'title' => $case->title,
            'folder' => $case->folder,
            'preconditions' => $case->preconditions,
            'steps' => $case->steps,
            'priority' => $case->priority->value,
            'status' => $case->status->value,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'case_id' => Type::integer()->required()->description('Case ID'),
        ];
    }
}
