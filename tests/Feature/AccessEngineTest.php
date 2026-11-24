<?php

namespace Tests\Feature;

use App\Models\ModuleState;
use App\Models\Project;
use App\Models\TestCase as TestCaseModel;
use App\Models\User;
use App\Support\Access\AccessService;
use App\Support\Access\Module;
use App\Support\Access\ModuleRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccessEngineTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $developer;

    private User $outsider;

    private Project $project;

    private AccessService $access;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->developer = User::factory()->create();
        $this->outsider = User::factory()->create();
        $this->project = Project::factory()->create();

        $this->project->users()->attach($this->developer->id, ['role' => 'developer']);

        $this->access = $this->app->make(AccessService::class);
    }

    public function test_registry_holds_the_qa_module(): void
    {
        $modules = ModuleRegistry::all();

        $this->assertCount(1, $modules);
        $this->assertInstanceOf(Module::class, $modules[0]);
        $this->assertSame('qa', $modules[0]->id);
        $this->assertSame(['cases', 'sessions'], $modules[0]->sections);
        $this->assertTrue($modules[0]->hasSection('sessions'));
        $this->assertFalse($modules[0]->hasSection('runs'));

        $this->assertNull(ModuleRegistry::find('backlog'));
        $this->assertFalse(ModuleRegistry::has('backlog'));
        // a module dropped from the registry shows as its id, never a 500
        $this->assertSame('backlog', ModuleRegistry::title('backlog'));
    }

    public function test_project_creation_seeds_qa_availability(): void
    {
        $state = $this->project->moduleStates->firstWhere('module', 'qa');

        $this->assertNotNull($state);
        $this->assertTrue($state->provided_by_admin);
        $this->assertTrue($state->enabled_by_org);
    }

    public function test_the_board_is_open_to_any_member(): void
    {
        $this->assertTrue($this->access->check($this->developer, $this->project, 'board.view'));
        $this->assertTrue($this->access->check($this->developer, $this->project, 'board.move'));

        $this->assertFalse($this->access->check($this->outsider, $this->project, 'board.view'));
        $this->assertTrue($this->access->check($this->admin, $this->project, 'board.view'));
    }

    public function test_module_abilities_follow_availability(): void
    {
        // seeded available: members hold all module verbs (v2-17 start)
        $this->assertTrue($this->access->check($this->developer, $this->project, 'qa.cases.view'));
        $this->assertTrue($this->access->check($this->developer, $this->project, 'qa.sessions.create'));

        // disabled by the project — the module disappears for members
        $this->disableQa();
        $this->access->flushMemo();
        $this->assertFalse($this->access->check($this->developer, $this->project, 'qa.cases.view'));

        // the board is core and stays
        $this->assertTrue($this->access->check($this->developer, $this->project, 'board.view'));

        // revoked by the instance admin — same effect
        $this->enableQa();
        $this->access->flushMemo();
        $this->assertTrue($this->access->check($this->developer, $this->project, 'qa.cases.view'));

        ModuleState::query()
            ->where('project_id', $this->project->id)
            ->where('module', 'qa')
            ->update(['provided_by_admin' => false]);
        $this->access->flushMemo();
        $this->assertFalse($this->access->check($this->developer, $this->project, 'qa.cases.view'));
    }

    public function test_module_availability_listing(): void
    {
        $this->assertSame(
            ['qa'],
            array_map(fn (Module $module) => $module->id, $this->access->availableModules($this->project)),
        );

        $this->disableQa();
        $this->access->flushMemo();
        $this->assertSame([], $this->access->availableModules($this->project));
    }

    public function test_outsiders_and_unknown_abilities_are_denied(): void
    {
        $this->assertFalse($this->access->check($this->outsider, $this->project, 'qa.cases.view'));
        $this->assertFalse($this->access->check($this->outsider, $this->project, 'project.settings'));

        // unknown modules and malformed abilities grant nothing
        $this->assertFalse($this->access->check($this->developer, $this->project, 'backlog.tasks.view'));
        $this->assertFalse($this->access->check($this->developer, $this->project, 'qa'));
        $this->assertFalse($this->access->check($this->developer, $this->project, 'nope'));
    }

    public function test_project_settings_stay_admin_only(): void
    {
        $this->assertFalse($this->access->check($this->developer, $this->project, 'project.settings'));
        $this->assertFalse($this->access->check($this->developer, $this->project, 'project.team'));
        $this->assertFalse($this->access->check($this->developer, $this->project, 'project.modules'));

        $this->assertTrue($this->access->check($this->admin, $this->project, 'project.settings'));
        $this->assertFalse($this->access->check($this->outsider, $this->project, 'project.settings'));
    }

    public function test_qa_policies_follow_module_availability(): void
    {
        // the v1 policies are wired through the engine: disabling the qa
        // module closes cases and sessions for members
        $case = TestCaseModel::factory()->create(['project_id' => $this->project->id]);

        $this->assertTrue($this->developer->can('create', [TestCaseModel::class, $this->project]));
        $this->assertTrue($this->developer->can('update', $case));

        $this->disableQa();
        $this->access->flushMemo();
        $this->assertFalse($this->developer->can('create', [TestCaseModel::class, $this->project]));
        $this->assertFalse($this->developer->can('update', $case));

        // the instance admin passes regardless (Gate::before)
        $this->assertTrue($this->admin->can('update', $case));
    }

    public function test_gate_bridge_routes_abilities_through_the_service(): void
    {
        $this->assertTrue($this->developer->can('access', [$this->project, 'qa.cases.update']));
        $this->assertFalse($this->developer->can('access', [$this->project, 'project.settings']));
        $this->assertFalse($this->outsider->can('access', [$this->project, 'qa.cases.update']));
        $this->assertTrue($this->admin->can('access', [$this->project, 'project.settings']));
    }

    public function test_availability_is_memoized_until_flushed(): void
    {
        $this->assertTrue($this->access->check($this->developer, $this->project, 'qa.cases.view'));

        $this->disableQa();

        // the memoized decision holds for the rest of the request…
        $this->assertTrue($this->access->check($this->developer, $this->project, 'qa.cases.view'));

        // …and the mutation owner flushes it
        $this->access->flushMemo();
        $this->assertFalse($this->access->check($this->developer, $this->project, 'qa.cases.view'));
    }

    private function disableQa(): void
    {
        $this->setQaState(enabled: false);
    }

    private function enableQa(): void
    {
        $this->setQaState(enabled: true);
    }

    private function setQaState(bool $enabled): void
    {
        ModuleState::query()
            ->where('project_id', $this->project->id)
            ->where('module', 'qa')
            ->update(['enabled_by_org' => $enabled]);
    }
}
