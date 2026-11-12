@include('help.partials.head', ['key' => 'sessions'])
@include('help.partials.timeline', ['key' => 'sessions'])
@include('help.partials.note', ['lead' => __('help.sessions.note_lead'), 'text' => __('help.sessions.note_text')])
