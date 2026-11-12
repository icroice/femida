<?php

namespace Tests\Feature;

use App\Enums\ProjectRole;
use App\Enums\RunSide;
use App\Enums\RunStatus;
use App\Enums\SessionStatus;
use App\Enums\StepStatus;
use App\Exceptions\ScreenshotStorageException;
use App\Livewire\SessionReview;
use App\Livewire\SessionsBrowser;
use App\Models\ApiKey;
use App\Models\Project;
use App\Models\Screenshot;
use App\Models\SessionCase;
use App\Models\StepResult;
use App\Models\TestCase as TestCaseModel;
use App\Models\TestSession;
use App\Models\User;
use App\Services\ScreenshotService;
use App\Services\SessionFlowService;
use App\Support\SessionPayload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * "Commit → session → empty cases".
 * Frozen passed steps, retake order, idempotent start_run,
 * auto-"passed", case/session resets, payload coverage, branch rail.
 */
class SessionFlowTest extends TestCase
{
    use RefreshDatabase;

    private Project $project;

    private User $developer;

    private User $tester;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('screenshots');

        $this->developer = User::factory()->create();
        $this->tester = User::factory()->create();
        $this->project = Project::factory()->create(['name' => 'Register']);
        $this->project->users()->attach($this->developer->id, ['role' => ProjectRole::Developer->value]);
        $this->project->users()->attach($this->tester->id, ['role' => ProjectRole::Tester->value]);
    }

    private function greenSession(): TestSession
    {
        $case = TestCaseModel::factory()->create([
            'project_id' => $this->project->id,
            'steps' => [['action' => 'Step', 'expected' => 'Ok']],
        ]);
        $session = TestSession::factory()->create([
            'project_id' => $this->project->id,
            'base_commit' => 'aaaaaaabbbbbbb',
            'head_commit' => 'cccccccddddddd',
        ]);
        $session->cases()->attach($case->id, ['position' => 1]);
        $sessionCase = SessionCase::query()->where('session_id', $session->id)->first();

        $flow = app(SessionFlowService::class);
        foreach ([RunSide::Before, RunSide::After] as $side) {
            $commit = $side === RunSide::Before ? 'aaaaaaabbbbbbb' : 'cccccccddddddd';
            ['run' => $run] = $flow->startRun($session, $side, $commit);
            $flow->submitStep($run, $sessionCase, 1, StepStatus::Passed, null, null,
                file_get_contents(__DIR__.'/fixtures/tiny.png') ?: '');
            $flow->finishRun($run, RunStatus::Done, null);
        }

        return $session->refresh();
    }

    /*
     * ── Frozen passed and retake order ───────────────────────────────────
     */

    public function test_passed_step_is_frozen(): void
    {
        $session = $this->greenSession();
        $this->assertSame(SessionStatus::Passed, $session->status);

        $flow = app(SessionFlowService::class);
        $run = $session->runs->firstWhere('side.value', 'after');
        $sessionCase = SessionCase::query()->where('session_id', $session->id)->first();

        try {
            $flow->submitStep($run, $sessionCase, 1, StepStatus::Failed, 'retake', null, null);
            $this->fail('a passed step must be frozen');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('already verified', implode(' ', $e->validator->errors()->all()));
        }

        // evidence untouched: the step stayed passed with its screenshot
        $step = $run->stepResults()->first();
        $this->assertSame(StepStatus::Passed, $step->status);
        $this->assertNotNull($step->screenshots()->first());
    }

    public function test_failed_step_retake_with_invalid_screenshot_keeps_old_evidence(): void
    {
        $case = TestCaseModel::factory()->create([
            'project_id' => $this->project->id,
            'steps' => [['action' => 'Step', 'expected' => 'Ok']],
        ]);
        $session = TestSession::factory()->create([
            'project_id' => $this->project->id,
            'only_after' => true,
            'head_commit' => 'cccccccddddddd',
        ]);
        $session->cases()->attach($case->id, ['position' => 1]);
        $sessionCase = SessionCase::query()->where('session_id', $session->id)->first();

        $flow = app(SessionFlowService::class);
        ['run' => $run] = $flow->startRun($session, RunSide::After, 'cccccccddddddd');

        // first submission: failed with a valid screenshot — that is the old evidence
        $valid = file_get_contents(__DIR__.'/fixtures/tiny.png');
        $flow->submitStep($run, $sessionCase, 1, StepStatus::Failed, 'did not recalculate', null, $valid);
        $oldShot = $run->stepResults()->first()->screenshots()->first();
        $this->assertNotNull($oldShot);
        Storage::disk('screenshots')->assertExists($oldShot->path);

        // a retake with a corrupt file (not an image) is rejected; the old shot is intact.
        // a contract violation is a ValidationException (the text
        // reaches REST and MCP alike), not abort()
        try {
            $flow->submitStep($run, $sessionCase, 1, StepStatus::Failed, 'retake', null, 'definitely not an image');
            $this->fail('a corrupt screenshot must be rejected');
        } catch (ValidationException $e) {
            $this->assertSame(
                'Unsupported screenshot format (PNG/JPEG/WebP expected)',
                $e->getMessage()
            );
        }

        $step = $run->stepResults()->first();
        $this->assertSame($oldShot->id, $step->screenshots()->first()?->id, 'the old screenshot record must survive');
        $this->assertSame(1, Screenshot::query()->where('step_result_id', $step->id)->count());
        Storage::disk('screenshots')->assertExists($oldShot->path);

        // a retake with a file over 5 MB is rejected too, also without losses
        // (a ValidationException, not abort-422)
        try {
            $flow->submitStep($run, $sessionCase, 1, StepStatus::Failed, 'retake', null, str_repeat('x', ScreenshotServiceLimit::MAX + 1));
            $this->fail('an oversized screenshot must be rejected');
        } catch (ValidationException $e) {
            $this->assertSame('Screenshot exceeds 5 MB limit', $e->getMessage());
        }
        Storage::disk('screenshots')->assertExists($oldShot->path);

        // a successful retake with a valid file: the old file is gone, the new one in place
        $flow->submitStep($run, $sessionCase, 1, StepStatus::Passed, 'flaky: ok on the second try', null, $valid);
        $newShot = $run->stepResults()->first()->screenshots()->first();
        $this->assertNotSame($oldShot->id, $newShot->id);
        Storage::disk('screenshots')->assertMissing($oldShot->path);
        Storage::disk('screenshots')->assertExists($newShot->path);
    }

    /**
     * The same mechanics via the real REST entry: multipart with a corrupt file
     * must not destroy the previous screenshot.
     */
    public function test_rest_retake_invalid_upload_keeps_old_evidence(): void
    {
        [$key, $token] = ApiKey::issue($this->project, $this->developer, 'dev-rest');

        $case = TestCaseModel::factory()->create([
            'project_id' => $this->project->id,
            'steps' => [['action' => 'Step', 'expected' => 'Ok']],
        ]);
        $session = TestSession::factory()->create([
            'project_id' => $this->project->id,
            'only_after' => true,
            'head_commit' => 'cccccccddddddd',
        ]);
        $session->cases()->attach($case->id, ['position' => 1]);
        $sessionCase = SessionCase::query()->where('session_id', $session->id)->first();

        $flow = app(SessionFlowService::class);
        ['run' => $run] = $flow->startRun($session, RunSide::After, 'cccccccddddddd');
        $flow->submitStep($run, $sessionCase, 1, StepStatus::Failed, 'failed', null,
            file_get_contents(__DIR__.'/fixtures/tiny.png'));
        $oldShot = $run->stepResults()->first()->screenshots()->first();

        $bad = UploadedFile::fake()->create('report.pdf', 100, 'application/pdf');
        $this->post("/api/v1/runs/{$run->id}/steps", [
            'case_id' => $case->id,
            'step_index' => 1,
            'status' => 'failed',
            'note' => 'retake',
            'screenshot' => $bad,
        ], ['Authorization' => "Bearer {$token}"])
            ->assertStatus(422);

        Storage::disk('screenshots')->assertExists($oldShot->path);
        $this->assertSame(1, Screenshot::query()->where('step_result_id', $run->stepResults()->first()->id)->count());
    }

    /*
     * ── Idempotent start_run ────────────────────────────────────────────
     */

    public function test_start_run_same_commit_idempotent_different_commit_rejected(): void
    {
        $case = TestCaseModel::factory()->create(['project_id' => $this->project->id]);
        $session = TestSession::factory()->create([
            'project_id' => $this->project->id,
            'only_after' => true,
            'head_commit' => 'cccccccddddddd',
        ]);
        $session->cases()->attach($case->id, ['position' => 1]);

        $flow = app(SessionFlowService::class);
        ['run' => $first, 'created' => $created] = $flow->startRun($session, RunSide::After, 'cccccccddddddd');
        $this->assertTrue($created);

        // the same commit again — the same run, no second run
        ['run' => $again, 'created' => $created2] = $flow->startRun($session, RunSide::After, 'cccccccddddddd');
        $this->assertFalse($created2);
        $this->assertSame($first->id, $again->id);

        // a different commit — the session is tied to its commit, create a new one
        try {
            $flow->startRun($session, RunSide::After, '11111112222222');
            $this->fail('a different commit must be rejected');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('create a new session', implode(' ', $e->validator->errors()->all()));
        }
    }

    /*
     * ── Auto-"passed" ───────────────────────────────────────────────────
     */

    public function test_fail_verdict_or_skipped_step_blocks_passed(): void
    {
        // a green session…
        $session = $this->greenSession();
        $this->assertSame(SessionStatus::Passed, $session->status);

        // …a human sets a fail verdict — the session is no longer passed
        $sessionCase = SessionCase::query()->where('session_id', $session->id)->first();
        $sessionCase->update(['verdict' => 'fail', 'verdict_by' => $this->tester->id, 'verdict_at' => now()]);
        app(SessionFlowService::class)->reevaluate($session->refresh());
        $this->assertSame(SessionStatus::NotPassed, $session->refresh()->status);

        // a session with a skipped step does not become "passed" on finishes
        $case = TestCaseModel::factory()->create([
            'project_id' => $this->project->id,
            'steps' => [['action' => 'Step', 'expected' => 'Ok']],
        ]);
        $session2 = TestSession::factory()->create([
            'project_id' => $this->project->id,
            'only_after' => true,
            'head_commit' => 'cccccccddddddd',
        ]);
        $session2->cases()->attach($case->id, ['position' => 1]);
        $sc = SessionCase::query()->where('session_id', $session2->id)->first();

        $flow = app(SessionFlowService::class);
        ['run' => $run] = $flow->startRun($session2, RunSide::After, 'cccccccddddddd');
        $flow->submitStep($run, $sc, 1, StepStatus::Skipped, 'the external gateway is unavailable', null, null);
        $flow->finishRun($run, RunStatus::Done, null);

        $this->assertSame(SessionStatus::NotPassed, $session2->refresh()->status);
    }

    /*
     * ── Case and session resets ─────────────────────────────────────────
     */

    public function test_clear_case_by_tester_wipes_evidence_keeps_attachment(): void
    {
        $session = $this->greenSession();
        $sessionCase = SessionCase::query()->where('session_id', $session->id)->first();
        $sessionCase->update(['verdict' => 'pass', 'verdict_by' => $this->tester->id, 'verdict_at' => now()]);
        $paths = Screenshot::query()->pluck('path');
        $this->assertCount(2, $paths);

        Livewire::actingAs($this->tester)
            ->test(SessionReview::class, ['project' => $this->project, 'session' => $session->refresh()])
            ->call('clearCase', $sessionCase->id)
            ->assertHasNoErrors();

        // files physically deleted, no steps, verdict cleared, attachment alive
        foreach ($paths as $path) {
            Storage::disk('screenshots')->assertMissing($path);
        }
        $this->assertSame(0, $sessionCase->stepResults()->count());
        $this->assertNull($sessionCase->refresh()->verdict);
        $this->assertNotNull(SessionCase::query()->find($sessionCase->id));
        $this->assertSame(SessionStatus::NotPassed, $session->refresh()->status);

        // the "empty" case is visible to the agent and gets refilled
        $payload = SessionPayload::make($session->refresh());
        $this->assertSame([$sessionCase->case_id], $payload['pending_case_ids']);
    }

    public function test_clear_session_requires_developer_and_wipes_runs(): void
    {
        $session = $this->greenSession();
        $caseId = $session->cases()->first()->id;
        $paths = Screenshot::query()->pluck('path');

        // a tester is not allowed the full reset
        Livewire::actingAs($this->tester)
            ->test(SessionReview::class, ['project' => $this->project, 'session' => $session->refresh()])
            ->call('clearSession');
        // authorize throws an exception Livewire does not swallow — assert nothing was wiped
        $this->assertSame(2, $session->runs()->count());

        // developer+ wipes everything: runs, steps, files; case attachments remain
        Livewire::actingAs($this->developer)
            ->test(SessionReview::class, ['project' => $this->project, 'session' => $session->refresh()])
            ->call('clearSession')
            ->assertHasNoErrors();

        $this->assertSame(0, $session->runs()->count());
        $this->assertSame(0, StepResult::query()->whereHas('run', fn ($q) => $q->where('runs.session_id', 0))->count());
        $this->assertDatabaseHas('session_case', ['session_id' => $session->id, 'case_id' => $caseId]);
        foreach ($paths as $path) {
            Storage::disk('screenshots')->assertMissing($path);
        }
        $this->assertSame(SessionStatus::NotPassed, $session->refresh()->status);

        // after the reset the agent starts a run again with the same commit
        ['run' => $run] = app(SessionFlowService::class)->startRun($session->refresh(), RunSide::After, 'cccccccddddddd');
        $this->assertSame('running', $run->status->value);
    }

    /*
     * ── Payload: coverage and pending ───────────────────────────────────
     */

    public function test_payload_coverage_and_pending_case_ids(): void
    {
        $case = TestCaseModel::factory()->create([
            'project_id' => $this->project->id,
            'steps' => [['action' => '1', 'expected' => 'ok'], ['action' => '2', 'expected' => 'ok']],
        ]);
        $session = TestSession::factory()->create([
            'project_id' => $this->project->id,
            'only_after' => true,
            'head_commit' => 'cccccccddddddd',
        ]);
        $session->cases()->attach($case->id, ['position' => 1]);
        $sessionCase = SessionCase::query()->where('session_id', $session->id)->first();

        $flow = app(SessionFlowService::class);
        ['run' => $run] = $flow->startRun($session, RunSide::After, 'cccccccddddddd');
        $flow->submitStep($run, $sessionCase, 1, StepStatus::Passed, null, null, null);

        $payload = SessionPayload::make($session->refresh());
        $coverage = $payload['cases'][0]['coverage'];

        // after: 1 of 2 steps filled → the case is pending, visible as "empty"
        $this->assertSame(2, $coverage['after']['expected']);
        $this->assertSame(1, $coverage['after']['filled']);
        $this->assertSame(1, $coverage['after']['passed']);
        $this->assertSame(0, $coverage['before']['filled'], 'the before side is not expected in only_after, but the block is symmetric');
        $this->assertSame([$case->id], $payload['pending_case_ids']);
        $this->assertSame('running', $payload['flow_state']);
    }

    /*
     * ── Branch rail in the sessions browser ─────────────────────────────
     */

    public function test_sessions_browser_branch_rail(): void
    {
        TestSession::factory()->create(['project_id' => $this->project->id, 'title' => 'Old fix', 'branch' => 'bug/sum']);
        TestSession::factory()->create(['project_id' => $this->project->id, 'title' => 'Fresh retest', 'branch' => 'bug/sum']);
        TestSession::factory()->create(['project_id' => $this->project->id, 'title' => 'Another branch', 'branch' => 'feature/payments']);
        // the title must not match the "No branch" bin label in the rail
        TestSession::factory()->create(['project_id' => $this->project->id, 'title' => 'Lonely session', 'branch' => null]);

        $component = Livewire::actingAs($this->developer)
            ->test(SessionsBrowser::class, ['project' => $this->project])
            ->assertSee('bug/sum')
            ->assertSee('feature/payments')
            ->assertSee(__('sessions.without_branch'));

        // branch selection by index (C1: branch names are not interpolated into JS)
        $branches = $component->instance()->branches();
        $this->assertCount(3, $branches);

        $sumIndex = array_search('bug/sum', array_column($branches, 'branch'), true);
        $component->call('selectBranch', $sumIndex)
            ->assertSee('Old fix')
            ->assertSee('Fresh retest')
            ->assertDontSee('Another branch')
            ->assertDontSee('Lonely session');

        $component->call('selectAllBranches')
            ->assertSee('Another branch')
            ->assertSee('Lonely session');
    }

    /*
     * ── M1 leftovers: finish finality and step intake (11/26) ──────────
     */

    public function test_finish_run_terminal_idempotent_same_status(): void
    {
        $session = $this->greenSession(); // both runs done, session passed
        $run = $session->runs->first();
        $flow = app(SessionFlowService::class);

        // re-finishing with the same status — an idempotent return (agent retries)
        $again = $flow->finishRun($run, RunStatus::Done, null);
        $this->assertSame($run->id, $again->id);
        $this->assertSame(RunStatus::Done, $again->refresh()->status);

        // changing the status of a finished run — 422
        try {
            $flow->finishRun($run->refresh(), RunStatus::Failed, 'a late reason');
            $this->fail('the terminal status is final');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('already finished', implode(' ', $e->validator->errors()->all()));
        }

        $this->assertSame(RunStatus::Done, $run->refresh()->status);
        $this->assertNull($run->error);
    }

    public function test_steps_rejected_in_finished_run(): void
    {
        $case = TestCaseModel::factory()->create([
            'project_id' => $this->project->id,
            'steps' => [['action' => 'Step', 'expected' => 'Ok']],
        ]);
        $session = TestSession::factory()->create([
            'project_id' => $this->project->id,
            'only_after' => true,
            'head_commit' => 'cccccccddddddd',
        ]);
        $session->cases()->attach($case->id, ['position' => 1]);
        $sessionCase = SessionCase::query()->where('session_id', $session->id)->first();

        $flow = app(SessionFlowService::class);
        ['run' => $run] = $flow->startRun($session, RunSide::After, 'cccccccddddddd');
        $flow->finishRun($run, RunStatus::Failed, 'the app did not start');

        // the run is finished — a hole can no longer be filled, evidence is final
        try {
            $flow->submitStep($run, $sessionCase, 1, StepStatus::Passed, null, null, null);
            $this->fail('steps are not accepted into a finished run');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('no longer accepted', implode(' ', $e->validator->errors()->all()));
        }

        $this->assertSame(0, $run->stepResults()->count());
    }

    /** An empty/blank skipped note is rejected in the shared layer, not a silent skip. */
    public function test_note_required_for_skipped_in_service(): void
    {
        $case = TestCaseModel::factory()->create([
            'project_id' => $this->project->id,
            'steps' => [['action' => 'Step', 'expected' => 'Ok']],
        ]);
        $session = TestSession::factory()->create([
            'project_id' => $this->project->id,
            'only_after' => true,
            'head_commit' => 'cccccccddddddd',
        ]);
        $session->cases()->attach($case->id, ['position' => 1]);
        $sessionCase = SessionCase::query()->where('session_id', $session->id)->first();

        $flow = app(SessionFlowService::class);
        ['run' => $run] = $flow->startRun($session, RunSide::After, 'cccccccddddddd');

        foreach ([null, '   '] as $emptyNote) {
            try {
                $flow->submitStep($run, $sessionCase, 1, StepStatus::Skipped, $emptyNote, null, null);
                $this->fail('skipped without a reason is not accepted');
            } catch (ValidationException $e) {
                $this->assertStringContainsString('note is required', implode(' ', $e->validator->errors()->all()));
            }
        }

        $flow->submitStep($run, $sessionCase, 1, StepStatus::Skipped, 'the gateway is unavailable', null, null);
        $this->assertSame(StepStatus::Skipped, $run->stepResults()->first()->status);
    }

    /**
     * A screenshot disk-write failure (full disk) — a loud 500,
     * not a DB record about a nonexistent file. Silent evidence loss
     * is unacceptable for an evidence service.
     */
    public function test_screenshot_write_failure_aborts_loudly(): void
    {
        $case = TestCaseModel::factory()->create([
            'project_id' => $this->project->id,
            'steps' => [
                ['action' => 'Step 1', 'expected' => 'Ok'],
                ['action' => 'Step 2', 'expected' => 'Ok'],
            ],
        ]);
        $session = TestSession::factory()->create([
            'project_id' => $this->project->id,
            'only_after' => true,
            'head_commit' => 'cccccccddddddd',
        ]);
        $session->cases()->attach($case->id, ['position' => 1]);
        $sessionCase = SessionCase::query()->where('session_id', $session->id)->first();

        $flow = app(SessionFlowService::class);
        ['run' => $run] = $flow->startRun($session, RunSide::After, 'cccccccddddddd');

        // the first step writes fine — a control that the mock does not break everything
        $flow->submitStep($run, $sessionCase, 1, StepStatus::Passed, null, null,
            file_get_contents(__DIR__.'/fixtures/tiny.png') ?: '');
        $this->assertSame(1, Screenshot::query()->count());

        // swap the disk: put() returns false (throw=false on the screenshots disk)
        $broken = \Mockery::mock(Storage::disk('screenshots'))->makePartial();
        $broken->shouldReceive('put')->andReturn(false);
        Storage::shouldReceive('disk')->with('screenshots')->andReturn($broken);

        try {
            $flow->submitStep($run, $sessionCase, 2, StepStatus::Passed, null, null,
                file_get_contents(__DIR__.'/fixtures/tiny.png') ?: '');
            $this->fail('a write failure must fail loudly, not pass silently');
        } catch (ScreenshotStorageException $e) {
            $this->assertStringContainsString('Failed to store screenshot', $e->getMessage());
        }

        // the failed step created neither a step record nor a screenshot record
        $this->assertSame(1, $run->stepResults()->count());
        $this->assertSame(1, Screenshot::query()->count());
    }

    /**
     * The same disk failure at the REST level: the controller turns
     * ScreenshotStorageException into a loud 500 with the message,
     * not a faceless "Server Error". Each transport catches it on its own.
     */
    public function test_screenshot_disk_failure_rest_returns_500_with_text(): void
    {
        $case = TestCaseModel::factory()->create([
            'project_id' => $this->project->id,
            'steps' => [['action' => 'Step', 'expected' => 'Ok']],
        ]);
        $session = TestSession::factory()->create([
            'project_id' => $this->project->id,
            'only_after' => true,
            'head_commit' => 'cccccccddddddd',
        ]);
        $session->cases()->attach($case->id, ['position' => 1]);

        $flow = app(SessionFlowService::class);
        ['run' => $run] = $flow->startRun($session, RunSide::After, 'cccccccddddddd');

        [, $token] = ApiKey::issue($this->project, $this->developer, 'dev-rest');

        $broken = \Mockery::mock(Storage::disk('screenshots'))->makePartial();
        $broken->shouldReceive('put')->andReturn(false);
        Storage::shouldReceive('disk')->with('screenshots')->andReturn($broken);

        $this->post("/api/v1/runs/{$run->id}/steps", [
            'case_id' => $case->id,
            'step_index' => 1,
            'status' => 'passed',
            'screenshot' => UploadedFile::fake()->image('shot.png', 40, 30),
        ], ['Authorization' => "Bearer {$token}", 'Accept' => 'application/json'])
            ->assertStatus(500)
            ->assertSee('Failed to store screenshot');

        // the failure left no trace: no step, no screenshot record
        $this->assertSame(0, $run->stepResults()->count());
    }
}

/**
 * A local alias for the limit constant — no need to pull the service just for MAX_BYTES.
 */
class ScreenshotServiceLimit
{
    public const MAX = ScreenshotService::MAX_BYTES;
}
