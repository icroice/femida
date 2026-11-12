@extends('layouts.guest')

@section('title', __('setup.title'))

@section('content')
<div class="min-h-screen flex flex-col items-center justify-center px-4 py-10">

    <div class="flex items-center gap-2.5 mb-6">
        <svg viewBox="0 0 24 24" class="w-8 h-8 text-primary" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M12 4v17M5 21h14M12 6l-6 3 6-3 6 3M3.5 12a3.2 3.2 0 0 0 6.4 0L6.7 9z M14.1 12a3.2 3.2 0 0 0 6.4 0L17.3 9z"/></svg>
        <span class="text-xl font-extrabold tracking-wide">{{ __('setup.brand') }}</span>
    </div>

    <div class="card bg-base-100 border border-base-200 rounded-xl w-full max-w-2xl p-8">

        <ul class="steps w-full mb-7">
            <li class="step step-primary">{{ __('setup.steps.checks') }}</li>
            <li class="step step-primary">{{ __('setup.steps.admin') }}</li>
            <li class="step">{{ __('setup.steps.done') }}</li>
        </ul>

        <div class="grid grid-cols-2 gap-x-6 gap-y-2 text-[13px] mb-7 px-1">
            @foreach ($checks as $check)
                <span class="flex items-center gap-2 {{ $check['ok'] ? 'text-base-content/70' : 'text-error font-bold' }}">
                    @if ($check['ok'])
                        <svg viewBox="0 0 12 12" class="w-3.5 h-3.5 text-success"><path d="M2 6.5l2.6 2.6L10 3" fill="none" stroke="currentColor" stroke-width="1.8"/></svg>
                    @else
                        <svg viewBox="0 0 12 12" class="w-3.5 h-3.5"><path d="M2.5 2.5l7 7M9.5 2.5l-7 7" fill="none" stroke="currentColor" stroke-width="1.8"/></svg>
                    @endif
                    {{ $check['label'] }}
                </span>
            @endforeach
        </div>

        <h1 class="text-lg font-extrabold mb-1">{{ __('setup.admin_title') }}</h1>
        <p class="text-[13px] text-base-content/50 mb-5">{{ __('setup.admin_hint') }}</p>

        <form method="POST" action="{{ route('setup.store') }}">
            @csrf

            <div class="grid grid-cols-2 gap-4">
                <label class="flex flex-col">
                    <div class="label py-1"><span class="text-xs font-bold text-base-content/60">{{ __('setup.attributes.name') }}</span></div>
                    <input name="name" value="{{ old('name') }}" required autofocus class="input input-bordered w-full @error('name') input-error @enderror">
                    @error('name') <div class="text-xs text-error mt-1">{{ $message }}</div> @enderror
                </label>

                <label class="flex flex-col">
                    <div class="label py-1"><span class="text-xs font-bold text-base-content/60">Email</span></div>
                    <input type="email" name="email" value="{{ old('email') }}" required class="input input-bordered w-full @error('email') input-error @enderror">
                    @error('email') <div class="text-xs text-error mt-1">{{ $message }}</div> @enderror
                </label>

                <label class="flex flex-col">
                    <div class="label py-1"><span class="text-xs font-bold text-base-content/60">{{ __('setup.attributes.password') }} <span class="font-normal text-base-content/40">· {{ __('setup.password_hint') }}</span></span></div>
                    <input type="password" name="password" required class="input input-bordered w-full @error('password') input-error @enderror">
                    @error('password') <div class="text-xs text-error mt-1">{{ $message }}</div> @enderror
                </label>

                <label class="flex flex-col">
                    <div class="label py-1"><span class="text-xs font-bold text-base-content/60">{{ __('setup.attributes.password_confirmation') }}</span></div>
                    <input type="password" name="password_confirmation" required class="input input-bordered w-full"></label>
            </div>

            <div class="mt-5">
                <div class="text-xs font-bold text-base-content/60 mb-2">{{ __('setup.attributes.locale') }}</div>
                <div class="grid grid-cols-2 gap-3">
                    <label class="flex items-center gap-3 border rounded-lg px-4 py-3 cursor-pointer {{ old('locale', 'en') === 'ru' ? 'border-primary bg-primary/10' : 'border-base-200' }}">
                        <input type="radio" name="locale" value="ru" class="radio radio-primary radio-sm" {{ old('locale', 'en') === 'ru' ? 'checked' : '' }}>
                        <span class="text-sm font-semibold">{{ __('setup.locale_ru') }}</span>
                    </label>
                    <label class="flex items-center gap-3 border rounded-lg px-4 py-3 cursor-pointer {{ old('locale', 'en') === 'en' ? 'border-primary bg-primary/10' : 'border-base-200' }}">
                        <input type="radio" name="locale" value="en" class="radio radio-primary radio-sm" {{ old('locale', 'en') === 'en' ? 'checked' : '' }}>
                        <span class="text-sm font-semibold">{{ __('setup.locale_en') }}</span>
                    </label>
                </div>
            </div>

            <button class="btn btn-primary btn-sm mt-7">{{ __('setup.submit') }}</button>
            <div class="text-xs text-base-content/40 mt-3 text-center">{{ __('setup.note') }}</div>
        </form>
    </div>

    <div class="text-xs text-base-content/40 mt-6">{{ __('setup.wizard_hint') }}</div>
</div>
@endsection
