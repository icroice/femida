<div x-data x-on:notify.window="alert($event.detail.message)">

    <h1 class="text-xl font-extrabold mb-5">{{ __('profile.title') }}</h1>

    <div class="max-w-xl space-y-5">

        {{-- Profile: name, email, locale --}}
        <div class="card bg-base-100 border border-base-200 rounded-xl p-6">
            <h2 class="text-[15px] font-extrabold mb-4">{{ __('profile.section_profile') }}</h2>
            <form wire:submit="saveProfile" class="space-y-4">
                <label class="flex flex-col">
                    <div class="label py-1"><span class="text-xs font-bold text-base-content/60">{{ __('profile.field_name') }}</span></div>
                    <input wire:model.blur="name" class="input input-bordered w-full @error('name') input-error @enderror">
                    @error('name')
                        <div class="text-xs text-error mt-1">{{ $message }}</div>
                    @enderror
                </label>
                <label class="flex flex-col">
                    <div class="label py-1"><span class="text-xs font-bold text-base-content/60">{{ __('profile.field_email') }}</span></div>
                    <input wire:model.blur="email" type="email" class="input input-bordered w-full @error('email') input-error @enderror">
                    @error('email')
                        <div class="text-xs text-error mt-1">{{ $message }}</div>
                    @enderror
                </label>
                <label class="flex flex-col">
                    <div class="label py-1"><span class="text-xs font-bold text-base-content/60">{{ __('profile.field_locale') }}</span></div>
                    <select wire:model.live="locale" class="select select-bordered w-full">
                        <option value="ru">{{ __('setup.locale_ru') }}</option>
                        <option value="en">English</option>
                    </select>
                </label>
                <label class="flex flex-col">
                    <div class="label py-1"><span class="text-xs font-bold text-base-content/60">{{ __('profile.field_theme') }}</span></div>
                    <select wire:model.live="theme" class="select select-bordered w-full">
                        <option value="light">{{ __('app.theme_light') }}</option>
                        <option value="dark">{{ __('app.theme_dark') }}</option>
                    </select>
                </label>
                <button class="btn btn-primary btn-sm">{{ __('app.save') }}</button>
            </form>
        </div>

        {{-- Password change --}}
        <div class="card bg-base-100 border border-base-200 rounded-xl p-6">
            <h2 class="text-[15px] font-extrabold mb-1">{{ __('profile.section_password') }}</h2>
            <p class="text-xs text-base-content/50 mb-4">{{ __('profile.password_hint') }}</p>
            <form wire:submit="savePassword" class="space-y-4">
                <label class="flex flex-col">
                    <div class="label py-1"><span class="text-xs font-bold text-base-content/60">{{ __('profile.field_current_password') }}</span></div>
                    <input wire:model="currentPassword" type="password" class="input input-bordered w-full @error('currentPassword') input-error @enderror">
                    @error('currentPassword')
                        <div class="text-xs text-error mt-1">{{ $message }}</div>
                    @enderror
                </label>
                <label class="flex flex-col">
                    <div class="label py-1"><span class="text-xs font-bold text-base-content/60">{{ __('profile.field_new_password') }}</span></div>
                    <input wire:model="password" type="password" class="input input-bordered w-full @error('password') input-error @enderror">
                    @error('password')
                        <div class="text-xs text-error mt-1">{{ $message }}</div>
                    @enderror
                </label>
                <label class="flex flex-col">
                    <div class="label py-1"><span class="text-xs font-bold text-base-content/60">{{ __('profile.field_confirm_password') }}</span></div>
                    <input wire:model="passwordConfirmation" type="password" class="input input-bordered w-full">
                </label>
                <button class="btn btn-primary btn-sm">{{ __('profile.change_password') }}</button>
            </form>
        </div>

        {{-- My API keys — a separate profile page (ProfileKeys) --}}
    </div>
</div>
