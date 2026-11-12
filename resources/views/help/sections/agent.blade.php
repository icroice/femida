@include('help.partials.head', ['key' => 'agent'])

<h2 class="mt-9 mb-1 text-[11px] font-bold uppercase tracking-[0.14em] text-base-content/40">{{ __('help.agent.rows_title') }}</h2>
<div class="border-t border-base-200/70">
    @foreach (__('help.agent.rows') as $row)
        <div class="border-b border-base-200/70 py-4">
            <div class="text-[14px] font-bold">{{ $row['title'] }}</div>
            <p class="mt-1 max-w-[620px] text-[13.5px] leading-relaxed text-base-content/70">{{ $row['text'] }}</p>
        </div>
    @endforeach
</div>

@include('help.partials.note', ['lead' => __('help.agent.note_lead'), 'text' => __('help.agent.note_text')])
