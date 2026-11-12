<div x-data x-on:notify.window="alert($event.detail.message)">

    <h1 class="text-xl font-extrabold mb-5">{{ __('settings.api_keys') }}</h1>

    <div class="max-w-4xl">
        {{-- All project keys: the owner sees who uses what and can revoke any;
             personal ones are issued in the profile --}}
        <div class="card bg-base-100 border border-base-200 rounded-xl p-6">
            <div class="flex items-center gap-3 flex-wrap mb-4">
                <h2 class="text-[15px] font-extrabold">{{ __('settings.keys_project_title') }}</h2>
                <span class="text-xs text-base-content/40">{{ __('settings.keys_project_hint') }}</span>
                <a href="{{ route('profile.keys') }}" class="btn btn-outline btn-sm border-base-200 text-base-content/60 ml-auto">{{ __('profile.section_keys') }} →</a>
            </div>

            @php($keys = $this->projectKeys())

            @if ($keys->isEmpty())
                <p class="text-[13px] text-base-content/50">{{ __('settings.no_keys') }}</p>
            @else
                <div class="overflow-x-auto">
                    <table class="table table-sm">
                        <thead>
                            <tr class="text-[11px] uppercase tracking-wide text-base-content/50">
                                <th>{{ __('settings.column_key') }}</th>
                                <th>{{ __('settings.column_prefix') }}</th>
                                <th>{{ __('settings.column_owner') }}</th>
                                <th>{{ __('settings.column_last_used') }}</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($keys as $key)
                                <tr class="{{ $key->revoked_at ? 'opacity-50' : '' }}">
                                    <td class="font-medium">{{ $key->name }}</td>
                                    <td class="font-mono text-xs">{{ $key->token_prefix }}</td>
                                    <td>{{ $key->owner?->name ?? '—' }}</td>
                                    <td class="text-xs">{{ $key->last_used_at?->locale(app()->getLocale())->translatedFormat('d.m.Y H:i') ?? __('settings.never') }}</td>
                                    <td class="text-right">
                                        @if ($key->revoked_at)
                                            <span class="badge badge-ghost badge-sm">{{ __('settings.revoked_badge') }}</span>
                                        @else
                                            <button wire:click="revokeProjectKey({{ $key->id }})" wire:confirm="{{ __('settings.revoke_confirm') }}"
                                                                    class="btn btn-ghost btn-xs text-error">{{ __('settings.revoke') }}</button>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>
