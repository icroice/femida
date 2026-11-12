<div x-data x-on:notify.window="alert($event.detail.message)">
    <div class="flex items-baseline gap-4 mb-2">
        <h1 class="text-xl font-extrabold">{{ __('app.users') }}</h1>
        <span class="badge badge-sm badge-ghost">{{ $users->count() }}</span>
    </div>
    <div class="text-xs text-base-content/40 mb-5">{{ __('users.hint') }}</div>

    @if ($generatedPassword)
        <div class="rounded-lg p-4 mb-5 bg-success/10 border border-success/30">
            <div class="text-[13px] font-bold text-success">{{ __('users.new_password_for', ['name' => $generatedFor]) }}</div>
            <div class="text-xs mt-1 text-success/80">{{ __('settings.key_once_hint') }}</div>
            <div class="font-mono text-[14px] mt-2 px-3 py-2 rounded-md bg-base-100 border border-base-200 select-all">{{ $generatedPassword }}</div>
        </div>
    @endif

    {{-- user creation --}}
    <div class="card bg-base-100 border border-base-200 rounded-xl p-6 mb-5">
        <h2 class="text-[15px] font-extrabold mb-4">{{ __('users.create') }}</h2>
        <form wire:submit="createUser">
            <div class="grid grid-cols-2 gap-4">
                <label class="flex flex-col">
                    <div class="label py-1"><span class="text-xs font-bold text-base-content/60">{{ __('users.field_name') }}</span></div>
                    <input wire:model.blur="name" class="input input-bordered w-full @error('name') input-error @enderror">
                    @error('name') <div class="text-xs text-error mt-1">{{ $message }}</div> @enderror
                </label>
                <label class="flex flex-col">
                    <div class="label py-1"><span class="text-xs font-bold text-base-content/60">Email</span></div>
                    <input wire:model.blur="email" type="email" class="input input-bordered w-full @error('email') input-error @enderror">
                    @error('email') <div class="text-xs text-error mt-1">{{ $message }}</div> @enderror
                </label>
                <label class="flex flex-col">
                    <div class="label py-1"><span class="text-xs font-bold text-base-content/60">{{ __('users.field_password') }} <span class="font-normal text-base-content/40">· {{ __('users.password_min') }}</span></span></div>
                    <input wire:model.blur="password" type="text" class="input input-bordered w-full font-mono @error('password') input-error @enderror">
                    @error('password') <div class="text-xs text-error mt-1">{{ $message }}</div> @enderror
                </label>
                <label class="flex flex-col">
                    <div class="label py-1"><span class="text-xs font-bold text-base-content/60">{{ __('users.field_locale') }}</span></div>
                    <select wire:model.live="locale" class="select select-bordered w-full">
                        <option value="ru">{{ __('setup.locale_ru') }}</option>
                        <option value="en">English</option>
                    </select>
                </label>
            </div>

            <div class="mt-5">
                <div class="text-xs font-bold text-base-content/60 mb-2">{{ __('users.field_roles') }}</div>
                <div class="grid grid-cols-2 gap-3">
                    @foreach ($projects as $project)
                        <div class="flex items-center gap-2 border border-base-200 rounded-lg px-3 py-2">
                            <span class="text-[13px] font-semibold flex-1">{{ $project->name }}</span>
                            <select wire:model.live="roles.{{ $project->id }}" class="select select-bordered select-xs">
                                <option value="">{{ __('users.role_none') }}</option>
                                @foreach ($assignableRoles as $roleValue)
                                    <option value="{{ $roleValue }}">{{ __('users.role_'.$roleValue) }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endforeach
                </div>
            </div>

            <button class="btn btn-primary btn-sm mt-5">{{ __('users.create') }}</button>
        </form>
    </div>

    {{-- table --}}
    <div class="card bg-base-100 border border-base-200 rounded-xl overflow-hidden">
        <table class="table table-sm text-[13.5px]">
            <thead>
            <tr class="text-[11px] uppercase tracking-wider text-base-content/50">
                <th>{{ __('users.column_user') }}</th>
                <th>{{ __('users.column_locale') }}</th>
                <th>{{ __('users.column_role') }}</th>
                <th>{{ __('users.column_projects') }}</th>
                <th class="text-right">{{ __('app.actions') }}</th>
            </tr>
            </thead>
            <tbody>
            @foreach ($users as $user)
                <tr class="{{ $user->isBlocked() ? 'opacity-60' : '' }}">
                    <td>
                        <div class="leading-tight">
                            <div class="font-bold">
                                {{ $user->name }}
                                @if ($user->isBlocked())
                                    <span class="badge badge-sm ml-1 bg-error/10 text-error">{{ __('users.blocked') }}</span>
                                @endif
                            </div>
                            <div class="text-xs text-base-content/50">{{ $user->email }}</div>
                        </div>
                    </td>
                    <td><span class="badge badge-sm badge-ghost">{{ strtoupper($user->locale) }}</span></td>
                    <td>
                        @if ($user->is_admin)
                            <span class="badge badge-sm bg-primary/10 text-primary">{{ __('users.instance_admin') }}</span>
                        @else
                            <span class="text-base-content/40">—</span>
                        @endif
                    </td>
                    <td>
                        {{-- membership editor: a row per managed project, '' = no access --}}
                        <div class="flex flex-col gap-1">
                            @foreach ($projects as $project)
                                @php $currentRole = $user->projects->firstWhere('id', $project->id)?->pivot?->role?->value ?? ''; @endphp
                                <div class="flex items-center gap-2">
                                    <span class="text-[13px] text-base-content/70 w-32 truncate" title="{{ $project->name }}">{{ $project->name }}</span>
                                    <select
                                        {{ $user->is_admin ? 'disabled' : '' }}
                                        wire:change="setMemberRole({{ $user->id }}, {{ $project->id }}, $event.target.value)"
                                        class="select select-bordered select-xs {{ $currentRole === 'owner' ? 'select-primary' : '' }}">
                                        <option value="" @selected($currentRole === '')>{{ __('users.role_none') }}</option>
                                        @foreach ($assignableRoles as $roleValue)
                                            <option value="{{ $roleValue }}" @selected($currentRole === $roleValue)>{{ __('users.role_'.$roleValue) }}</option>
                                        @endforeach
                                        @if ($currentRole !== '' && ! in_array($currentRole, $assignableRoles))
                                            {{-- the role exists but the current user cannot assign it — shown as-is --}}
                                            <option value="{{ $currentRole }}" selected disabled>{{ __('users.role_'.$currentRole) }}</option>
                                        @endif
                                    </select>
                                </div>
                            @endforeach
                        </div>
                    </td>
                    <td class="text-right">
                        @can('resetPassword', $user)
                            <button wire:click="resetPassword({{ $user->id }})" class="btn btn-ghost btn-xs">{{ __('users.reset_password') }}</button>
                        @endcan
                        {{-- blocking — admin only --}}
                        @can('block', $user)
                            @if (! $user->is_admin)
                                <button wire:click="toggleBlock({{ $user->id }})" class="btn btn-ghost btn-xs">
                                    {{ $user->isBlocked() ? __('users.unblock') : __('users.block') }}
                                </button>
                            @endif
                        @endcan
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
