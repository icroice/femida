{{--
    Settings categories rail (geometry mirrors the folder/branch rails): to
    the left of the content on project and profile settings pages.
    $project — null for the profile; $active — 'main' | 'keys'.
    Plain links (<a>): categories are separate routes, the active item's
    state lives on the server on full loads; wire updates never touch the rail.
--}}
@php
    $project = $project ?? null;
    $active = $active ?? 'main';
    $item = fn (bool $isActive) => 'flex items-center gap-2 w-full text-left px-3.5 py-[7px] text-[13px] border-l-2 '
        .($isActive ? 'border-primary bg-primary/10 text-primary font-bold' : 'border-transparent text-base-content/60 hover:bg-base-200/50 hover:text-base-content');
    $links = $project !== null
        ? [
            'main' => ['href' => route('projects.settings', $project), 'label' => __('settings.section_main')],
            'keys' => ['href' => route('projects.settings.keys', $project), 'label' => __('settings.api_keys')],
        ]
        : [
            'main' => ['href' => route('profile'), 'label' => __('settings.section_main')],
            'keys' => ['href' => route('profile.keys'), 'label' => __('profile.section_keys')],
        ];
@endphp
<aside class="w-[260px] flex-none bg-base-100 border-r border-base-200 overflow-y-auto fem-scroll pt-3 pb-10">
    <div class="px-3.5 pb-1.5 text-[10.5px] font-bold uppercase tracking-[0.14em] text-base-content/40">{{ __('app.settings') }}</div>

    @foreach ($links as $key => $link)
        <a href="{{ $link['href'] }}" class="{{ $item($active === $key) }}">
            <span class="flex-1 truncate">{{ $link['label'] }}</span>
        </a>
    @endforeach
</aside>
