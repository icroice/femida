<?php

namespace App\Livewire;

use App\Enums\Verdict;
use App\Models\Project;
use App\Models\Run;
use App\Models\Screenshot;
use App\Models\SessionCase;
use App\Models\StepResult;
use App\Models\TestCase;
use App\Models\TestSession;
use App\Services\ScreenshotService;
use App\Services\SessionFlowService;
use App\Support\Like;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The main review screen ("Focus" layout, B2): cases on the left, the
 * before/after pair of the selected step in the center, agent notes and the verdict on the right.
 */
class SessionReview extends FemidaComponent
{
    public Project $project;

    public TestSession $session;

    public ?int $selectedCaseId = null;

    public int $selectedStep = 1;

    public string $verdictComment = '';

    /** The comment form stays collapsed while the case has no saved comment. */
    public bool $commentOpen = false;

    public string $attachQuery = '';

    public function mount(Project $project, TestSession $session): void
    {
        abort_unless($session->project->is($project), 404);
        $this->authorize('view', $session);

        $this->project = $project;
        $this->session = $session;
        $this->selectedCaseId = $this->cases()->first()?->id;
        $this->syncCommentState();
    }

    /**
     * The selected case's comment goes into the textarea; the form is expanded when one exists.
     */
    private function syncCommentState(): void
    {
        $this->verdictComment = (string) $this->current()?->verdict_comment;
        $this->commentOpen = $this->current()?->verdict_comment !== null;
    }

    /** @return Collection<int, SessionCase> */
    public function cases(): Collection
    {
        return SessionCase::query()
            ->where('session_id', $this->session->id)
            ->with(['case', 'stepResults.screenshots', 'verdictBy'])
            ->orderBy('position')
            ->get();
    }

    public function current(): ?SessionCase
    {
        return $this->cases()->firstWhere('id', $this->selectedCaseId);
    }

    /** @return array<int, string> run_id => side */
    public function runSides(): array
    {
        return $this->session->runs->mapWithKeys(
            fn ($run) => [$run->id => $run->side->value]
        )->all();
    }

    /**
     * The case's steps for a run side.
     */
    /** @return Collection<int, StepResult> */
    public function stepsFor(SessionCase $sessionCase, string $side): Collection
    {
        $sides = $this->runSides();

        return $sessionCase->stepResults
            ->filter(fn ($r) => ($sides[$r->run_id] ?? null) === $side)
            ->sortBy('step_index')
            ->values();
    }

    public function screenshotUrl(StepResult $stepResult): ?string
    {
        $shot = $stepResult->screenshots->first();

        return $shot !== null ? route('screenshots.show', [$this->project, $shot]) : null;
    }

    /** Duration of a side's run ("→ 3m 12s" in the header meta line). */
    public function fmtRunDuration(?Run $run): ?string
    {
        if (! $run?->started_at || ! $run?->finished_at) {
            return null;
        }

        // Carbon 3 diff is signed — compute started → finished and clamp at zero
        return $this->fmtSeconds(max(0, (int) round($run->started_at->diffInSeconds($run->finished_at))));
    }

    public function fmtSeconds(?int $seconds): ?string
    {
        if ($seconds === null) {
            return null;
        }

        $s = (int) round($seconds);
        if ($s < 60) {
            return $s.' '.__('sessions.unit_s');
        }
        if ($s < 3600) {
            return intdiv($s, 60).' '.__('sessions.unit_m').' '.($s % 60).' '.__('sessions.unit_s');
        }

        return intdiv($s, 3600).' '.__('sessions.unit_h').' '.intdiv($s % 3600, 60).' '.__('sessions.unit_m');
    }

    /** 1234567 → «1.2M», 45600 → «46k». */
    public function fmtTokens(?int $tokens): string
    {
        if ($tokens === null) {
            return '—';
        }
        if ($tokens >= 1_000_000) {
            return round($tokens / 1_000_000, 1).'M';
        }
        if ($tokens >= 1_000) {
            return round($tokens / 1_000, 1).'k';
        }

        return (string) $tokens;
    }

    public function selectCase(int $caseId, int $step = 1): void
    {
        // leaving a case — persist the typed comment
        $this->persistVerdictComment();

        $this->selectedCaseId = $caseId;
        $this->selectedStep = $step;
        $this->syncCommentState();
    }

    public function moveNextCase(): void
    {
        // "Save and next" also saves a comment without a verdict:
        // silently dropping what was typed is the worst outcome for a reviewer
        $this->persistVerdictComment();

        $ids = $this->cases()->pluck('id')->all();
        $pos = array_search($this->selectedCaseId, $ids, true);
        $next = $ids[$pos + 1] ?? null;

        if ($next !== null) {
            $this->selectCase((int) $next);
        }
    }

    public function movePrevCase(): void
    {
        $ids = $this->cases()->pluck('id')->all();
        $pos = array_search($this->selectedCaseId, $ids, true);
        $prev = $pos > 0 ? $ids[$pos - 1] : null;

        if ($prev !== null) {
            $this->selectCase((int) $prev);
        }
    }

    /**
     * A verdict comment lives without the verdict itself: saved on
     * any departure from the case, so typed text never silently disappears.
     */
    private function persistVerdictComment(): void
    {
        $current = $this->current();
        if ($current !== null && $current->verdict_comment !== $this->verdictComment) {
            $current->update([
                'verdict_comment' => $this->verdictComment !== '' ? $this->verdictComment : null,
            ]);
        }
    }

    /**
     * Active project cases not yet in the session — candidates for
     * manual addition (the agent may have missed some).
     */
    /** @return Collection<int, TestCase> */
    public function attachableCases(): Collection
    {
        $attached = $this->session->cases()->pluck('test_cases.id');

        return $this->project->testCases()
            ->active()
            ->whereNotIn('test_cases.id', $attached)
            ->when($this->attachQuery !== '', function ($query) {
                // SQLite LIKE is not case-insensitive for Cyrillic — search both cases;
                // escape wildcards in the input (Like + ESCAPE)
                $term = trim($this->attachQuery);
                $capitalized = mb_strtoupper(mb_substr($term, 0, 1)).mb_substr($term, 1);
                $query->where(fn ($w) => $w
                    ->whereRaw("title LIKE ? ESCAPE '\\'", [Like::contains($term)])
                    ->orWhereRaw("title LIKE ? ESCAPE '\\'", [Like::contains($capitalized)]));
            })
            ->orderBy('title')
            ->limit(15)
            ->get();
    }

    /**
     * Manually adding a case to the session (appended at the end). Any member
     * of the project; adding to a "passed" session returns it to not_passed —
     * a new case without results breaks the "passed" invariant.
     */
    public function attachCase(int $caseId): void
    {
        $this->authorize('attachCase', $this->session);

        $case = $this->project->testCases()->active()->find($caseId);
        if ($case === null) {
            return;
        }

        // transaction: concurrent additions must not get the same position
        // (max+1 without serialization duplicates) or fail on a unique conflict
        DB::transaction(function () use ($caseId): void {
            if ($this->session->cases()->where('test_cases.id', $caseId)->exists()) {
                return;
            }

            $maxPosition = (int) SessionCase::query()
                ->where('session_id', $this->session->id)
                ->lockForUpdate()
                ->max('position');
            $this->session->cases()->attach($caseId, ['position' => $maxPosition + 1]);
        });

        app(SessionFlowService::class)->reevaluate($this->session->refresh());

        // show the added case right away
        $sessionCase = SessionCase::query()
            ->where('session_id', $this->session->id)
            ->where('case_id', $caseId)
            ->first();
        $this->selectCase($sessionCase->id);
        $this->attachQuery = '';
    }

    /**
     * Removing a case from a session: the detached case loses its position,
     * screenshot files are deleted from disk (no orphans).
     */
    public function removeCase(int $sessionCaseId): void
    {
        $this->authorize('attachCase', $this->session);

        $sessionCase = SessionCase::query()
            ->where('session_id', $this->session->id)
            ->find($sessionCaseId);
        if ($sessionCase === null) {
            return;
        }

        // collect paths before deletion: files leave the disk together with the records (no orphans)
        $paths = Screenshot::query()
            ->whereIn('step_result_id', $sessionCase->stepResults()->pluck('id'))
            ->pluck('path');

        $sessionCase->delete();

        ScreenshotService::deleteFiles($paths);

        // if the selected case was detached — move to the first remaining one
        if ($this->selectedCaseId === $sessionCase->id) {
            $this->selectedCaseId = $this->cases()->first()?->id;
        }
    }

    public function setVerdict(string $verdict): void
    {
        $this->authorize('verdict', $this->session);

        // The string comes from a client Livewire request — Enum::from on
        // garbage throws ValueError (500). Unknown values are silently ignored.
        $verdictEnum = Verdict::tryFrom($verdict);
        if ($verdictEnum === null) {
            return;
        }

        $current = $this->current();
        if ($current === null) {
            return;
        }

        $current->update([
            'verdict' => $verdictEnum,
            'verdict_comment' => $this->verdictComment !== '' ? $this->verdictComment : $current->verdict_comment,
            'verdict_by' => auth()->id(),
            'verdict_at' => now(),
        ]);

        // a reject verdict breaks the "passed" invariant
        app(SessionFlowService::class)->reevaluate($this->session->refresh());

        // move to the next unreviewed case (or just the next one)
        $next = $this->cases()->first(fn ($sc) => $sc->verdict === null && $sc->id !== $current->id)
            ?? $this->cases()->first(fn ($sc) => $sc->id !== $current->id);

        if ($next !== null) {
            $this->selectCase($next->id);
        }
    }

    /**
     * Send a case for re-verification: step results, screenshots
     * and the verdict are cleared; the case stays in the session and shows up as "empty" on
     * the next run. Any member (including testers).
     */
    public function clearCase(int $sessionCaseId): void
    {
        $this->authorize('clearCase', $this->session);

        $sessionCase = SessionCase::query()
            ->where('session_id', $this->session->id)
            ->find($sessionCaseId);
        if ($sessionCase === null) {
            return;
        }

        app(SessionFlowService::class)->clearCase($sessionCase);

        $this->syncCommentState();
    }

    /**
     * A full session reset: all cases + runs, files physically deleted.
     * Case attachments remain. Developer+.
     */
    public function clearSession(): void
    {
        $this->authorize('clearSession', $this->session);

        app(SessionFlowService::class)->clearSession($this->session->refresh());

        $this->selectedCaseId = $this->cases()->first()?->id;
        $this->selectedStep = 1;
        $this->syncCommentState();
    }

    /**
     * A markdown summary for pasting into a tracker. Markup is constant,
     * all texts come from dictionaries.
     */
    public function summary(): string
    {
        $lines = [
            '**'.$this->session->external_ref.' — '.$this->session->title.'**',
            __('sessions.summary_session').' '.route('sessions.show', [$this->project, $this->session]),
            __('sessions.summary_commits').' '.($this->session->only_after
                ? __('sessions.summary_only_after').' '.$this->session->head_commit
                : $this->session->base_commit.' → '.$this->session->head_commit),
            __('sessions.summary_run_by').' '.($this->session->createdBy?->name ?? '—').($this->session->agent ? ' · '.$this->session->agent : ''),
        ];

        $runs = $this->session->runs;
        $metrics = [];
        foreach (['after', 'before'] as $side) {
            if ($dur = $this->fmtRunDuration($runs->firstWhere('side.value', $side))) {
                $metrics[] = $side.' '.__('sessions.summary_'.$side).' '.$dur;
            }
        }
        if ($metrics !== []) {
            $lines[] = __('sessions.summary_time').' '.implode(' · ', $metrics);
        }
        if ($this->session->tokens_in !== null || $this->session->tokens_out !== null) {
            $lines[] = __('sessions.summary_tokens').' ↓ '.$this->fmtTokens($this->session->tokens_in).' · ↑ '.$this->fmtTokens($this->session->tokens_out);
        }

        $lines[] = '';

        foreach ($this->cases() as $sc) {
            $verdict = match ($sc->verdict) {
                Verdict::Pass => '✅ pass',
                Verdict::Fail => '❌ fail',
                Verdict::Blocked => '⚠️ blocked',
                null => '⏳ '.__('sessions.summary_not_checked'),
            };
            $lines[] = sprintf('- %s %s — %s', $verdict, 'TC-'.$sc->case_id, $sc->case?->title);
            if ($sc->verdict_comment) {
                $lines[] = '  > '.str_replace("\n", ' ', $sc->verdict_comment);
            }
        }

        return implode("\n", $lines);
    }

    public function copySummary(): void
    {
        $this->dispatch('summary-copied', summary: $this->summary());
    }

    public function render(): View
    {
        return view('livewire.session-review')
            ->title($this->session->external_ref.' · '.$this->session->title);
    }
}
