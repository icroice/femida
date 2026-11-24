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

    // The shell (module tabs, dark rail, project switcher) exists only inside
    // a project; home is the project picker itself, other pages are bare.
    // The context comes from the route parameter — a $project view variable
    // would leak from content sections (e.g. the home page foreach).
    $project = request()->route('project');
    $switcherProjects = $user && $user->isAdmin()
        ? \App\Models\Project::query()->orderBy('name')->get()
        : $user?->projects()->orderBy('name')->get() ?? collect();

    // Module tabs: registry modules the user may view (spec §5 — membership ∧
    // right; today every member holds every verb, v2-18). A module without
    // sections has no landing route and shows no tab.
    $access = app(\App\Support\Access\AccessService::class);
    $modules = $project && $user
        ? collect(\App\Support\Access\ModuleRegistry::all())
            ->filter(fn ($m) => $m->sections !== []
                && $access->check($user, $project, $m->ability($m->sections[0], \App\Support\Access\Permission::View)))
        : collect();

    // The open section is the URL segment after the project slug
    // (/projects/{slug}/{section}); pages outside module sections (settings)
    // keep the first module rail without a highlighted item.
    $sectionSlug = (string) request()->segment(3);
    $activeModule = null;
    $activeSection = null;
    foreach ($modules as $module) {
        if ($module->hasSection($sectionSlug)) {
            $activeModule = $module;
            $activeSection = $sectionSlug;
            break;
        }
    }
    $activeModule ??= $modules->first();

    $moduleIcon = fn (string $id): string => match ($id) {
        'qa' => 'flask-conical',
        default => 'layers',
    };
    $sectionIcon = fn (string $id): string => match ($id) {
        'cases' => 'list-todo',
        'sessions' => 'play',
        default => 'layers',
    };
    $sectionTitle = fn (string $id): string => match ($id) {
        'cases' => __('app.cases'),
        'sessions' => __('app.sessions'),
        default => ucfirst($id),
    };
    $sectionCount = fn (string $id) => match ($id) {
        'cases' => \App\Models\TestCase::query()->where('project_id', $project->id)->count(),
        'sessions' => \App\Models\TestSession::query()->where('project_id', $project->id)->count(),
        default => null,
    };
@endphp

{{-- App shell header (styleguide «Каркас»): logo · module tabs · project switcher · profile --}}
<header class="flex-none h-14 z-40 flex items-center gap-4 sm:gap-6 px-4 sm:px-5 bg-base-100 border-b border-base-200">
    <a href="{{ route('home') }}" class="flex-none flex items-center gap-2.5">
        <span class="grid size-8 place-items-center rounded-lg bg-primary text-primary-content shadow-(--fm-shadow-sm)">
            <svg viewBox="0 0 24 24" class="w-[18px] h-[18px]" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M12 4v17M5 21h14M12 6l-6 3 6-3 6 3M3.5 12a3.2 3.2 0 0 0 6.4 0L6.7 9zM14.1 12a3.2 3.2 0 0 0 6.4 0L17.3 9z"/></svg>
        </span>
        <span class="text-[15px] font-semibold tracking-tight">Femida</span>
    </a>

    @if ($modules->isNotEmpty())
        <nav class="flex-1 flex items-center gap-0.5 min-w-0 overflow-x-auto fem-scroll" aria-label="{{ __('app.modules') }}">
            @foreach ($modules as $module)
                <a href="{{ route("projects.{$module->sections[0]}.index", $project) }}"
                   @if ($activeModule?->id === $module->id) aria-current="page" @endif
                   class="inline-flex items-center gap-2 rounded-md px-3 py-1.5 text-(--fm-ink-2) hover:bg-base-200 hover:text-base-content transition-colors whitespace-nowrap">
                    <i data-lucide="{{ $moduleIcon($module->id) }}" class="size-4 text-(--fm-subtle)"></i>
                    {{ $module->title }}
                </a>
            @endforeach
        </nav>
    @endif

    <div class="ml-auto flex items-center gap-1.5 sm:gap-2">
        @if ($project)
            <div class="dropdown dropdown-end">
                <div tabindex="0" role="button" aria-label="{{ __('app.projects') }}"
                     class="flex items-center gap-2 rounded-lg border border-(--fm-line) bg-base-100 py-1 pl-1 pr-2.5 shadow-(--fm-shadow-xs) hover:border-(--fm-line-strong) cursor-pointer transition-colors">
                    <span class="grid size-6 place-items-center rounded-md bg-(--fm-primary-soft-2) text-[11px] font-semibold text-primary">{{ mb_strtoupper(mb_substr($project->name, 0, 1)) }}</span>
                    <span class="hidden md:block max-w-48 truncate font-medium">{{ $project->name }}</span>
                    <i data-lucide="chevrons-up-down" class="size-3.5 text-(--fm-subtle)"></i>
                </div>
                <ul tabindex="0" class="dropdown-content menu z-30 mt-2 w-72 rounded-box border border-(--fm-line) bg-base-100 p-1.5 shadow-(--fm-shadow-lg)">
                    <li class="menu-title text-xs font-medium text-(--fm-muted)">{{ __('app.projects') }}</li>
                    @forelse ($switcherProjects as $p)
                        <li>
                            <a href="{{ route('projects.cases.index', $p) }}" class="justify-between">
                                <span class="flex items-center gap-2 min-w-0">
                                    <span class="grid size-5 place-items-center rounded bg-(--fm-primary-soft-2) text-[10px] font-semibold text-primary">{{ mb_strtoupper(mb_substr($p->name, 0, 1)) }}</span>
                                    <span class="truncate">{{ $p->name }}</span>
                                </span>
                                @if ($p->is($project))<i data-lucide="check" class="size-4 text-primary"></i>@endif
                            </a>
                        </li>
                    @empty
                        <li class="disabled text-(--fm-muted) px-3 py-2">{{ __('app.only_yours') }}</li>
                    @endforelse
                    <li aria-hidden="true" class="pointer-events-none my-1 border-t border-(--fm-line)"></li>
                    @can('update', $project)
                        <li>
                            <a href="{{ route('projects.settings', $project) }}">
                                <i data-lucide="settings" class="size-4"></i>{{ __('app.settings') }}
                            </a>
                        </li>
                    @endcan
                    <li>
                        <a href="{{ route('home') }}">
                            <i data-lucide="layout-dashboard" class="size-4"></i>{{ __('app.all_projects_menu') }}
                        </a>
                    </li>
                    @can('create', \App\Models\Project::class)
                        <li>
                            <a href="{{ route('projects.create') }}" class="text-primary font-semibold">
                                <i data-lucide="plus" class="size-4"></i>{{ __('app.create_project') }}
                            </a>
                        </li>
                    @endcan
                </ul>
            </div>
        @endif

        <div class="dropdown dropdown-end">
            <div tabindex="0" role="button" aria-label="{{ __('profile.title') }}"
                 class="flex items-center gap-2 rounded-lg py-1 pl-1 pr-2 hover:bg-base-200 cursor-pointer transition-colors">
                <div class="avatar avatar-placeholder">
                    <div class="w-7 rounded-full bg-neutral text-neutral-content"><span class="text-[11px] font-semibold">{{ $initials }}</span></div>
                </div>
                <span class="font-medium hidden sm:block max-w-40 truncate">{{ $user?->name }}</span>
                <i data-lucide="chevron-down" class="size-3.5 text-(--fm-subtle)"></i>
            </div>
            <ul tabindex="0" class="dropdown-content menu z-30 mt-2 w-60 rounded-box border border-(--fm-line) bg-base-100 p-1.5 shadow-(--fm-shadow-lg)">
                <li class="pointer-events-none px-3 py-2">
                    <div class="flex flex-col items-start gap-0 p-0">
                        <span class="font-medium">{{ $user?->name }}</span>
                        <span class="text-xs text-(--fm-muted)">{{ $user?->is_admin ? __('app.role_admin') : __('app.role_user') }}</span>
                    </div>
                </li>
                <li aria-hidden="true" class="pointer-events-none my-1 border-t border-(--fm-line)"></li>
                <li><a href="{{ route('profile') }}"><i data-lucide="user" class="size-4"></i>{{ __('profile.title') }}</a></li>
                @if ($user?->managesUsers())
                    <li><a href="{{ route('users.index') }}"><i data-lucide="users" class="size-4"></i>{{ __('app.users') }}</a></li>
                @endif
                <li><a href="{{ route('help.index') }}"><i data-lucide="book-open" class="size-4"></i>{{ __('app.help') }}</a></li>
                <li aria-hidden="true" class="pointer-events-none my-1 border-t border-(--fm-line)"></li>
                <li class="-mt-1 pt-1 border-t border-(--fm-line)">
                    <form method="POST" action="{{ route('logout') }}" class="px-2 pb-1">
                        @csrf
                        <button type="submit"
                                class="flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-error hover:bg-base-200 transition-colors">
                            <i data-lucide="log-out" class="size-4"></i>{{ __('app.logout') }}
                        </button>
                    </form>
                </li>
            </ul>
        </div>
    </div>
</header>

{{-- Work area: dark module rail (desktop) + content. Rail pages provide a
     shell section and split the content column into independent scrolls --}}
<main class="flex-1 min-h-0 flex">
    @if ($activeModule)
        <aside class="app-sidebar hidden lg:flex w-62 flex-none flex-col border-r">
            <div class="px-4 pt-5 pb-2 flex-none">
                <p class="px-2 text-[11px] font-semibold uppercase tracking-[0.08em] text-(--fm-subtle)">{{ __('app.section') }}</p>
                <p class="px-2 mt-1 text-[15px] font-semibold tracking-tight">{{ $activeModule->title }}</p>
            </div>
            <nav class="flex flex-col gap-0.5 px-3 py-2 overflow-y-auto fem-scroll" aria-label="{{ $activeModule->title }}">
                @foreach ($activeModule->sections as $section)
                    @php $count = $sectionCount($section); @endphp
                    <a href="{{ route("projects.{$section}.index", $project) }}"
                       @if ($activeSection === $section) aria-current="page" @endif
                       class="flex items-center gap-2.5 rounded-lg px-2.5 py-2 text-(--fm-ink-2) hover:bg-(--fm-row-hover) hover:text-base-content transition-colors">
                        <i data-lucide="{{ $sectionIcon($section) }}" class="size-4 text-(--fm-subtle)"></i>
                        <span>{{ $sectionTitle($section) }}</span>
                        @if ($count !== null)
                            <span class="ml-auto text-xs tabular-nums text-(--fm-subtle)">{{ $count }}</span>
                        @endif
                    </a>
                @endforeach
            </nav>
        </aside>
    @endif

    <div class="flex-1 min-w-0 flex flex-col">
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
    </div>
</main>

@livewireScripts
</body>
</html>
