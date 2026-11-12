<?php

namespace Tests\Feature;

use App\Enums\ProjectRole;
use App\Enums\RunSide;
use App\Enums\StepStatus;
use App\Enums\Verdict;
use App\Models\ApiKey;
use App\Models\Project;
use App\Models\Run;
use App\Models\SessionCase;
use App\Models\TestCase as TestCaseModel;
use App\Models\TestSession;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DataModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_membership_and_roles(): void
    {
        $project = Project::factory()->create();
        $dev = User::factory()->create();
        $tester = User::factory()->create();

        $project->users()->attach($dev->id, ['role' => ProjectRole::Developer->value]);
        $project->users()->attach($tester->id, ['role' => ProjectRole::Tester->value]);

        $this->assertSame(ProjectRole::Developer, $dev->projects->first()->pivot->role);
        $this->assertSame(ProjectRole::Tester, $project->users->find($tester->id)->pivot->role);
    }

    public function test_user_name_is_unique_in_db(): void
    {
        // uniqueness is duplicated at the DB level — form validation
        // does not protect against concurrent creation
        User::factory()->create(['name' => 'igor']);

        $this->expectException(QueryException::class);
        User::factory()->create(['name' => 'igor']);
    }

    public function test_api_key_issue_find_revoke(): void
    {
        $project = Project::factory()->create();
        $owner = User::factory()->create();

        [$key, $token] = ApiKey::issue($project, $owner, 'dev-dk');
        $this->assertStringStartsWith('fem_', $token);

        $found = ApiKey::findByToken($token);
        $this->assertNotNull($found);
        $this->assertTrue($found->owner()->is($owner));
        $this->assertSame(hash('sha256', $token), $found->token_hash);

        $key->revoke();
        $this->assertNull(ApiKey::findByToken($token));
        $this->assertTrue($key->refresh()->isRevoked());
    }

    public function test_case_steps_roundtrip(): void
    {
        $case = TestCaseModel::factory()->create();

        $this->assertIsArray($case->steps);
        $this->assertSame('action', array_key_first($case->steps[0]));
    }

    public function test_session_attaches_cases_with_verdict(): void
    {
        $session = TestSession::factory()->create();
        $case = TestCaseModel::factory()->create(['project_id' => $session->project_id]);
        $maria = User::factory()->create();

        $session->cases()->attach($case->id, [
            'position' => 1,
            'agent_suggestion' => Verdict::Fail->value,
            'verdict' => Verdict::Fail->value,
            'verdict_by' => $maria->id,
            'verdict_at' => now(),
        ]);

        $pivot = $session->cases->first()->pivot;

        $this->assertSame(Verdict::Fail, $pivot->verdict);
        $this->assertSame(Verdict::Fail, $pivot->agent_suggestion);
        $this->assertTrue($pivot->verdictBy()->is($maria));
    }

    public function test_run_steps_and_screenshots_cascade(): void
    {
        Storage::fake('screenshots');

        $session = TestSession::factory()->create();
        $case = TestCaseModel::factory()->create(['project_id' => $session->project_id]);
        $session->cases()->attach($case->id, ['position' => 1]);
        $sessionCase = SessionCase::query()
            ->where('session_id', $session->id)
            ->where('case_id', $case->id)
            ->first();

        $run = Run::factory()->create(['session_id' => $session->id]);
        $step = $run->stepResults()->create([
            'session_case_id' => $sessionCase->id,
            'step_index' => 1,
            'status' => StepStatus::Passed,
        ]);

        $path = 'session-1/after/case-1/step-1.png';
        Storage::disk('screenshots')->put($path, 'png-bytes');
        $step->screenshots()->create([
            'path' => $path,
            'width' => 1280,
            'height' => 800,
            'bytes' => 9,
            'captured_at' => now(),
        ]);

        $this->assertSame(1, $step->screenshots()->count());
        $this->assertTrue($session->run(RunSide::After)->is($run));

        $session->delete();

        $this->assertDatabaseCount('runs', 0);
        $this->assertDatabaseCount('step_results', 0);
        $this->assertDatabaseCount('screenshots', 0);
    }

    public function test_case_used_in_session_cannot_be_deleted(): void
    {
        $session = TestSession::factory()->create();
        $case = TestCaseModel::factory()->create(['project_id' => $session->project_id]);
        $session->cases()->attach($case->id, ['position' => 1]);

        $this->expectException(QueryException::class);
        $case->delete();
    }
}
