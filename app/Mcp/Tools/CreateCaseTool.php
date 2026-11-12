<?php

namespace App\Mcp\Tools;

use App\Enums\CasePriority;
use App\Enums\CaseStatus;
use App\Mcp\Concerns\ResolvesApiKeyContext;
use App\Models\TestCase;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('create_case')]
#[Description('Create a test case in the project library (usually at the task spec stage).')]
class CreateCaseTool extends Tool
{
    use ResolvesApiKeyContext;

    public function handle(Request $request): Response
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:500'],
            'folder' => TestCase::FOLDER_RULES,
            'preconditions' => ['nullable', 'string', 'max:5000'],
            'steps' => ['required', 'array', 'min:1', 'max:50'],
            'steps.*.action' => ['required', 'string', 'max:2000'],
            'steps.*.expected' => ['required', 'string', 'max:2000'],
            'priority' => ['nullable', 'in:P0,P1,P2,P3'],
            'status' => ['nullable', 'in:active,inactive'],
        ]);

        $case = $this->project()->testCases()->create([
            'title' => $data['title'],
            'folder' => TestCase::normalizeFolder($data['folder'] ?? null),
            'preconditions' => $data['preconditions'] ?? null,
            'steps' => $data['steps'],
            'priority' => CasePriority::tryFrom($data['priority'] ?? '') ?? CasePriority::P2,
            'status' => CaseStatus::tryFrom($data['status'] ?? '') ?? CaseStatus::Active,
        ]);

        return Response::json(['id' => $case->id, 'title' => $case->title, 'folder' => $case->folder, 'status' => $case->status->value]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'title' => Type::string()->required()->max(500),
            'folder' => Type::string()->description('Folder up to two levels separated by /, e.g. "Checkout/Payment"; empty — no folder'),
            'preconditions' => Type::string()->max(5000),
            'steps' => Type::array(
                Type::object([
                    'action' => Type::string()->required()->max(2000)->description('What the tester does'),
                    'expected' => Type::string()->required()->max(2000)->description('Expected result'),
                ])->required()
            )->required()->min(1)->max(50),
            'priority' => Type::string()->enum(['P0', 'P1', 'P2', 'P3']),
            'status' => Type::string()->enum(['active', 'inactive'])->description('Defaults to active'),
        ];
    }
}
