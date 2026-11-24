<?php

namespace App\Support\Shell;

/**
 * One item of the module rail / narrow-viewport section strip: the page
 * link with its presentation. A dumb value object — active-state marking
 * stays in the template (aria-current on the matching id).
 */
final readonly class SectionLink
{
    public function __construct(
        public string $id,
        public string $title,
        public string $icon,
        public string $url,
        public ?int $count = null,
    ) {}
}
