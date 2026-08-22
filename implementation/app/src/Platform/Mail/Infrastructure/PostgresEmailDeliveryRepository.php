<?php

declare(strict_types=1);

namespace Atlas\Platform\Mail\Infrastructure;

use Atlas\Platform\Mail\EmailAcceptance;
use Illuminate\Support\Facades\DB;

final class PostgresEmailDeliveryRepository
{
    public function hasTerminalStatus(string $eventId): bool
    {
        return DB::table('platform.email_deliveries')
            ->where('event_id', $eventId)
            ->whereIn('status', ['Accepted', 'Cancelled', 'Suppressed'])
            ->exists();
    }

    public function recordAccepted(
        string $eventId,
        string $eventType,
        string $templateKey,
        string $recipient,
        EmailAcceptance $acceptance,
    ): void {
        $this->record(
            eventId: $eventId,
            eventType: $eventType,
            templateKey: $templateKey,
            recipientFingerprint: hash('sha256', strtolower(trim($recipient))),
            status: 'Accepted',
            provider: $acceptance->provider,
            providerMessageId: $acceptance->providerMessageId,
        );
    }

    public function recordCancelled(
        string $eventId,
        string $eventType,
        string $templateKey,
        ?string $recipient = null,
    ): void {
        $this->record(
            eventId: $eventId,
            eventType: $eventType,
            templateKey: $templateKey,
            recipientFingerprint: $recipient !== null
                ? hash('sha256', strtolower(trim($recipient)))
                : null,
            status: 'Cancelled',
            provider: null,
            providerMessageId: null,
        );
    }

    private function record(
        string $eventId,
        string $eventType,
        string $templateKey,
        ?string $recipientFingerprint,
        string $status,
        ?string $provider,
        ?string $providerMessageId,
    ): void {
        DB::table('platform.email_deliveries')->updateOrInsert(
            ['event_id' => $eventId],
            [
                'event_type' => $eventType,
                'template_key' => $templateKey,
                'template_version' => '1.0.0',
                'recipient_fingerprint' => $recipientFingerprint,
                'status' => $status,
                'provider' => $provider,
                'provider_message_id' => $providerMessageId,
                'accepted_at' => $status === 'Accepted' ? now()->toIso8601String() : null,
                'updated_at' => now()->toIso8601String(),
            ],
        );
    }
}
