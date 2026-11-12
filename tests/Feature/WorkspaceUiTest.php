<?php

namespace Tests\Feature;

use App\Enums\ProjectRole;
use App\Enums\SessionStatus;
use App\Livewire\CaseEditor;
use App\Livewire\CasesBrowser;
use App\Livewire\ProfileSettings;
use App\Livewire\SessionReview;
use App\Livewire\SessionsBrowser;
use App\Livewire\UsersAdmin;
use App\Models\Project;
use App\Models\SessionCase;
use App\Models\TestCase as TestCaseModel;
use App\Models\TestSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Workspace UI: two-tier header, Livewire browsers, profile theme,
 * tester rights, name uniqueness.
 */
class WorkspaceUiTest extends TestCase
{
    use RefreshDatabase;

    private User $developer;

    private User $tester;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->developer = User::factory()->create(['name' => 'Igor']);
        $this->tester = User::factory()->create(['name' => 'Maria T.']);
        $this->project = Project::factory()->create(['name' => 'Register']);
        $this->project->users()->attach($this->developer->id, ['role' => ProjectRole::Developer->value]);
        $this->project->users()->attach($this->tester->id, ['role' => ProjectRole::Tester->value]);
    }

    /*
     * ── Theme ───────────────────────────────────────────────────────────
     */

    public function test_layout_applies_user_theme(): void
    {
        $this->actingAs($this->developer)
            ->get(route('home'))
            ->assertOk()
            ->assertSee('data-theme="femida"', false);

        $this->developer->forceFill(['theme' => 'dark'])->save();

        $this->actingAs($this->developer)
            ->get(route('home'))
            ->assertOk()
            ->assertSee('data-theme="femida-dark"', false);
    }

    public function test_theme_switches_from_profile_immediately(): void
    {
        Livewire::actingAs($this->developer)
            ->test(ProfileSettings::class)
            ->assertSet('theme', 'light')
            ->set('theme', 'dark');

        $this->assertSame('dark', $this->developer->fresh()->theme);
    }

    /*
     * ── Cases browser ───────────────────────────────────────────────────
     */

    public function test_cases_browser_folder_click_filters_table(): void
    {
        TestCaseModel::factory()->create(['project_id' => $this->project->id, 'title' => 'Login by code', 'folder' => 'Account']);
        TestCaseModel::factory()->create(['project_id' => $this->project->id, 'title' => 'City map', 'folder' => 'City']);

        Livewire::actingAs($this->developer)
            ->test(CasesBrowser::class, ['project' => $this->project])
            ->assertSee('Login by code')
            ->assertSee('City map')
            // folders are passed by index (C1): 0 = "Account" (alphabetical order)
            ->call('selectFolder', 0)
            ->assertSee('Login by code')
            ->assertDontSee('City map');
    }

    public function test_cases_browser_search_and_reset(): void
    {
        TestCaseModel::factory()->create(['project_id' => $this->project->id, 'title' => 'Login by code']);
        // "\u{041a}\u{0430}\u{0440}\u{0442}\u{0430} \u{0433}\u{043e}\u{0440}\u{043e}\u{0434}\u{0430}" is
        // Cyrillic for "City map": the point of this test is case-insensitive Cyrillic search
        TestCaseModel::factory()->create(['project_id' => $this->project->id, 'title' => "\u{041a}\u{0430}\u{0440}\u{0442}\u{0430} \u{0433}\u{043e}\u{0440}\u{043e}\u{0434}\u{0430}"]);

        $browser = Livewire::actingAs($this->developer)
            ->test(CasesBrowser::class, ['project' => $this->project]);

        $browser->set('q', "\u{041a}\u{0430}\u{0440}\u{0442}\u{0430}")
            ->assertSee("\u{041a}\u{0430}\u{0440}\u{0442}\u{0430} \u{0433}\u{043e}\u{0440}\u{043e}\u{0434}\u{0430}")
            ->assertDontSee('Login by code');

        $browser->call('resetFilters')
            ->assertSee('Login by code');
    }

    /*
     * ── Sessions browser ────────────────────────────────────────────────
     */

    public function test_sessions_browser_status_filter(): void
    {
        TestSession::factory()->create(['project_id' => $this->project->id, 'title' => 'Finished', 'status' => SessionStatus::Passed]);
        TestSession::factory()->create(['project_id' => $this->project->id, 'title' => 'Fresh', 'status' => SessionStatus::NotPassed]);

        Livewire::actingAs($this->developer)
            ->test(SessionsBrowser::class, ['project' => $this->project])
            ->assertSee('Finished')
            ->assertSee('Fresh')
            ->set('status', 'passed')
            ->assertSee('Finished')
            ->assertDontSee('Fresh');
    }

    public function test_sessions_browser_search_lowercase_cyrillic(): void
    {
        // SQLite LIKE is not case-insensitive for Cyrillic;
        // the search covers both cases of the first letter.
        // The title/query literals are Cyrillic via \u{} escapes (the search
        // behavior under test only exists for non-ASCII input).
        TestSession::factory()->create(['project_id' => $this->project->id, 'title' => "\u{0417}\u{0430}\u{0434}\u{0430}\u{0447}\u{0430} \u{043f}\u{0440}\u{043e} \u{043e}\u{043f}\u{043b}\u{0430}\u{0442}\u{0443}"]);
        TestSession::factory()->create(['project_id' => $this->project->id, 'title' => 'Another']);

        Livewire::actingAs($this->developer)
            ->test(SessionsBrowser::class, ['project' => $this->project])
            ->set('q', "\u{0437}\u{0430}\u{0434}\u{0430}\u{0447}\u{0430}")
            ->assertSee("\u{0417}\u{0430}\u{0434}\u{0430}\u{0447}\u{0430} \u{043f}\u{0440}\u{043e} \u{043e}\u{043f}\u{043b}\u{0430}\u{0442}\u{0443}")
            ->assertDontSee('Another');
    }

    public function test_sessions_browser_hides_zero_verdict_counters(): void
    {
        // no verdicts yet — zero counters are hidden, replaced by a quiet dash
        TestSession::factory()->create(['project_id' => $this->project->id, 'title' => 'Empty']);

        Livewire::actingAs($this->developer)
            ->test(SessionsBrowser::class, ['project' => $this->project])
            ->assertSee('Empty')
            ->assertDontSee('✓0')
            ->assertDontSee('✕0')
            ->assertDontSee('•0');
    }

    /*
     * ── Tester rights ───────────────────────────────────────────────────
     */

    public function test_tester_can_create_and_edit_cases(): void
    {
        $case = TestCaseModel::factory()->create(['project_id' => $this->project->id, 'title' => 'Old case']);

        // the editor pages are accessible to a tester
        $this->actingAs($this->tester)
            ->get(route('cases.create', $this->project))
            ->assertOk();
        $this->actingAs($this->tester)
            ->get(route('cases.edit', [$this->project, $case]))
            ->assertOk();

        // a tester creates a case with steps via the editor
        Livewire::actingAs($this->tester)
            ->test(CaseEditor::class, ['project' => $this->project])
            ->set('title', 'A tester case')
            ->set('steps', [['action' => 'Open the form', 'expected' => 'the form is visible']])
            ->set('status', 'active')
            ->call('save')
            ->assertRedirect(route('projects.cases.index', $this->project));

        $this->assertDatabaseHas('test_cases', ['title' => 'A tester case']);

        // and edits an existing one
        Livewire::actingAs($this->tester)
            ->test(CaseEditor::class, ['project' => $this->project, 'case' => $case])
            ->set('title', 'Renamed by a tester')
            ->call('save');

        $this->assertDatabaseHas('test_cases', ['id' => $case->id, 'title' => 'Renamed by a tester']);
    }

    public function test_tester_can_attach_and_remove_session_cases(): void
    {
        $caseA = TestCaseModel::factory()->create(['project_id' => $this->project->id, 'title' => 'Case A']);
        $caseB = TestCaseModel::factory()->create(['project_id' => $this->project->id, 'title' => 'Case B']);
        $session = TestSession::factory()->create(['project_id' => $this->project->id, 'title' => 'Review']);
        $session->cases()->attach($caseA->id, ['position' => 1]);
        $sessionCaseA = SessionCase::query()->where('session_id', $session->id)->where('case_id', $caseA->id)->first();

        $review = Livewire::actingAs($this->tester)
            ->test(SessionReview::class, ['project' => $this->project, 'session' => $session]);

        // addition
        $review->call('attachCase', $caseB->id);
        $this->assertDatabaseHas('session_case', ['session_id' => $session->id, 'case_id' => $caseB->id]);

        // removal
        $review->call('removeCase', $sessionCaseA->id);
        $this->assertDatabaseMissing('session_case', ['id' => $sessionCaseA->id]);
    }

    /**
     * No "session finished" gate — cases are added any time,
     * and a "passed" session returns to not_passed.
     */
    public function test_passed_session_accepts_attach_and_flips_status(): void
    {
        $case = TestCaseModel::factory()->create(['project_id' => $this->project->id]);
        $session = TestSession::factory()->create(['project_id' => $this->project->id, 'status' => SessionStatus::Passed]);

        Livewire::actingAs($this->tester)
            ->test(SessionReview::class, ['project' => $this->project, 'session' => $session])
            ->call('attachCase', $case->id);

        $this->assertDatabaseHas('session_case', ['session_id' => $session->id, 'case_id' => $case->id]);
        $this->assertTrue($session->refresh()->status === SessionStatus::NotPassed);
    }

    /*
     * ── Username uniqueness ─────────────────────────────────────────────
     */

    public function test_user_name_must_be_unique_on_create(): void
    {
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(UsersAdmin::class)
            ->set('name', $this->developer->name)
            ->set('email', 'dup@example.com')
            ->set('password', 'password123')
            ->call('createUser')
            ->assertHasErrors(['name']);

        $this->assertDatabaseMissing('users', ['email' => 'dup@example.com']);
    }

    public function test_profile_rename_to_existing_name_rejected(): void
    {
        Livewire::actingAs($this->developer)
            ->test(ProfileSettings::class)
            ->set('name', $this->tester->name)
            ->call('saveProfile')
            ->assertHasErrors(['name']);
    }
}
