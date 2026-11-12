{{--
    Vertical numbered timeline: steps from lang ("$key.steps"), each with title,
    text and optional tone chips. A connector line runs behind the circles.
--}}
<h2 class="mt-9 mb-4 text-[11px] font-bold uppercase tracking-[0.14em] text-base-content/40">{{ __("help.$key.flow_title") }}</h2>
<div class="relative ml-1">
    <div class="absolute left-[14px] top-3 bottom-3 w-[3px] rounded bg-base-300"></div>
    @foreach (__("help.$key.steps") as $step)
        <div class="relative z-10 flex gap-4 {{ $loop->last ? '' : 'pb-6' }}">
            <div class="w-8 h-8 flex-none rounded-full bg-base-100 border-[1.5px] border-primary text-primary flex items-center justify-center text-[12.5px] font-bold">
                {{ $loop->iteration }}
            </div>
            <div class="min-w-0 pt-0.5">
                <div class="text-[14px] font-bold">{{ $step['title'] }}</div>
                <p class="mt-0.5 max-w-[560px] text-[13.5px] leading-relaxed text-base-content/70">{{ $step['text'] }}</p>
                @if (! empty($step['chips']))
                    <div class="mt-2 flex flex-wrap gap-1.5">
                        @foreach ($step['chips'] as $chip)
                            @include('help.partials.chip', ['chip' => $chip])
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    @endforeach
</div>
