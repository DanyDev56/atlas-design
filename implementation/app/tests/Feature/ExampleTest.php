<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
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

    public function test_the_public_frontend_can_force_https_urls_behind_a_tunnel(): void
    {
        config()->set('platform.http.force_https', true);

        try {
            $response = $this->get('http://atlas-demo.example/');

            $response->assertOk()
                ->assertSee('href="https://atlas-demo.example/build/', false)
                ->assertSee('src="https://atlas-demo.example/build/', false)
                ->assertDontSee('http://atlas-demo.example/build/', false);
        } finally {
            URL::forceScheme(null);
        }
    }
}
