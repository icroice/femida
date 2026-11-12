<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SetupTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_visit_redirects_to_setup(): void
    {
        // /login redirects straight to the wizard
        $this->get('/login')->assertRedirect('/setup');

        // / goes through the auth redirect and lands on the wizard
        $this->followingRedirects()
            ->get('/')
            ->assertOk()
            ->assertSee(__('setup.admin_title'));
    }

    public function test_setup_form_renders(): void
    {
        $this->get('/setup')
            ->assertOk()
            ->assertSee(__('setup.admin_title'))
            ->assertSee(__('setup.checks.storage'))
            ->assertSee(__('setup.submit'))
            // English is preselected by default
            ->assertSee('name="locale" value="en" class="radio radio-primary radio-sm" checked', false);
    }

    public function test_setup_creates_admin_and_logs_in(): void
    {
        $response = $this->post('/setup', [
            'name' => 'Igor',
            'email' => 'igor@example.team',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
            'locale' => 'ru',
        ]);

        $response->assertRedirect('/');

        $user = User::query()->sole();
        $this->assertTrue($user->is_admin);
        $this->assertSame('ru', $user->locale);
        $this->assertAuthenticatedAs($user);
    }

    public function test_setup_validates_input(): void
    {
        $this->post('/setup', [
            'name' => '',
            'email' => 'not-an-email',
            'password' => 'short',
            'password_confirmation' => 'different',
            'locale' => 'de',
        ])->assertSessionHasErrors(['name', 'email', 'password', 'locale']);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_setup_locked_after_first_user(): void
    {
        User::factory()->create();

        $this->get('/setup')->assertRedirect('/login');
        $this->post('/setup', [])->assertRedirect('/login');
    }
}
