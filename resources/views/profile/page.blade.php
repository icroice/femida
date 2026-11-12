@extends('layouts.app')

@section('title', __('profile.title'))

@section('shell')
    <div class="flex-1 min-h-0 flex">
        @include('partials.settings-rail', ['project' => null, 'active' => 'main'])

        {{-- content --}}
        <div class="flex-1 min-w-0 overflow-y-auto fem-scroll p-6">
            <livewire:profile-settings/>
        </div>
    </div>
@endsection
