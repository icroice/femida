<?php

namespace App\Livewire;

use App\Models\ApiKey;
use App\Models\Project;
use App\Models\ProjectUser;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;

/**
 * "Profile → My API keys": issuing and revoking personal keys.
 * The key owner is always the current user; keys are issued in projects
 * where they are developer or above.
 */
class ProfileKeys extends FemidaComponent
{
    public string $keyName = '';

    public int|string $keyProject = '';

    public ?string $newToken = null;

    /**
     * Projects where the current user may issue API keys
     * (developer role or above).
     */
    /** @return Collection<int, Project&object{pivot: ProjectUser}> */
    public function keyProjects(): Collection
    {
        /** @var User $user */
        $user = auth()->user();

        return $user->projects()
            ->get()
            ->filter(fn ($project) => $user->isDeveloper($project))
            ->values();
    }

    /**
     * My keys across all projects (the key owner is always the current user).
     *
     * @return Collection<int, ApiKey>
     */
    public function myKeys(): Collection
    {
        return ApiKey::query()
            ->where('user_id', auth()->id())
            ->with('project')
            ->orderByDesc('created_at')
            ->get();
    }

    public function issueKey(): void
    {
        $project = Project::query()->find((int) $this->keyProject);

        // an empty selection is a form error; a deliberate foreign-project request is a 403
        if ($project === null) {
            $this->addError('keyProject', __('profile.key_project_required'));

            return;
        }
        abort_unless(auth()->user()->isDeveloper($project), 403);

        $this->validate([
            'keyName' => ['required', 'string', 'max:100'],
        ]);

        [, $token] = ApiKey::issue($project, auth()->user(), $this->keyName);

        $this->newToken = $token;
        $this->keyName = '';
    }

    public function revokeKey(int $keyId): void
    {
        $key = ApiKey::query()
            ->where('user_id', auth()->id())
            ->whereNull('revoked_at')
            ->findOrFail($keyId);

        $this->authorize('delete', $key);

        $key->revoke();

        $this->dispatch('notify', message: __('settings.key_revoked'));
    }

    public function render(): View
    {
        return view('livewire.profile-keys')
            ->title(__('profile.section_keys'));
    }
}
