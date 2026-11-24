<?php

namespace Tests\Feature;

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

    public function test_the_board_is_open_to_any_member(): void
    {
        $this->assertTrue($this->access->check($this->developer, $this->project, 'board.view'));
        $this->assertTrue($this->access->check($this->developer, $this->project, 'board.move'));

        $this->assertFalse($this->access->check($this->outsider, $this->project, 'board.view'));
        $this->assertTrue($this->access->check($this->admin, $this->project, 'board.view'));
    }

    public function test_module_abilities_belong_to_members(): void
    {
        // modules are available to everyone (v2-18): any member holds
        // all verbs of every registry module
        $this->assertTrue($this->access->check($this->developer, $this->project, 'qa.cases.view'));
        $this->assertTrue($this->access->check($this->developer, $this->project, 'qa.sessions.create'));

        $this->assertFalse($this->access->check($this->outsider, $this->project, 'qa.cases.view'));

        // unknown modules and malformed abilities grant nothing
        $this->assertFalse($this->access->check($this->developer, $this->project, 'backlog.tasks.view'));
        $this->assertFalse($this->access->check($this->developer, $this->project, 'qa'));
        $this->assertFalse($this->access->check($this->developer, $this->project, 'nope'));
    }

    public function test_project_settings_stay_admin_only(): void
    {
        $this->assertFalse($this->access->check($this->developer, $this->project, 'project.settings'));
        $this->assertFalse($this->access->check($this->developer, $this->project, 'project.team'));

        $this->assertTrue($this->access->check($this->admin, $this->project, 'project.settings'));
        $this->assertFalse($this->access->check($this->outsider, $this->project, 'project.settings'));
    }

    public function test_gate_bridge_routes_abilities_through_the_service(): void
    {
        $this->assertTrue($this->developer->can('access', [$this->project, 'qa.cases.update']));
        $this->assertFalse($this->developer->can('access', [$this->project, 'project.settings']));
        $this->assertFalse($this->outsider->can('access', [$this->project, 'qa.cases.update']));
        $this->assertTrue($this->admin->can('access', [$this->project, 'project.settings']));
    }

    public function test_qa_policies_resolve_through_the_engine(): void
    {
        // the v1 policies are wired through the engine
        $case = TestCaseModel::factory()->create(['project_id' => $this->project->id]);

        $this->assertTrue($this->developer->can('create', [TestCaseModel::class, $this->project]));
        $this->assertTrue($this->developer->can('update', $case));
        $this->assertFalse($this->outsider->can('update', $case));

        // the instance admin passes regardless (Gate::before)
        $this->assertTrue($this->admin->can('update', $case));
    }
}
