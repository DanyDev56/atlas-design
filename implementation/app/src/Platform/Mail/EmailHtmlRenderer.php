<?php

declare(strict_types=1);

namespace Atlas\Platform\Mail;

final class EmailHtmlRenderer
{
    public function render(string $title, string $body, ?string $actionLabel = null, ?string $actionUrl = null): string
    {
        $safeTitle = htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $safeBody = nl2br(htmlspecialchars($body, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
        $action = '';

        if ($actionLabel !== null && $actionUrl !== null) {
            $safeLabel = htmlspecialchars($actionLabel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $safeUrl = htmlspecialchars($actionUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $action = <<<HTML
                <p style="margin:28px 0"><a href="{$safeUrl}" style="background:#315c4d;color:#fff;padding:12px 18px;border-radius:8px;text-decoration:none;font-weight:600">{$safeLabel}</a></p>
                <p style="font-size:12px;color:#667085;word-break:break-all">{$safeUrl}</p>
                HTML;
        }

        return <<<HTML
            <!doctype html>
            <html lang="fr"><body style="margin:0;background:#f5f2eb;color:#1f2937;font-family:Arial,sans-serif">
            <div style="max-width:600px;margin:0 auto;padding:32px 16px">
              <div style="background:#fff;border:1px solid #e5e0d7;border-radius:12px;padding:32px">
                <p style="margin:0 0 24px;color:#315c4d;font-weight:700;letter-spacing:.08em">ATLAS</p>
                <h1 style="font-size:24px;margin:0 0 18px">{$safeTitle}</h1>
                <div style="font-size:16px;line-height:1.6">{$safeBody}</div>
                {$action}
                <p style="margin:28px 0 0;font-size:12px;color:#667085">Cet email transactionnel a été envoyé par Atlas.</p>
              </div>
            </div>
            </body></html>
            HTML;
    }
}
