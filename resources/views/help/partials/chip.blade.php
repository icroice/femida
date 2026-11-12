@php
    // tone -> theme classes: neutral attributes vs status/verdict colors
    $tones = [
        'neutral' => 'bg-primary/10 text-primary',
        'ok' => 'bg-success/10 text-success',
        'warn' => 'bg-warning/15 text-warning',
        'err' => 'bg-error/10 text-error',
    ];
@endphp
<span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[11px] font-semibold {{ $tones[$chip['tone']] ?? $tones['neutral'] }}">
    {{ $chip['text'] }}
</span>
