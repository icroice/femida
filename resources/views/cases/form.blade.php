@extends('layouts.app', ['project' => $project])

@section('title', $project->name.' · '.($case ? __('cases.editor_title_edit', ['id' => $case->id]) : __('cases.editor_title')))

@section('content')
<div class="text-[13px] text-base-content/50 mb-4">
    <a href="{{ route('projects.cases.index', $project) }}" class="hover:text-base-content">{{ __('cases.title') }}</a>
    <span class="mx-1">/</span>
    {{ $case ? 'TC-'.$case->id : __('cases.new') }}
</div>

<livewire:case-editor :project="$project" :case="$case" :key="$case?->id ?? 'new'"/>
@endsection
