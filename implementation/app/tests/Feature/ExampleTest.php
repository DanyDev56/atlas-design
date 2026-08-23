<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_the_public_landing_page_exposes_marketing_metadata(): void
    {
        $response = $this->get('/');

        $response->assertOk()
            ->assertSee('Atlas — Pilotez votre activité avec confiance')
            ->assertSee('Atlas relie clients, devis, factures et données réelles')
            ->assertSee('<div id="root"></div>', false);
    }
}
