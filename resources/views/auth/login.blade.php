@extends('layouts.guest')

@section('title', __('auth.title').' · '.config('app.name'))

@section('content')
<div class="absolute top-5 right-6 flex gap-1 text-xs font-bold">
    @foreach (['ru', 'en'] as $l)
        <a href="{{ route('locale.switch', $l) }}"
           class="px-3 py-1.5 rounded-lg {{ app()->getLocale() === $l ? 'bg-primary text-primary-content' : 'text-base-content/40 hover:text-base-content/70' }}">
            {{ strtoupper($l) }}
        </a>
    @endforeach
</div>

<div class="min-h-screen flex flex-col items-center justify-center px-4 py-10">

    <div class="flex flex-col items-center gap-3 mb-7">
        <svg viewBox="0 0 24 24" class="w-12 h-12 text-primary" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M12 4v17M5 21h14M12 6l-6 3 6-3 6 3M3.5 12a3.2 3.2 0 0 0 6.4 0L6.7 9z M14.1 12a3.2 3.2 0 0 0 6.4 0L17.3 9z"/></svg>
        <div class="text-2xl font-extrabold tracking-wide">Femida</div>
        <div class="text-sm text-base-content/50">{{ __('auth.tagline') }}</div>
    </div>

    <div class="card bg-base-100 border border-base-200 rounded-xl w-full max-w-sm p-7">
        <h1 class="text-lg font-extrabold mb-4">{{ __('auth.title') }}</h1>

        @if ($errors->any())
            <div class="alert alert-error mb-4 text-[13px] py-2.5 px-3">
                <span>{{ $errors->first() }}</span>
            </div>
        @endif

        <form method="POST" action="{{ route('login.attempt') }}">
            @csrf

            <label class="flex flex-col">
                <div class="label py-1"><span class="text-xs font-bold text-base-content/60">Email</span></div>
                <input type="email" name="email" value="{{ old('email') }}" required autofocus
                       class="input input-bordered w-full @error('email') input-error @enderror">
                @error('email')
                    <div class="text-xs text-error mt-1">{{ $message }}</div>
                @enderror
            </label>

            <label class="flex flex-col mt-3">
                <div class="label py-1"><span class="text-xs font-bold text-base-content/60">{{ __('auth.attributes.password') }}</span></div>
                <input type="password" name="password" required
                       class="input input-bordered w-full @error('password') input-error @enderror">
                @error('password')
                    <div class="text-xs text-error mt-1">{{ $message }}</div>
                @enderror
            </label>

            <button class="btn btn-primary btn-sm mt-6">{{ __('auth.submit') }}</button>
        </form>

        <div class="text-xs text-base-content/50 mt-5 leading-relaxed text-center">
            {{ __('auth.hint') }}<br>
            {{ __('auth.recovery') }}
        </div>
    </div>

    <div class="text-xs text-base-content/40 mt-6">Femida · self-hosted</div>
</div>
@endsection
