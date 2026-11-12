<?php

namespace Tests\Feature;

use App\Enums\ProjectRole;
use App\Mcp\Servers\FemidaServer;
use App\Mcp\Tools\CreateCaseTool;
use App\Mcp\Tools\CreateSessionTool;
use App\Mcp\Tools\FinishRunTool;
use App\Mcp\Tools\StartRunTool;
use App\Mcp\Tools\SubmitStepTool;
use App\Models\ApiKey;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * API and UI edge cases: security headers, LIKE wildcard escaping,
 * the finish_run warning about missing steps, base64 length pre-check.
 */
class EdgeCasesTest extends TestCase
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
        [, $this->token] = ApiKey::issue($this->project, $owner, 'dev-low');
    }

    private function headers(): array
    {
        return ['Authorization' => 'Bearer '.$this->token];
    }

    /** The key MCP context (set by middleware in the HTTP flow). */
    private function bindMcpContext(): void
    {
        $key = ApiKey::findByToken($this->token);
        $request = new HttpRequest;
        $request->attributes->set('apiKey', $key);
        $request->attributes->set('apiProject', $this->project);
        $request->attributes->set('apiUser', $key->owner);
        $this->app->instance('request', $request);
    }

    public function test_security_headers_on_all_responses(): void
    {
        $this->get('/up')
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');

        // no HSTS without TLS — on localhost it would only break login
        $this->get('/up')->assertHeaderMissing('Strict-Transport-Security');
    }

    /**
     * /sessions uses standard Laravel pagination — data + links
     * + meta, same as /cases.
     */
    public function test_sessions_list_uses_standard_pagination_format(): void
    {
        $case = $this->project->testCases()->create([
            'title' => 'Case for the session list',
            'steps' => [['action' => 'Step', 'expected' => 'result']],
        ]);

        foreach ([1, 2] as $i) {
            $this->postJson('/api/v1/sessions', [
                'external_ref' => 'PROJ-30'.$i,
                'title' => 'Session '.$i,
                'base_commit' => 'aaaaaaabbbbbbb',
                'head_commit' => 'cccccccddddddd',
                'case_ids' => [$case->id],
            ], $this->headers())->assertCreated();
        }

        $this->getJson('/api/v1/sessions', $this->headers())
            ->assertOk()
            ->assertJsonStructure([
                'data' => [['id', 'external_ref', 'title', 'status']],
                'links' => ['first', 'last', 'prev', 'next'],
                'meta' => ['current_page', 'last_page', 'total', 'per_page'],
            ])
            ->assertJsonPath('meta.total', 2)
            ->assertJsonCount(2, 'data');
    }

    public function test_search_escapes_like_wildcards(): void
    {
        $cases = $this->project->testCases();
        $cases->create([
            'title' => '100% off the first order',
            'steps' => [['action' => 'Apply the discount', 'expected' => 'the total is recalculated']],
        ]);
        $cases->create([
            'title' => 'Card payment',
            'steps' => [['action' => 'Pay', 'expected' => 'paid']],
        ]);

        // "100%" is a literal percent sign, not an "any string" wildcard
        $this->getJson('/api/v1/cases?q='.urlencode('100%'), $this->headers())
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', '100% off the first order');

        // a bare % is a literal percent, not a wildcard:
        // only a case that actually contains it matches
        $this->getJson('/api/v1/cases?q=%25', $this->headers())
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', '100% off the first order');
    }

    public function test_finish_run_warns_about_cases_with_missing_steps(): void
    {
        $this->bindMcpContext();

        $caseId = FemidaServer::tool(CreateCaseTool::class, [
            'title' => 'Case with two steps',
            'steps' => [
                ['action' => 'Step 1', 'expected' => 'result 1'],
                ['action' => 'Step 2', 'expected' => 'result 2'],
            ],
        ])->assertOk();
        $caseId = 1; // RefreshDatabase: ids are deterministic within a test

        FemidaServer::tool(CreateSessionTool::class, [
            'external_ref' => 'PROJ-2001',
            'title' => 'Missing steps',
            'base_commit' => 'a3f9c12deadbeef',
            'head_commit' => 'f71e04bdeadbeef',
            'case_ids' => [$caseId],
        ])->assertOk();

        $runId = FemidaServer::tool(StartRunTool::class, [
            'session_id' => 1,
            'side' => 'after',
            'commit' => 'f71e04bdeadbeef',
        ])->assertOk();
        $runId = 1; // RefreshDatabase: the first run

        // the agent submitted only the first of two steps — the warning must surface at finish
        FemidaServer::tool(SubmitStepTool::class, [
            'run_id' => $runId,
            'case_id' => $caseId,
            'step_index' => 1,
            'status' => 'passed',
        ])->assertOk();

        FemidaServer::tool(FinishRunTool::class, [
            'run_id' => $runId,
            'status' => 'done',
        ])->assertOk()->assertSee('fewer submitted steps than the case defines');
    }

    public function test_submit_step_rejects_oversized_base64_before_decoding(): void
    {
        $this->bindMcpContext();

        $caseId = FemidaServer::tool(CreateCaseTool::class, [
            'title' => 'Case for an oversized base64',
            'steps' => [['action' => 'Step', 'expected' => 'result']],
        ])->assertOk();
        $caseId = 1; // RefreshDatabase: ids are deterministic within a test

        FemidaServer::tool(CreateSessionTool::class, [
            'external_ref' => 'PROJ-2002',
            'title' => 'Oversized base64',
            'base_commit' => 'a3f9c12deadbeef',
            'head_commit' => 'f71e04bdeadbeef',
            'case_ids' => [$caseId],
        ])->assertOk();

        $runId = FemidaServer::tool(StartRunTool::class, [
            'session_id' => 1,
            'side' => 'after',
            'commit' => 'f71e04bdeadbeef',
        ])->assertOk();
        $runId = 1; // RefreshDatabase: the first run

        // 7 MB of base64 would decode to >5 MB — reject before base64_decode,
        // without allocating memory for the whole input
        FemidaServer::tool(SubmitStepTool::class, [
            'run_id' => $runId,
            'case_id' => $caseId,
            'step_index' => 1,
            'status' => 'failed',
            'note' => 'no screenshot',
            'screenshot_base64' => str_repeat('A', 7_000_000),
        ])->assertSee('exceeds the 5 MB limit');
    }
}
