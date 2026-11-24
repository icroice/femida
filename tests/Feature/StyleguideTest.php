<?php

namespace Tests\Feature;

use Tests\TestCase;

class StyleguideTest extends TestCase
{
    public function test_styleguide_renders_in_dev_environment(): void
    {
        // phpunit runs with APP_ENV=testing, one of the dev environments
        $this->get('/styleguide')
            ->assertOk()
            ->assertSee('Стильгайд')
            ->assertSee('--fm-ink-2');
    }

    public function test_styleguide_is_a_standalone_guest_page(): void
    {
        // no auth redirect: the page is dev-only, not user-facing
        $this->get('/styleguide')->assertOk();
    }
}
