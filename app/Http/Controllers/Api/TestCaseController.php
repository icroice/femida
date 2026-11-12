<?php

namespace App\Http\Controllers\Api;

use App\Enums\CasePriority;
use App\Enums\CaseStatus;
use App\Http\Controllers\Api\Concerns\InteractsWithApiKey;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreCaseRequest;
use App\Http\Resources\TestCaseResource;
use App\Models\TestCase;
use App\Support\Like;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TestCaseController extends Controller
{
    use InteractsWithApiKey;

    public function index(Request $request): AnonymousResourceCollection
    {
        // the library defaults to active cases only (same as list_cases),
        // status=all — an explicit request for the full list
        // per_page: one pagination contract — an integer 1..100, else 422
        $request->validate([
            'status' => ['nullable', 'in:active,inactive,all'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $status = $request->filled('status') ? $request->string('status')->toString() : 'active';

        $cases = $this->apiProject($request)
            ->testCases()
            ->when($request->filled('q'), function ($query) use ($request) {
                // SQLite LIKE is not case-insensitive for Cyrillic — search both cases of the first letter
                $term = trim($request->string('q')->toString());
                $capitalized = mb_strtoupper(mb_substr($term, 0, 1)).mb_substr($term, 1);

                $query->where(fn ($w) => $w
                    ->whereRaw("title LIKE ? ESCAPE '\\'", [Like::contains($term)])
                    ->orWhereRaw("title LIKE ? ESCAPE '\\'", [Like::contains($capitalized)]));
            })
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($request->filled('folder'), function ($query) use ($request) {
                // exact folder, or a folder with all its subfolders (prefix)
                $folder = trim((string) $request->input('folder'));
                $query->where(fn ($w) => $w
                    ->where('folder', $folder)
                    ->orWhereRaw("folder LIKE ? ESCAPE '\\'", [Like::prefix($folder)]));
            })
            ->orderBy('title')
            ->paginate((int) $request->integer('per_page', 50));

        return TestCaseResource::collection($cases);
    }

    public function store(StoreCaseRequest $request): JsonResponse
    {
        $case = $this->apiProject($request)->testCases()->create([
            'title' => $request->string('title')->toString(),
            'folder' => TestCase::normalizeFolder($request->input('folder')),
            'preconditions' => $request->input('preconditions'),
            'steps' => $request->array('steps'),
            'priority' => $request->enum('priority', CasePriority::class) ?? CasePriority::P2,
            'status' => $request->enum('status', CaseStatus::class) ?? CaseStatus::Active,
        ]);

        return (new TestCaseResource($case))->response()->setStatusCode(201);
    }

    public function show(Request $request, TestCase $case): TestCaseResource
    {
        $this->ensureProjectScope($request, $case->project);

        return new TestCaseResource($case);
    }

    public function update(StoreCaseRequest $request, TestCase $case): TestCaseResource
    {
        $this->ensureProjectScope($request, $case->project);

        $data = $request->validated();
        if (array_key_exists('folder', $data)) {
            $data['folder'] = TestCase::normalizeFolder($data['folder']);
        }

        $case->update($data);

        return new TestCaseResource($case->refresh());
    }

    /**
     * Deleting a case = moving it to inactive (cases inside sessions are protected from deletion).
     */
    public function destroy(Request $request, TestCase $case): TestCaseResource
    {
        $this->ensureProjectScope($request, $case->project);

        $case->forceFill(['status' => CaseStatus::Inactive])->save();

        return new TestCaseResource($case->refresh());
    }
}
