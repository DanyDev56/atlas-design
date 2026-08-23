<?php

declare(strict_types=1);

namespace Tests\Unit\Platform;

use Atlas\Platform\Mail\EmailHtmlRenderer;
use PHPUnit\Framework\TestCase;

final class EmailHtmlRendererTest extends TestCase
{
    public function test_transactional_email_footer_links_to_the_public_site(): void
    {
        $renderer = new EmailHtmlRenderer('https://atlas.example/');

        $html = $renderer->render('Votre facture', 'Le document est disponible.');

        $this->assertStringContainsString(
            '<a href="https://atlas.example"',
            $html,
        );
        $this->assertStringContainsString('Découvrir Atlas</a>', $html);
    }
}
