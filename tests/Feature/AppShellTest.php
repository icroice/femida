<?php

namespace Tests\Feature;

use App\Enums\ProjectRole;
use App\Models\Project;
use App\Models\TestCase as TestCaseModel;
use App\Models\TestSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The v2 app shell (006): header with module tabs, dark module rail,
 * project switcher, profile menu. Outside a project the shell degrades
 * to the bare header (home is the picker).
 */
class AppShellTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $developer;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create(['name' => 'Igor']);
        $this->developer = User::factory()->create(['name' => 'Dmitry K.']);

        $this->project = Project::factory()->create(['name' => 'Register']);
        $this->project->users()->attach($this->developer->id, ['role' => ProjectRole::Developer->value]);
    }

    public function test_module_rail_lists_sections_with_counts(): void
    {
        TestCaseModel::factory()->count(3)->create(['project_id' => $this->project->id]);
        TestSession::factory()->count(2)->create(['project_id' => $this->project->id]);

        $html = $this->actingAs($this->developer)
            ->get(route('projects.cases.index', $this->project))
            ->assertOk()
            ->getContent();

        // the rail (dark panel) and both qa sections are rendered
        $this->assertStringContainsString('app-sidebar', $html);
        $this->assertStringContainsString(route('projects.cases.index', $this->project), $html);
        $this->assertStringContainsString(route('projects.sessions.index', $this->project), $html);

        // section counters (006 decision): entity totals
        $slug = $this->project->slug;
        $this->assertSame(1, preg_match('#<a href="[^"]*/projects/'.$slug.'/cases"[^>]*>.*?<span>Cases</span>.*?</a>#s', $html, $caseItem));
        $this->assertStringContainsString('>3</span>', $caseItem[0]);
        $this->assertSame(1, preg_match('#<a href="[^"]*/projects/'.$slug.'/sessions"[^>]*>.*?<span>Sessions</span>.*?</a>#s', $html, $sessionItem));
        $this->assertStringContainsString('>2</span>', $sessionItem[0]);
    }

    public function test_module_tab_is_marked_current_for_the_whole_module(): void
    {
        $html = $this->actingAs($this->developer)
            ->get(route('projects.sessions.index', $this->project))
            ->assertOk()
            ->getContent();

        // exactly one module tab nav, current on every page of the module
        $this->assertSame(1, preg_match_all('#<nav [^>]*aria-label="Modules"#', $html));
        $slug = $this->project->slug;
        $this->assertSame(1, preg_match('#<a href="[^"]*/projects/'.$slug.'/cases"\s+aria-current="page"#', $html));
    }

    public function test_shell_degrades_to_bare_header_outside_a_project(): void
    {
        // /help redirects to its first section — use the section page directly
        foreach ([route('home'), route('users.index'), route('profile'), route('help.show', 'overview')] as $url) {
            $html = $this->actingAs($this->admin)->get($url)->assertOk()->getContent();

            $this->assertStringNotContainsString('app-sidebar', $html, $url);
            $this->assertSame(0, preg_match('#<nav [^>]*aria-label="Modules"#', $html), $url);
        }
    }

    public function test_project_switcher_lists_projects_and_admin_extras(): void
    {
        $other = Project::factory()->create(['name' => 'Atlas']);
        $this->project->users()->attach($this->admin->id, ['role' => ProjectRole::Admin->value]);

        $memberHtml = $this->actingAs($this->developer)
            ->get(route('projects.cases.index', $this->project))
            ->assertOk()
            ->getContent();

        // a plain member switches between their projects only: no foreign
        // projects, no project settings, no project creation
        $this->assertStringContainsString(route('projects.cases.index', $this->project), $memberHtml);
        $this->assertStringNotContainsString(route('projects.cases.index', $other), $memberHtml);
        $this->assertStringNotContainsString(route('projects.settings', $this->project), $memberHtml);
        $this->assertStringNotContainsString(route('projects.create'), $memberHtml);

        $adminHtml = $this->actingAs($this->admin)
            ->get(route('projects.cases.index', $this->project))
            ->assertOk()
            ->getContent();

        // the project admin opens settings from the switcher (006 decision);
        // the instance admin also creates projects
        $this->assertStringContainsString(route('projects.settings', $this->project), $adminHtml);
        $this->assertStringContainsString(route('projects.create'), $adminHtml);
    }

    public function test_profile_menu_holds_users_link_for_instance_admin_only(): void
    {
        $developerHtml = $this->actingAs($this->developer)
            ->get(route('home'))
            ->assertOk()
            ->getContent();
        $this->assertStringNotContainsString(route('users.index'), $developerHtml);

        $adminHtml = $this->actingAs($this->admin)
            ->get(route('home'))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString(route('users.index'), $adminHtml);
        $this->assertStringContainsString(route('help.index'), $adminHtml);
        $this->assertStringContainsString(route('logout'), $adminHtml);
    }
}
