<?php

namespace Tests\Feature;

use App\Enums\CaseStatus;
use App\Enums\ProjectRole;
use App\Enums\RunSide;
use App\Enums\SessionStatus;
use App\Enums\StepStatus;
use App\Enums\Verdict;
use App\Livewire\CaseEditor;
use App\Livewire\CasesBrowser;
use App\Livewire\ProfileKeys;
use App\Livewire\ProjectKeys;
use App\Livewire\ProjectSettings;
use App\Livewire\SessionReview;
use App\Livewire\UsersAdmin;
use App\Models\ApiKey;
use App\Models\Project;
use App\Models\Run;
use App\Models\Screenshot;
use App\Models\SessionCase;
use App\Models\TestCase as TestCaseModel;
use App\Models\TestSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class UiPagesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $developer;

    private User $tester;

    private User $stranger;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('screenshots');

        $this->admin = User::factory()->admin()->create(['name' => 'Igor']);
        $this->developer = User::factory()->create(['name' => 'Dmitry K.']);
        $this->tester = User::factory()->create(['name' => 'Maria T.']);
        $this->stranger = User::factory()->create();

        $this->project = Project::factory()->create(['name' => 'Register']);
        $this->project->users()->attach($this->developer->id, ['role' => ProjectRole::Developer->value]);
        $this->project->users()->attach($this->tester->id, ['role' => ProjectRole::Tester->value]);
    }

    /*
     * ── Cases ───────────────────────────────────────────────────────────
     */

    public function test_cases_page_matrix(): void
    {
        TestCaseModel::factory()->create(['project_id' => $this->project->id, 'title' => 'Partial cancellation']);

        $this->actingAs($this->developer)
            ->get(route('projects.cases.index', $this->project))
            ->assertOk()
            ->assertSee('Partial cancellation')
            ->assertSee(__('cases.new'));

        $this->actingAs($this->tester)
            ->get(route('projects.cases.index', $this->project))
            ->assertOk()
            ->assertSee('Partial cancellation')
            ->assertSee(__('cases.new')); // testers create cases

        $this->actingAs($this->stranger)
            ->get(route('projects.cases.index', $this->project))
            ->assertNotFound();
    }

    public function test_developer_creates_case_via_editor(): void
    {
        Livewire::actingAs($this->developer)
            ->test(CaseEditor::class, ['project' => $this->project])
            ->set('title', 'Card refund')
            ->set('steps', [['action' => 'Open the history', 'expected' => 'the refund is visible']])
            ->set('status', 'active')
            ->call('save')
            ->assertRedirect(route('projects.cases.index', $this->project));

        $case = TestCaseModel::query()->sole();
        $this->assertSame('active', $case->status->value);
    }

    /*
     * ── Session review ──────────────────────────────────────────────────
     */

    private function sessionWithEvidence(): TestSession
    {
        $case = TestCaseModel::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'Partial cancellation: recalculating the total',
            'steps' => [['action' => 'Check the total', 'expected' => 'recalculated']],
        ]);

        $session = TestSession::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->developer->id,
            'base_commit' => 'aaaaaaabbbbbbb',
            'head_commit' => 'cccccccddddddd',
        ]);
        $session->cases()->attach($case->id, ['position' => 1, 'agent_suggestion' => 'fail', 'agent_notes' => 'the total was not recalculated']);
        $sessionCase = SessionCase::query()->where('session_id', $session->id)->first();

        foreach ([RunSide::Before, RunSide::After] as $side) {
            $run = Run::factory()->create(['session_id' => $session->id, 'side' => $side]);
            $step = $run->stepResults()->create([
                'session_case_id' => $sessionCase->id,
                'step_index' => 1,
                'status' => StepStatus::Passed,
            ]);
            $path = "session-{$session->id}/{$side->value}/case-{$case->id}/step-01.png";
            Storage::disk('screenshots')->put($path, 'png');
            $step->screenshots()->create(['path' => $path, 'width' => 640, 'height' => 480, 'bytes' => 3, 'captured_at' => now()]);
        }

        return $session->refresh();
    }

    public function test_cases_grouped_by_folders(): void
    {
        TestCaseModel::factory()->create([
            'project_id' => $this->project->id,
            'folder' => 'Checkout/Payment',
            'title' => 'Card payment',
            'status' => CaseStatus::Active,
        ]);
        TestCaseModel::factory()->create([
            'project_id' => $this->project->id,
            'folder' => null,
            'title' => 'Root case',
            'status' => CaseStatus::Active,
        ]);

        // folder rail with two levels
        $this->actingAs($this->tester)
            ->get(route('projects.cases.index', $this->project))
            ->assertOk()
            ->assertSee('Checkout')
            ->assertSee('Payment');

        // a top-level folder filter pulls in the subfolder, the root is excluded
        $this->actingAs($this->tester)
            ->get(route('projects.cases.index', ['project' => $this->project, 'folder' => 'Checkout']))
            ->assertOk()
            ->assertSee('Card payment')
            ->assertDontSee('Root case');

        // the editor stores the normalized path
        Livewire::actingAs($this->developer)
            ->test(CaseEditor::class, ['project' => $this->project])
            ->set('title', 'New with a folder')
            ->set('folder', ' Checkout / Refund ')
            ->set('steps', [['action' => 'action', 'expected' => 'result']])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('test_cases', [
            'title' => 'New with a folder',
            'folder' => 'Checkout/Refund',
        ]);
    }

    /**
     * Section tabs live in the layout header, outside every Livewire
     * component: wire updates never re-render them, so the active highlight
     * only has to be right on a full page load (route-based).
     */
    public function test_header_tab_highlights_the_current_section(): void
    {
        $project = $this->project;
        $tabClass = function (string $html, string $section) use ($project): string {
            $slug = $project->slug;
            $ok = preg_match('#<a href="[^"]*/projects/'.$slug.'/'.$section.'"\s+class="([^"]*)"#', $html, $m);

            return $ok === 1 ? $m[1] : '';
        };

        $cases = $this->actingAs($this->tester)
            ->get(route('projects.cases.index', $this->project))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('font-bold', $tabClass($cases, 'cases'));
        $this->assertStringNotContainsString('font-bold', $tabClass($cases, 'sessions'));

        $sessions = $this->actingAs($this->tester)
            ->get(route('projects.sessions.index', $this->project))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('font-bold', $tabClass($sessions, 'sessions'));
        $this->assertStringNotContainsString('font-bold', $tabClass($sessions, 'cases'));
    }

    /**
     * C1 (XSS regression): folder names are user strings; only an index may
     * reach the wire:click JS expression. The "malicious" folder is created
     * bypassing validation — like legacy data from before the rules tightened.
     */
    public function test_folder_rail_passes_index_not_name_to_click_handler(): void
    {
        TestCaseModel::factory()->create([
            'project_id' => $this->project->id,
            'folder' => "a');alert(1);('",
            'title' => 'Case in a tricky folder',
            'status' => CaseStatus::Active,
        ]);

        $component = Livewire::actingAs($this->tester)
            ->test(CasesBrowser::class, ['project' => $this->project]);

        // wire:click must carry no string arguments — only selectFolder(0)
        $this->assertStringNotContainsString("selectFolder('", $component->html());

        // the index click selects that very folder
        $component->call('selectFolder', 0)
            ->assertSet('folder', "a');alert(1);('");
    }

    public function test_review_screen_shows_pairs_and_accepts_verdict(): void
    {
        $session = $this->sessionWithEvidence();

        $this->actingAs($this->tester)
            ->get(route('sessions.show', [$this->project, $session]))
            ->assertOk()
            ->assertSee('Partial cancellation: recalculating the total')
            ->assertSee('the total was not recalculated');

        Livewire::actingAs($this->tester)
            ->test(SessionReview::class, ['project' => $this->project, 'session' => $session])
            ->set('verdictComment', 'Confirming: regression')
            ->call('setVerdict', 'fail')
            ->assertHasNoErrors()
            // the saved state is visible on the card, not only in the textarea
            ->assertSeeHtml('«Confirming: regression»');

        $pivot = SessionCase::query()->where('session_id', $session->id)->first();
        $this->assertSame(Verdict::Fail, $pivot->verdict);
        $this->assertSame($this->tester->id, $pivot->verdict_by);
        $this->assertSame('Confirming: regression', $pivot->verdict_comment);
    }

    /**
     * The verdict is a client string; Enum::from on garbage throws
     * ValueError (500). Unknown values are silently ignored.
     */
    public function test_review_ignores_unknown_verdict_value(): void
    {
        $session = $this->sessionWithEvidence();
        $sessionCase = SessionCase::query()->where('session_id', $session->id)->first();

        Livewire::actingAs($this->tester)
            ->test(SessionReview::class, ['project' => $this->project, 'session' => $session])
            ->call('setVerdict', 'definitely-not-a-verdict')
            ->assertHasNoErrors();

        $this->assertNull($sessionCase->refresh()->verdict);
    }

    /**
     * "Save and next" without a verdict keeps the typed comment —
     * it survives without a verdict.
     */
    public function test_verdict_comment_survives_navigation_without_verdict(): void
    {
        $session = $this->sessionWithEvidence();

        Livewire::actingAs($this->tester)
            ->test(SessionReview::class, ['project' => $this->project, 'session' => $session])
            ->set('verdictComment', 'looks like a regression, take another look')
            ->call('moveNextCase')
            ->assertHasNoErrors()
            ->assertSeeHtml('Comment without a verdict');

        $pivot = SessionCase::query()->where('session_id', $session->id)->first();
        $this->assertSame('looks like a regression, take another look', $pivot->verdict_comment);
        $this->assertNull($pivot->verdict);
    }

    public function test_numbered_pagination_on_list_pages(): void
    {
        TestCaseModel::factory()->count(25)->create([
            'project_id' => $this->project->id,
            'status' => CaseStatus::Active,
        ]);

        $this->actingAs($this->tester)
            ->get(route('projects.cases.index', $this->project))
            ->assertOk()
            ->assertSee('join-item')
            ->assertSee('aria-label="'.__('app.go_to_page', ['page' => 2]).'"', false);

        TestSession::factory()->count(25)->create([
            'project_id' => $this->project->id,
            'created_by' => $this->developer->id,
        ]);

        $this->actingAs($this->tester)
            ->get(route('projects.sessions.index', $this->project))
            ->assertOk()
            ->assertSee('join-item')
            ->assertSee('aria-label="'.__('app.next_page').'"', false);
    }

    public function test_review_pair_images_open_compare_modal(): void
    {
        $session = $this->sessionWithEvidence();

        Livewire::actingAs($this->tester)
            ->test(SessionReview::class, ['project' => $this->project, 'session' => $session])
            ->assertSee('openCompare(')
            ->assertSee('cursor-zoom-in')
            ->assertSee('cmp.open');
    }

    /**
     * Manually adding a case from the review screen: the agent missed one —
     * the developer adds it and the case is selected right away.
     */
    public function test_review_manually_attaches_case(): void
    {
        $session = $this->sessionWithEvidence();

        $extra = TestCaseModel::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'An extra payment case',
        ]);
        $inactive = TestCaseModel::factory()->inactive()->create([
            'project_id' => $this->project->id,
            'title' => 'Inactive payment case',
        ]);

        $component = Livewire::actingAs($this->developer)
            ->test(SessionReview::class, ['project' => $this->project, 'session' => $session->refresh()])
            ->set('attachQuery', 'payment')
            ->assertSee('An extra payment case')
            // inactive cases are not offered as candidates
            ->assertDontSee('Inactive payment case')
            ->call('attachCase', $extra->id)
            ->assertHasNoErrors();

        $pivot = SessionCase::query()->where('session_id', $session->id)->where('case_id', $extra->id)->first();
        $this->assertNotNull($pivot);
        $this->assertSame(2, $pivot->position);
        $this->assertSame($pivot->id, $component->get('selectedCaseId'));

        // adding a case to a "passed" session returns it to not_passed
        $session->update(['status' => SessionStatus::Passed]);
        $another = TestCaseModel::factory()->create(['project_id' => $this->project->id]);
        Livewire::actingAs($this->developer)
            ->test(SessionReview::class, ['project' => $this->project, 'session' => $session->refresh()])
            ->call('attachCase', $another->id)
            ->assertHasNoErrors();
        $this->assertNotNull(SessionCase::query()->where('case_id', $another->id)->first());
        $this->assertSame(
            SessionStatus::NotPassed,
            $session->refresh()->status,
            'a new case without results breaks the "passed" invariant',
        );
    }

    /**
     * Detaching a case from a session deletes its screenshot files too —
     * no orphans are left on disk.
     */
    public function test_removing_case_deletes_its_screenshot_files(): void
    {
        $session = $this->sessionWithEvidence();
        $sessionCase = SessionCase::query()->where('session_id', $session->id)->first();
        $paths = Screenshot::query()->pluck('path');
        $this->assertCount(2, $paths); // a base/head pair

        Livewire::actingAs($this->tester)
            ->test(SessionReview::class, ['project' => $this->project, 'session' => $session])
            ->call('removeCase', $sessionCase->id)
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('screenshots', ['path' => $paths->first()]);
        foreach ($paths as $path) {
            Storage::disk('screenshots')->assertMissing($path);
        }
    }

    public function test_review_marks_steps_without_screenshot(): void
    {
        $session = $this->sessionWithEvidence();

        // "after" arrived without a frame: the step exists but there is no evidence —
        // the reviewer must see it in the step list, not guess
        $afterRun = $session->runs->firstWhere('side.value', 'after');
        $afterRun->stepResults()->first()->screenshots()->delete();

        Livewire::actingAs($this->tester)
            ->test(SessionReview::class, ['project' => $this->project, 'session' => $session->refresh()])
            ->assertSeeHtml('title="'.__('sessions.no_screenshot'))
            ->assertSee(__('sessions.no_screenshot'));
    }

    /**
     * No manual status buttons — badges only (passed/not_passed
     * are stored, intermediate states are derived) plus resets.
     */
    public function test_review_status_badges_and_clear_actions(): void
    {
        $session = $this->sessionWithEvidence();

        Livewire::actingAs($this->tester)
            ->test(SessionReview::class, ['project' => $this->project, 'session' => $session])
            ->assertDontSeeHtml('setStatus(')
            ->assertSee(__('sessions.status_not_passed'))
            ->assertSeeHtml('copySummary')
            // tester: case reset yes, session reset no
            ->assertSeeHtml('clearCase(')
            ->assertDontSeeHtml('clearSession');

        Livewire::actingAs($this->developer)
            ->test(SessionReview::class, ['project' => $this->project, 'session' => $session->refresh()])
            ->assertSeeHtml('clearCase(')
            ->assertSeeHtml('wire:click="clearSession"');
    }

    public function test_copy_summary_writes_to_clipboard(): void
    {
        $session = $this->sessionWithEvidence();

        // the button dispatches summary-copied; an Alpine listener copies the text
        $component = Livewire::actingAs($this->tester)
            ->test(SessionReview::class, ['project' => $this->project, 'session' => $session])
            ->assertSee('summary-copied')
            ->assertSee('navigator.clipboard.writeText')
            ->call('copySummary')
            ->assertDispatched('summary-copied');

        // the summary contains a clickable link to the session page
        $this->assertStringContainsString(
            route('sessions.show', [$this->project, $session]),
            $component->instance()->summary(),
        );
    }

    public function test_screenshot_route_is_scoped(): void
    {
        $session = $this->sessionWithEvidence();
        $shot = Screenshot::query()->first();

        $this->actingAs($this->tester)
            ->get(route('screenshots.show', [$this->project, $shot]))
            ->assertOk();

        $this->actingAs($this->stranger)
            ->get(route('screenshots.show', [$this->project, $shot]))
            ->assertNotFound();
    }

    /*
     * ── Settings ────────────────────────────────────────────────────────
     */

    public function test_settings_matrix_and_key_lifecycle(): void
    {
        // project settings (both categories) — owner/admin; tester and developer do not pass
        $this->actingAs($this->tester)
            ->get(route('projects.settings', $this->project))
            ->assertForbidden();
        $this->actingAs($this->tester)
            ->get(route('projects.settings.keys', $this->project))
            ->assertForbidden();
        $this->actingAs($this->developer)
            ->get(route('projects.settings', $this->project))
            ->assertForbidden();

        // keys live in the profile (a separate page): a developer issues and revokes their own key
        $profile = Livewire::actingAs($this->developer)
            ->test(ProfileKeys::class)
            ->set('keyProject', $this->project->id)
            ->set('keyName', 'dev-dk')
            ->call('issueKey');

        $token = $profile->get('newToken');
        $this->assertStringStartsWith('fem_', $token);

        $key = ApiKey::findByToken($token);
        $this->assertNotNull($key);
        $this->assertSame($this->developer->id, $key->user_id);

        $profile->call('revokeKey', $key->id);
        $this->assertNull(ApiKey::findByToken($token));
    }

    /**
     * Settings categories rail: two pages per project and two in the profile,
     * the active item matches the route.
     */
    public function test_settings_pages_have_category_rail(): void
    {
        $pages = [
            ['get' => route('projects.settings', $this->project), 'active' => __('settings.section_main'), 'inactive' => __('settings.api_keys')],
            ['get' => route('projects.settings.keys', $this->project), 'active' => __('settings.api_keys'), 'inactive' => __('settings.section_main')],
            ['get' => route('profile'), 'active' => __('settings.section_main'), 'inactive' => __('profile.section_keys')],
            ['get' => route('profile.keys'), 'active' => __('profile.section_keys'), 'inactive' => __('settings.section_main')],
        ];

        foreach ($pages as $page) {
            $html = $this->actingAs($this->admin)->get($page['get'])->assertOk()->getContent();

            // the active rail item is highlighted, the neighbor is a plain link
            $this->assertMatchesRegularExpression(
                '/text-primary font-bold">\s*<span class="flex-1 truncate">'.preg_quote($page['active']).'<\/span>/',
                $html
            );
            $this->assertMatchesRegularExpression(
                '/text-base-content\/60 hover:bg-base-200\/50 hover:text-base-content">\s*<span class="flex-1 truncate">'.preg_quote($page['inactive']).'<\/span>/',
                $html
            );
        }
    }

    /** The project owner sees all project keys and may revoke any of them. */
    public function test_owner_manages_project_keys(): void
    {
        $owner = User::factory()->create(['name' => 'Line item']);
        $this->project->users()->attach($owner->id, ['role' => ProjectRole::Owner->value]);
        [, $token] = ApiKey::issue($this->project, $this->developer, 'dev-dk');

        $settings = Livewire::actingAs($owner)
            ->test(ProjectKeys::class, ['project' => $this->project])
            ->assertSee('dev-dk')
            ->assertSee($this->developer->name);

        $key = ApiKey::findByToken($token);

        $settings->call('revokeProjectKey', $key->id)
            ->assertHasNoErrors();

        $this->assertNull(ApiKey::findByToken($token));

        // revoking an already-revoked key is silently ignored
        $settings->call('revokeProjectKey', $key->id);
        $this->assertNotNull($key->refresh());
    }

    /** Deleting a project cleans screenshot files, not only DB records. */
    public function test_deleting_project_removes_screenshot_files(): void
    {
        $session = $this->sessionWithEvidence();
        $paths = Screenshot::query()->pluck('path');
        $projectId = $this->project->id;

        Livewire::actingAs($this->admin)
            ->test(ProjectSettings::class, ['project' => $this->project])
            ->call('deleteProject')
            ->assertRedirect(route('home'));

        $this->assertDatabaseMissing('projects', ['id' => $projectId]);
        $this->assertDatabaseMissing('test_sessions', ['id' => $session->id]);
        foreach ($paths as $path) {
            Storage::disk('screenshots')->assertMissing($path);
        }
    }

    /*
     * ── Users ───────────────────────────────────────────────────────────
     */

    public function test_users_admin_page_and_actions(): void
    {
        $this->actingAs($this->developer)
            ->get(route('users.index'))
            ->assertForbidden();

        // the project navigation does not disappear on the users page either,
        // the roles in the select box are translated
        $this->actingAs($this->admin)
            ->get(route('users.index'))
            ->assertOk()
            ->assertSee('Cases')
            ->assertSee(route('projects.cases.index', $this->project))
            ->assertSee('Developer')
            ->assertSee('Tester');

        Livewire::actingAs($this->admin)
            ->test(UsersAdmin::class)
            ->set('name', 'Olga Sh.')
            ->set('email', 'olga@example.team')
            ->set('password', 'strong-password')
            ->set('roles', [$this->project->id => 'tester'])
            ->call('createUser')
            ->assertHasNoErrors();

        $olga = User::query()->where('email', 'olga@example.team')->firstOrFail();
        $this->assertSame(ProjectRole::Tester, $olga->roleIn($this->project));

        Livewire::actingAs($this->admin)
            ->test(UsersAdmin::class)
            ->call('resetPassword', $olga->id)
            ->assertNotSet('generatedPassword', null);

        Livewire::actingAs($this->admin)
            ->test(UsersAdmin::class)
            ->call('toggleBlock', $olga->id);

        $this->assertTrue($olga->refresh()->isBlocked());

        // a blocked user cannot log in
        $this->post('/logout');
        $this->post('/login', ['email' => 'olga@example.team', 'password' => 'whatever-new'])
            ->assertSessionHasErrors('email');
    }

    /**
     * A password reset and blocking kill the user's active sessions
     * (database driver — rows of the sessions table) and null the remember token.
     */
    public function test_reset_password_and_block_purge_user_sessions(): void
    {
        $victim = User::factory()->create();
        $this->project->users()->attach($victim->id, ['role' => ProjectRole::Tester->value]);

        config(['session.driver' => 'database']);

        $seedSession = [
            'user_id' => $victim->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'test',
            'payload' => 'test',
            'last_activity' => time(),
        ];

        $victim->forceFill(['remember_token' => 'keep-me'])->saveQuietly();

        DB::table('sessions')->insert(['id' => 'row-reset'] + $seedSession);
        Livewire::actingAs($this->admin)
            ->test(UsersAdmin::class)
            ->call('resetPassword', $victim->id)
            ->assertNotSet('generatedPassword', null);

        $this->assertDatabaseMissing('sessions', ['id' => 'row-reset']);
        $this->assertNull($victim->refresh()->remember_token);

        // blocking does the same
        $victim->forceFill(['remember_token' => 'keep-me'])->saveQuietly();
        DB::table('sessions')->insert(['id' => 'row-block'] + $seedSession);

        Livewire::actingAs($this->admin)
            ->test(UsersAdmin::class)
            ->call('toggleBlock', $victim->id);

        $this->assertDatabaseMissing('sessions', ['id' => 'row-block']);
        $this->assertNull($victim->refresh()->remember_token);
    }

    /*
     * ── Projects ────────────────────────────────────────────────────────
     */

    public function test_only_admin_sees_create_project_button(): void
    {
        $this->actingAs($this->developer)
            ->get(route('home'))
            ->assertOk()
            ->assertDontSee(__('app.create_project'));

        $this->actingAs($this->admin)
            ->get(route('home'))
            ->assertOk()
            ->assertSee(__('app.create_project'));
    }

    public function test_admin_creates_project(): void
    {
        $this->actingAs($this->admin)
            ->post(route('projects.store'), [
                'name' => 'Internal Portal',
                'slug' => 'internal-portal',
                'description' => 'Tickets',
            ])
            ->assertRedirect();

        $project = Project::query()->where('slug', 'internal-portal')->firstOrFail();
        $this->assertSame('Internal Portal', $project->name);
    }

    /** The slug is typed explicitly: latin only, no auto-transliteration. */
    public function test_project_slug_is_validated(): void
    {
        $existing = Project::factory()->create(['slug' => 'taken-slug']);

        // "\u{418}\u{43c}..." spells a Cyrillic slug — non-latin input must be rejected
        foreach (['', "\u{418}\u{43c}\u{435}\u{435}\u{442}\u{441}\u{44f} \u{421}\u{43b}\u{430}\u{433}", 'UPPER', 'two words', '-leading', 'trailing-', 'a--b'] as $slug) {
            $this->actingAs($this->admin)
                ->post(route('projects.store'), ['name' => 'X', 'slug' => $slug])
                ->assertSessionHasErrors(['slug']);
        }

        // a slug already claimed by another project is rejected
        $this->actingAs($this->admin)
            ->post(route('projects.store'), ['name' => 'X', 'slug' => 'taken-slug'])
            ->assertSessionHasErrors(['slug']);

        $this->assertSame(1, Project::query()->where('slug', 'taken-slug')->count());
    }

    /*
     * ── Case status policy, @can on buttons ─────────────────────────────
     */

    /** Only a developer+ may change a case status via the form select. */
    public function test_case_status_field_writes_only_for_developers(): void
    {
        $case = TestCaseModel::factory()->create([
            'project_id' => $this->project->id,
            'status' => 'active',
        ]);

        // tester: the select is hidden, a smuggled value is ignored
        Livewire::actingAs($this->tester)
            ->test(CaseEditor::class, ['project' => $this->project, 'case' => $case])
            ->set('status', 'inactive')
            ->call('save')
            ->assertRedirect(route('projects.cases.index', $this->project));

        $this->assertSame('active', $case->refresh()->status->value);

        // developer (policy delete): the status change goes through
        Livewire::actingAs($this->developer)
            ->test(CaseEditor::class, ['project' => $this->project, 'case' => $case])
            ->set('status', 'inactive')
            ->call('save')
            ->assertRedirect(route('projects.cases.index', $this->project));

        $this->assertSame('inactive', $case->refresh()->status->value);
    }

    /** The Settings tab is owner-only; a member gets no click-403. */
    public function test_workspace_settings_tab_gated_by_owner_policy(): void
    {
        $this->actingAs($this->tester)
            ->get(route('projects.cases.index', $this->project))
            ->assertOk()
            ->assertDontSee(route('projects.settings', $this->project));

        $owner = User::factory()->create();
        $this->project->users()->attach($owner->id, ['role' => ProjectRole::Owner->value]);

        $this->actingAs($owner)
            ->get(route('projects.cases.index', $this->project))
            ->assertOk()
            ->assertSee(route('projects.settings', $this->project));
    }

    /** The block button is admin-only; a project owner does not see it. */
    public function test_block_button_hidden_for_project_owner(): void
    {
        $owner = User::factory()->create();
        $this->project->users()->attach($owner->id, ['role' => ProjectRole::Owner->value]);

        // the owner reaches the page (viewAny) but without the block button
        Livewire::actingAs($owner)
            ->test(UsersAdmin::class)
            ->assertOk()
            ->assertDontSee(__('users.block'));

        Livewire::actingAs($this->admin)
            ->test(UsersAdmin::class)
            ->assertOk()
            ->assertSee(__('users.block'));
    }

    /** Validation error texts are visible under the user-creation form fields. */
    public function test_users_admin_form_shows_error_texts(): void
    {
        Livewire::actingAs($this->admin)
            ->test(UsersAdmin::class)
            ->set('name', '')
            ->set('email', 'not-an-email')
            ->set('password', '123')
            ->call('createUser')
            ->assertHasErrors(['name', 'email', 'password'])
            ->assertSee('field is required'); // Laravel's default EN validation message

        $this->assertDatabaseCount('users', 4); // admin, developer, tester, stranger
    }

    /** The tracker summary is built from dictionaries — switch to RU, no hardcoded strings. */
    public function test_review_summary_is_localized(): void
    {
        $session = TestSession::factory()->create([
            'project_id' => $this->project->id,
            'base_commit' => 'aaaaaaabbbbbbb',
            'head_commit' => 'cccccccddddddd',
        ]);

        App::setLocale('ru');

        $component = Livewire::actingAs($this->tester)
            ->test(SessionReview::class, ['project' => $this->project, 'session' => $session]);

        $summary = $component->instance()->summary();
        // the suite default is EN: assert the summary follows the RU dictionary
        $this->assertStringContainsString(__('sessions.column_commits', [], 'ru'), $summary);
        $this->assertStringContainsString(__('sessions.column_author', [], 'ru'), $summary);
        $this->assertStringNotContainsString(__('sessions.column_commits', [], 'en'), $summary);
    }

    /** A session without cases shows an explicit empty state, not a blank page. */
    public function test_review_empty_state_without_cases(): void
    {
        $session = TestSession::factory()->create(['project_id' => $this->project->id]);

        Livewire::actingAs($this->tester)
            ->test(SessionReview::class, ['project' => $this->project, 'session' => $session])
            ->assertOk()
            ->assertSee(__('sessions.no_cases'));
    }
}
