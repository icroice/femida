<div class="grid grid-cols-[1fr_300px] gap-5 items-start">

    <div class="space-y-5">
        <div class="card bg-base-100 border border-base-200 rounded-xl p-6">
            <h1 class="text-lg font-extrabold mb-4">
                @if ($case !== null)
                    {{ __('cases.editor_title_edit', ['id' => $case->id]) }}
                @else
                    {{ __('cases.editor_title') }}
                @endif
            </h1>

            <label class="flex flex-col">
                <div class="label py-1"><span class="text-xs font-bold text-base-content/60">{{ __('cases.field_title') }}</span></div>
                <input wire:model.blur="title" class="input input-bordered w-full @error('title') input-error @enderror">
                @error('title') <div class="text-xs text-error mt-1">{{ $message }}</div> @enderror
            </label>

            <label class="flex flex-col mt-4">
                <div class="label py-1"><span class="text-xs font-bold text-base-content/60">{{ __('cases.field_preconditions') }}</span></div>
                <textarea wire:model.blur="preconditions" rows="2" class="textarea textarea-bordered w-full @error('preconditions') input-error @enderror"></textarea>
            </label>
        </div>

        <div class="card bg-base-100 border border-base-200 rounded-xl p-6">
            <div class="flex items-center mb-4">
                <h2 class="text-[15px] font-extrabold">{{ __('cases.field_steps') }}</h2>
                <span class="text-xs text-base-content/40 ml-3">{{ __('cases.steps_hint') }}</span>
            </div>

            <div class="space-y-3">
                @foreach ($steps as $index => $step)
                    <div class="flex gap-3 items-start">
                        <span class="font-mono text-xs font-bold text-base-content/40 w-8 pt-3">{{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}</span>
                        <div class="flex-1 grid grid-cols-2 gap-3">
                            <input wire:model.blur="steps.{{ $index }}.action" placeholder="{{ __('cases.step_action') }}"
                                   class="input input-bordered input-sm @error("steps.{$index}.action") input-error @enderror">
                            <input wire:model.blur="steps.{{ $index }}.expected" placeholder="{{ __('cases.step_expected') }}"
                                   class="input input-bordered input-sm @error("steps.{$index}.expected") input-error @enderror">
                        </div>
                        <button wire:click="removeStep({{ $index }})" class="btn btn-ghost btn-xs mt-1" title="{{ __('app.cancel') }}">✕</button>
                    </div>
                @endforeach
            </div>

            <button wire:click="addStep" class="btn btn-outline btn-sm mt-4">{{ __('cases.add_step') }}</button>
        </div>

        <div class="flex items-center gap-3">
            <button wire:click="save" wire:loading.attr="disabled" class="btn btn-primary btn-sm px-6">{{ __('app.save') }}</button>
            <a href="{{ route('projects.cases.index', $project) }}" class="btn btn-ghost btn-sm">{{ __('app.cancel') }}</a>
            @can('delete', $case)
                @if ($case !== null && $case->status !== \App\Enums\CaseStatus::Inactive)
                    <button wire:click="archive" wire:confirm="{{ __('cases.archive') }}?"
                            class="btn btn-sm btn-outline ml-auto border-error/40 text-error hover:bg-error/10 hover:border-error">
                        {{ __('cases.archive') }}
                    </button>
                @endif
            @endcan
        </div>
    </div>

    <div class="space-y-5">
        <div class="card bg-base-100 border border-base-200 rounded-xl p-5 space-y-4">
            <h3 class="text-xs font-bold uppercase tracking-widest text-base-content/50">{{ __('cases.params') }}</h3>

            <label class="flex flex-col">
                <div class="label py-1"><span class="text-xs font-bold text-base-content/60">{{ __('cases.field_priority') }}</span></div>
                <select wire:model.live="priority" class="select select-bordered select-sm w-full">
                    @foreach (\App\Enums\CasePriority::cases() as $p)
                        <option value="{{ $p->value }}">{{ $p->value }}</option>
                    @endforeach
                </select>
            </label>

            <label class="flex flex-col">
                <div class="label py-1"><span class="text-xs font-bold text-base-content/60">{{ __('cases.field_status') }}</span></div>
                {{-- Changing status = deliberate archiving (policy delete,
                     developer+) — a member sees a badge without the toggle --}}
                @if ($case !== null && Gate::allows('delete', $case))
                    <select wire:model.live="status" class="select select-bordered select-sm w-full">
                        <option value="active">{{ __('cases.status_active') }}</option>
                        <option value="inactive">{{ __('cases.status_inactive') }}</option>
                    </select>
                @else
                    <div class="h-[30px] flex items-center">
                        <span class="badge badge-sm {{ $status === 'active' ? 'badge-success badge-outline' : 'badge-ghost text-base-content/50' }}">
                            {{ __('cases.status_'.$status) }}
                        </span>
                    </div>
                @endif
            </label>

            <label class="flex flex-col">
                <div class="label py-1"><span class="text-xs font-bold text-base-content/60">{{ __('cases.field_folder') }}</span></div>
                <input wire:model.blur="folder" list="folder-options" placeholder="{{ __('cases.folder_placeholder') }}"
                       class="input input-bordered input-sm w-full @error('folder') input-error @enderror">
                <datalist id="folder-options">
                    @foreach ($this->folders() as $existingFolder)
                        <option value="{{ $existingFolder }}"></option>
                    @endforeach
                </datalist>
                @error('folder')
                    <div class="text-xs text-error mt-1">{{ $message }}</div>
                @enderror
                <div class="text-xs text-base-content/40 mt-1">{{ __('cases.folder_hint') }}</div>
            </label>
        </div>

        @if ($case !== null)
            <div class="card bg-base-100 border border-base-200 rounded-xl p-5 space-y-2">
                <h3 class="text-xs font-bold uppercase tracking-widest text-base-content/50 mb-1">{{ __('cases.info') }}</h3>
                <div class="flex justify-between text-[13px]">
                    <span class="text-base-content/50">{{ __('cases.created') }}</span>
                    <span>{{ $case->created_at?->format(__('app.date_short')) }}</span>
                </div>
                <div class="flex justify-between text-[13px]">
                    <span class="text-base-content/50">{{ __('cases.updated') }}</span>
                    <span>{{ $case->updated_at?->format(__('app.date_short')) }}</span>
                </div>
                <div class="flex justify-between text-[13px]">
                    <span class="text-base-content/50">{{ __('cases.in_sessions') }}</span>
                    <span>{{ $case->sessionCases()->count() }}</span>
                </div>
            </div>
        @endif
    </div>
</div>
