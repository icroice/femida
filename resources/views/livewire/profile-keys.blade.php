<div x-data x-on:notify.window="alert($event.detail.message)">

    <h1 class="text-xl font-extrabold mb-5">{{ __('profile.section_keys') }}</h1>

    <div class="max-w-4xl">
        {{-- My API keys: personal keys live in the profile, not in project settings --}}
        <div class="card bg-base-100 border border-base-200 rounded-xl p-6">
            <h2 class="text-[15px] font-extrabold mb-1">{{ __('profile.section_keys') }}</h2>
            <p class="text-xs text-base-content/50 mb-4">{{ __('profile.keys_hint') }}</p>

            @if ($newToken)
                <div class="rounded-lg p-4 mb-4 bg-success/10 border border-success/30">
                    <div class="text-[13px] font-bold text-success">{{ __('settings.key_created', ['name' => auth()->user()->name]) }}</div>
                    <div class="text-xs mt-1 text-success/80">{{ __('settings.key_once_hint') }}</div>
                    <div class="font-mono text-[13px] mt-2 px-3 py-2 rounded-md bg-base-100 border border-base-200 select-all">{{ $newToken }}</div>
                </div>
            @endif

            @if ($this->keyProjects()->isNotEmpty())
                <form wire:submit.prevent="issueKey" class="flex flex-wrap gap-3 mb-4">
                    <select wire:model.live="keyProject" class="select select-bordered select-sm">
                        <option value="">{{ __('profile.key_project_placeholder') }}</option>
                        @foreach ($this->keyProjects() as $project)
                            <option value="{{ $project->id }}">{{ $project->name }}</option>
                        @endforeach
                    </select>
                    <input wire:model="keyName" placeholder="{{ __('settings.key_name_placeholder') }}"
                           class="input input-bordered input-sm flex-1 min-w-48 @error('keyName') input-error @enderror">
                    <button class="btn btn-primary btn-sm">{{ __('settings.create_key') }}</button>
                </form>
            @endif

            <table class="table table-sm text-[13.5px]">
                <thead>
                <tr class="text-[11px] uppercase tracking-wider text-base-content/50">
                    <th>{{ __('settings.column_project') }}</th>
                    <th>{{ __('settings.column_key') }}</th>
                    <th>{{ __('settings.column_prefix') }}</th>
                    <th>{{ __('settings.column_last_used') }}</th>
                    <th class="text-right">{{ __('app.actions') }}</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($this->myKeys() as $key)
                    <tr class="{{ $key->isRevoked() ? 'opacity-60' : '' }}">
                        <td class="font-semibold">{{ $key->project?->name ?? '—' }}</td>
                        <td class="{{ $key->isRevoked() ? 'line-through' : '' }}">{{ $key->name }}</td>
                        <td class="font-mono text-xs text-base-content/60">{{ $key->token_prefix }}</td>
                        <td class="text-xs text-base-content/60">{{ $key->last_used_at?->locale(app()->getLocale())->diffForHumans() ?? __('settings.never') }}</td>
                        <td class="text-right">
                            @unless ($key->isRevoked())
                                <button wire:click="revokeKey({{ $key->id }})" wire:confirm="{{ __('settings.revoke_confirm') }}?"
                                        class="btn btn-ghost btn-xs">{{ __('settings.revoke') }}</button>
                            @endunless
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-sm text-base-content/50 py-6">{{ __('profile.no_keys') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
