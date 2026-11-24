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

    // the guest request above also proves the page is standalone: no auth
    // redirect, dev-only middleware (local.env) — nothing user-facing
}
