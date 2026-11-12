<?php

namespace App\Livewire;

use App\Enums\CaseStatus;
use App\Models\Project;
use App\Models\TestCase;
use App\Support\Like;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Url;
use Livewire\WithPagination;

/**
 * The test case browser: a folder rail on the left + a table on the right, shared filters
 * in the section bar. Clicking a folder/filter updates only the content (Livewire),
 * state is synced with the URL (#[Url]).
 */
class CasesBrowser extends FemidaComponent
{
    use WithPagination;

    public Project $project;

    #[Url]
    public string $q = '';

    #[Url]
    public string $status = '';

    #[Url]
    public ?string $folder = null;

    #[Url(as: 'no_folder')]
    public bool $noFolder = false;

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

    public function selectAll(): void
    {
        $this->folder = null;
        $this->noFolder = false;
        $this->resetPage();
    }

    /**
     * Folder selection by index in folderList(): folder names are user
     * strings, interpolating them into wire:click (a JS expression) is not allowed — XSS (C1).
     */
    public function selectFolder(int $index): void
    {
        $path = $this->folderList()[$index]['path'] ?? null;
        if ($path === null) {
            return;
        }

        $this->folder = $path;
        $this->noFolder = false;
        $this->resetPage();
    }

    public function selectNoFolder(): void
    {
        $this->folder = null;
        $this->noFolder = true;
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset(['q', 'status', 'folder', 'noFolder']);
        $this->resetPage();
    }

    /**
     * The filtered case list (paginated).
     *
     * @return LengthAwarePaginator<int, TestCase>
     */
    public function cases(): LengthAwarePaginator
    {
        return $this->project->testCases()
            ->when(trim($this->q) !== '', function ($query) {
                // SQLite LIKE is not case-insensitive for Cyrillic — search both cases of the first letter;
                // escape wildcards in the input (Like + ESCAPE)
                $term = trim($this->q);
                $capitalized = mb_strtoupper(mb_substr($term, 0, 1)).mb_substr($term, 1);

                $query->where(fn ($w) => $w
                    ->whereRaw("title LIKE ? ESCAPE '\\'", [Like::contains($term)])
                    ->orWhereRaw("title LIKE ? ESCAPE '\\'", [Like::contains($capitalized)]));
            })
            ->when($this->status !== '', fn ($q) => $q->where('status', $this->status))
            ->when($this->folder !== null && $this->folder !== '', function ($query) {
                // the selected folder, or a folder with all its subfolders (prefix)
                $folder = trim((string) $this->folder);
                $query->where(fn ($w) => $w
                    ->where('folder', $folder)
                    ->orWhereRaw("folder LIKE ? ESCAPE '\\'", [Like::prefix($folder)]));
            })
            ->when($this->noFolder, fn ($q) => $q->whereNull('folder'))
            ->orderBy('title')
            ->paginate(20);
    }

    /**
     * The folder tree (two levels) with counts of live cases.
     *
     * @return array<string, mixed>
     */
    public function folderTree(): array
    {
        /** @var Collection<int, TestCase&object{cases_count: int}> $folderRows */
        $folderRows = $this->project->testCases()
            ->whereNotNull('folder')
            ->where('status', '!=', CaseStatus::Inactive)
            ->groupBy('folder')
            ->orderBy('folder')
            ->selectRaw('folder, count(*) as cases_count')
            ->get();

        $folderTree = [];
        foreach ($folderRows as $row) {
            [$level1, $level2] = array_pad(explode('/', $row->folder, 2), 2, null);
            $folderTree[$level1]['total'] = ($folderTree[$level1]['total'] ?? 0) + $row->cases_count;
            if ($level2 !== null) {
                $folderTree[$level1]['children'][$level2] = $row->cases_count;
            } else {
                $folderTree[$level1]['own'] = $row->cases_count;
            }
        }

        return $folderTree;
    }

    /**
     * A flat indexed folder list for the rail: a click passes only
     * an index (int); the path and label stay on the server.
     *
     * @return list<array{label: string, path: string, count: int, level: int, active: bool}>
     */
    public function folderList(): array
    {
        $list = [];

        foreach ($this->folderTree() as $level1 => $node) {
            $list[] = [
                'label' => $level1,
                'path' => $level1,
                'count' => (int) $node['total'],
                'level' => 1,
                'active' => $this->folderIsActive($level1),
            ];

            foreach (($node['children'] ?? []) as $level2 => $count) {
                $path = $level1.'/'.$level2;
                $list[] = [
                    'label' => $level2,
                    'path' => $path,
                    'count' => (int) $count,
                    'level' => 2,
                    'active' => $this->folderIsActive($path),
                ];
            }
        }

        return $list;
    }

    private function folderIsActive(string $path): bool
    {
        return ! $this->noFolder && (string) $this->folder === $path;
    }

    public function rootCount(): int
    {
        return (int) $this->project->testCases()
            ->whereNull('folder')
            ->where('status', '!=', CaseStatus::Inactive)
            ->count();
    }

    public function totalCount(): int
    {
        return (int) $this->project->testCases()->count();
    }

    public function activeCount(): int
    {
        return (int) $this->project->testCases()->active()->count();
    }

    public function paginationView(): string
    {
        return 'partials.pagination-lw';
    }

    public function render(): View
    {
        return view('livewire.cases-browser');
    }
}
