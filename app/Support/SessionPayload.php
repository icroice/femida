<?php

namespace App\Support;

use App\Models\SessionCase;
use App\Models\StepResult;
use App\Models\TestSession;
use Illuminate\Support\Collection;

/**
 * Aggregated session representation for the API/UI: cases with per-side steps
 * (before/after pairs are grouped by (session_case_id, step_index)).
 */
class SessionPayload
{
    /**
     * @return array<string, mixed>
     */
    public static function make(TestSession $session): array
    {
        $session->loadMissing(['runs', 'createdBy']);

        $runSides = $session->runs->mapWithKeys(
            fn ($run) => [$run->id => $run->side->value]
        );

        $sessionCases = SessionCase::query()
            ->where('session_id', $session->id)
            ->with(['case', 'stepResults.screenshots', 'verdictBy'])
            ->orderBy('position')
            ->get();

        return [
            'id' => $session->id,
            'external_ref' => $session->external_ref,
            'title' => $session->title,
            'description' => $session->description,
            'base_commit' => $session->base_commit,
            'head_commit' => $session->head_commit,
            'only_after' => $session->only_after,
            'repo_remote' => $session->repo_remote,
            'branch' => $session->branch,
            'status' => $session->status->value,
            // derived flow state: awaiting_run | running | awaiting_review
            'flow_state' => $session->flowState(),
            'agent' => $session->agent,
            'tokens_in' => $session->tokens_in,
            'tokens_out' => $session->tokens_out,
            'created_by' => $session->createdBy
                ? ['id' => $session->createdBy->id, 'name' => $session->createdBy->name]
                : null,
            'created_at' => $session->created_at?->toIso8601String(),

            'runs' => $session->runs
                ->values()
                ->map(fn ($run) => [
                    'id' => $run->id,
                    'side' => $run->side->value,
                    'commit' => $run->commit,
                    'status' => $run->status->value,
                    'error' => $run->error,
                    'started_at' => $run->started_at?->toIso8601String(),
                    'finished_at' => $run->finished_at?->toIso8601String(),
                ]),

            // completeness of agent suggestions: a skipped set_case_suggestion
            // must not hide behind agent_suggestion: null deep inside the cases
            'suggestions' => [
                'total' => $sessionCases->count(),
                'set' => $sessionCases->where('agent_suggestion', '!==', null)->count(),
                'missing' => $sessionCases->where('agent_suggestion', '===', null)->count(),
            ],

            'cases' => $sessionCases->map(fn (SessionCase $sc) => [
                'case_id' => $sc->case_id,
                'title' => $sc->case?->title,
                'position' => $sc->position,
                'agent_suggestion' => $sc->agent_suggestion?->value,
                'agent_notes' => $sc->agent_notes,
                'suggestion_missing' => $sc->agent_suggestion === null,
                'verdict' => $sc->verdict?->value,
                'verdict_comment' => $sc->verdict_comment,
                'verdict_by' => $sc->verdictBy
                    ? ['id' => $sc->verdictBy->id, 'name' => $sc->verdictBy->name]
                    : null,
                'verdict_at' => $sc->verdict_at?->toIso8601String(),
                // per-side coverage: "pick up the empty cases" as a first-class
                'coverage' => self::coverage($sc, $runSides),
                'steps' => [
                    'before' => $sc->stepResults
                        ->filter(fn ($r) => ($runSides[$r->run_id] ?? null) === 'before')
                        ->sortBy('step_index')
                        ->values()
                        ->map(fn ($r) => self::step($r)),
                    'after' => $sc->stepResults
                        ->filter(fn ($r) => ($runSides[$r->run_id] ?? null) === 'after')
                        ->sortBy('step_index')
                        ->values()
                        ->map(fn ($r) => self::step($r)),
                ],
            ]),

            // cases the agent must run: incomplete coverage or
            // non-passed steps on at least one expected side
            'pending_case_ids' => $sessionCases
                ->filter(fn (SessionCase $sc) => self::pending($sc, $session, $runSides))
                ->pluck('case_id')
                ->values()
                ->all(),
        ];
    }

    /**
     * Per-side case coverage: how many steps the case expects and how many
     * are recorded/passed on each side.
     *
     * @param  Collection<int, string>  $runSides
     * @return array<string, array<string, int>>
     */
    private static function coverage(SessionCase $sc, Collection $runSides): array
    {
        $expected = count($sc->case->steps ?? []);

        $bySide = fn (string $side) => $sc->stepResults
            ->filter(fn ($r) => ($runSides[$r->run_id] ?? null) === $side);

        return [
            'before' => [
                'expected' => $expected,
                'filled' => $bySide('before')->count(),
                'passed' => $bySide('before')->where('status.value', 'passed')->count(),
            ],
            'after' => [
                'expected' => $expected,
                'filled' => $bySide('after')->count(),
                'passed' => $bySide('after')->where('status.value', 'passed')->count(),
            ],
        ];
    }

    /**
     * A case is "empty" for the agent when some expected side has incomplete
     * coverage or a non-passed step.
     *
     * @param  Collection<int, string>  $runSides
     */
    private static function pending(SessionCase $sc, TestSession $session, Collection $runSides): bool
    {
        $expectedSteps = count($sc->case->steps ?? []);
        $sides = $session->only_after ? ['after'] : ['before', 'after'];

        foreach ($sides as $side) {
            $results = $sc->stepResults
                ->filter(fn ($r) => ($runSides[$r->run_id] ?? null) === $side);

            if ($results->count() < $expectedSteps
                || $results->contains(fn ($r) => $r->status->value !== 'passed')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    private static function step(StepResult $result): array
    {
        $shot = $result->screenshots->first();

        return [
            'step_index' => $result->step_index,
            'status' => $result->status->value,
            'note' => $result->note,
            'elapsed_ms' => $result->elapsed_ms,
            'screenshot' => $shot !== null
                ? [
                    'id' => $shot->id,
                    'width' => $shot->width,
                    'height' => $shot->height,
                    'bytes' => $shot->bytes,
                    'page_url' => $shot->page_url,
                    'captured_at' => $shot->captured_at?->toIso8601String(),
                ]
                : null,
        ];
    }
}
