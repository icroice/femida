<?php

namespace App\Livewire;

use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\App;
use Illuminate\Validation\Rule;

class ProfileSettings extends FemidaComponent
{
    public string $name = '';

    public string $email = '';

    public string $locale = 'en';

    public string $theme = 'light';

    public string $currentPassword = '';

    public string $password = '';

    public string $passwordConfirmation = '';

    public function mount(): void
    {
        /** @var User $user */
        $user = auth()->user();

        $this->name = (string) $user->name;
        $this->email = (string) $user->email;
        $this->locale = (string) $user->locale;
        $this->theme = (string) ($user->theme ?? 'light');
    }

    public function saveProfile(): void
    {
        /** @var User $user */
        $user = auth()->user();

        $this->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('users', 'name')->ignore($user->getKey())],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->getKey())],
            'locale' => ['required', 'in:ru,en'],
        ], [], [
            'name' => __('profile.field_name'),
            'email' => __('profile.field_email'),
            'locale' => __('profile.field_locale'),
        ]);

        try {
            $user->forceFill([
                'name' => $this->name,
                'email' => $this->email,
                'locale' => $this->locale,
            ])->save();
        } catch (UniqueConstraintViolationException) {
            // TOCTOU on name/email uniqueness — a form error, not a 500
            $this->addError('name', __('profile.name_taken'));

            return;
        }

        App::setLocale($this->locale);
        session()->put('locale', $this->locale);

        $this->dispatch('notify', message: __('profile.saved_profile'));
    }

    /**
     * The theme applies immediately when selected in the profile:
     * we save it and ask the browser to switch data-theme without a reload.
     */
    public function updatedTheme(string $value): void
    {
        if (! in_array($value, ['light', 'dark'], true)) {
            $this->theme = 'light';

            return;
        }

        auth()->user()->forceFill(['theme' => $value])->save();

        $this->dispatch('theme-changed', theme: $value);
    }

    public function savePassword(): void
    {
        $this->validate([
            'currentPassword' => ['required', 'current_password'],
            'password' => ['required', 'string', 'min:8', 'same:passwordConfirmation'],
        ], [], [
            'currentPassword' => __('profile.field_current_password'),
            'password' => __('profile.field_new_password'),
        ]);

        /** @var User $user */
        $user = auth()->user();
        $user->forceFill(['password' => $this->password])->save();

        $this->reset('currentPassword', 'password', 'passwordConfirmation');

        $this->dispatch('notify', message: __('profile.saved_password'));
    }

    // Keys live on a separate page, "Profile → My API keys" (ProfileKeys)
}
