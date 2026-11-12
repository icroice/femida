<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // the setup wizard must be "completed" — at least one user exists
        User::factory()->create();
    }

    public function test_login_screen_renders(): void
    {
        $this->get('/login')->assertOk()->assertSee(__('auth.submit'));
    }

    public function test_user_can_log_in(): void
    {
        $user = User::factory()->create(['password' => 'secret-password']);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'secret-password',
        ])->assertRedirect('/');

        $this->assertAuthenticatedAs($user);

        // the login is remembered: a long-lived remember cookie (survives rebuilds)
        $this->assertNotNull($user->refresh()->remember_token);
    }

    public function test_wrong_password_shows_error(): void
    {
        $user = User::factory()->create();

        $this->from('/login')
            ->post('/login', [
                'email' => $user->email,
                'password' => 'wrong-password',
            ])
            ->assertRedirect('/login')
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_user_can_log_out(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_home_requires_authentication(): void
    {
        $this->get('/')->assertRedirect('/login');
    }

    /** Login is rate-limited, password brute-force hits 429. */
    public function test_login_is_rate_limited(): void
    {
        $user = User::factory()->create();

        RateLimiter::for('login', fn () => Limit::perMinute(2));

        $this->from('/login')->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertRedirect('/login');

        $this->from('/login')->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertRedirect('/login');

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertStatus(429);
    }

    /**
     * Blocking tears down a live session on the very next request —
     * the remember cookie and an already open tab stop working.
     */
    public function test_blocked_user_session_is_killed_on_next_request(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/')->assertOk();

        $user->block();

        $this->actingAs($user)->get('/')
            ->assertRedirect(route('login'))
            ->assertSessionHas('status', __('auth.blocked'));
    }
}
