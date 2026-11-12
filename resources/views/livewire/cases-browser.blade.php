@php
    $cases = $this->cases();
    $folderList = $this->folderList();
    $filtersActive = trim($this->q) !== '' || $this->status !== ''
        || (filled($this->folder)) || $this->noFolder;
    $fi = fn (bool $active, string $extra = '') => 'flex items-center gap-2 w-full text-left px-3.5 py-[7px] text-[13px] border-l-2 '
        .($active ? 'border-primary bg-primary/10 text-primary font-bold' : 'border-transparent text-base-content/60 hover:bg-base-200/50 hover:text-base-content')
        .' '.$extra;
@endphp

<div class="flex-1 min-h-0 flex">
        {{-- folder rail: a click updates only the table --}}
        <aside class="w-[260px] flex-none bg-base-100 border-r border-base-200 overflow-y-auto fem-scroll pt-3 pb-10">
            <div class="px-3.5 pb-1.5 text-[10.5px] font-bold uppercase tracking-[0.14em] text-base-content/40">{{ __('cases.folders') }}</div>

            <button wire:click="selectAll" class="{{ $fi($this->folder === null && ! $this->noFolder) }}">
                <span class="flex-1 truncate">{{ __('cases.all_cases') }}</span>
                <span class="text-[11px] tabular-nums opacity-70">{{ $this->totalCount() }}</span>
            </button>

            @if ($this->rootCount() > 0)
                <button wire:click="selectNoFolder" class="{{ $fi($this->noFolder) }} text-base-content/50">
                    <span class="flex-1 truncate">{{ __('cases.without_folder') }}</span>
                    <span class="text-[11px] tabular-nums opacity-70">{{ $this->rootCount() }}</span>
                </button>
            @endif

            {{-- wire:click carries only an index: folder names are user strings (C1, XSS) --}}
            @foreach ($folderList as $index => $entry)
                <button wire:click="selectFolder({{ $index }})" class="{{ $fi($entry['active'], $entry['level'] === 1 ? 'font-semibold' : 'fi-sub text-[12.5px]') }}">
                    <span class="flex-1 truncate">{{ $entry['label'] }}</span>
                    <span class="text-[11px] tabular-nums opacity-60">{{ $entry['count'] }}</span>
                </button>
            @endforeach
        </aside>

        {{-- content --}}
        <div class="flex-1 min-w-0 overflow-y-auto fem-scroll p-6">
            <div class="flex items-center gap-3 mb-4">
                <h1 class="text-xl font-extrabold">{{ __('cases.title') }}</h1>
                <span class="badge badge-sm badge-ghost">{{ $this->totalCount() }}</span>
                <span class="badge badge-sm badge-ghost">{{ __('cases.active_count', ['count' => $this->activeCount()]) }}</span>
                @can('create', [\App\Models\TestCase::class, $project])
                    <a href="{{ route('cases.create', $project) }}" class="btn btn-primary btn-sm h-[30px] min-h-0 ml-auto gap-1.5">
                        <svg viewBox="0 0 12 12" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M6 1v10M1 6h10"/></svg>
                        {{ __('cases.new') }}
                    </a>
                @endcan
            </div>

            {{-- filter toolbar: search + status + counter + reset --}}
            <div class="flex items-center gap-2 mb-4">
                <div class="relative">
                    <svg viewBox="0 0 24 24" class="w-3.5 h-3.5 absolute left-2.5 top-1/2 -translate-y-1/2 text-base-content/40 pointer-events-none" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="11" cy="11" r="7"/><path d="M20 20l-4-4"/></svg>
                    <input type="text" wire:model.live.debounce.350ms="q" placeholder="{{ __('cases.search') }}"
                           class="input input-bordered input-sm w-64 pl-7 text-xs font-normal bg-base-100">
                </div>
                <select wire:model.live="status" class="select select-bordered select-sm w-32 text-xs font-normal bg-base-100">
                    <option value="">{{ __('cases.any_status') }}</option>
                    <option value="active">{{ __('cases.status_active') }}</option>
                    <option value="inactive">{{ __('cases.status_inactive') }}</option>
                </select>
                @if ($filtersActive)
                    <button wire:click="resetFilters" class="btn btn-ghost btn-sm btn-square" title="{{ __('app.reset_filters') }}">
                        <svg viewBox="0 0 24 24" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M6 6l12 12M18 6L6 18"/></svg>
                    </button>
                @endif
                <span class="text-xs text-base-content/40 tabular-nums" title="{{ __('app.shown_of', ['shown' => $cases->total(), 'total' => $this->totalCount()]) }}">
                    {{ $cases->total() }} / {{ $this->totalCount() }}
                </span>
            </div>

            <div class="card bg-base-100 border border-base-200 rounded-xl overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="table table-sm hover text-[13.5px]">
                        <thead>
                        <tr class="text-[10.5px] uppercase tracking-wider text-base-content/50">
                            <th class="w-[72px]">ID</th>
                            <th>{{ __('cases.column_title') }}</th>
                            <th class="max-[959px]:hidden">{{ __('cases.column_folder') }}</th>
                            <th class="w-[96px] max-[1099px]:hidden">{{ __('cases.column_priority') }}</th>
                            <th class="w-14 max-[1099px]:hidden">{{ __('cases.column_steps') }}</th>
                            <th class="w-[104px]">{{ __('cases.column_status') }}</th>
                            <th class="w-[84px] max-[1279px]:hidden">{{ __('cases.column_updated') }}</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse ($cases as $case)
                            <tr class="{{ $case->status === \App\Enums\CaseStatus::Inactive ? 'opacity-60' : '' }}">
                                <td class="font-mono text-xs text-base-content/60 whitespace-nowrap">TC-{{ $case->id }}</td>
                                <td>
                                    <a href="{{ route('cases.edit', [$project, $case]) }}" class="font-semibold hover:text-primary">
                                        {{ $case->title }}
                                    </a>
                                </td>
                                <td class="text-xs text-base-content/50 max-[959px]:hidden whitespace-nowrap">{{ $case->folder ?? '—' }}</td>
                                <td class="max-[1099px]:hidden">
                                    <span class="badge badge-sm {{ $case->priority === \App\Enums\CasePriority::P0 ? 'bg-error/10 text-error' : ($case->priority === \App\Enums\CasePriority::P1 ? 'bg-warning/10 text-warning' : ($case->priority === \App\Enums\CasePriority::P2 ? 'bg-info/10 text-info' : 'bg-base-content/10 text-base-content/50')) }}">
                                        {{ $case->priority->value }}</span>
                                </td>
                                <td class="text-xs max-[1099px]:hidden">{{ count($case->steps ?? []) }}</td>
                                <td>
                                    <span class="badge badge-sm {{ $case->status === \App\Enums\CaseStatus::Active ? 'bg-success/10 text-success' : 'bg-base-200/70 text-base-content/60' }}">
                                        {{ __('cases.status_'.$case->status->value) }}</span>
                                </td>
                                <td class="text-xs text-base-content/60 max-[1279px]:hidden">{{ $case->updated_at?->format(__('app.date_short')) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center text-sm text-base-content/50 py-8">{{ __('cases.empty') }}</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @if ($cases->hasPages())
                <div class="flex justify-center mt-4">
                    {{ $cases->onEachSide(1)->links('partials.pagination-lw') }}
                </div>
            @endif
        </div>
    </div>
