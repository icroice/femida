@extends('layouts.app', ['project' => $project])

@section('title', $project->name.' · '.__('app.cases'))

@section('shell')
    <livewire:cases-browser :project="$project" class="flex-1 min-h-0 flex flex-col" />
@endsection
