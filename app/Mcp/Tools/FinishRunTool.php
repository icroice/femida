<?php

namespace App\Mcp\Tools;

use App\Enums\RunStatus;
use App\Mcp\Concerns\ResolvesApiKeyContext;
use App\Models\Run;
use App\Models\SessionCase;
use App\Services\SessionFlowService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('finish_run')]
#[Description('Finish a run: done or failed (with the reason in error). On finish the session status is recalculated: it is marked "passed" automatically once all expected runs are complete, every case is covered by passed steps on all sides, and there are no reject verdicts.')]
class FinishRunTool extends Tool
{
    use ResolvesApiKeyContext;

    public function __construct(private readonly SessionFlowService $flow) {}

    public function handle(Request $request): Response
    {
        $run = $this->runOrFail($this->requiredInt($request, 'run_id'));

        $data = $request->validate([
            'status' => ['required', 'in:done,failed'],
            'error' => ['nullable', 'string', 'max:2000'],
        ]);

        $run = $this->flow->finishRun(
            $run,
            RunStatus::from($data['status']),
            $data['error'] ?? null,
        );

        return Response::json([
            'run_id' => $run->id,
            'status' => $run->status->value,
            'finished_at' => $run->finished_at?->toIso8601String(),
            'session_status' => $run->session->refresh()->status->value,
            'warnings' => [...$this->suggestionWarnings($run), ...$this->stepCoverageWarnings($run)],
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'run_id' => Type::integer()->required(),
            'status' => Type::string()->enum(['done', 'failed'])->required(),
            'error' => Type::string()->max(2000)->description('Failure reason (for failed), e.g. the app failed to start'),
        ];
    }

    /**
     * Session cases without an agent suggestion: a lost set_case_suggestion
     * call must surface before the finish, not during review.
     *
     * @return list<string>
     */
    private function suggestionWarnings(Run $run): array
    {
        $missing = SessionCase::query()
            ->where('session_id', $run->session_id)
            ->whereNull('agent_suggestion')
            ->pluck('case_id');

        if ($missing->isEmpty()) {
            return [];
        }

        return [sprintf(
            '%d of %d cases have no agent suggestion (case_id: %s) — call set_case_suggestion for them',
            $missing->count(),
            $run->session->cases()->count(),
            $missing->implode(', ')
        )];
    }

    /**
     * Cases where fewer steps were submitted than the case defines: collapsing
     * a case into a single step is a typical agent mistake. Warnings are
     * visible to the agent before the finish.
     *
     * @return list<string>
     */
    private function stepCoverageWarnings(Run $run): array
    {
        /** @var Collection<int, SessionCase&object{submitted: int}> $rows */
        $rows = SessionCase::query()
            ->where('session_id', $run->session_id)
            ->with('case')
            ->withCount(['stepResults as submitted' => fn ($q) => $q->where('run_id', $run->id)])
            ->get();

        $incomplete = $rows->filter(fn (SessionCase $sc) => $sc->submitted < count($sc->case->steps ?? []));

        if ($incomplete->isEmpty()) {
            return [];
        }

        return [sprintf(
            '%d of %d cases have fewer submitted steps than the case defines (case_id: %s) — submit_step for every step',
            $incomplete->count(),
            $run->session->cases()->count(),
            $incomplete->pluck('case_id')->implode(', ')
        )];
    }
}
