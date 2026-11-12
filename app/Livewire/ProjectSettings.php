<?php

namespace App\Livewire;

use App\Models\Project;
use App\Models\Screenshot;
use App\Models\SessionCase;
use App\Models\StepResult;
use App\Services\ScreenshotService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;

class ProjectSettings extends FemidaComponent
{
    public Project $project;

    public string $name = '';

    public string $description = '';

    public function mount(Project $project): void
    {
        $this->authorize('update', $project);

        $this->project = $project;
        $this->name = $project->name;
        $this->description = (string) $project->description;
    }

    public function saveProject(): void
    {
        $this->authorize('update', $this->project);

        $this->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        $this->project->update([
            'name' => $this->name,
            'description' => $this->description !== '' ? $this->description : null,
        ]);

        $this->dispatch('notify', message: __('settings.saved'));
    }

    /**
     * All project keys — a separate page "Settings → API keys" (ProjectKeys):
     * the owner sees who uses what and can revoke any of them.
     * Personal keys are issued in the profile.
     */
    public function deleteProject(): void
    {
        $this->authorize('delete', $this->project);

        // collect paths before deletion — the records are about to go (no orphan files)
        $paths = Screenshot::query()
            ->whereIn('step_result_id', StepResult::query()
                ->whereIn('session_case_id', SessionCase::query()
                    ->whereIn('session_id', $this->project->testSessions()->pluck('id'))
                    ->pluck('id'))
                ->pluck('id'))
            ->pluck('path');

        // The order matters: session_case.case_id → test_cases is RESTRICT; SQLite
        // does not guarantee cross-cascade order — deleting a project with data
        // Sessions first (the cascade removes session_case → steps → shots),
        // then cases, then the project itself.
        DB::transaction(function (): void {
            $this->project->testSessions()->delete();
            $this->project->testCases()->delete();
            $this->project->delete();
        });

        // files — after the records are successfully deleted: an orphan file is better than a record without a file
        ScreenshotService::deleteFiles($paths);

        session()->flash('status', __('settings.project_deleted'));

        $this->redirect(route('home'), navigate: false);
    }

    public function render(): View
    {
        return view('livewire.project-settings')
            ->title($this->project->name.' · '.__('app.settings'));
    }
}
