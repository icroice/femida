@php
    $sessions = $this->sessions();
    $statuses = \App\Enums\SessionStatus::cases();
    $branchList = $this->branches();
    $filtersActive = trim($this->q) !== '' || $this->status !== '';

    $statusBadge = [
        'passed' => 'bg-success/10 text-success',
        'not_passed' => 'bg-base-content/10 text-base-content/60',
    ];
    $flowBadge = [
        'running' => 'bg-warning/10 text-warning',
        'awaiting_review' => 'bg-primary/10 text-primary',
    ];
    $fi = fn (bool $active, string $extra = '') => 'flex items-center gap-2 w-full text-left px-3.5 py-[7px] text-[13px] border-l-2 '
        .($active ? 'border-primary bg-primary/10 text-primary font-bold' : 'border-transparent text-base-content/60 hover:bg-base-200/50 hover:text-base-content')
        .' '.$extra;
@endphp

<div class="flex-1 min-h-0 flex">
        {{-- branch rail: newest on top — the "problems → fix → ok" history stays visible --}}
        <aside class="w-[260px] flex-none bg-base-100 border-r border-base-200 overflow-y-auto fem-scroll pt-3 pb-10">
            <div class="px-3.5 pb-1.5 text-[10.5px] font-bold uppercase tracking-[0.14em] text-base-content/40">{{ __('sessions.branches') }}</div>

            <button wire:click="selectAllBranches" class="{{ $fi($this->branch === null) }}">
                <span class="flex-1 truncate">{{ __('sessions.all_branches') }}</span>
                <span class="text-[11px] tabular-nums opacity-70">{{ $this->totalCount() }}</span>
            </button>

            {{-- wire:click carries only an index: branch names come from outside (lesson C1) --}}
            @foreach ($branchList as $index => $entry)
                <button wire:click="selectBranch({{ $index }})" class="{{ $fi(($entry['branch'] ?? '') === '' && $this->branch === '' ? true : ($entry['branch'] !== null && $this->branch === $entry['branch'])) }}">
                    <span class="flex-1 truncate font-mono text-[12.5px]">{{ $entry['branch'] !== null ? $entry['branch'] : __('sessions.without_branch') }}</span>
                    <span class="text-[11px] tabular-nums opacity-60">{{ $entry['count'] }}</span>
                </button>
            @endforeach
        </aside>

        {{-- content --}}
        <div class="flex-1 min-w-0 overflow-y-auto fem-scroll p-6">
            <div class="flex items-center gap-3 mb-4">
                <h1 class="text-xl font-extrabold">{{ __('sessions.title') }}</h1>
                <span class="badge badge-sm badge-ghost">{{ $this->totalCount() }}</span>
            </div>

            {{-- filter toolbar: search + status + counter + reset --}}
            <div class="flex items-center gap-2 mb-4">
                <div class="relative">
                    <svg viewBox="0 0 24 24" class="w-3.5 h-3.5 absolute left-2.5 top-1/2 -translate-y-1/2 text-base-content/40 pointer-events-none" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="11" cy="11" r="7"/><path d="M20 20l-4-4"/></svg>
                    <input type="text" wire:model.live.debounce.350ms="q" placeholder="{{ __('sessions.search_placeholder') }}"
                           class="input input-bordered input-sm w-64 pl-7 text-xs font-normal bg-base-100">
                </div>
                <select wire:model.live="status" class="select select-bordered select-sm w-32 text-xs font-normal bg-base-100">
                    <option value="">{{ __('sessions.any_status') }}</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status->value }}">{{ __('sessions.status_'.$status->value) }}</option>
                    @endforeach
                </select>
                @if ($filtersActive)
                    <button wire:click="resetFilters" class="btn btn-ghost btn-sm btn-square" title="{{ __('app.reset_filters') }}">
                        <svg viewBox="0 0 24 24" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M6 6l12 12M18 6L6 18"/></svg>
                    </button>
                @endif
                <span class="text-xs text-base-content/40 tabular-nums" title="{{ __('app.shown_of', ['shown' => $sessions->total(), 'total' => $this->totalCount()]) }}">
                    {{ $sessions->total() }} / {{ $this->totalCount() }}
                </span>
            </div>

            <div class="card bg-base-100 border border-base-200 rounded-xl overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="table table-sm hover text-[13.5px]">
                        <thead>
                        <tr class="text-[10.5px] uppercase tracking-wider text-base-content/50">
                            <th>{{ __('sessions.column_task') }}</th>
                            <th class="w-[150px]">{{ __('sessions.column_status') }}</th>
                            <th class="w-36">{{ __('sessions.column_verdicts') }}</th>
                            <th>{{ __('sessions.column_commits') }}</th>
                            <th>{{ __('sessions.column_author') }}</th>
                            <th class="w-28 max-[1279px]:hidden">{{ __('sessions.column_created') }}</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse ($sessions as $session)
                            @php
                                $flow = $session->flowState();
                            @endphp
                            <tr>
                                <td>
                                    <a href="{{ route('sessions.show', [$project, $session]) }}" class="leading-tight block hover:text-primary">
                                        <div><span class="font-mono font-bold text-xs">{{ $session->external_ref }}</span>
                                            <span class="font-semibold">{{ $session->title }}</span></div>
                                        @if ($flow === 'running' && $session->runs->firstWhere('status', \App\Enums\RunStatus::Failed)?->error)
                                            <div class="text-xs text-error/70 mt-0.5">{{ Str::limit($session->runs->firstWhere('status', \App\Enums\RunStatus::Failed)->error, 70) }}</div>
                                        @endif
                                    </a>
                                </td>
                                <td>
                                    <div class="flex items-center gap-1.5 flex-wrap">
                                        <span class="badge badge-sm {{ $statusBadge[$session->status->value] ?? '' }}">{{ __('sessions.status_'.$session->status->value) }}</span>
                                        @if ($flow !== 'awaiting_run' && $session->status === \App\Enums\SessionStatus::NotPassed)
                                            <span class="badge badge-sm {{ $flowBadge[$flow] ?? '' }}">{{ __('sessions.flow_'.$flow) }}</span>
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    {{-- verdict counters: zeros hidden (no noise), all zeros — a quiet dash --}}
                                    @php
                                        $verdictParts = [];
                                        if ($session->verdicts_pass_count) {
                                            $verdictParts[] = '<span class="text-success">✓'.$session->verdicts_pass_count.'</span>';
                                        }
                                        if ($session->verdicts_fail_count) {
                                            $verdictParts[] = '<span class="text-error">✕'.$session->verdicts_fail_count.'</span>';
                                        }
                                        if ($session->verdicts_pending_count) {
                                            $verdictParts[] = '<span class="text-warning">•'.$session->verdicts_pending_count.'</span>';
                                        }
                                    @endphp
                                    <span class="text-xs font-bold whitespace-nowrap">
                                        {!! $verdictParts
                                            ? implode(' <span class="text-base-content/30">·</span> ', $verdictParts)
                                            : '<span class="text-base-content/40 font-normal">—</span>' !!}
                                    </span>
                                </td>
                                <td class="font-mono text-xs text-base-content/60">
                                    @if ($session->only_after)
                                        <span title="{{ __('sessions.only_after_hint') }}">—→{{ substr($session->head_commit, 0, 7) }}</span>
                                    @else
                                        {{ substr((string) $session->base_commit, 0, 7) }}→{{ substr($session->head_commit, 0, 7) }}
                                    @endif
                                </td>
                                <td class="text-xs">{{ $session->createdBy?->name }}</td>
                                <td class="text-xs text-base-content/60 max-[1279px]:hidden">{{ $session->created_at?->format(__('app.datetime_short')) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-sm text-base-content/50 py-8">{{ __('sessions.empty') }}</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @if ($sessions->hasPages())
                <div class="flex justify-center mt-4">
                    {{ $sessions->onEachSide(1)->links('partials.pagination-lw') }}
                </div>
            @endif
        </div>
    </div>