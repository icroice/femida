<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\ResolvesApiKeyContext;
use App\Models\SessionCase;
use App\Services\SessionFlowService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\DB;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('add_cases_to_session')]
#[Description('Add cases to an already created session (e.g. agreed additions). Active cases only; the session status is recalculated.')]
class AddCasesToSessionTool extends Tool
{
    use ResolvesApiKeyContext;

    public function __construct(private readonly SessionFlowService $flow) {}

    public function handle(Request $request): Response
    {
        $session = $this->sessionOrFail($this->requiredInt($request, 'session_id'));

        $data = $request->validate([
            'case_ids' => ['required', 'array', 'min:1'],
            'case_ids.*' => ['integer', 'exists:test_cases,id'],
        ]);

        $caseIds = array_values(array_unique(array_map('intval', $data['case_ids'])));
        // same as the UI and create_session: active cases only
        $knownIds = $this->project()->testCases()->active()->whereIn('id', $caseIds)->pluck('id')->all();
        if (count($knownIds) !== count($caseIds)) {
            $this->fail('case_ids must be active cases of the key project');
        }

        // transaction: concurrent additions must not get the same position
        // (max+1 without serialization would duplicate); syncWithoutDetaching is idempotent
        DB::transaction(function () use ($session, $caseIds): void {
            $maxPosition = (int) SessionCase::query()
                ->where('session_id', $session->id)
                ->lockForUpdate()
                ->max('position');

            $attach = [];
            foreach ($caseIds as $i => $caseId) {
                $attach[$caseId] = ['position' => $maxPosition + $i + 1];
            }
            $session->cases()->syncWithoutDetaching($attach);
        });

        // a newly added case without steps breaks the "passed" invariant —
        // reevaluate, like the UI does on manual addition
        $this->flow->reevaluate($session);

        return Response::json(['session_id' => $session->id, 'case_ids' => $caseIds]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'session_id' => Type::integer()->required(),
            'case_ids' => Type::array(Type::integer())->required()->min(1),
        ];
    }
}
