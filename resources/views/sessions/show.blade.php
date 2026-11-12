@extends('layouts.app', ['project' => $project])

@section('title', $session->external_ref.' · '.$session->title)

@section('shell')
<livewire:session-review :project="$project" :session="$session" :key="$session->id" class="flex-1 min-h-0 flex flex-col"/>
@endsection
