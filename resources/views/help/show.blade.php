@extends('layouts.app')

@section('title', __('help.sections.'.$section).' — '.__('app.help'))

@section('shell')
    <div class="flex-1 min-h-0 flex">

        {{-- rail: numbered section navigation (echoes the review rail) --}}
        <aside class="w-[272px] flex-none flex flex-col bg-base-100 border-r border-base-200">
            <div class="px-[18px] pt-4 pb-2 text-[10.5px] font-bold uppercase tracking-[0.14em] text-base-content/40">
                {{ __('app.help') }}
            </div>
            <nav class="flex-1 min-h-0 overflow-y-auto fem-scroll pb-2">
                @foreach ($sections as $s)
                    <a href="{{ route('help.show', $s) }}"
                       class="flex items-center gap-2.5 px-[18px] py-[9px] border-t border-base-200/60 text-[13.5px] {{ $section === $s
                            ? 'bg-primary/10 text-primary font-bold shadow-[inset_3px_0_0_var(--color-primary)]'
                            : 'text-base-content/60 hover:bg-base-200/40 hover:text-base-content' }}">
                        <span class="w-[18px] text-[10.5px] font-semibold tabular-nums {{ $section === $s ? '' : 'text-base-content/35' }}">
                            {{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}
                        </span>
                        {{ __("help.sections.$s") }}
                    </a>
                @endforeach
            </nav>
        </aside>

        {{-- content: one section, its own scroll --}}
        <div class="flex-1 min-w-0 overflow-y-auto fem-scroll">
            <div class="max-w-[800px] px-12 py-10 pb-16">
                <div class="text-[11px] font-bold uppercase tracking-[0.14em] text-primary">
                    {{ __('app.help') }} · {{ __("help.sections.$section") }}
                </div>

                @include("help.sections.$section")

                @if ($next)
                    <a href="{{ route('help.show', $next) }}"
                       class="mt-10 inline-flex items-center gap-2 text-[13.5px] font-bold text-primary hover:opacity-80">
                        {{ __('help.next_section') }}: {{ __("help.sections.$next") }}
                        <svg viewBox="0 0 24 24" class="w-[15px] h-[15px]" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                    </a>
                @endif
            </div>
        </div>
    </div>
@endsection
