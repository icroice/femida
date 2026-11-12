@include('help.partials.head', ['key' => 'roles'])

@php
    // role icon squares, tinted per the app palette
    $icons = [
        0 => ['color' => 'bg-primary/10 text-primary', 'path' => 'M12 3l8 3v6c0 4.4-3.2 7.4-8 9-4.8-1.6-8-4.6-8-9V6z'],
        1 => ['color' => 'bg-secondary/15 text-secondary', 'path' => 'M11.56 3.27a.5.5 0 0 1 .88 0l2.95 5.6a1 1 0 0 0 1.52.3l4.27-3.67a.5.5 0 0 1 .8.52l-2.83 10.25a1 1 0 0 1-.96.73H5.81a1 1 0 0 1-.96-.73L2.02 6.02a.5.5 0 0 1 .8-.52l4.27 3.67a1 1 0 0 0 1.52-.3z'],
        2 => ['color' => 'bg-accent/15 text-accent', 'path' => 'M8 6l-5 6 5 6M16 6l5 6-5 6M13 4l-2 16'],
        3 => ['color' => 'bg-warning/15 text-warning', 'path' => 'M12 5V3M12 21v-2M5 12H3m18 0h-2M6.3 6.3L5 5m14 14l-1.3-1.3m0-11.4L19 5M5 19l1.3-1.3'],
    ];
@endphp

<h2 class="mt-9 mb-1 text-[11px] font-bold uppercase tracking-[0.14em] text-base-content/40">{{ __('help.roles.rows_title') }}</h2>
<div class="border-t border-base-200/70">
    @foreach (__('help.roles.rows') as $row)
        <div class="flex gap-4 border-b border-base-200/70 py-4">
            <div class="w-[34px] h-[34px] flex-none rounded-lg flex items-center justify-center {{ $icons[$loop->index]['color'] }}">
                <svg viewBox="0 0 24 24" class="w-[17px] h-[17px]" fill="none" stroke="currentColor" stroke-width="1.7"><path d="{{ $icons[$loop->index]['path'] }}"/></svg>
            </div>
            <div class="min-w-0">
                <div class="text-[14px] font-bold">{{ $row['title'] }}</div>
                <p class="mt-0.5 max-w-[560px] text-[13.5px] leading-relaxed text-base-content/70">{{ $row['text'] }}</p>
                <div class="mt-2 flex flex-wrap gap-1.5">
                    @foreach ($row['chips'] as $chip)
                        @include('help.partials.chip', ['chip' => $chip])
                    @endforeach
                </div>
            </div>
        </div>
    @endforeach
</div>

@include('help.partials.note', ['lead' => __('help.roles.note_lead'), 'text' => __('help.roles.note_text')])
