@php
    /**
     * Numeric daisyUI-style pagination for Livewire browsers:
     * clicks go through wire:click="gotoPage" — no page reload.
     * Variables: $paginator, $elements.
     */
@endphp

@if ($paginator->hasPages())
    <nav class="join" aria-label="{{ __('app.pagination') }}">
        @if ($paginator->onFirstPage())
            <button class="join-item btn btn-sm btn-disabled" aria-disabled="true" tabindex="-1">‹</button>
        @else
            <button wire:click="previousPage('{{ $paginator->getPageName() }}')"
                    class="join-item btn btn-sm" aria-label="{{ __('app.prev_page') }}">‹</button>
        @endif

        @foreach ($elements as $element)
            @if (is_string($element))
                <button class="join-item btn btn-sm btn-disabled pointer-events-none">…</button>
            @elseif (is_array($element))
                @foreach ($element as $page => $url)
                    @if ($page === $paginator->currentPage())
                        <button class="join-item btn btn-sm btn-primary pointer-events-none" aria-current="page">{{ $page }}</button>
                    @else
                        <button wire:click="gotoPage({{ $page }}, '{{ $paginator->getPageName() }}')"
                                class="join-item btn btn-sm" aria-label="{{ __('app.go_to_page', ['page' => $page]) }}">{{ $page }}</button>
                    @endif
                @endforeach
            @endif
        @endforeach

        @if ($paginator->hasMorePages())
            <button wire:click="nextPage('{{ $paginator->getPageName() }}')"
                    class="join-item btn btn-sm" aria-label="{{ __('app.next_page') }}">›</button>
        @else
            <button class="join-item btn btn-sm btn-disabled" aria-disabled="true" tabindex="-1">›</button>
        @endif
    </nav>
@endif
