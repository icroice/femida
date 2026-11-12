@extends('layouts.app')

@section('title', __('app.users').' · '.config('app.name'))

@section('content')
<livewire:users-admin/>
@endsection
