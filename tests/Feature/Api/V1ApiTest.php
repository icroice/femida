<?php

namespace Tests\Feature\Api;

use App\Enums\ProjectRole;
use App\Enums\RunSide;
use App\Enums\RunStatus;
use App\Models\ApiKey;
use App\Models\Project;
use App\Models\Run;
use App\Models\Screenshot;
use App\Models\TestCase as TestCaseModel;
use App\Models\TestSession;
use App\Models\User;
use App\Services\ScreenshotService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class V1ApiTest extends TestCase
{
    use RefreshDatabase;

    private Project $project;

    private Project $otherProject;

    private string $token;

    private string $otherToken;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('screenshots');

        $owner = User::factory()->create();
        $stranger = User::factory()->create();

        $this->project = Project::factory()->create(['name' => 'Register']);
        $this->otherProject = Project::factory()->create(['name' => 'Another project']);

        $this->project->users()->attach($owner->id, ['role' => ProjectRole::Developer->value]);
        $this->otherProject->users()->attach($stranger->id, ['role' => ProjectRole::Developer->value]);

        [, $this->token] = ApiKey::issue($this->project, $owner, 'dev-test');
        [, $this->otherToken] = ApiKey::issue($this->otherProject, $stranger, 'dev-other');
    }

    private function headers(?string $token = null): array
    {
        return ['Authorization' => 'Bearer '.($token ?? $this->token)];
    }

    /*
     * ── Keys ────────────────────────────────────────────────────────────
     */

    public function test_me_returns_project_info(): void
    {
        $response = $this->getJson('/api/v1/me', $this->headers())
            ->assertOk()
            ->assertJsonPath('project.name', 'Register');

        $this->assertStringStartsWith('fem_', $response->json('key.prefix'));
    }

    public function test_invalid_or_revoked_key_is_rejected(): void
    {
        $this->getJson('/api/v1/me', ['Authorization' => 'Bearer fem_totally_wrong'])
            ->assertUnauthorized();

        $key = ApiKey::findByToken($this->token);
        $key->revoke();

        $this->getJson('/api/v1/me', $this->headers())
            ->assertUnauthorized();
    }

    /**
     * A key lives exactly as long as its owner keeps
     * developer+ rights in the project and stays unblocked.
     */
    public function test_api_key_follows_owner_access_changes(): void
    {
        $owner = $this->project->users()->first();

        // removing the owner from the project — the key is dead
        $this->project->users()->detach($owner->id);
        $this->getJson('/api/v1/me', $this->headers())->assertUnauthorized();

        // demoting to tester — no key rights
        $this->project->users()->attach($owner->id, ['role' => ProjectRole::Tester->value]);
        $this->getJson('/api/v1/me', $this->headers())->assertUnauthorized();

        // promoting back to developer — works again
        $this->project->users()->syncWithoutDetaching([$owner->id => ['role' => ProjectRole::Developer->value]]);
        $this->getJson('/api/v1/me', $this->headers())->assertOk();

        // blocking the owner — dead; unblocking — alive again
        $owner->block();
        $this->getJson('/api/v1/me', $this->headers())->assertUnauthorized();

        $owner->unblock();
        $this->getJson('/api/v1/me', $this->headers())->assertOk();

        // deleting the owner — final (user_id → null)
        $owner->delete();
        $this->getJson('/api/v1/me', $this->headers())->assertUnauthorized();
    }

    /** /api/v1 is rate-limited, exceeding it gives 429. */
    public function test_api_requests_are_rate_limited(): void
    {
        RateLimiter::for('api', fn () => Limit::perMinute(3));

        for ($i = 0; $i < 3; $i++) {
            $this->getJson('/api/v1/me', $this->headers())->assertOk();
        }

        $this->getJson('/api/v1/me', $this->headers())->assertStatus(429);
    }

    /*
     * ── Cases ───────────────────────────────────────────────────────────
     */

    public function test_case_crud_via_api(): void
    {
        $create = $this->postJson('/api/v1/cases', [
            'title' => 'Card refund in payment history',
            'steps' => [
                ['action' => 'Open "Payment history"', 'expected' => 'the operations list loaded'],
                ['action' => 'Check the refund entry', 'expected' => 'entry "Refund · 1 350 ₽" in the list'],
            ],
            'priority' => 'P1',
            'status' => 'active',
        ], $this->headers());

        $create->assertCreated()
            ->assertJsonPath('data.title', 'Card refund in payment history')
            ->assertJsonPath('data.priority', 'P1');

        $caseId = $create->json('data.id');

        // search filter
        $this->getJson('/api/v1/cases?q=refund', $this->headers())
            ->assertOk()
            ->assertJsonCount(1, 'data');

        // update
        $this->putJson("/api/v1/cases/{$caseId}", [
            'title' => 'Card refund in payment history',
            'steps' => [['action' => 'Open the history', 'expected' => 'loaded']],
        ], $this->headers())->assertOk()->assertJsonPath('data.steps.0.action', 'Open the history');

        // delete = moving to inactive
        $this->deleteJson("/api/v1/cases/{$caseId}", [], $this->headers())
            ->assertOk()
            ->assertJsonPath('data.status', 'inactive');
    }

    public function test_case_folders_via_api(): void
    {
        // the path is normalized: trimmed segments, a single slash
        $this->postJson('/api/v1/cases', [
            'title' => 'Card payment',
            'folder' => ' Checkout / Payment ',
            'steps' => [['action' => 'Open the payment', 'expected' => 'the form is open']],
        ], $this->headers())->assertCreated()
            ->assertJsonPath('data.folder', 'Checkout/Payment');

        $this->postJson('/api/v1/cases', [
            'title' => 'Level-1 case',
            'folder' => 'Checkout',
            'steps' => [['action' => 'Step', 'expected' => 'Ok']],
        ], $this->headers())->assertCreated();

        // more than two levels — rejected
        $this->postJson('/api/v1/cases', [
            'title' => 'Too deep',
            'folder' => 'a/b/c',
            'steps' => [['action' => 'Step', 'expected' => 'Ok']],
        ], $this->headers())->assertUnprocessable();

        // a top-level folder filter pulls in subfolders
        $this->getJson('/api/v1/cases?folder='.urlencode('Checkout'), $this->headers())
            ->assertOk()
            ->assertJsonCount(2, 'data');

        // an exact subfolder
        $this->getJson('/api/v1/cases?folder='.urlencode('Checkout/Payment'), $this->headers())
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    /*
     * ── Sessions, runs, steps ───────────────────────────────────────────
     */

    public function test_full_agent_flow(): void
    {
        $caseA = TestCaseModel::factory()->create(['project_id' => $this->project->id]);
        $caseB = TestCaseModel::factory()->create(['project_id' => $this->project->id]);

        // session
        $session = $this->postJson('/api/v1/sessions', [
            'external_ref' => 'PROJ-1284',
            'title' => 'Receipt on a partial order cancellation',
            'base_commit' => 'a3f9c12deadbeef',
            'head_commit' => 'f71e04bdeadbeef',
            'branch' => 'feature/partial-refund',
            'agent' => 'Claude Code + Playwright',
            'case_ids' => [$caseA->id, $caseB->id],
        ], $this->headers());

        $session->assertCreated()
            ->assertJsonPath('data.status', 'not_passed')
            ->assertJsonPath('data.cases.0.position', 1)
            ->assertJsonPath('data.cases.1.position', 2);

        $sessionId = $session->json('data.id');

        // the after run
        $afterRun = $this->postJson("/api/v1/sessions/{$sessionId}/runs", [
            'side' => 'after',
            'commit' => 'f71e04bdeadbeef',
        ], $this->headers());

        $afterRun->assertCreated()->assertJsonPath('data.side', 'after');
        $runId = $afterRun->json('data.id');

        // the same commit on the same side — idempotent (backfill)
        $this->postJson("/api/v1/sessions/{$sessionId}/runs", [
            'side' => 'after',
            'commit' => 'f71e04bdeadbeef',
        ], $this->headers())
            ->assertOk()
            ->assertJsonPath('data.id', $runId);

        // a different commit on the same side — 422: a new commit means a new session
        $this->postJson("/api/v1/sessions/{$sessionId}/runs", [
            'side' => 'after',
            'commit' => '1111111deadbeef',
        ], $this->headers())->assertStatus(422);

        // a step with a screenshot
        $shot = UploadedFile::fake()->image('step-1.png', 1280, 800);
        $step = $this->post("/api/v1/runs/{$runId}/steps", [
            'case_id' => $caseA->id,
            'step_index' => 1,
            'status' => 'passed',
            'note' => 'the cancel panel opened',
            'elapsed_ms' => 5400,
            'screenshot' => $shot,
        ], $this->headers())
            ->assertCreated()
            ->assertJsonPath('data.status', 'passed')
            ->assertJsonPath('data.elapsed_ms', 5400)
            ->assertJsonPath('data.screenshot.width', 1280);

        // agent suggestion and verdict (the verdict is attributed to the key owner)
        $owner = ApiKey::findByToken($this->token)->owner;

        $this->patchJson("/api/v1/sessions/{$sessionId}/cases/{$caseA->id}", [
            'agent_suggestion' => 'fail',
            'agent_notes' => 'the total was not recalculated',
        ], $this->headers())
            ->assertOk()
            ->assertJsonPath('data.agent_suggestion', 'fail');

        $this->patchJson("/api/v1/sessions/{$sessionId}/cases/{$caseA->id}", [
            'verdict' => 'fail',
            'verdict_comment' => 'Confirming: regression',
        ], $this->headers())
            ->assertOk()
            ->assertJsonPath('data.verdict', 'fail')
            ->assertJsonPath('data.verdict_by.id', $owner->id);

        // the before run and a step
        $beforeRun = $this->postJson("/api/v1/sessions/{$sessionId}/runs", [
            'side' => 'before',
            'commit' => 'a3f9c12deadbeef',
        ], $this->headers())->assertCreated();

        $this->post("/api/v1/runs/{$beforeRun->json('data.id')}/steps", [
            'case_id' => $caseA->id,
            'step_index' => 1,
            'status' => 'passed',
        ], $this->headers())->assertCreated();

        // finishing the run and metrics (the session status is never set by hand)
        $this->patchJson("/api/v1/runs/{$runId}", ['status' => 'done'], $this->headers())
            ->assertOk()
            ->assertJsonPath('data.status', 'done');

        $this->patchJson("/api/v1/sessions/{$sessionId}", [
            'tokens_in' => 987_654,
            'tokens_out' => 32_000,
        ], $this->headers())
            ->assertOk()
            ->assertJsonPath('data.tokens_in', 987654)
            ->assertJsonPath('data.tokens_out', 32000);

        // the aggregated session: step pairs per side
        $aggregated = $this->getJson("/api/v1/sessions/{$sessionId}", $this->headers())
            ->assertOk()
            ->assertJsonPath('data.cases.0.verdict', 'fail');

        $this->assertNotNull($aggregated->json('data.cases.0.steps.after.0.screenshot'));
        $this->assertSame(5400, $aggregated->json('data.cases.0.steps.after.0.elapsed_ms'));
        $this->assertSame(1, $aggregated->json('data.cases.0.steps.before.0.step_index'));
        $aggregated->assertJsonPath('data.cases.1.steps.after.0.screenshot', null);
    }

    public function test_only_after_session_forbids_base_commit_and_before_run(): void
    {
        $case = TestCaseModel::factory()->create(['project_id' => $this->project->id]);

        $session = $this->postJson('/api/v1/sessions', [
            'external_ref' => 'PROJ-1300',
            'title' => 'Quick smoke',
            'head_commit' => 'abcdefabcdef',
            'only_after' => true,
            'case_ids' => [$case->id],
            'base_commit' => 'should-be-rejected',
        ], $this->headers());

        $session->assertStatus(422)->assertJsonValidationErrors('base_commit');

        $ok = $this->postJson('/api/v1/sessions', [
            'external_ref' => 'PROJ-1300',
            'title' => 'Quick smoke',
            'head_commit' => 'abcdefabcdef',
            'only_after' => true,
            'case_ids' => [$case->id],
        ], $this->headers())->assertCreated();

        $this->postJson("/api/v1/sessions/{$ok->json('data.id')}/runs", [
            'side' => 'before',
            'commit' => 'abcdefabcdef',
        ], $this->headers())->assertStatus(422);
    }

    public function test_base_commit_required_in_regular_mode(): void
    {
        $case = TestCaseModel::factory()->create(['project_id' => $this->project->id]);

        $this->postJson('/api/v1/sessions', [
            'external_ref' => 'PROJ-1301',
            'title' => 'No base',
            'head_commit' => 'abcdefabcdef',
            'case_ids' => [$case->id],
        ], $this->headers())->assertStatus(422)->assertJsonValidationErrors('base_commit');
    }

    /*
     * ── Isolation and protection ───────────────────────────────────────
     */

    public function test_foreign_project_is_hidden(): void
    {
        $foreignCase = TestCaseModel::factory()->create(['project_id' => $this->otherProject->id]);

        $this->getJson("/api/v1/cases/{$foreignCase->id}", $this->headers())
            ->assertNotFound();

        $foreignSession = TestSession::factory()->create(['project_id' => $this->otherProject->id]);
        $this->getJson("/api/v1/sessions/{$foreignSession->id}", $this->headers())
            ->assertNotFound();
    }

    public function test_step_validation(): void
    {
        $case = TestCaseModel::factory()->create(['project_id' => $this->project->id]);
        $session = TestSession::factory()->create(['project_id' => $this->project->id]);
        $session->cases()->attach($case->id, ['position' => 1]);
        $run = Run::factory()->create(['session_id' => $session->id]);

        // a case not in the session
        $otherCase = TestCaseModel::factory()->create(['project_id' => $this->project->id]);
        $this->postJson("/api/v1/runs/{$run->id}/steps", [
            'case_id' => $otherCase->id,
            'step_index' => 1,
            'status' => 'passed',
        ], $this->headers())->assertStatus(422);

        // step_index beyond the case (the case has 3 steps)
        $this->postJson("/api/v1/runs/{$run->id}/steps", [
            'case_id' => $case->id,
            'step_index' => 9,
            'status' => 'passed',
        ], $this->headers())->assertStatus(422);
    }

    public function test_screenshot_rejects_non_image_content(): void
    {
        $case = TestCaseModel::factory()->create(['project_id' => $this->project->id]);
        $session = TestSession::factory()->create(['project_id' => $this->project->id]);
        $session->cases()->attach($case->id, ['position' => 1]);
        $run = Run::factory()->create(['session_id' => $session->id]);

        $fake = UploadedFile::fake()->createWithContent('shot.png', 'this is definitely not an image');

        $this->post("/api/v1/runs/{$run->id}/steps", [
            'case_id' => $case->id,
            'step_index' => 1,
            'status' => 'passed',
            'screenshot' => $fake,
        ], $this->headers())->assertStatus(422);
    }

    /*
     * ── M1 leftovers: one REST ↔ MCP contract (11/14/16) ───────────────
     */

    /** The default case list is active-only, same as list_cases. */
    public function test_case_list_defaults_to_active(): void
    {
        TestCaseModel::factory()->create(['project_id' => $this->project->id, 'title' => 'Active case']);
        TestCaseModel::factory()->inactive()->create(['project_id' => $this->project->id, 'title' => 'Archived case']);

        // the default — active only
        $this->getJson('/api/v1/cases', $this->headers())
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Active case');

        // explicit statuses
        $this->getJson('/api/v1/cases?status=inactive', $this->headers())
            ->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/cases?status=all', $this->headers())
            ->assertOk()->assertJsonCount(2, 'data');

        // a garbage status — 422, not an empty list
        $this->getJson('/api/v1/cases?status=deleted', $this->headers())->assertUnprocessable();
    }

    /** An inactive case cannot be attached to a session — "not used" means not used. */
    public function test_session_rejects_inactive_case(): void
    {
        $inactive = TestCaseModel::factory()->inactive()->create(['project_id' => $this->project->id]);

        $this->postJson('/api/v1/sessions', [
            'external_ref' => 'PROJ-1',
            'title' => 'Session with an archive',
            'base_commit' => 'aaaaaaabbbbbbb',
            'head_commit' => 'cccccccddddddd',
            'case_ids' => [$inactive->id],
        ], $this->headers())->assertStatus(422);
    }

    /** Skipped/error without a note is rejected in REST too (shared-layer rule). */
    public function test_step_note_required_for_skipped(): void
    {
        $case = TestCaseModel::factory()->create(['project_id' => $this->project->id]);
        $session = TestSession::factory()->create(['project_id' => $this->project->id]);
        $session->cases()->attach($case->id, ['position' => 1]);
        $run = Run::factory()->create(['session_id' => $session->id, 'status' => RunStatus::Running]);

        $this->postJson("/api/v1/runs/{$run->id}/steps", [
            'case_id' => $case->id,
            'step_index' => 1,
            'status' => 'skipped',
        ], $this->headers())->assertStatus(422);

        $this->postJson("/api/v1/runs/{$run->id}/steps", [
            'case_id' => $case->id,
            'step_index' => 1,
            'status' => 'skipped',
            'note' => 'the payment gateway is unavailable',
        ], $this->headers())->assertCreated();
    }

    /** PATCH /runs — done|failed only, the finish is terminal, a repeat is idempotent. */
    public function test_run_finish_is_terminal(): void
    {
        $case = TestCaseModel::factory()->create(['project_id' => $this->project->id]);
        $session = TestSession::factory()->create(['project_id' => $this->project->id]);
        $session->cases()->attach($case->id, ['position' => 1]);
        $run = Run::factory()->create(['session_id' => $session->id, 'status' => RunStatus::Running, 'finished_at' => null]);

        // running is no longer accepted (the finish is terminal)
        $this->patchJson("/api/v1/runs/{$run->id}", ['status' => 'running'], $this->headers())
            ->assertStatus(422);

        $this->patchJson("/api/v1/runs/{$run->id}", ['status' => 'done'], $this->headers())
            ->assertOk()->assertJsonPath('data.status', 'done');

        // re-finishing with the same status — idempotent (agent retries)
        $this->patchJson("/api/v1/runs/{$run->id}", ['status' => 'done'], $this->headers())
            ->assertOk()->assertJsonPath('data.status', 'done');

        // changing the status of a finished run — 422
        $this->patchJson("/api/v1/runs/{$run->id}", ['status' => 'failed', 'error' => 'was late'], $this->headers())
            ->assertStatus(422);
        $this->assertDatabaseHas('runs', ['id' => $run->id, 'error' => null]);
    }

    /** Steps are accepted into a running run only: evidence is not rewritten after the finish. */
    public function test_step_rejected_in_finished_run(): void
    {
        $case = TestCaseModel::factory()->create(['project_id' => $this->project->id]);
        $session = TestSession::factory()->create(['project_id' => $this->project->id]);
        $session->cases()->attach($case->id, ['position' => 1]);
        $run = Run::factory()->create(['session_id' => $session->id, 'status' => RunStatus::Failed]);

        $this->postJson("/api/v1/runs/{$run->id}/steps", [
            'case_id' => $case->id,
            'step_index' => 1,
            'status' => 'passed',
        ], $this->headers())->assertStatus(422);

        $this->assertDatabaseCount('step_results', 0);
    }

    /*
     * ── Project isolation ───────────────────────────────────────────────
     */

    /** Pagination contract: per_page is an integer 1..100, else 422 (0/-1 broke the query). */
    public function test_per_page_is_validated_on_both_lists(): void
    {
        TestCaseModel::factory()->count(2)->create(['project_id' => $this->project->id]);
        TestSession::factory()->create(['project_id' => $this->project->id]);

        foreach (['0', '-1', '101', 'abc'] as $bad) {
            $this->getJson("/api/v1/cases?per_page={$bad}", $this->headers())
                ->assertStatus(422)->assertJsonValidationErrors('per_page');
            $this->getJson("/api/v1/sessions?per_page={$bad}", $this->headers())
                ->assertStatus(422)->assertJsonValidationErrors('per_page');
        }

        // valid values pass, including the upper bound
        $this->getJson('/api/v1/cases?per_page=1', $this->headers())
            ->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/cases?per_page=100', $this->headers())
            ->assertOk()->assertJsonCount(2, 'data');
    }

    /**
     * Magic-byte branches: JPEG and WebP are accepted; the extension comes from content,
     * not from the client's claim.
     */
    public function test_screenshot_accepts_jpeg_and_webp(): void
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
        $run = Run::factory()->create([
            'session_id' => $session->id,
            'side' => RunSide::After,
            'commit' => 'cccccccddddddd',
            'status' => RunStatus::Running,
        ]);

        $image = imagecreatetruecolor(40, 30);

        ob_start();
        imagejpeg($image);
        $jpeg = (string) ob_get_clean();

        $this->post("/api/v1/runs/{$run->id}/steps", [
            'case_id' => $case->id,
            'step_index' => 1,
            'status' => 'passed',
            'screenshot' => UploadedFile::fake()->createWithContent('shot.jpeg', $jpeg),
        ], $this->headers())->assertCreated();

        $shot = Screenshot::query()->where('path', 'like', '%.jpg')->first();
        $this->assertNotNull($shot, 'JPEG is stored with the extension from magic bytes');

        if (! function_exists('imagewebp')) {
            $this->markTestSkipped('GD without WebP support');
        }

        ob_start();
        imagewebp($image);
        $webp = (string) ob_get_clean();

        // a separate step: a passed step is frozen, a retake of the same index — 422
        $this->post("/api/v1/runs/{$run->id}/steps", [
            'case_id' => $case->id,
            'step_index' => 2,
            'status' => 'passed',
            'screenshot' => UploadedFile::fake()->createWithContent('shot.bin', $webp),
        ], $this->headers())->assertCreated();

        $this->assertNotNull(
            Screenshot::query()->where('path', 'like', '%.webp')->first(),
            'WebP is detected by content even without an extension'
        );
    }

    /**
     * The exact 5 MB boundary: exactly the limit passes, +1 byte — 422.
     * The PNG is zero-padded after IEND: the parser ignores the tail, magic bytes intact.
     */
    public function test_screenshot_size_boundary_is_exact(): void
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
        $run = Run::factory()->create([
            'session_id' => $session->id,
            'side' => RunSide::After,
            'commit' => 'cccccccddddddd',
            'status' => RunStatus::Running,
        ]);

        $png = file_get_contents(__DIR__.'/../fixtures/tiny.png') ?: '';
        $exact = str_pad($png, ScreenshotService::MAX_BYTES, "\x00");
        $this->post("/api/v1/runs/{$run->id}/steps", [
            'case_id' => $case->id,
            'step_index' => 1,
            'status' => 'passed',
            'screenshot' => UploadedFile::fake()->createWithContent('exact.png', $exact),
        ], $this->headers())->assertCreated();

        $over = str_pad($png, ScreenshotService::MAX_BYTES + 1, "\x00");
        $this->post("/api/v1/runs/{$run->id}/steps", [
            'case_id' => $case->id,
            'step_index' => 1,
            'status' => 'failed',
            'note' => 'retake over the limit',
            'screenshot' => UploadedFile::fake()->createWithContent('over.png', $over),
        ], $this->headers())->assertStatus(422);

        // the failed retake did not touch the first step's evidence
        $this->assertSame(1, Screenshot::query()->count());
    }

    /** last_used_at is written at most once a minute — no DB hammering per request. */
    public function test_last_used_at_is_throttled_to_one_minute(): void
    {
        $this->travelTo(now());

        $this->getJson('/api/v1/me', $this->headers())->assertOk();
        $first = ApiKey::findByToken($this->token)->last_used_at;
        $this->assertNotNull($first);

        // after 30 seconds — not updated
        $this->travel(30)->seconds();
        $this->getJson('/api/v1/me', $this->headers())->assertOk();
        $this->assertSame(
            $first->toDateTimeString(),
            ApiKey::findByToken($this->token)->last_used_at->toDateTimeString()
        );

        // after a minute — updated
        $this->travel(31)->seconds();
        $this->getJson('/api/v1/me', $this->headers())->assertOk();
        $this->assertTrue(ApiKey::findByToken($this->token)->last_used_at->gt($first));
    }
}
