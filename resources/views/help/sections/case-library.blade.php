@include('help.partials.head', ['key' => 'case_library'])

<h2 class="mt-9 mb-1 text-[11px] font-bold uppercase tracking-[0.14em] text-base-content/40">{{ __('help.case_library.fields_title') }}</h2>
<div class="border-t border-base-200/70">
    @foreach (__('help.case_library.fields') as $field)
        <div class="flex gap-8 border-b border-base-200/70 py-3.5">
            <div class="w-[170px] flex-none text-[13.5px] font-bold">{{ $field['name'] }}</div>
            <div class="min-w-0 text-[13.5px] leading-relaxed text-base-content/70">{{ $field['text'] }}</div>
        </div>
    @endforeach
</div>

@include('help.partials.note', ['lead' => __('help.case_library.note_lead'), 'text' => __('help.case_library.note_text')])
