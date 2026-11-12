<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"
      data-theme="{{ auth()->user()?->theme === 'dark' ? 'femida-dark' : 'femida' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name'))</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="antialiased h-screen overflow-hidden bg-canvas text-base-content flex flex-col"
      x-data x-on:theme-changed.window="document.documentElement.setAttribute('data-theme', $event.detail.theme === 'dark' ? 'femida-dark' : 'femida')">

@php
    $user = auth()->user();
    $initials = mb_strtoupper(collect(explode(' ', (string) $user?->name))
        ->filter()
        ->map(fn ($part) => mb_substr($part, 0, 1))
        ->take(2)
        ->implode(''));
    $project = $project ?? null;
    $switcherProjects = $user && $user->isAdmin()
        ? \App\Models\Project::query()->orderBy('name')->get()
        : $user?->projects()->orderBy('name')->get() ?? collect();
    // outside project pages the navigation is built from the user's first project,
    // so the menu never disappears; the home page is the project picker itself
    $project = $project ?? (request()->routeIs('home') ? null : $switcherProjects->first());

    // header pill link: quiet by default, highlighted for the open section
    $navLink = fn (bool $isActive) => 'px-2.5 py-1.5 rounded-lg text-[13px] '.($isActive
        ? 'bg-base-200/70 font-bold text-base-content'
        : 'text-base-content/60 hover:text-base-content hover:bg-base-200/50');
@endphp

{{-- Single-tier header: logo · project switcher · section tabs · system/user/logout --}}
<header class="flex-none h-[50px] flex items-stretch bg-base-100 border-b border-base-200">
    <div class="flex items-center gap-2 px-4 border-r border-base-200">
        <a href="{{ route('home') }}" class="flex items-center gap-2 font-extrabold text-[15px] tracking-wide">
            <svg viewBox="0 0 24 24" class="w-5 h-5 text-primary" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M12 4v17M5 21h14M12 6l-6 3 6-3 6 3M3.5 12a3.2 3.2 0 0 0 6.4 0L6.7 9zM14.1 12a3.2 3.2 0 0 0 6.4 0L17.3 9z"/></svg>
            Femida
        </a>
    </div>

    @if ($project)
        <div class="flex items-center px-2.5">
            <div class="dropdown">
                <div tabindex="0" role="button" class="btn btn-ghost btn-sm h-8 normal-case font-normal gap-2 px-3 rounded-lg bg-base-200/50 text-base-content/80">
                    <svg viewBox="0 0 24 24" class="w-3.5 h-3.5 text-base-content/40" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>
                    <b class="font-bold">{{ $project->name }}</b>
                    <svg viewBox="0 0 12 12" class="w-2.5 h-2.5 opacity-60"><path d="M2 4l4 4 4-4" fill="none" stroke="currentColor" stroke-width="1.6"/></svg>
                </div>
                <ul tabindex="0" class="dropdown-content menu bg-base-100 rounded-box border border-base-200 w-64 p-2 shadow-lg mt-1 z-50 text-sm">
                    @forelse ($switcherProjects as $p)
                        <li><a href="{{ route('projects.cases.index', $p) }}">{{ $p->name }}</a></li>
                    @empty
                        <li class="disabled text-base-content/40 px-3 py-2">{{ __('app.only_yours') }}</li>
                    @endforelse
                    @can('create', \App\Models\Project::class)
                        <li class="border-t border-base-200/70 mt-1 pt-1">
                            <a href="{{ route('projects.create') }}" class="text-primary font-semibold">＋ {{ __('app.create_project') }}</a>
                        </li>
                    @endcan
                </ul>
            </div>
        </div>
    @endif

    {{-- project sections: cases / sessions / settings (settings — owner only) --}}
    @if ($project)
        <nav class="flex items-center gap-0.5 pl-1">
            <a href="{{ route('projects.cases.index', $project) }}"
               class="{{ $navLink(request()->routeIs('projects.cases.*')) }}">{{ __('app.cases') }}</a>
            <a href="{{ route('projects.sessions.index', $project) }}"
               class="{{ $navLink(request()->routeIs('projects.sessions.*', 'sessions.*')) }}">{{ __('app.sessions') }}</a>
            @can('update', $project)
                <a href="{{ route('projects.settings', $project) }}"
                   class="{{ $navLink(request()->routeIs('projects.settings*')) }}">{{ __('app.settings') }}</a>
            @endcan
        </nav>
    @endif

    <div class="ml-auto flex items-stretch">
        @if ($user?->managesUsers())
            <div class="flex items-center px-2">
                <a href="{{ route('users.index') }}"
                   class="px-2.5 py-1.5 rounded-lg text-[13px] {{ request()->routeIs('users.*') ? 'bg-base-200/70 font-bold text-base-content' : 'text-base-content/60 hover:text-base-content hover:bg-base-200/50' }}">
                    {{ __('app.users') }}</a>
            </div>
        @endif
        <div class="flex items-center px-1.5">
            <a href="{{ route('help.index') }}" title="{{ __('app.help') }}" aria-label="{{ __('app.help') }}"
               class="px-2 py-1.5 rounded-lg {{ request()->routeIs('help.*') ? 'bg-base-200/70 text-base-content' : 'text-base-content/60 hover:text-base-content hover:bg-base-200/50' }}">
                <svg viewBox="0 0 24 24" class="w-[17px] h-[17px]" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M2 5.5A2.5 2.5 0 0 1 4.5 3H11v16H4.5A2.5 2.5 0 0 0 2 21.5zM22 5.5A2.5 2.5 0 0 0 19.5 3H13v16h6.5a2.5 2.5 0 0 1 2.5 2.5z"/></svg>
            </a>
        </div>
        <div class="flex items-center gap-2.5 px-3.5 border-l border-base-200">
            <a href="{{ route('profile') }}" title="{{ __('profile.title') }}" class="flex items-center gap-2.5 hover:opacity-80">
                <div class="w-7 h-7 rounded-lg bg-primary/10 text-primary flex items-center justify-center text-[11px] font-bold">{{ $initials }}</div>
                <div class="leading-tight hidden sm:block">
                    <div class="text-[12.5px] font-bold">{{ $user?->name }}</div>
                    <div class="text-[10.5px] text-base-content/50">{{ $user?->is_admin ? __('app.role_admin') : __('app.role_user') }}</div>
                </div>
            </a>
        </div>
        <div class="flex items-center px-2 border-l border-base-200">
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="btn btn-ghost btn-sm btn-square" title="{{ __('app.logout') }}">
                    <svg viewBox="0 0 24 24" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M9 4H5a1 1 0 0 0-1 1v14a1 1 0 0 0 1 1h4M15 8l4 4-4 4M19 12H9"/></svg>
                </button>
            </form>
        </div>
    </div>
</header>

{{-- Work area: regular pages scroll as a whole; rail pages
     provide a shell section and split the area into independent scrolls --}}
<main class="flex-1 min-h-0 flex flex-col">
    @hasSection('shell')
        @yield('shell')
    @else
        <div class="flex-1 min-h-0 overflow-y-auto px-6 py-6">
            @if (session('status'))
                <div class="alert alert-success mb-5 text-sm"><span>{{ session('status') }}</span></div>
            @endif
            @yield('content')
        </div>
    @endif
</main>

@livewireScripts
</body>
</html>
