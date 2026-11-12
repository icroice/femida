<?php

namespace Tests\Feature;

use App\Enums\ProjectRole;
use App\Mcp\Resources\OnboardingResource;
use App\Mcp\Servers\FemidaServer;
use App\Mcp\Tools\AddCasesToSessionTool;
use App\Mcp\Tools\CreateCaseTool;
use App\Mcp\Tools\CreateSessionTool;
use App\Mcp\Tools\FinishRunTool;
use App\Mcp\Tools\GetCaseTool;
use App\Mcp\Tools\GetProjectTool;
use App\Mcp\Tools\GetSessionTool;
use App\Mcp\Tools\ListCasesTool;
use App\Mcp\Tools\SetCaseSuggestionTool;
use App\Mcp\Tools\StartRunTool;
use App\Mcp\Tools\SubmitSessionMetricsTool;
use App\Mcp\Tools\SubmitStepTool;
use App\Models\ApiKey;
use App\Models\Project;
use App\Models\Run;
use App\Models\Screenshot;
use App\Models\SessionCase;
use App\Models\TestSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Support\Facades\Storage;
use Laravel\Mcp\Server\Attributes\Instructions;
use Tests\TestCase;

class McpServerTest extends TestCase
{
    use RefreshDatabase;

    private Project $project;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('screenshots');

        $owner = User::factory()->create();
        $this->project = Project::factory()->create(['name' => 'Register']);
        $this->project->users()->attach($owner->id, ['role' => ProjectRole::Developer->value]);

        [$key, $this->token] = ApiKey::issue($this->project, $owner, 'dev-mcp');

        // Key context: middleware sets it in the HTTP flow; tests do it by hand
        $request = new HttpRequest;
        $request->attributes->set('apiKey', $key);
        $request->attributes->set('apiProject', $this->project);
        $request->attributes->set('apiUser', $owner);
        $this->app->instance('request', $request);
    }

    private function pngBase64(int $width = 64, int $height = 48): string
    {
        $image = imagecreatetruecolor($width, $height);
        ob_start();
        imagepng($image);
        $bytes = (string) ob_get_clean();

        return base64_encode($bytes);
    }

    public function test_tools_are_registered(): void
    {
        FemidaServer::tool(GetProjectTool::class)->assertOk()->assertSee('Register');
    }

    /**
     * tools/list builds each tool's inputSchema via Tool::toArray() —
     * a regression guard for a fatal like "Call to undefined method ArrayType::minItems()",
     * which killed the whole MCP surface on a client's first request.
     */
    public function test_tools_list_builds_schema_for_every_tool(): void
    {
        $toolClasses = (new \ReflectionClass(FemidaServer::class))
            ->getDefaultProperties()['tools'];

        $this->assertNotEmpty($toolClasses);

        foreach ($toolClasses as $class) {
            // a fatal on a missing schema-builder method surfaces here as Error
            $definition = app($class)->toArray();

            $this->assertArrayHasKey('inputSchema', $definition, $class);
            // zero-argument tools (get_project) have empty properties — that is normal
        }
    }

    public function test_case_tools_flow(): void
    {
        FemidaServer::tool(CreateCaseTool::class, [
            'title' => 'Partial cancellation: recalculating the receipt total',
            'folder' => 'Checkout/Payment',
            'steps' => [
                ['action' => 'Open the order, cancel part of the items', 'expected' => 'the cancel panel opened'],
                ['action' => 'Check the total in the receipt', 'expected' => 'the total is recalculated'],
            ],
            'priority' => 'P0',
            'status' => 'active',
        ])->assertOk()->assertSee('Partial cancellation')->assertSee('Checkout/Payment');

        // an inactive case: not in the library by default
        FemidaServer::tool(CreateCaseTool::class, [
            'title' => 'Old payment flow',
            'steps' => [['action' => 'Pay', 'expected' => 'paid']],
            'status' => 'inactive',
        ])->assertOk()->assertSee('inactive');

        FemidaServer::tool(ListCasesTool::class, [])
            ->assertOk()
            ->assertDontSee('Old payment flow');

        FemidaServer::tool(ListCasesTool::class, ['status' => 'all'])
            ->assertOk()
            ->assertSee('Old payment flow');

        FemidaServer::tool(ListCasesTool::class, ['query' => 'cancellation'])
            ->assertOk()
            ->assertSee('Partial cancellation');

        // a top-level folder filter pulls in subfolders
        FemidaServer::tool(ListCasesTool::class, ['folder' => 'Checkout'])
            ->assertOk()
            ->assertSee('Partial cancellation');

        // with_steps: steps in the list response — no N get_case calls
        FemidaServer::tool(ListCasesTool::class, ['query' => 'cancellation', 'with_steps' => true])
            ->assertOk()
            ->assertSee('the cancel panel opened');

        FemidaServer::tool(GetCaseTool::class, ['case_id' => 1])
            ->assertOk()
            ->assertSee('the cancel panel opened')
            ->assertSee('Checkout/Payment');
    }

    public function test_full_mcp_flow_with_screenshots(): void
    {
        $case = FemidaServer::tool(CreateCaseTool::class, [
            'title' => 'Refund in payment history',
            'steps' => [['action' => 'Open the history', 'expected' => 'the refund is visible']],
        ])->assertOk();

        $session = FemidaServer::tool(CreateSessionTool::class, [
            'external_ref' => 'PROJ-1284',
            'title' => 'Receipt on partial cancellation',
            'base_commit' => 'a3f9c12deadbeef',
            'head_commit' => 'f71e04bdeadbeef',
            'case_ids' => [1],
            'agent' => 'Claude Code + Playwright',
        ])->assertOk()->assertSee('PROJ-1284');

        $run = FemidaServer::tool(StartRunTool::class, [
            'session_id' => 1,
            'side' => 'after',
            'commit' => 'f71e04bdeadbeef',
        ])->assertOk()->assertSee('after');

        // The session status is not "running"; it stays not_passed until coverage is complete
        $this->assertSame('not_passed', TestSession::query()->find(1)->status->value);

        FemidaServer::tool(SubmitStepTool::class, [
            'run_id' => 1,
            'case_id' => 1,
            'step_index' => 1,
            'status' => 'passed',
            'note' => 'the history opened',
            'elapsed_ms' => 3100,
            'screenshot_base64' => $this->pngBase64(1280, 800),
        ])->assertOk()->assertSee('passed')->assertSee(3100);

        FemidaServer::tool(SetCaseSuggestionTool::class, [
            'session_id' => 1,
            'case_id' => 1,
            'suggestion' => 'fail',
            'notes' => 'the refund is not shown after the edit',
        ])->assertOk()->assertSee('fail');

        FemidaServer::tool(FinishRunTool::class, [
            'run_id' => 1,
            'status' => 'done',
        ])->assertOk()->assertSee('done')->assertSee('"warnings":[]');

        FemidaServer::tool(StartRunTool::class, [
            'session_id' => 1,
            'side' => 'before',
            'commit' => 'a3f9c12deadbeef',
        ])->assertOk();

        FemidaServer::tool(SubmitStepTool::class, [
            'run_id' => 2,
            'case_id' => 1,
            'step_index' => 1,
            'status' => 'passed',
            'screenshot_base64' => 'data:image/png;base64,'.$this->pngBase64(),
        ])->assertOk();

        // the first finish_run does not "complete" the session: before has not started
        $this->assertSame('not_passed', TestSession::query()->find(1)->status->value);

        FemidaServer::tool(FinishRunTool::class, [
            'run_id' => 2,
            'status' => 'done',
        ])->assertOk()->assertSee('"session_status":"passed"');

        // all runs finished, fully green coverage — the session passes automatically
        $this->assertSame('passed', TestSession::query()->find(1)->status->value);

        FemidaServer::tool(GetSessionTool::class, ['session_id' => 1])
            ->assertOk()
            ->assertSee('passed')
            ->assertSee('"suggestion_missing":false')
            ->assertSee('"missing":0');

        // metrics: token usage for the session, once at the end
        FemidaServer::tool(SubmitSessionMetricsTool::class, [
            'session_id' => 1,
            'tokens_in' => 1_234_567,
            'tokens_out' => 42_000,
        ])->assertOk()->assertSee('"tokens_in":1234567');

        $session = TestSession::query()->first();
        $this->assertSame(1_234_567, $session->tokens_in);
        $this->assertSame(42_000, $session->tokens_out);
        $this->assertSame(3100, SessionCase::query()
            ->where('session_id', 1)
            ->first()
            ->stepResults
            ->firstWhere('step_index', 1)
            ->elapsed_ms);

        $this->assertSame(2, Screenshot::query()->count());
    }

    public function test_submit_step_rejects_non_image_base64(): void
    {
        FemidaServer::tool(CreateCaseTool::class, [
            'title' => 'Case for an error',
            'steps' => [['action' => 'Step', 'expected' => 'Result']],
        ])->assertOk();

        FemidaServer::tool(CreateSessionTool::class, [
            'external_ref' => 'PROJ-1',
            'title' => 'Session',
            'base_commit' => 'aaaaaaabbbbbbb',
            'head_commit' => 'cccccccddddddd',
            'case_ids' => [1],
        ])->assertOk();

        FemidaServer::tool(StartRunTool::class, [
            'session_id' => 1,
            'side' => 'after',
            'commit' => 'cccccccddddddd',
        ])->assertOk();

        FemidaServer::tool(SubmitStepTool::class, [
            'run_id' => 1,
            'case_id' => 1,
            'step_index' => 1,
            'status' => 'passed',
            'screenshot_base64' => base64_encode('definitely not an image'),
        ])->assertSee('Unsupported screenshot format');
    }

    /**
     * A lost set_case_suggestion call: finish_run warns immediately,
     * get_session flags the case with suggestion_missing and a summary.
     */
    public function test_finish_run_warns_about_missing_suggestions(): void
    {
        foreach (['Case one', 'Case two'] as $title) {
            FemidaServer::tool(CreateCaseTool::class, [
                'title' => $title,
                'steps' => [['action' => 'Step', 'expected' => 'Result']],
            ])->assertOk();
        }

        FemidaServer::tool(CreateSessionTool::class, [
            'external_ref' => 'PROJ-3',
            'title' => 'Session with skips',
            'head_commit' => 'eeeeeeefffffff',
            'only_after' => true,
            'case_ids' => [1, 2],
        ])->assertOk();

        FemidaServer::tool(StartRunTool::class, [
            'session_id' => 1,
            'side' => 'after',
            'commit' => 'eeeeeeefffffff',
        ])->assertOk();

        FemidaServer::tool(SubmitStepTool::class, [
            'run_id' => 1,
            'case_id' => 1,
            'step_index' => 1,
            'status' => 'passed',
            'screenshot_base64' => $this->pngBase64(),
        ])->assertOk();

        // the suggestion was set for the first case only
        FemidaServer::tool(SetCaseSuggestionTool::class, [
            'session_id' => 1,
            'case_id' => 1,
            'suggestion' => 'pass',
        ])->assertOk();

        FemidaServer::tool(FinishRunTool::class, ['run_id' => 1, 'status' => 'done'])
            ->assertOk()
            ->assertSee('1 of 2 cases have no agent suggestion (case_id: 2)');

        // the only run is terminal, but case 2 is uncovered — not_passed
        $this->assertSame('not_passed', TestSession::query()->find(1)->status->value);

        FemidaServer::tool(GetSessionTool::class, ['session_id' => 1])
            ->assertOk()
            ->assertSee('"suggestion_missing":true')
            ->assertSee('"missing":1');
    }

    /**
     * skipped/error without a reason are rejected: "the agent forgot"
     * must not look like a deliberate step skip.
     */
    public function test_submit_step_requires_note_for_skipped_and_error(): void
    {
        FemidaServer::tool(CreateCaseTool::class, [
            'title' => 'Skips case',
            'steps' => [['action' => 'Step', 'expected' => 'Result']],
        ])->assertOk();

        FemidaServer::tool(CreateSessionTool::class, [
            'external_ref' => 'PROJ-4',
            'title' => 'Session',
            'head_commit' => 'aaaaaaabbbbbbb',
            'only_after' => true,
            'case_ids' => [1],
        ])->assertOk();

        FemidaServer::tool(StartRunTool::class, [
            'session_id' => 1,
            'side' => 'after',
            'commit' => 'aaaaaaabbbbbbb',
        ])->assertOk();

        FemidaServer::tool(SubmitStepTool::class, [
            'run_id' => 1, 'case_id' => 1, 'step_index' => 1, 'status' => 'skipped',
        ])->assertSee('note');

        FemidaServer::tool(SubmitStepTool::class, [
            'run_id' => 1, 'case_id' => 1, 'step_index' => 1, 'status' => 'skipped',
            'note' => 'a third-party payment gateway is unavailable',
        ])->assertOk()->assertSee('skipped');

        FemidaServer::tool(SubmitStepTool::class, [
            'run_id' => 1, 'case_id' => 1, 'step_index' => 1, 'status' => 'error',
        ])->assertSee('note');
    }

    public function test_http_endpoint_requires_api_key(): void
    {
        // setUp swapped the request for a stub for MCP calls — restore the real one
        $this->app->instance('request', HttpRequest::create('/mcp', 'POST'));

        $this->postJson('/mcp', [])->assertUnauthorized();
    }

    /**
     * A forgotten required identifier — an explicit field error,
     * not "Case #0 not found" after null is cast to zero.
     */
    public function test_missing_id_argument_reports_the_field(): void
    {
        FemidaServer::tool(GetCaseTool::class, [])
            ->assertSee("Missing required field 'case_id'");

        FemidaServer::tool(GetSessionTool::class, [])
            ->assertSee("Missing required field 'session_id'");

        FemidaServer::tool(SubmitStepTool::class, ['case_id' => 1, 'step_index' => 1, 'status' => 'passed'])
            ->assertSee("Missing required field 'run_id'");

        FemidaServer::tool(GetCaseTool::class, ['case_id' => 'one'])
            ->assertSee("Field 'case_id' must be an integer");
    }

    public function test_finish_run_rejects_running_status(): void
    {
        FemidaServer::tool(CreateCaseTool::class, [
            'title' => 'Case',
            'steps' => [['action' => 'Step', 'expected' => 'Result']],
        ])->assertOk();

        FemidaServer::tool(CreateSessionTool::class, [
            'external_ref' => 'PROJ-2',
            'title' => 'Session',
            'head_commit' => 'cccccccddddddd',
            'only_after' => true,
            'case_ids' => [1],
        ])->assertOk();

        FemidaServer::tool(StartRunTool::class, [
            'session_id' => 1,
            'side' => 'after',
            'commit' => 'cccccccddddddd',
        ])->assertOk();

        // finish is a terminal status, running is no longer accepted
        FemidaServer::tool(FinishRunTool::class, ['run_id' => 1, 'status' => 'running']);

        $this->assertSame('running', Run::query()->first()->status->value);
        $this->assertNull(Run::query()->first()->finished_at);

        // a failed run does not block review: a human still looks at the partial evidence
        FemidaServer::tool(FinishRunTool::class, ['run_id' => 1, 'status' => 'failed', 'error' => 'the app did not start'])
            ->assertOk()->assertSee('"session_status":"not_passed"');
    }

    /*
     * ── M1 leftovers: one REST ↔ MCP contract (11/14/16) ───────────────
     */

    /** Same as REST: base_commit in only_after mode is an explicit error, not a silent null. */
    public function test_create_session_rejects_base_commit_in_only_after_mode(): void
    {
        FemidaServer::tool(CreateCaseTool::class, [
            'title' => 'Case',
            'steps' => [['action' => 'Step', 'expected' => 'Result']],
        ])->assertOk();

        FemidaServer::tool(CreateSessionTool::class, [
            'external_ref' => 'PROJ-5',
            'title' => 'Session',
            'head_commit' => 'aaaaaaabbbbbbb',
            'only_after' => true,
            'base_commit' => 'eeeeeeefffffff',
            'case_ids' => [1],
        ])->assertSee('base_commit must be null in only_after mode');

        $this->assertDatabaseCount('test_sessions', 0);
    }

    /** An inactive case attaches neither at creation nor by addition. */
    public function test_session_tools_reject_inactive_cases(): void
    {
        FemidaServer::tool(CreateCaseTool::class, [
            'title' => 'Future archive',
            'steps' => [['action' => 'Step', 'expected' => 'Result']],
        ])->assertOk();
        \App\Models\TestCase::query()->find(1)->forceFill(['status' => 'inactive'])->save();

        FemidaServer::tool(CreateSessionTool::class, [
            'external_ref' => 'PROJ-6',
            'title' => 'Session',
            'head_commit' => 'aaaaaaabbbbbbb',
            'only_after' => true,
            'case_ids' => [1],
        ])->assertSee('must be active');

        // a separate session with an active case — try to sneak an archived one in
        FemidaServer::tool(CreateCaseTool::class, [
            'title' => 'Working case',
            'steps' => [['action' => 'Step', 'expected' => 'Result']],
        ])->assertOk();
        FemidaServer::tool(CreateSessionTool::class, [
            'external_ref' => 'PROJ-7',
            'title' => 'Session',
            'head_commit' => 'bbbbbbbbcccccccc',
            'only_after' => true,
            'case_ids' => [2],
        ])->assertOk();

        FemidaServer::tool(AddCasesToSessionTool::class, ['session_id' => 1, 'case_ids' => [1]])
            ->assertSee('must be active');
        $this->assertDatabaseCount('session_case', 1);
    }

    /** add_cases_to_session recalculates the status: a new case breaks "passed". */
    public function test_add_cases_reevaluates_passed_session(): void
    {
        FemidaServer::tool(CreateCaseTool::class, [
            'title' => 'Covered case',
            'steps' => [['action' => 'Step', 'expected' => 'Result']],
        ])->assertOk();

        FemidaServer::tool(CreateSessionTool::class, [
            'external_ref' => 'PROJ-8',
            'title' => 'Session',
            'head_commit' => 'aaaaaaabbbbbbb',
            'only_after' => true,
            'case_ids' => [1],
        ])->assertOk();

        FemidaServer::tool(StartRunTool::class, [
            'session_id' => 1, 'side' => 'after', 'commit' => 'aaaaaaabbbbbbb',
        ])->assertOk();

        FemidaServer::tool(SubmitStepTool::class, [
            'run_id' => 1, 'case_id' => 1, 'step_index' => 1, 'status' => 'passed',
        ])->assertOk();

        FemidaServer::tool(FinishRunTool::class, ['run_id' => 1, 'status' => 'done'])->assertOk();
        $this->assertSame('passed', TestSession::query()->find(1)->status->value);

        // one more active case added — it is uncovered, "passed" dropped
        FemidaServer::tool(CreateCaseTool::class, [
            'title' => 'An extra case',
            'steps' => [['action' => 'Step', 'expected' => 'Result']],
        ])->assertOk();

        FemidaServer::tool(AddCasesToSessionTool::class, ['session_id' => 1, 'case_ids' => [2]])
            ->assertOk();

        $this->assertSame('not_passed', TestSession::query()->find(1)->status->value);
    }

    /** Re-finishing with the same status is idempotent, a change is rejected. */
    public function test_finish_run_idempotent_same_status_rejects_change(): void
    {
        FemidaServer::tool(CreateCaseTool::class, [
            'title' => 'Case',
            'steps' => [['action' => 'Step', 'expected' => 'Result']],
        ])->assertOk();

        FemidaServer::tool(CreateSessionTool::class, [
            'external_ref' => 'PROJ-9',
            'title' => 'Session',
            'head_commit' => 'aaaaaaabbbbbbb',
            'only_after' => true,
            'case_ids' => [1],
        ])->assertOk();

        FemidaServer::tool(StartRunTool::class, [
            'session_id' => 1, 'side' => 'after', 'commit' => 'aaaaaaabbbbbbb',
        ])->assertOk();

        FemidaServer::tool(FinishRunTool::class, ['run_id' => 1, 'status' => 'done'])
            ->assertOk();

        // a network retry of the same finish — ok, the status does not flip
        FemidaServer::tool(FinishRunTool::class, ['run_id' => 1, 'status' => 'done'])
            ->assertOk()->assertSee('"status":"done"');

        // the status of a finished run cannot be changed
        FemidaServer::tool(FinishRunTool::class, ['run_id' => 1, 'status' => 'failed', 'error' => 'was late'])
            ->assertSee('already finished as done');
    }

    /**
     * MCP project isolation: the "Register" project key (context from setUp)
     * cannot see or touch another project's resources — not by read, not by write.
     */
    public function test_project_isolation_hides_foreign_resources(): void
    {
        $strangerProject = Project::factory()->create(['name' => 'Foreign project']);
        $strangerProject->users()->attach(
            User::factory()->create()->id,
            ['role' => ProjectRole::Developer->value]
        );

        $foreignCase = \App\Models\TestCase::factory()->create([
            'project_id' => $strangerProject->id,
            'steps' => [['action' => 'Step', 'expected' => 'Result']],
        ]);
        $foreignSession = TestSession::factory()->create([
            'project_id' => $strangerProject->id,
            'head_commit' => 'deadbeefdeadbeef',
        ]);
        $foreignSession->cases()->attach($foreignCase->id, ['position' => 1]);

        // reading another project's data — "not found", existence not disclosed
        FemidaServer::tool(GetCaseTool::class, ['case_id' => $foreignCase->id])
            ->assertSee("Case #{$foreignCase->id} not found");
        FemidaServer::tool(GetSessionTool::class, ['session_id' => $foreignSession->id])
            ->assertSee("Session #{$foreignSession->id} not found");

        // list_cases does not return another project's cases
        FemidaServer::tool(ListCasesTool::class, ['status' => 'all'])
            ->assertOk()
            ->assertDontSee($foreignCase->title);

        // writing into another project's session is impossible via every path
        FemidaServer::tool(AddCasesToSessionTool::class, [
            'session_id' => $foreignSession->id,
            'case_ids' => [$foreignCase->id],
        ])->assertSee("Session #{$foreignSession->id} not found");

        FemidaServer::tool(StartRunTool::class, [
            'session_id' => $foreignSession->id,
            'side' => 'after',
            'commit' => 'deadbeefdeadbeef',
        ])->assertSee("Session #{$foreignSession->id} not found");

        FemidaServer::tool(SetCaseSuggestionTool::class, [
            'session_id' => $foreignSession->id,
            'case_id' => $foreignCase->id,
            'suggestion' => 'pass',
        ])->assertSee("Session #{$foreignSession->id} not found");

        // the other project's session is unchanged: one case, zero runs,
        // no agent suggestion set
        $this->assertSame(1, $foreignSession->cases()->count());
        $this->assertSame(0, $foreignSession->runs()->count());
        $this->assertNull(SessionCase::query()
            ->where('session_id', $foreignSession->id)
            ->first()->agent_suggestion);
    }

    /**
     * The onboarding resource: registered, readable, containing
     * everything a new project needs to assemble the two skills.
     */
    public function test_onboarding_resource_is_readable(): void
    {
        FemidaServer::resources()
            ->assertRegistered(OnboardingResource::class);

        FemidaServer::resource(OnboardingResource::class)
            ->assertOk()
            ->assertSee([
                'femida-write-cases',
                'femida-run-session',
                'femida.mjs',
                'drive.mjs',
                'femida.yaml',
                'FEMIDA_URL',
                'submit_step',
                'set_case_suggestion',
                'PLAYWRIGHT_PATH',
                '429',
                'Retry-After',
            ]);
    }

    /**
     * Instructions point a new project at the onboarding resource.
     */
    public function test_instructions_point_to_onboarding_resource(): void
    {
        $instructions = (new \ReflectionClass(FemidaServer::class))
            ->getAttributes(Instructions::class)[0]
            ->getArguments()[0];

        $this->assertStringContainsString('femida://onboarding', $instructions);
    }

    /**
     * A screenshot disk-write failure reaches the agent as text via fail(),
     * not as "internal error" — the tool catches the narrow
     * ScreenshotStorageException instead of a blanket HttpException.
     */
    public function test_screenshot_disk_failure_reaches_agent_as_text(): void
    {
        $case = \App\Models\TestCase::factory()->create([
            'project_id' => $this->project->id,
            'steps' => [['action' => 'Step', 'expected' => 'Ok']],
        ]);
        $session = TestSession::factory()->create([
            'project_id' => $this->project->id,
            'only_after' => true,
            'head_commit' => 'cccccccddddddd',
        ]);
        $session->cases()->attach($case->id, ['position' => 1]);

        FemidaServer::tool(StartRunTool::class, [
            'session_id' => $session->id, 'side' => 'after', 'commit' => 'cccccccddddddd',
        ]);

        $broken = \Mockery::mock(Storage::disk('screenshots'))->makePartial();
        $broken->shouldReceive('put')->andReturn(false);
        Storage::shouldReceive('disk')->with('screenshots')->andReturn($broken);

        FemidaServer::tool(SubmitStepTool::class, [
            'run_id' => 1,
            'case_id' => $case->id,
            'step_index' => 1,
            'status' => 'passed',
            'screenshot_base64' => $this->pngBase64(),
        ])->assertSee('Failed to store screenshot');
    }
}
