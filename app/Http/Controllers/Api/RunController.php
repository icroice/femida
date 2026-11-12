<?php

namespace App\Http\Controllers\Api;

use App\Enums\RunSide;
use App\Enums\RunStatus;
use App\Http\Controllers\Api\Concerns\InteractsWithApiKey;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreRunRequest;
use App\Http\Requests\Api\UpdateRunRequest;
use App\Models\Run;
use App\Models\TestSession;
use App\Services\SessionFlowService;
use Illuminate\Http\JsonResponse;

class RunController extends Controller
{
    use InteractsWithApiKey;

    public function __construct(private readonly SessionFlowService $flow) {}

    /**
     * Start a run. Idempotent on the same commit (backfilling holes);
     * a different commit → 422 "create a new session".
     */
    public function store(StoreRunRequest $request, TestSession $session): JsonResponse
    {
        $this->ensureProjectScope($request, $session->project);

        ['run' => $run, 'created' => $created] = $this->flow->startRun(
            $session,
            RunSide::from($request->string('side')->toString()),
            $request->string('commit')->toString(),
        );

        return response()->json(['data' => $this->present($run->refresh())], $created ? 201 : 200);
    }

    public function update(UpdateRunRequest $request, Run $run): JsonResponse
    {
        $this->ensureProjectScope($request, $run->session->project);

        $run = $this->flow->finishRun(
            $run,
            RunStatus::from($request->string('status')->toString()),
            $request->input('error'),
        );

        return response()->json(['data' => $this->present($run->refresh())]);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Run $run): array
    {
        return [
            'id' => $run->id,
            'session_id' => $run->session_id,
            'side' => $run->side->value,
            'commit' => $run->commit,
            'status' => $run->status->value,
            'error' => $run->error,
            'started_at' => $run->started_at?->toIso8601String(),
            'finished_at' => $run->finished_at?->toIso8601String(),
        ];
    }
}
