<?php

namespace App\Mcp\Tools;

use App\Enums\SessionStatus;
use App\Mcp\Concerns\ResolvesApiKeyContext;
use App\Support\SessionPayload;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('create_session')]
#[Description('Create a task session with a set of cases. base_commit is required unless only_after = true.')]
class CreateSessionTool extends Tool
{
    use ResolvesApiKeyContext;

    public function handle(Request $request): Response
    {
        $data = $request->validate([
            'external_ref' => ['required', 'string', 'max:100'],
            'title' => ['required', 'string', 'max:300'],
            'description' => ['nullable', 'string', 'max:5000'],
            'base_commit' => ['nullable', 'string', 'between:7,40'],
            'head_commit' => ['required', 'string', 'between:7,40'],
            'only_after' => ['nullable', 'boolean'],
            'repo_remote' => ['nullable', 'url', 'max:500'],
            'branch' => ['nullable', 'string', 'max:200'],
            'agent' => ['nullable', 'string', 'max:200'],
            'case_ids' => ['required', 'array', 'min:1'],
            'case_ids.*' => ['integer', 'exists:test_cases,id'],
        ]);

        $onlyAfter = (bool) ($data['only_after'] ?? false);
        if (! $onlyAfter && empty($data['base_commit'])) {
            $this->fail('base_commit is required unless only_after is true.');
        }
        // same as REST (StoreSessionRequest): base_commit in only_after mode is an error, not a silent null
        if ($onlyAfter && ! empty($data['base_commit'])) {
            $this->fail('base_commit must be null in only_after mode.');
        }

        // only active cases go into a session; inactive means "not used"
        $caseIds = array_values(array_unique(array_map('intval', $data['case_ids'])));
        $knownIds = $this->project()->testCases()->active()->whereIn('id', $caseIds)->pluck('id')->all();
        if (count($knownIds) !== count($caseIds)) {
            $this->fail('case_ids must be active cases of the key project');
        }

        $session = $this->project()->testSessions()->create([
            'created_by' => $this->owner()?->id,
            'external_ref' => $data['external_ref'],
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'base_commit' => $onlyAfter ? null : $data['base_commit'],
            'head_commit' => $data['head_commit'],
            'only_after' => $onlyAfter,
            'repo_remote' => $data['repo_remote'] ?? null,
            'branch' => $data['branch'] ?? null,
            'agent' => $data['agent'] ?? null,
            'status' => SessionStatus::NotPassed,
        ]);

        $session->cases()->attach(array_combine(
            $caseIds,
            array_map(fn ($position) => ['position' => $position], range(1, count($caseIds)))
        ));

        return Response::json(['data' => SessionPayload::make($session->refresh())]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'external_ref' => Type::string()->required()->max(100)->description('Task number in the tracker, e.g. PROJ-1284'),
            'title' => Type::string()->required()->max(300),
            'description' => Type::string()->max(5000),
            'base_commit' => Type::string()->min(7)->max(40)->description('Base commit ("before"); null in only_after mode'),
            'head_commit' => Type::string()->required()->min(7)->max(40)->description('The commit under test ("after")'),
            'only_after' => Type::boolean()->description('Quick run without the "before" side'),
            'repo_remote' => Type::string()->max(500),
            'branch' => Type::string()->max(200),
            'agent' => Type::string()->max(200)->description('E.g. Claude Code + Playwright'),
            'case_ids' => Type::array(Type::integer())->required()->min(1),
        ];
    }
}
