@php
    $cases = $this->cases();
    $current = $this->current();
    $sides = $this->runSides();
    $runMap = collect($sides);
    $beforeRun = $session->runs->firstWhere('side.value', 'before');
    $afterRun = $session->runs->firstWhere('side.value', 'after');

    // text meta line: run durations (← before, → after) + tokens
    $metaParts = [];
    if ($dur = $this->fmtRunDuration($beforeRun)) {
        $metaParts[] = '← '.$dur;
    }
    if ($dur = $this->fmtRunDuration($afterRun)) {
        $metaParts[] = '→ '.$dur;
    }
    if ($session->tokens_in !== null || $session->tokens_out !== null) {
        $metaParts[] = '↓ '.$this->fmtTokens($session->tokens_in).' · ↑ '.$this->fmtTokens($session->tokens_out);
    }
    $checkedTotal = $cases->where('verdict', '!==', null)->count();

    $verdictBadge = [
        'pass' => 'bg-success/10 text-success',
        'fail' => 'bg-error/10 text-error',
        'blocked' => 'bg-warning/10 text-warning',
    ];
@endphp

<div x-data="{
        cmp: { open: false, before: null, after: null },
        attach: { open: false },
        copied: false,
        openCompare(before, after) {
            this.cmp = { open: true, before, after };
        },
    }"
    x-on:summary-copied.window="
        navigator.clipboard.writeText($event.detail.summary).then(() => {
            copied = true;
            setTimeout(() => copied = false, 2000);
        });
    "
    @keydown.window="
    if ($event.key === 'Escape' && cmp.open) cmp.open = false;
    if ($event.key === 'Escape' && attach.open) attach.open = false;
"
    class="flex-1 min-h-0 flex flex-col">

    {{-- session header: title/meta on the left, statuses and summary on the right --}}
    <div class="flex-none flex items-start gap-4 flex-wrap px-6 pt-4 pb-3.5 border-b border-base-200/70">
        <div class="leading-tight min-w-0">
            <div class="text-xl font-extrabold">{{ $session->title }}</div>
            <div class="text-[13px] text-base-content/50 mt-1.5">
                <a href="{{ $session->repo_remote ?: route('projects.sessions.index', $project) }}" class="font-mono font-bold text-base-content/70">{{ $session->external_ref }}</a>
                · <span class="font-mono">{{ $session->only_after ? '—→ '.substr($session->head_commit, 0, 7) : substr((string) $session->base_commit, 0, 7).' → '.substr($session->head_commit, 0, 7) }}</span>
                @if ($session->branch) · {{ $session->branch }} @endif
                · {{ __('sessions.created_by') }} {{ $session->createdBy?->name }}
                @if ($session->agent) · {{ $session->agent }} @endif
                · {{ $session->created_at?->format(__('app.datetime_short')) }}
            </div>
            @if ($metaParts)
                <div class="text-[13px] text-base-content/50 mt-1.5 font-mono"
                     title="{{ __('sessions.duration') }} · {{ __('sessions.tokens') }}">{{ implode(' · ', $metaParts) }}</div>
            @endif
        </div>

        <div class="ml-auto flex items-center gap-2 flex-wrap">
            @if ($beforeRun && $beforeRun->status === \App\Enums\RunStatus::Failed)
                <span class="badge badge-sm bg-error/10 text-error"
                      title="{{ $beforeRun->error }}">{{ __('sessions.before_failed') }}</span>
            @endif
            @php
                $flow = $session->flowState();
            @endphp
            <span class="badge badge-sm {{ $session->status === \App\Enums\SessionStatus::Passed ? 'bg-success/10 text-success' : 'bg-base-content/10 text-base-content/60' }}">
                {{ __('sessions.status_'.$session->status->value) }}</span>
            @if ($flow !== 'awaiting_run' && $session->status === \App\Enums\SessionStatus::NotPassed)
                <span class="badge badge-sm {{ $flow === 'running' ? 'bg-warning/10 text-warning' : 'bg-primary/10 text-primary' }}">
                    {{ __('sessions.flow_'.$flow) }}</span>
            @endif
            @can('clearSession', $session)
                <button wire:click="clearSession" wire:confirm="{{ __('sessions.clear_session_confirm') }}"
                        class="btn btn-outline btn-xs border-base-200 text-base-content/60 hover:border-error/40 hover:text-error"
                        title="{{ __('sessions.clear_session_hint') }}">{{ __('sessions.clear_session') }}</button>
            @endcan
            <button wire:click="copySummary" class="btn btn-outline btn-xs border-base-200 text-base-content/60">{{ __('sessions.copy_summary') }}</button>
        </div>
    </div>

    {{-- split: case rail + content, independent scrolls --}}
    <div class="flex-1 min-h-0 flex">

        <aside class="w-[296px] flex-none flex flex-col bg-base-100 border-r border-base-200">
            <div class="flex-none flex items-center justify-between px-4 pt-3.5 pb-2">
                <h3 class="text-[10.5px] font-bold uppercase tracking-[0.14em] text-base-content/40">{{ __('sessions.cases') }}</h3>
                @can('attachCase', $session)
                    <button type="button" x-on:click="attach.open = true"
                            class="btn btn-ghost btn-xs px-2 text-primary font-bold"
                            title="{{ __('sessions.add_case') }}">＋</button>
                @endcan
            </div>
            <div class="flex-1 min-h-0 overflow-y-auto fem-scroll pb-2">
                @forelse ($cases as $sc)
                    <div wire:click="selectCase({{ $sc->id }})"
                         class="group w-full text-left flex gap-3 px-4 py-3 border-t border-base-200 cursor-pointer hover:bg-base-200/40 {{ $selectedCaseId === $sc->id ? 'bg-primary/10 shadow-[inset_3px_0_0_var(--color-primary)]' : '' }}">
                        <div class="w-9 h-9 rounded-lg flex items-center justify-center shrink-0 {{ $sc->verdict === \App\Enums\Verdict::Fail ? 'bg-error/10 text-error' : ($sc->verdict === \App\Enums\Verdict::Pass ? 'bg-success/10 text-success' : 'bg-warning/10 text-warning') }}">
                            @if ($sc->verdict === \App\Enums\Verdict::Fail)
                                <svg viewBox="0 0 12 12" class="w-4 h-4"><path d="M2.5 2.5l7 7M9.5 2.5l-7 7" fill="none" stroke="currentColor" stroke-width="1.8"/></svg>
                            @elseif ($sc->verdict === \App\Enums\Verdict::Pass)
                                <svg viewBox="0 0 12 12" class="w-4 h-4"><path d="M2 6.5l2.6 2.6L10 3" fill="none" stroke="currentColor" stroke-width="1.8"/></svg>
                            @else
                                <svg viewBox="0 0 12 12" class="w-4 h-4"><circle cx="6" cy="6" r="4.4" fill="none" stroke="currentColor" stroke-width="1.5"/></svg>
                            @endif
                        </div>
                        <div class="text-[13px] leading-snug min-w-0">
                            <b class="block font-bold">TC-{{ $sc->case_id }} · {{ $sc->case?->title }}</b>
                            <span class="text-xs text-base-content/50">
                                {{ __('sessions.agent') }}:
                                {{ $sc->agent_suggestion ? __('sessions.verdict_'.$sc->agent_suggestion->value) : '—' }}
                            </span>
                            <span class="block mt-1 badge badge-sm {{ $verdictBadge[$sc->verdict?->value ?? ''] ?? 'badge-ghost text-base-content/50' }}">
                                {{ $sc->verdict !== null ? __('sessions.verdict_'.$sc->verdict->value).($sc->verdictBy ? ' · '.$sc->verdictBy->name : '') : __('sessions.not_checked') }}</span>
                        </div>
                        @can('clearCase', $session)
                            <button wire:click.stop="clearCase({{ $sc->id }})" wire:key="clr-{{ $sc->id }}"
                                    wire:confirm="{{ __('sessions.clear_case_confirm') }}"
                                    title="{{ __('sessions.clear_case') }}"
                                    class="self-start shrink-0 w-5 h-5 rounded flex items-center justify-center text-base-content/30 hover:text-primary hover:bg-primary/10 {{ $selectedCaseId === $sc->id ? '' : 'opacity-0 group-hover:opacity-100 focus:opacity-100' }}">
                                <svg viewBox="0 0 14 14" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="1.4"><path d="M12 7a5 5 0 1 1-1.5-3.6M12 1.5V4h-2.5"/></svg>
                            </button>
                        @endcan
                        @can('attachCase', $session)
                            <button wire:click.stop="removeCase({{ $sc->id }})" wire:key="rm-{{ $sc->id }}"
                                    title="{{ __('sessions.remove_case') }}"
                                    class="self-start shrink-0 w-5 h-5 rounded flex items-center justify-center text-base-content/30 hover:text-error hover:bg-error/10 {{ $selectedCaseId === $sc->id ? '' : 'opacity-0 group-hover:opacity-100 focus:opacity-100' }}">
                                <svg viewBox="0 0 12 12" class="w-2.5 h-2.5"><path d="M2.5 2.5l7 7M9.5 2.5l-7 7" stroke="currentColor" stroke-width="1.6" fill="none"/></svg>
                            </button>
                        @endcan
                    </div>
                @empty
                    {{-- empty state — a session without cases --}}
                    <div class="px-4 py-8 text-center text-[13px] text-base-content/40 leading-relaxed">
                        {{ __('sessions.no_cases') }}
                    </div>
                @endforelse
            </div>
            {{-- review progress pinned to the rail bottom: visible without scrolling --}}
            <div class="flex-none border-t border-base-200 px-4 py-3 text-xs text-base-content/50 leading-relaxed">
                {{ __('sessions.review_progress') }}: <b class="text-base-content">{{ $checkedTotal }} / {{ $cases->count() }}</b>
                <progress class="progress progress-primary w-full my-1.5" max="{{ $cases->count() }}" value="{{ $checkedTotal }}"></progress>
            </div>
        </aside>

        {{-- content: a full-width case card, the verdict as a compact card below --}}
        <div class="flex-1 min-w-0 overflow-y-auto fem-scroll p-6">
            @if ($current)
                <main class="card bg-base-100 border border-base-200 rounded-xl">
                    <div class="px-5 py-4 border-b border-base-200 flex items-center gap-3 flex-wrap">
                        <span class="font-mono text-[13px] text-base-content/50">TC-{{ $current->case_id }}</span>
                        <h2 class="text-lg font-extrabold">{{ $current->case?->title }}</h2>
                        @if ($current->agent_suggestion)
                            <span class="badge ml-auto {{ $current->agent_suggestion === \App\Enums\Verdict::Fail ? 'bg-error/10 text-error' : 'bg-success/10 text-success' }}">
                                {{ __('sessions.agent') }}: {{ __('sessions.verdict_'.$current->agent_suggestion->value) }}</span>
                        @endif
                    </div>

                    @if ($current->agent_notes)
                        <div class="px-5 pt-3.5">
                            <div class="rounded-lg px-3.5 py-2.5 text-[13px] leading-relaxed {{ $current->agent_suggestion === \App\Enums\Verdict::Fail ? 'bg-error/10 text-error/90' : 'bg-success/10 text-success/90' }}">
                                {{ $current->agent_notes }}
                            </div>
                        </div>
                    @endif

                    @php
                        $after = $this->stepsFor($current, 'after')->firstWhere('step_index', $this->selectedStep)
                            ?? $this->stepsFor($current, 'after')->first();
                        $before = $this->stepsFor($current, 'before')->firstWhere('step_index', $this->selectedStep)
                            ?? $this->stepsFor($current, 'before')->first();
                        $beforeUrl = $before ? $this->screenshotUrl($before) : null;
                        $afterUrl = $after ? $this->screenshotUrl($after) : null;
                        $stepNo = $after?->step_index ?? $before?->step_index;
                        $stepAction = $stepNo !== null ? ($current->case?->steps[$stepNo - 1]['action'] ?? null) : null;
                    @endphp

                    <div class="grid grid-cols-2 gap-4 px-5 pt-4">
                        <div>
                            <div class="flex justify-between items-center mb-2">
                                <span class="text-xs font-bold tracking-widest text-base-content/50">{{ strtoupper(__('sessions.before')) }} @if ($session->base_commit) · {{ substr($session->base_commit, 0, 7) }} @endif</span>
                                @if ($before)
                                    <span class="badge badge-sm {{ $before->status === \App\Enums\StepStatus::Passed ? 'bg-success/10 text-success' : 'bg-error/10 text-error' }}">{{ __('sessions.step_'.$before->status->value) }}</span>
                                @else
                                    <span class="badge badge-sm badge-ghost">{{ __('sessions.no_run') }}</span>
                                @endif
                            </div>
                            <div class="border-[1.5px] border-base-200 rounded-xl overflow-hidden bg-base-200/40 min-h-[180px] flex items-center justify-center">
                                @if ($beforeUrl)
                                    <img src="{{ $beforeUrl }}" alt="{{ __('sessions.before') }}"
                                         class="block w-full cursor-zoom-in"
                                         x-on:click="openCompare(@js($beforeUrl), @js($afterUrl))">
                                @elseif ($before)
                                    <span class="text-xs text-base-content/40 py-10">{{ __('sessions.no_screenshot') }}</span>
                                @else
                                    <span class="text-xs text-base-content/40 py-10">{{ __('sessions.no_run') }}</span>
                                @endif
                            </div>
                        </div>
                        <div>
                            <div class="flex justify-between items-center mb-2">
                                <span class="text-xs font-bold tracking-widest text-base-content/50">{{ strtoupper(__('sessions.after')) }} · {{ substr($session->head_commit, 0, 7) }}</span>
                                @if ($after)
                                    <span class="badge badge-sm {{ $after->status === \App\Enums\StepStatus::Passed ? 'bg-success/10 text-success' : 'bg-error/10 text-error' }}">{{ __('sessions.step_'.$after->status->value) }}</span>
                                @else
                                    <span class="badge badge-sm badge-ghost">{{ __('sessions.no_run') }}</span>
                                @endif
                            </div>
                            <div class="border-[1.5px] border-base-200 rounded-xl overflow-hidden bg-base-200/40 min-h-[180px] flex items-center justify-center">
                                @if ($afterUrl)
                                    <img src="{{ $afterUrl }}" alt="{{ __('sessions.after') }}"
                                         class="block w-full cursor-zoom-in"
                                         x-on:click="openCompare(@js($beforeUrl), @js($afterUrl))">
                                @elseif ($after)
                                    <span class="text-xs text-base-content/40 py-10">{{ __('sessions.no_screenshot') }}</span>
                                @else
                                    <span class="text-xs text-base-content/40 py-10">{{ __('sessions.no_run') }}</span>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="px-5 pb-4 pt-3 text-[12.5px] text-base-content/50">
                        @if ($after?->note) <b class="text-base-content">{{ __('sessions.after') }}:</b> {{ $after->note }} @endif
                    </div>

                    <div class="border-t border-base-200">
                        <h3 class="text-xs font-bold uppercase tracking-widest text-base-content/50 px-5 pt-3 pb-1">{{ __('cases.field_steps') }}</h3>
                        @foreach ($current->case?->steps ?? [] as $index => $step)
                            @php
                                $stepNo = $index + 1;
                                $beforeStep = $this->stepsFor($current, 'before')->firstWhere('step_index', $stepNo);
                                $afterStep = $this->stepsFor($current, 'after')->firstWhere('step_index', $stepNo);
                            @endphp
                            <button wire:click="selectCase({{ $current->id }}, {{ $stepNo }})"
                                    class="w-full grid grid-cols-[56px_1fr_92px_92px] gap-2.5 items-center px-5 py-2 text-sm text-left {{ $selectedStep === $stepNo ? 'bg-primary/10' : '' }}">
                                <span class="font-mono text-xs text-base-content/50">{{ $current->case_id }}.{{ $stepNo }}</span>
                                <span>{{ $step['action'] }}</span>
                                <span class="text-xs font-bold inline-flex gap-1 items-center {{ $beforeStep?->status === \App\Enums\StepStatus::Passed ? 'text-success' : ($beforeStep ? 'text-error' : 'text-base-content/30') }}">
                                    @if ($beforeStep)
                                        {{ __('sessions.step_'.$beforeStep->status->value) }}
                                        @if ($beforeStep->screenshots->isEmpty())
                                            <span class="text-warning/80" title="{{ __('sessions.no_screenshot') }}"><svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.3"><path d="M1.5 4.5h2l1-1.5h3l1 1.5h2v5h-9z"/><circle cx="6" cy="6.8" r="1.5"/><path d="M1 11 11 1"/></svg></span>
                                        @endif
                                    @else — @endif
                                </span>
                                <span class="text-xs font-bold inline-flex gap-1 items-center {{ $afterStep?->status === \App\Enums\StepStatus::Passed ? 'text-success' : ($afterStep ? 'text-error' : 'text-base-content/30') }}">
                                    @if ($afterStep)
                                        {{ __('sessions.step_'.$afterStep->status->value) }}
                                        @if ($afterStep->screenshots->isEmpty())
                                            <span class="text-warning/80" title="{{ __('sessions.no_screenshot') }}"><svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.3"><path d="M1.5 4.5h2l1-1.5h3l1 1.5h2v5h-9z"/><circle cx="6" cy="6.8" r="1.5"/><path d="M1 11 11 1"/></svg></span>
                                        @endif
                                    @else — @endif
                                </span>
                            </button>
                        @endforeach
                    </div>

                    {{-- compare modal: both sides full-screen, no zoom --}}
                    <div x-show="cmp.open" x-cloak x-transition.opacity role="dialog" aria-modal="true"
                         aria-label="{{ __('sessions.compare') }}"
                         class="fixed inset-0 z-50 flex flex-col bg-black/90"
                         x-on:click.self="cmp.open = false">
                        <div class="flex items-center gap-3 px-4 py-2.5 shrink-0">
                            <span class="font-mono text-xs text-white/50">TC-{{ $current->case_id }}</span>
                            <span class="text-sm font-bold text-white/90 truncate">{{ $current->case?->title }}</span>
                            @if ($stepAction)
                                <span class="text-xs text-white/50 truncate">{{ __('sessions.step') }} {{ $stepNo }} · {{ $stepAction }}</span>
                            @endif
                            <button type="button" class="btn btn-ghost btn-sm btn-circle text-white/70 ml-auto shrink-0"
                                    x-on:click="cmp.open = false" aria-label="{{ __('sessions.close') }}">✕</button>
                        </div>
                        @php
                            $compareSides = [
                                'before' => [strtoupper(__('sessions.before')), $session->base_commit ? substr((string) $session->base_commit, 0, 7) : null],
                                'after' => [strtoupper(__('sessions.after')), substr($session->head_commit, 0, 7)],
                            ];
                        @endphp
                        <div class="flex-1 min-h-0 grid gap-4 px-4 pb-4"
                             :class="cmp.before && cmp.after ? 'grid-cols-2' : 'grid-cols-1'">
                            @foreach ($compareSides as $sideKey => [$sideLabel, $sideHash])
                                <div class="flex flex-col min-h-0">
                                    <div class="flex justify-between items-baseline mb-1.5 px-1 text-[11px] font-bold tracking-widest text-white/50">
                                        <span>{{ $sideLabel }}</span>
                                        @if ($sideHash)<span class="font-mono">{{ $sideHash }}</span>@endif
                                    </div>
                                    <div class="flex-1 min-h-0 rounded-xl overflow-hidden bg-white/5 flex items-center justify-center">
                                        <template x-if="cmp.{{ $sideKey }}">
                                            <img :src="cmp.{{ $sideKey }}" alt="{{ $sideLabel }}"
                                                 class="max-w-full max-h-full object-contain select-none cursor-pointer"
                                                 draggable="false" x-on:click="cmp.open = false">
                                        </template>
                                        <template x-if="!cmp.{{ $sideKey }}">
                                            <span class="text-xs text-white/40 py-10">{{ __('sessions.no_screenshot') }}</span>
                                        </template>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </main>

                {{-- runs + verdict: a compact card under the case --}}
                <div class="card bg-base-100 border border-base-200 rounded-xl mt-5">
                    <div class="px-5 pt-3.5 pb-2 flex flex-wrap gap-x-6 gap-y-1">
                        @foreach ($session->runs as $run)
                            <div class="flex justify-between gap-3 text-xs text-base-content/50">
                                <span>{{ __('sessions.'.$run->side->value) }} · <span class="font-mono">{{ substr($run->commit, 0, 7) }}</span></span>
                                <span class="{{ $run->status === \App\Enums\RunStatus::Failed ? 'text-error' : '' }}">{{ __('sessions.run_'.$run->status->value) }}</span>
                            </div>
                        @endforeach
                    </div>

                    @can('verdict', $session)
                        <div class="px-5 pb-4 border-t border-base-200 pt-3">
                            @if ($current->verdict || $current->verdict_comment)
                                {{-- the case's saved state: visible on return, not only in the textarea --}}
                                <div class="rounded-lg bg-base-200/60 px-3 py-2 mb-2.5 text-[12.5px] leading-relaxed">
                                    @if ($current->verdict)
                                        <span class="font-bold">{{ __('sessions.verdict_'.$current->verdict->value) }}</span>
                                        @if ($current->verdictBy)
                                            <span class="text-base-content/50"> · {{ $current->verdictBy->name }}</span>
                                        @endif
                                    @else
                                        <span class="text-base-content/50">{{ __('sessions.comment_without_verdict') }}</span>
                                    @endif
                                    @if ($current->verdict_comment)
                                        <div class="text-base-content/70">«{{ $current->verdict_comment }}»</div>
                                    @endif
                                </div>
                            @endif
                            <div class="flex flex-wrap items-center gap-2 mb-2.5">
                                <button wire:click="setVerdict('pass')"
                                        class="btn btn-sm {{ $current->verdict === \App\Enums\Verdict::Pass ? 'btn-success' : 'btn-outline' }}">
                                    {{ __('sessions.verdict_pass') }}</button>
                                <button wire:click="setVerdict('fail')"
                                        class="btn btn-sm {{ $current->verdict === \App\Enums\Verdict::Fail ? 'btn-error' : 'btn-outline' }}">
                                    {{ __('sessions.verdict_fail') }}</button>
                                <button wire:click="setVerdict('blocked')"
                                        class="btn btn-sm {{ $current->verdict === \App\Enums\Verdict::Blocked ? 'btn-warning' : 'btn-outline' }}">
                                    {{ __('sessions.verdict_blocked') }}</button>
                                {{-- comment toggle: the input form does not take up space permanently --}}
                                <button wire:click="$toggle('commentOpen')"
                                        class="btn btn-ghost btn-xs ml-auto {{ $commentOpen ? 'text-primary' : 'text-base-content/50' }}">
                                    ＋ {{ __('sessions.comment_toggle') }}</button>
                            </div>
                            @if ($commentOpen)
                                <textarea wire:model="verdictComment" rows="2"
                                          placeholder="{{ __('sessions.verdict_comment_hint') }}"
                                          class="textarea textarea-bordered w-full text-[13px]"></textarea>
                                <button wire:click="moveNextCase" class="btn btn-primary btn-sm mt-3">{{ __('sessions.save_and_next') }}</button>
                            @endif
                        </div>
                    @endcan
                </div>
            @else
                <div class="card bg-base-100 border border-dashed border-base-200 rounded-xl p-12 text-center text-sm text-base-content/40">
                    {{ __('sessions.no_cases') }}
                </div>
            @endif
        </div>
    </div>

    {{-- manual case-add modal: active project cases outside the session --}}
    <div x-show="attach.open" x-cloak x-transition.opacity role="dialog" aria-modal="true"
         aria-label="{{ __('sessions.add_case') }}"
         class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-6"
         x-on:click.self="attach.open = false">
        <div class="card bg-base-100 border border-base-200 rounded-xl w-full max-w-lg max-h-[70vh] flex flex-col">
            <div class="px-5 py-3.5 border-b border-base-200 flex items-center gap-3">
                <h3 class="text-sm font-bold">{{ __('sessions.add_case') }}</h3>
                <button type="button" class="btn btn-ghost btn-xs btn-circle ml-auto"
                        x-on:click="attach.open = false" aria-label="{{ __('sessions.close') }}">✕</button>
            </div>
            <div class="px-5 pt-3">
                <input type="text" wire:model.live.debounce.400ms="attachQuery"
                       placeholder="{{ __('sessions.attach_search') }}"
                       class="input input-bordered input-sm w-full">
            </div>
            <div class="px-5 py-3 overflow-y-auto space-y-1.5">
                @forelse ($this->attachableCases() as $attachable)
                    <button wire:click="attachCase({{ $attachable->id }})" wire:key="attach-{{ $attachable->id }}"
                            class="w-full flex items-center gap-3 text-left px-3 py-2 rounded-lg border border-base-200 hover:bg-base-200/60">
                        <span class="font-mono text-xs text-base-content/50 shrink-0">TC-{{ $attachable->id }}</span>
                        <span class="text-[13px] flex-1 truncate">{{ $attachable->title }}</span>
                        @if ($attachable->folder)
                            <span class="text-xs text-base-content/40 truncate max-w-40 shrink-0">{{ $attachable->folder }}</span>
                        @endif
                    </button>
                @empty
                    <div class="text-sm text-base-content/50 py-6 text-center">{{ __('sessions.attach_empty') }}</div>
                @endforelse
            </div>
        </div>
    </div>

    {{-- "summary copied" toast --}}
    <div x-show="copied" x-cloak class="toast toast-end z-[60]">
        <div class="alert alert-success py-2 px-4 text-sm">{{ __('sessions.summary_copied') }}</div>
    </div>
</div>
