<?php

namespace Tests\Feature;

use App\Http\Controllers\HelpController;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HelpPagesTest extends TestCase
{
    use RefreshDatabase;

    private User $member;

    protected function setUp(): void
    {
        parent::setUp();

        // a plain member without projects: the handbook is for every signed-in user
        $this->member = User::factory()->create();
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/help')->assertRedirect('/login');
        $this->get('/help/overview')->assertRedirect('/login');
    }

    public function test_index_redirects_to_the_first_section(): void
    {
        $this->actingAs($this->member)
            ->get('/help')
            ->assertRedirect('/help/overview');
    }

    public function test_every_section_renders_with_its_title(): void
    {
        foreach (HelpController::SECTIONS as $section) {
            $langKey = str_replace('-', '_', $section);

            $response = $this->actingAs($this->member)->get("/help/$section");

            $response->assertOk()
                ->assertSee(__('help.sections.'.$section))
                ->assertSee(__('help.'.$langKey.'.title'))
                ->assertSee(__('help.'.$langKey.'.lead'));
        }
    }

    public function test_unknown_section_is_not_found(): void
    {
        $this->actingAs($this->member)
            ->get('/help/unknown')
            ->assertNotFound();
    }

    public function test_rail_lists_every_section_and_points_to_the_next_one(): void
    {
        $response = $this->actingAs($this->member)->get('/help/quick-start');

        $response->assertOk();

        foreach (HelpController::SECTIONS as $section) {
            $response->assertSee('href="'.route('help.show', $section).'"', false);
        }

        // the active rail item is highlighted, the footer link leads to the next section
        $response->assertSee('bg-primary/10 text-primary font-bold')
            ->assertSee(__('help.next_section'))
            ->assertSee('href="'.route('help.show', 'case-library').'"', false);

        // the last section has no "next" link
        $this->actingAs($this->member)
            ->get('/help/roles')
            ->assertDontSee(__('help.next_section'));
    }

    public function test_the_header_book_icon_is_available_to_every_signed_in_user(): void
    {
        $this->actingAs($this->member)
            ->get(route('home'))
            ->assertOk()
            ->assertSee('href="'.route('help.index').'"', false)
            ->assertSee('title="'.__('app.help').'"', false);

        // and highlighted on the help pages themselves
        $this->actingAs($this->member)
            ->get('/help/overview')
            ->assertSee(__('app.help'));
    }
}
