<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\ResolvesApiKeyContext;
use App\Models\TestCase;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('update_case')]
#[Description('Update an existing case (pass only the fields being changed).')]
class UpdateCaseTool extends Tool
{
    use ResolvesApiKeyContext;

    public function handle(Request $request): Response
    {
        $case = $this->caseOrFail($this->requiredInt($request, 'case_id'));

        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:500'],
            'folder' => ['sometimes', ...TestCase::FOLDER_RULES],
            'preconditions' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'steps' => ['sometimes', 'array', 'min:1', 'max:50'],
            'steps.*.action' => ['required', 'string', 'max:2000'],
            'steps.*.expected' => ['required', 'string', 'max:2000'],
            'priority' => ['sometimes', 'in:P0,P1,P2,P3'],
            'status' => ['sometimes', 'in:active,inactive'],
        ]);

        if (array_key_exists('folder', $data)) {
            $data['folder'] = TestCase::normalizeFolder($data['folder']);
        }

        $case->update(array_filter(
            $data,
            fn ($value, $key) => $key === 'preconditions' || $key === 'folder' || $value !== null,
            ARRAY_FILTER_USE_BOTH
        ));

        return Response::json(['id' => $case->id, 'title' => $case->title, 'status' => $case->refresh()->status->value]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'case_id' => Type::integer()->required(),
            'title' => Type::string()->max(500),
            'folder' => Type::string()->description('New folder path (up to two levels separated by /); null — remove from folders'),
            'preconditions' => Type::string()->max(5000),
            'steps' => Type::array(
                Type::object([
                    'action' => Type::string()->required()->max(2000),
                    'expected' => Type::string()->required()->max(2000),
                ])->required()
            )->min(1)->max(50),
            'priority' => Type::string()->enum(['P0', 'P1', 'P2', 'P3']),
            'status' => Type::string()->enum(['active', 'inactive']),
        ];
    }
}
