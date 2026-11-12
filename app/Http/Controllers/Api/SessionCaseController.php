<?php

namespace App\Http\Controllers\Api;

use App\Enums\Verdict;
use App\Http\Controllers\Api\Concerns\InteractsWithApiKey;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\PatchSessionCaseRequest;
use App\Models\SessionCase;
use App\Models\TestCase;
use App\Models\TestSession;
use App\Services\SessionFlowService;
use Illuminate\Http\JsonResponse;

class SessionCaseController extends Controller
{
    use InteractsWithApiKey;

    /**
     * Agent suggestion and verdict for a case in a session.
     * A verdict via an API key is allowed: the key acts as developer,
     * the verdict author is recorded as the key owner.
     */
    public function update(PatchSessionCaseRequest $request, TestSession $session, TestCase $case): JsonResponse
    {
        $this->ensureProjectScope($request, $session->project);
        $this->ensureProjectScope($request, $case->project);

        $sessionCase = SessionCase::query()
            ->where('session_id', $session->id)
            ->where('case_id', $case->id)
            ->first();
        abort_unless($sessionCase !== null, 404, 'Case is not attached to this session');

        $attributes = [];

        if ($request->filled('agent_suggestion')) {
            $attributes['agent_suggestion'] = Verdict::from($request->string('agent_suggestion')->toString());
        }

        if ($request->exists('agent_notes')) {
            $attributes['agent_notes'] = $request->input('agent_notes');
        }

        if ($request->filled('verdict')) {
            $attributes['verdict'] = Verdict::from($request->string('verdict')->toString());
            $attributes['verdict_by'] = $this->apiUser($request)?->id;
            $attributes['verdict_at'] = now();
        }

        if ($request->exists('verdict_comment')) {
            $attributes['verdict_comment'] = $request->input('verdict_comment');
        }

        $sessionCase->update($attributes);
        $sessionCase->refresh()->load('verdictBy');

        // a reject verdict breaks the "passed" invariant — the status is reevaluated
        if (array_key_exists('verdict', $attributes)) {
            app(SessionFlowService::class)->reevaluate($session->refresh());
        }

        return response()->json([
            'data' => [
                'session_id' => $sessionCase->session_id,
                'case_id' => $sessionCase->case_id,
                'agent_suggestion' => $sessionCase->agent_suggestion?->value,
                'agent_notes' => $sessionCase->agent_notes,
                'verdict' => $sessionCase->verdict?->value,
                'verdict_comment' => $sessionCase->verdict_comment,
                'verdict_by' => $sessionCase->verdictBy
                    ? ['id' => $sessionCase->verdictBy->id, 'name' => $sessionCase->verdictBy->name]
                    : null,
                'verdict_at' => $sessionCase->verdict_at?->toIso8601String(),
            ],
        ]);
    }
}
