<?php

namespace App\Livewire;

use App\Models\ApiKey;
use App\Models\Project;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;

/**
 * "Settings → API keys": all project keys. The owner/admin sees who uses
 * what and can revoke any of them. Personal keys live in the profile.
 */
class ProjectKeys extends FemidaComponent
{
    public Project $project;

    public function mount(Project $project): void
    {
        $this->authorize('update', $project);

        $this->project = $project;
    }

    /** @return Collection<int, ApiKey> */
    public function projectKeys(): Collection
    {
        return $this->project->apiKeys()
            ->with('owner')
            ->orderByRaw('revoked_at is not null') // active keys first
            ->orderByDesc('created_at')
            ->get();
    }

    public function revokeProjectKey(int $keyId): void
    {
        $this->authorize('update', $this->project);

        $key = $this->project->apiKeys()->whereNull('revoked_at')->find($keyId);
        if ($key === null) {
            return;
        }

        $key->revoke();

        $this->dispatch('notify', message: __('settings.key_revoked'));
    }

    public function render(): View
    {
        return view('livewire.project-keys')
            ->title($this->project->name.' · '.__('settings.api_keys'));
    }
}
