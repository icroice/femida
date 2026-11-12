<div x-data x-on:notify.window="alert($event.detail.message)">

    <h1 class="text-xl font-extrabold mb-5">{{ __('settings.title', ['project' => $project->name]) }}</h1>

    <div class="max-w-4xl space-y-5">

        {{-- Project --}}
        <div class="card bg-base-100 border border-base-200 rounded-xl p-6">
            <h2 class="text-[15px] font-extrabold mb-4">{{ __('settings.project') }}</h2>
            <label class="flex flex-col">
                <div class="label py-1"><span class="text-xs font-bold text-base-content/60">{{ __('app.attributes.name') }}</span></div>
                <input wire:model.blur="name" class="input input-bordered w-full @error('name') input-error @enderror">
            </label>
            <label class="flex flex-col mt-4">
                <div class="label py-1"><span class="text-xs font-bold text-base-content/60">{{ __('app.attributes.description') }}</span></div>
                <textarea wire:model.blur="description" rows="2" class="textarea textarea-bordered w-full"></textarea>
            </label>
            <button wire:click="saveProject" class="btn btn-primary btn-sm mt-4">{{ __('app.save') }}</button>
        </div>

        {{-- Project API keys — a separate settings page (ProjectKeys) --}}

        {{-- Danger zone --}}
        @can('delete', $project)
            <div class="card border rounded-xl p-6 border-error/40 bg-error/5">
                <h2 class="text-[15px] font-extrabold text-error">{{ __('settings.danger') }}</h2>
                <p class="text-[13px] text-base-content/60 mt-1 mb-4">{{ __('settings.danger_hint') }}</p>
                <div class="flex items-center gap-3">
                    <button wire:click="deleteProject" wire:confirm="{{ __('settings.danger_confirm') }}"
                            class="btn btn-sm btn-error btn-outline">{{ __('settings.delete_project') }}</button>
                </div>
            </div>
        @endcan
    </div>
</div>
