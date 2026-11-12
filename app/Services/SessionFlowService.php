<?php

namespace App\Services;

use App\Enums\RunSide;
use App\Enums\RunStatus;
use App\Enums\SessionStatus;
use App\Enums\StepStatus;
use App\Enums\Verdict;
use App\Models\Run;
use App\Models\Screenshot;
use App\Models\SessionCase;
use App\Models\StepResult;
use App\Models\TestSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The single session lifecycle contract — the only implementation
 * for REST, MCP and the UI.
 *
 * The "commit → session → empty cases" model:
 * - a passed step is frozen; holes (failed/error/skipped/empty) are filled;
 * - a new screenshot is validated and saved BEFORE the old one is deleted;
 * - start_run is idempotent on the same commit; a different commit means a new session;
 * - the session status (passed/not_passed) is always derived by reevaluate().
 */
class SessionFlowService
{
    public function __construct(private readonly ScreenshotService $screenshots) {}

    /**
     * Record a step result (+ an optional raw-bytes screenshot).
     *
     * Order: validate bytes (no side effects) → file to disk →
     * update the step and swap screenshot records in one transaction →
     * delete old files after the commit. A failed request changes
     * nothing; the worst failure leaves an orphan file, never a record without a file.
     *
     * @param  string|null  $bytes  screenshot (REST: readUpload, MCP: base64_decode)
     * @return array{step: StepResult, screenshot: ?Screenshot}
     */
    public function submitStep(
        Run $run,
        SessionCase $sessionCase,
        int $stepIndex,
        StepStatus $status,
        ?string $note,
        ?int $elapsedMs,
        ?string $bytes,
        string $fallbackExtension = 'png',
    ): array {
        self::contractError($sessionCase->session_id === $run->session_id,
            'Case is not attached to this session', 'case_id');
        self::contractError($stepIndex >= 1 && $stepIndex <= count($sessionCase->case->steps ?? []),
            'step_index is out of range for the case', 'step_index');

        $existing = StepResult::query()
            ->where('run_id', $run->id)
            ->where('session_case_id', $sessionCase->id)
            ->where('step_index', $stepIndex)
            ->first();
        if ($existing !== null && $existing->status === StepStatus::Passed) {
            self::contractError(false, 'Step is already verified (passed) — clear the case to re-check it', 'step_index');
        }

        // The reason is required — "deliberately skipped" differs from "forgot".
        // The rule lives in the shared layer and applies to REST and MCP alike.
        if (in_array($status, [StepStatus::Skipped, StepStatus::Error], true)
            && ($note === null || trim($note) === '')) {
            self::contractError(false, 'note is required for skipped/error steps (reason)', 'note');
        }

        // evidence is not rewritten after the finish: steps are accepted only
        // into a running run; for re-verification — reset the case/session
        self::contractError($run->status === RunStatus::Running,
            "Run is already finished ({$run->status->value}); steps are no longer accepted — clear the case or session to re-run", 'run_id');

        // validate the new screenshot BEFORE any mutation:
        // null — no screenshot in the request; ''/garbage — 422, not a silent skip
        $meta = $bytes !== null ? $this->screenshots->check($bytes, $fallbackExtension) : null;

        $transaction = DB::transaction(function () use ($run, $sessionCase, $stepIndex, $status, $note, $elapsedMs, $bytes, $meta) {
            $step = StepResult::query()->updateOrCreate(
                [
                    'run_id' => $run->id,
                    'session_case_id' => $sessionCase->id,
                    'step_index' => $stepIndex,
                ],
                [
                    'status' => $status,
                    'note' => $note,
                    'elapsed_ms' => $elapsedMs,
                ],
            );

            $oldPaths = [];
            if ($meta !== null) {
                // write the new file before deleting the old records
                $fileMeta = $this->screenshots->write($bytes, $meta, $run, $sessionCase, $stepIndex);
                $newPath = $fileMeta['path'];
                $oldPaths = $step->screenshots()->pluck('path')->all();
                $step->screenshots()->delete();
                $step->screenshots()->create([...$fileMeta, 'captured_at' => now()]);

                // path-collision guard: the new file is never deleted
                $oldPaths = array_values(array_diff($oldPaths, [$newPath]));
            }

            return [$step, $oldPaths];
        });
        [$step, $oldPaths] = $transaction;

        ScreenshotService::deleteFiles($oldPaths);

        return ['step' => $step, 'screenshot' => $step->screenshots()->first()];
    }

    /**
     * Start a side's run. The same commit on an existing side —
     * an idempotent return of the existing run (backfilling holes);
     * a different commit is an error: the session is tied to a commit; create
     * a new session for a new commit.
     *
     * @return array{run: Run, created: bool}
     */
    public function startRun(TestSession $session, RunSide $side, string $commit): array
    {
        self::contractError(! ($session->only_after && $side === RunSide::Before),
            'before runs are not allowed in only_after sessions', 'side');

        $existing = $session->runs()->where('side', $side->value)->first();
        if ($existing !== null) {
            if ($existing->commit !== $commit) {
                self::contractError(false,
                    "Session is bound to {$side->value} commit {$existing->commit}; create a new session for a different commit", 'commit');
            }

            return ['run' => $existing, 'created' => false];
        }

        $run = $session->runs()->create([
            'side' => $side,
            'commit' => $commit,
            'status' => RunStatus::Running,
            'started_at' => now(),
        ]);

        // a new run breaks the "passed" invariant (defensive: usually preceded
        // by a session reset that already returned not_passed)
        if ($session->status === SessionStatus::Passed) {
            $session->update(['status' => SessionStatus::NotPassed]);
        }

        return ['run' => $run, 'created' => true];
    }

    /**
     * Finish a run and recalculate the session status. The finish is terminal:
     * re-finishing with the same status is idempotent (agent network retries),
     * a status change or wiping error — 422.
     */
    public function finishRun(Run $run, RunStatus $status, ?string $error): Run
    {
        if (in_array($run->status, [RunStatus::Done, RunStatus::Failed], true)) {
            self::contractError($run->status === $status,
                "Run is already finished as {$run->status->value}; terminal status is final", 'status');

            return $run;
        }

        $run->update([
            'status' => $status,
            'error' => $error,
            'finished_at' => in_array($status, [RunStatus::Done, RunStatus::Failed], true) ? now() : null,
        ]);

        $this->reevaluate($run->session);

        return $run;
    }

    /**
     * Reset a case: step results, screenshots (physically), the verdict.
     * The case attachment and the agent suggestion remain. Session → not_passed.
     */
    public function clearCase(SessionCase $sessionCase): void
    {
        $paths = Screenshot::query()
            ->whereIn('step_result_id', $sessionCase->stepResults()->pluck('id'))
            ->pluck('path');

        DB::transaction(function () use ($sessionCase): void {
            Screenshot::query()
                ->whereIn('step_result_id', $sessionCase->stepResults()->pluck('id'))
                ->delete();
            $sessionCase->stepResults()->delete();
            $sessionCase->update([
                'verdict' => null,
                'verdict_comment' => null,
                'verdict_by' => null,
                'verdict_at' => null,
            ]);
        });

        ScreenshotService::deleteFiles($paths);
        $sessionCase->session->update(['status' => SessionStatus::NotPassed]);
    }

    /**
     * Reset a session: everything a case reset does, across all cases + the runs.
     * Case attachments remain — the agent picks them up as empty and runs them.
     */
    public function clearSession(TestSession $session): void
    {
        $stepResultIds = StepResult::query()
            ->whereIn('session_case_id', SessionCase::query()->where('session_id', $session->id)->pluck('id'))
            ->pluck('id');
        $paths = Screenshot::query()->whereIn('step_result_id', $stepResultIds)->pluck('path');

        DB::transaction(function () use ($session, $stepResultIds): void {
            Screenshot::query()->whereIn('step_result_id', $stepResultIds)->delete();
            StepResult::query()->whereIn('id', $stepResultIds)->delete();
            SessionCase::query()->where('session_id', $session->id)->update([
                'verdict' => null,
                'verdict_comment' => null,
                'verdict_by' => null,
                'verdict_at' => null,
            ]);
            $session->runs()->delete();
        });

        ScreenshotService::deleteFiles($paths);
        $session->update(['status' => SessionStatus::NotPassed]);
    }

    /**
     * Recalculate the session status by the "passed" invariant: all expected
     * runs finished + every case covered by steps with status
     * passed on every expected side + no human reject verdicts
     * (fail/blocked). Otherwise — not_passed.
     */
    public function reevaluate(TestSession $session): void
    {
        $expectedSides = $session->only_after
            ? [RunSide::After]
            : [RunSide::Before, RunSide::After];

        $runs = $session->runs()->get();
        $passed = true;

        foreach ($expectedSides as $side) {
            $run = $runs->firstWhere('side', $side);
            if ($run === null || ! in_array($run->status, [RunStatus::Done, RunStatus::Failed], true)) {
                $passed = false;
                break;
            }
        }

        if ($passed && SessionCase::query()
            ->where('session_id', $session->id)
            ->whereIn('verdict', [Verdict::Fail->value, Verdict::Blocked->value])
            ->exists()) {
            $passed = false;
        }

        if ($passed) {
            foreach ($session->cases()->get() as $case) {
                $sessionCase = SessionCase::query()
                    ->where('session_id', $session->id)
                    ->where('case_id', $case->id)
                    ->first();
                $expectedSteps = count($case->steps ?? []);

                foreach ($expectedSides as $side) {
                    $results = StepResult::query()
                        ->where('session_case_id', $sessionCase->id)
                        ->whereHas('run', fn ($q) => $q->where('side', $side->value))
                        ->pluck('status'); // Eloquent pluck applies the cast → StepStatus

                    if ($results->count() < $expectedSteps
                        || $results->contains(fn ($s) => $s !== StepStatus::Passed)) {
                        $passed = false;
                        break 2;
                    }
                }
            }
        }

        $session->update(['status' => $passed ? SessionStatus::Passed : SessionStatus::NotPassed]);
    }

    /**
     * A service-level contract violation. ValidationException, not abort():
     * REST renders it as a 422 with the text, the MCP layer passes the text to the agent
     * (abort() hides behind "internal error"). A shared layer = shared errors.
     */
    private static function contractError(bool $ok, string $message, string $key = 'input'): void
    {
        if (! $ok) {
            throw ValidationException::withMessages([$key => $message]);
        }
    }
}
