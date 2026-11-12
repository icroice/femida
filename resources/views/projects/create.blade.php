@extends('layouts.app')

@section('title', __('app.create_project').' · '.config('app.name'))

@section('content')
<div class="max-w-xl">
    <div class="text-[13px] text-base-content/50 mb-4">
        <a href="{{ route('home') }}" class="hover:text-base-content">{{ __('app.projects') }}</a> <span class="mx-1">/</span> {{ __('app.create_project') }}
    </div>

    <div class="card bg-base-100 border border-base-200 rounded-xl p-6">
        <h1 class="text-lg font-extrabold mb-1">{{ __('app.create_project') }}</h1>
        <p class="text-[13px] text-base-content/50 mb-5">{{ __('app.create_project_hint') }}</p>

        @if ($errors->any())
            <div class="alert alert-error mb-4 text-[13px] py-2.5 px-3"><span>{{ $errors->first() }}</span></div>
        @endif

        <form method="POST" action="{{ route('projects.store') }}">
            @csrf

            <label class="flex flex-col">
                <div class="label py-1"><span class="text-xs font-bold text-base-content/60">{{ __('app.attributes.name') }}</span></div>
                <input name="name" value="{{ old('name') }}" required autofocus class="input input-bordered w-full @error('name') input-error @enderror">
                @error('name') <div class="text-xs text-error mt-1">{{ $message }}</div> @enderror
            </label>

            <label class="flex flex-col mt-4">
                <div class="label py-1"><span class="text-xs font-bold text-base-content/60">{{ __('app.attributes.slug') }}</span></div>
                <input name="slug" value="{{ old('slug') }}" required placeholder="my-project" pattern="[a-z0-9]+(-[a-z0-9]+)*" maxlength="60" class="input input-bordered w-full font-mono @error('slug') input-error @enderror">
                @error('slug') <div class="text-xs text-error mt-1">{{ $message }}</div> @enderror
                <div class="text-xs text-base-content/50 mt-1">{{ __('app.slug_hint') }}</div>
            </label>

            <label class="flex flex-col mt-4">
                <div class="label py-1"><span class="text-xs font-bold text-base-content/60">{{ __('app.attributes.description') }}</span></div>
                <textarea name="description" rows="2" class="textarea textarea-bordered w-full">{{ old('description') }}</textarea>
            </label>

            <button class="btn btn-primary btn-sm mt-5 px-6">{{ __('app.save') }}</button>
            <a href="{{ route('home') }}" class="btn btn-ghost btn-sm mt-5 px-6">{{ __('app.cancel') }}</a>
        </form>
    </div>
</div>
@endsection
