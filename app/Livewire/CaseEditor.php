<?php

namespace App\Livewire;

use App\Enums\CasePriority;
use App\Enums\CaseStatus;
use App\Models\Project;
use App\Models\TestCase;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;

#[Layout('layouts.app')]
class CaseEditor extends FemidaComponent
{
    public Project $project;

    public ?TestCase $case = null;

    public string $title = '';

    public string $folder = '';

    public string $preconditions = '';

    /** @var array<int, array{action: string, expected: string}> */
    public array $steps = [['action' => '', 'expected' => '']];

    public string $priority = 'P2';

    public string $status = 'active';

    public function mount(Project $project, ?TestCase $case = null): void
    {
        // the case must belong to the project from the URL (double check with the page controller:
        // mount is resolved again on Livewire updates)
        abort_unless($case === null || $case->project->is($project), 404);

        $this->project = $project;
        $this->case = $case;

        if ($case !== null) {
            $this->title = $case->title;
            $this->folder = (string) ($case->folder ?? '');
            $this->preconditions = (string) $case->preconditions;
            $this->steps = array_values($case->steps ?: [['action' => '', 'expected' => '']]);
            $this->priority = $case->priority->value;
            $this->status = $case->status->value;
        }
    }

    /**
     * @return array<string, array<int, string>>
     */
    protected function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:500'],
            'folder' => TestCase::FOLDER_RULES,
            'preconditions' => ['nullable', 'string', 'max:5000'],
            'steps' => ['required', 'array', 'min:1', 'max:50'],
            'steps.*.action' => ['required', 'string', 'max:2000'],
            'steps.*.expected' => ['required', 'string', 'max:2000'],
            'priority' => ['required', 'in:P0,P1,P2,P3'],
            'status' => ['required', 'in:active,inactive'],
        ];
    }

    /**
     * The project's existing folders for autocomplete (datalist).
     *
     * @return Collection<int, string|null>
     */
    public function folders(): Collection
    {
        return $this->project->testCases()
            ->whereNotNull('folder')
            ->distinct()
            ->orderBy('folder')
            ->pluck('folder');
    }

    /** @return array<string, string> */
    protected function messages(): array
    {
        return [
            'folder.regex' => __('cases.folder_format'),
        ];
    }

    public function addStep(): void
    {
        $this->steps[] = ['action' => '', 'expected' => ''];
    }

    public function removeStep(int $index): void
    {
        if (count($this->steps) > 1) {
            unset($this->steps[$index]);
            $this->steps = array_values($this->steps);
        }
    }

    public function save(): void
    {
        $this->validate();

        $data = [
            'title' => $this->title,
            'folder' => TestCase::normalizeFolder($this->folder),
            'preconditions' => $this->preconditions !== '' ? $this->preconditions : null,
            'steps' => $this->steps,
            'priority' => CasePriority::from($this->priority),
        ];

        // Status = deliberate archiving, written only with the
        // delete right (developer+). A member cannot deactivate a case via the form select.
        // A new case from the UI is always active.
        if ($this->case === null) {
            $data['status'] = CaseStatus::Active;
        } elseif (Gate::allows('delete', $this->case)) {
            $data['status'] = CaseStatus::from($this->status);
        }

        if ($this->case !== null) {
            $this->authorize('update', $this->case);
            $this->case->update($data);
        } else {
            $this->authorize('create', [TestCase::class, $this->project]);
            $this->project->testCases()->create($data);
        }

        session()->flash('status', __('cases.saved'));

        $this->redirect(route('projects.cases.index', $this->project));
    }

    public function archive(): void
    {
        $this->authorize('delete', $this->case);

        $this->case->forceFill(['status' => CaseStatus::Inactive])->save();

        session()->flash('status', __('cases.archived'));

        $this->redirect(route('projects.cases.index', $this->project));
    }

    public function render(): View
    {
        return view('livewire.case-editor')
            ->title($this->case !== null
                ? __('cases.editor_title_edit', ['id' => $this->case->id])
                : __('cases.editor_title'));
    }
}
