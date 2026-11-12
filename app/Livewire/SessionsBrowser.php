<?php

namespace App\Livewire;

use App\Models\Project;
use App\Models\TestSession;
use App\Support\Like;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Url;
use Livewire\WithPagination;

/**
 * The project's session list: a branch rail on the left (newest first — the
 * "there were problems → a commit fixed it → ok" history), sessions of the selected branch
 * on the right by date (newest first). The layout mirrors the cases browser with folders.
 */
class SessionsBrowser extends FemidaComponent
{
    use WithPagination;

    public Project $project;

    #[Url]
    public string $q = '';

    #[Url]
    public string $status = '';

    /** The selected branch: null = all sessions, '' = no branch. */
    #[Url]
    public ?string $branch = null;

    public function mount(Project $project): void
    {
        $this->project = $project;
    }

    public function updatedQ(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function updatedBranch(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset(['q', 'status']);
        $this->resetPage();
    }

    public function selectAllBranches(): void
    {
        $this->branch = null;
        $this->resetPage();
    }

    /**
     * wire:click carries only an index: branch names come from the agent/forms
     * and are never interpolated into JS expressions (lesson C1).
     */
    public function selectBranch(int $index): void
    {
        $this->branch = ($this->branches()[$index]['branch'] ?? null) ?? '';
        $this->resetPage();
    }

    /**
     * @return list<array{branch: ?string, count: int, latest: string}>
     */
    public function branches(): array
    {
        $rows = $this->project->testSessions()
            ->toBase()
            ->selectRaw('branch, count(*) as c, max(created_at) as latest')
            ->groupBy('branch')
            ->orderByDesc('latest')
            ->get();

        return $rows
            ->map(fn ($row) => [
                'branch' => $row->branch,
                'count' => (int) $row->c,
                'latest' => (string) $row->latest,
            ])
            ->all();
    }

    /**
     * The filtered session list (paginated).
     *
     * @return LengthAwarePaginator<int, TestSession>
     */
    public function sessions(): LengthAwarePaginator
    {
        return $this->project->testSessions()
            ->with(['createdBy', 'runs'])
            ->withCount(['cases as verdicts_pass_count' => fn ($q) => $q->whereNotNull('verdict')->where('verdict', 'pass'),
                'cases as verdicts_fail_count' => fn ($q) => $q->whereNotNull('verdict')->where('verdict', 'fail'),
                'cases as verdicts_pending_count' => fn ($q) => $q->whereNull('verdict')])
            ->when($this->branch === '', fn ($q) => $q->whereNull('branch'))
            ->when($this->branch !== null && $this->branch !== '', fn ($q) => $q->where('branch', $this->branch))
            ->when($this->status !== '', fn ($q) => $q->where('status', $this->status))
            ->when(trim($this->q) !== '', function ($query) {
                // SQLite LIKE is not case-insensitive for Cyrillic — search both
                // cases of the first letter (same as CasesBrowser);
                // escape wildcards in the input (Like + ESCAPE)
                $term = trim($this->q);
                $capitalized = mb_strtoupper(mb_substr($term, 0, 1)).mb_substr($term, 1);

                $query->where(fn ($w) => $w
                    ->whereRaw("external_ref LIKE ? ESCAPE '\\'", [Like::contains($term)])
                    ->orWhereRaw("external_ref LIKE ? ESCAPE '\\'", [Like::contains($capitalized)])
                    ->orWhereRaw("title LIKE ? ESCAPE '\\'", [Like::contains($term)])
                    ->orWhereRaw("title LIKE ? ESCAPE '\\'", [Like::contains($capitalized)]));
            })
            ->latest('id')
            ->paginate(20);
    }

    public function totalCount(): int
    {
        return (int) $this->project->testSessions()->count();
    }

    public function paginationView(): string
    {
        return 'partials.pagination-lw';
    }

    public function render(): View
    {
        return view('livewire.sessions-browser');
    }
}
