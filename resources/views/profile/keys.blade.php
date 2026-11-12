@extends('layouts.app')

@section('title', __('profile.section_keys'))

@section('shell')
    <div class="flex-1 min-h-0 flex">
        @include('partials.settings-rail', ['project' => null, 'active' => 'keys'])

        {{-- content --}}
        <div class="flex-1 min-w-0 overflow-y-auto fem-scroll p-6">
            <livewire:profile-keys/>
        </div>
    </div>
@endsection
