@extends('layouts.app')

@section('title', __('app.projects').' · '.config('app.name'))

@section('content')
<div class="flex items-center gap-4 mb-6">
    <h1 class="text-xl font-extrabold">{{ __('app.projects') }}</h1>
    <span class="text-[13px] text-base-content/50">
        {{ $projects->count() }} · {{ auth()->user()->isAdmin() ? __('app.all_projects') : __('app.only_yours') }}
    </span>
    @can('create', \App\Models\Project::class)
        <a href="{{ route('projects.create') }}" class="btn btn-primary btn-sm ml-auto">{{ __('app.create_project') }}</a>
    @endcan
</div>

@if ($projects->isEmpty())
    <div class="card bg-base-100 border border-base-200 rounded-xl p-10 text-center">
        <div class="text-sm text-base-content/50">{{ __('app.projects_empty') }}</div>
    </div>
@else
    <div class="grid grid-cols-3 gap-5">
        @foreach ($projects as $project)
            <a href="{{ route('projects.cases.index', $project) }}"
               class="card bg-base-100 border border-base-200 rounded-xl p-5 flex flex-col gap-3 hover:shadow-md transition-shadow">
                <div>
                    <div class="font-extrabold text-[15px]">{{ $project->name }}</div>
                    @if ($project->description)
                        <div class="text-[13px] text-base-content/50 mt-0.5">{{ $project->description }}</div>
                    @endif
                </div>
                <div class="flex gap-5 text-[13px] text-base-content/60 pt-1">
                    <span><b class="text-base-content">{{ $project->test_cases_count }}</b> {{ __('app.cases_count') }}</span>
                    <span><b class="text-base-content">{{ $project->test_sessions_count }}</b> {{ __('app.sessions_count') }}</span>
                </div>
                @if (! auth()->user()->isAdmin())
                    <div>
                        <span class="badge badge-sm {{ auth()->user()->roleIn($project) === \App\Enums\ProjectRole::Developer ? 'bg-primary/10 text-primary' : 'bg-warning/10 text-warning' }}">
                            {{ auth()->user()->roleIn($project) ? __('users.role_'.auth()->user()->roleIn($project)->value) : '' }}
                        </span>
                    </div>
                @endif
            </a>
        @endforeach
    </div>
@endif
@endsection
