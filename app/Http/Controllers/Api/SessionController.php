<?php

namespace App\Http\Controllers\Api;

use App\Enums\SessionStatus;
use App\Http\Controllers\Api\Concerns\InteractsWithApiKey;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreSessionRequest;
use App\Http\Resources\TestSessionResource;
use App\Models\TestSession;
use App\Support\SessionPayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SessionController extends Controller
{
    use InteractsWithApiKey;

    public function index(Request $request): AnonymousResourceCollection
    {
        // per_page: one pagination contract — an integer 1..100, else 422
        $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'status' => ['nullable', 'in:passed,not_passed'],
        ]);

        $sessions = $this->apiProject($request)
            ->testSessions()
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()))
            ->when($request->filled('external_ref'), fn ($q) => $q->where('external_ref', $request->string('external_ref')->toString()))
            ->latest()
            ->paginate((int) $request->integer('per_page', 30));

        // The standard Laravel pagination format (data + links + meta), same as /cases
        return TestSessionResource::collection($sessions);
    }

    public function store(StoreSessionRequest $request): JsonResponse
    {
        $project = $this->apiProject($request);
        $caseIds = array_values(array_unique((array) $request->input('case_ids')));

        // cases must belong to the key's project and be active
        $knownIds = $project->testCases()->active()->whereIn('id', $caseIds)->pluck('id')->all();
        abort_if(count($knownIds) !== count($caseIds), 422, 'case_ids must be active cases of the key project');

        $session = TestSession::query()->create([
            'project_id' => $project->id,
            'created_by' => $this->apiUser($request)?->id,
            'external_ref' => $request->string('external_ref')->toString(),
            'title' => $request->string('title')->toString(),
            'description' => $request->input('description'),
            'base_commit' => $request->boolean('only_after') ? null : $request->string('base_commit')->toString(),
            'head_commit' => $request->string('head_commit')->toString(),
            'only_after' => $request->boolean('only_after'),
            'repo_remote' => $request->input('repo_remote'),
            'branch' => $request->input('branch'),
            'agent' => $request->input('agent'),
            'status' => SessionStatus::NotPassed,
        ]);

        $session->cases()->attach(array_combine(
            $caseIds,
            array_map(fn ($position) => ['position' => $position], range(1, count($caseIds)))
        ));

        return response()->json(['data' => SessionPayload::make($session->refresh())], 201);
    }

    public function show(Request $request, TestSession $session): JsonResponse
    {
        $this->ensureProjectScope($request, $session->project);

        return response()->json(['data' => SessionPayload::make($session)]);
    }

    /**
     * The session status is not set manually: it is derived from runs and
     * coverage (SessionFlowService). Only metrics here.
     */
    public function update(Request $request, TestSession $session): JsonResponse
    {
        $this->ensureProjectScope($request, $session->project);

        $data = $request->validate([
            'tokens_in' => ['sometimes', 'integer', 'min:0'],
            'tokens_out' => ['sometimes', 'integer', 'min:0'],
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

        return response()->json(['data' => SessionPayload::make($session->refresh())]);
    }
}
