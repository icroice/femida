<?php

namespace Tests\Feature;

use App\Enums\ProjectRole;
use App\Livewire\UsersAdmin;
use App\Models\Project;
use App\Models\TestCase as TestCaseModel;
use App\Models\TestSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AccessControlTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $owner;

    private User $developer;

    private User $tester;

    private User $stranger;

    private Project $projectA;

    private Project $projectB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->owner = User::factory()->create();
        $this->developer = User::factory()->create();
        $this->tester = User::factory()->create();
        $this->stranger = User::factory()->create();

        $this->projectA = Project::factory()->create(['name' => 'Register']);
        $this->projectB = Project::factory()->create(['name' => 'Internal Portal']);

        $this->projectA->users()->attach($this->owner->id, ['role' => ProjectRole::Owner->value]);
        $this->projectA->users()->attach($this->developer->id, ['role' => ProjectRole::Developer->value]);
        $this->projectA->users()->attach($this->tester->id, ['role' => ProjectRole::Tester->value]);
    }

    public function test_home_lists_only_own_projects(): void
    {
        $this->actingAs($this->developer)
            ->get('/')
            ->assertOk()
            ->assertSee('Register')
            ->assertDontSee('Internal Portal');
    }

    public function test_admin_sees_all_projects(): void
    {
        $this->actingAs($this->admin)
            ->get('/')
            ->assertOk()
            ->assertSee('Register')
            ->assertSee('Internal Portal');
    }

    public function test_stranger_sees_no_projects(): void
    {
        $this->actingAs($this->stranger)
            ->get('/')
            ->assertOk()
            ->assertSee(__('app.projects_empty'));
    }

    public function test_case_management_matrix(): void
    {
        $caseA = TestCaseModel::factory()->create(['project_id' => $this->projectA->id]);

        $this->assertTrue($this->admin->can('create', [TestCaseModel::class, $this->projectA]));
        $this->assertTrue($this->developer->can('create', [TestCaseModel::class, $this->projectA]));
        // a tester creates and edits cases but does not archive
        $this->assertTrue($this->tester->can('create', [TestCaseModel::class, $this->projectA]));
        $this->assertTrue($this->tester->can('update', $caseA));
        $this->assertFalse($this->tester->can('delete', $caseA));
        $this->assertFalse($this->stranger->can('create', [TestCaseModel::class, $this->projectA]));
        $this->assertFalse($this->developer->can('create', [TestCaseModel::class, $this->projectB]));
    }

    public function test_session_and_verdict_matrix(): void
    {
        $session = TestSession::factory()->create(['project_id' => $this->projectA->id]);

        // creating/driving sessions — developer
        $this->assertTrue($this->developer->can('create', [TestSession::class, $this->projectA]));
        $this->assertFalse($this->tester->can('create', [TestSession::class, $this->projectA]));

        // verdict — any member: it belongs to a human
        $this->assertTrue($this->tester->can('verdict', $session));
        $this->assertTrue($this->developer->can('verdict', $session));
        $this->assertFalse($this->stranger->can('verdict', $session));
    }

    // test_api_key_management_is_developer_only was removed together with the dead
    // ApiKeyPolicy::viewAny/create: key management lives in the Livewire components
    // (ProjectKeys/ProfileKeys) with their own authorization checks

    public function test_project_creation_is_admin_only(): void
    {
        $this->assertFalse($this->developer->can('create', Project::class));
        $this->assertFalse($this->tester->can('create', Project::class));
        $this->assertTrue($this->admin->can('create', Project::class));
    }

    public function test_owner_matrix(): void
    {
        // project settings — owner and admin, not a developer
        $this->assertTrue($this->owner->can('update', $this->projectA));
        $this->assertFalse($this->developer->can('update', $this->projectA));
        $this->assertFalse($this->owner->can('update', $this->projectB));

        // the owner inherits developer rights in their project
        $this->assertTrue($this->owner->isDeveloper($this->projectA));
        $this->assertTrue($this->owner->can('create', [TestCaseModel::class, $this->projectA]));

        // the "Users" section: admin, owner; a developer — no
        $this->assertTrue($this->admin->can('viewAny', User::class));
        $this->assertTrue($this->owner->can('viewAny', User::class));
        $this->assertFalse($this->developer->can('viewAny', User::class));
    }

    public function test_users_page_scoping_for_owner(): void
    {
        // the owner sees only members of their own projects
        $this->actingAs($this->owner)
            ->get('/users')
            ->assertOk()
            ->assertSee($this->developer->name)
            ->assertSee($this->tester->name)
            ->assertDontSee($this->stranger->name);

        // a developer does not pass
        $this->actingAs($this->developer)
            ->get('/users')
            ->assertForbidden();
    }

    public function test_owner_manages_membership_but_not_owners(): void
    {
        // the owner adds a tester to their project
        Livewire::actingAs($this->owner)
            ->test(UsersAdmin::class)
            ->call('setMemberRole', $this->stranger->id, $this->projectA->id, 'tester')
            ->assertHasNoErrors();
        $this->assertSame(ProjectRole::Tester, $this->stranger->fresh()->roleIn($this->projectA));

        // the owner cannot assign the owner role
        Livewire::actingAs($this->owner)
            ->test(UsersAdmin::class)
            ->call('setMemberRole', $this->stranger->id, $this->projectA->id, 'owner');
        $this->assertSame(ProjectRole::Tester, $this->stranger->fresh()->roleIn($this->projectA));

        // the owner does not touch another project
        Livewire::actingAs($this->owner)
            ->test(UsersAdmin::class)
            ->call('setMemberRole', $this->stranger->id, $this->projectB->id, 'tester');
        $this->assertNull($this->stranger->fresh()->roleIn($this->projectB));

        // '' removes access
        Livewire::actingAs($this->owner)
            ->test(UsersAdmin::class)
            ->call('setMemberRole', $this->stranger->id, $this->projectA->id, '');
        $this->assertNull($this->stranger->fresh()->roleIn($this->projectA));

        // the owner creates a user directly in their project
        Livewire::actingAs($this->owner)
            ->test(UsersAdmin::class)
            ->set('name', 'New person')
            ->set('email', 'new@example.com')
            ->set('password', 'secret12345')
            ->set('roles', [$this->projectA->id => 'developer'])
            ->call('createUser')
            ->assertHasNoErrors();
        $newUser = User::query()->where('email', 'new@example.com')->first();
        $this->assertSame(ProjectRole::Developer, $newUser->fresh()->roleIn($this->projectA));
    }
}
