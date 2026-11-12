<?php

namespace Tests\Feature;

use App\Livewire\ProfileSettings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_requires_auth(): void
    {
        $this->get(route('profile'))->assertRedirect(route('login'));
    }

    public function test_user_updates_profile(): void
    {
        $user = User::factory()->create(['name' => 'Igor', 'locale' => 'ru', 'email' => 'igor@example.team']);

        Livewire::actingAs($user)
            ->test(ProfileSettings::class)
            ->set('name', 'Igor S.')
            ->set('email', 'igor.selivanov@example.team')
            ->set('locale', 'en')
            ->call('saveProfile')
            ->assertHasNoErrors();

        $user->refresh();
        $this->assertSame('Igor S.', $user->name);
        $this->assertSame('igor.selivanov@example.team', $user->email);
        $this->assertSame('en', $user->locale);
    }

    public function test_email_must_be_unique(): void
    {
        User::factory()->create(['email' => 'busy@example.team']);
        $user = User::factory()->create(['email' => 'free@example.team']);

        Livewire::actingAs($user)
            ->test(ProfileSettings::class)
            ->set('email', 'busy@example.team')
            ->call('saveProfile')
            ->assertHasErrors(['email']);
    }

    public function test_password_change_flow(): void
    {
        $user = User::factory()->create(['password' => 'old-password']);

        // a wrong current password — rejected
        Livewire::actingAs($user)
            ->test(ProfileSettings::class)
            ->set('currentPassword', 'wrong-password')
            ->set('password', 'new-strong-password')
            ->set('passwordConfirmation', 'new-strong-password')
            ->call('savePassword')
            ->assertHasErrors(['currentPassword']);

        // a correct one — the password changes, the fields clear
        Livewire::actingAs($user)
            ->test(ProfileSettings::class)
            ->set('currentPassword', 'old-password')
            ->set('password', 'new-strong-password')
            ->set('passwordConfirmation', 'new-strong-password')
            ->call('savePassword')
            ->assertHasNoErrors()
            ->assertSet('password', '');

        $this->assertTrue(Hash::check('new-strong-password', $user->refresh()->password));
    }
}
