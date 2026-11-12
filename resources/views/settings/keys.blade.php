@extends('layouts.app', ['project' => $project])

@section('title', $project->name.' · '.__('settings.api_keys'))

@section('shell')
    <div class="flex-1 min-h-0 flex">
        @include('partials.settings-rail', ['project' => $project, 'active' => 'keys'])

        {{-- content --}}
        <div class="flex-1 min-w-0 overflow-y-auto fem-scroll p-6">
            <livewire:project-keys :project="$project" :key="$project->id"/>
        </div>
    </div>
@endsection
