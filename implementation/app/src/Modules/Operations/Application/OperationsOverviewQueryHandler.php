<?php

declare(strict_types=1);

namespace Atlas\Modules\Operations\Application;

use Atlas\Modules\Operations\Contracts\OperationsOverviewSource;

final class OperationsOverviewQueryHandler
{
    public function __construct(
        private readonly OperationsOverviewSource $source,
        private readonly bool $readOnly,
        private readonly bool $actionsEnabled,
    ) {}

    /** @return array{generated_at: string, read_only: bool, actions_enabled: bool, attention_count: int, cards: list<array<string, mixed>>} */
    public function overview(): array
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $cards = [
            $this->outboxCard($now),
            $this->emailCard($now),
            $this->notCollectedCard(
                'http',
                'Disponibilité API',
                'Les métriques HTTP RED attendent un backend de métriques durable.',
                'OpenTelemetry metrics',
                60,
                300,
            ),
            $this->notCollectedCard(
                'runtime',
                'Worker et scheduler',
                'Aucun heartbeat persistant n’est encore collecté.',
                'Operations heartbeat',
                60,
                300,
            ),
            $this->notCollectedCard(
                'backup',
                'Sauvegarde récente',
                'Le résultat des sauvegardes n’est pas encore ingéré dans Operations.',
                'Backup verification jobs',
                86400,
                90000,
            ),
            $this->notCollectedCard(
                'beta',
                'Participants beta',
                'La cohorte reste tenue dans le registre de recherche avant l’incrément 3.',
                'Operations beta projection',
                900,
                3600,
            ),
            $this->notCollectedCard(
                'support',
                'Demandes support',
                'Le workflow SupportCase n’est pas encore implémenté.',
                'Operations support cases',
                300,
                900,
            ),
            $this->notCollectedCard(
                'data-requests',
                'Demandes de données',
                'Le registre DataRequest sera livré avec l’incrément conformité.',
                'Operations data requests',
                300,
                900,
            ),
        ];

        return [
            'generated_at' => $now->format(DATE_ATOM),
            'read_only' => $this->readOnly,
            'actions_enabled' => $this->actionsEnabled,
            'attention_count' => count(array_filter(
                $cards,
                static fn (array $card): bool => in_array($card['tone'], ['Warning', 'Critical'], true),
            )),
            'cards' => $cards,
        ];
    }

    public function outbox(string $status, int $page, int $perPage): array
    {
        return $this->source->outboxPage($status, $page, $perPage);
    }

    public function emails(string $status, int $page, int $perPage): array
    {
        return $this->source->emailPage($status, $page, $perPage);
    }

    private function outboxCard(\DateTimeImmutable $now): array
    {
        try {
            $snapshot = $this->source->outboxSnapshot($now);
        } catch (\Throwable) {
            return $this->unavailableCard(
                'outbox',
                'Outbox',
                'Le registre de messages n’a pas pu être lu.',
                'platform.outbox_messages',
                60,
                300,
                '/backoffice/outbox',
                'operations.outbox.read',
            );
        }

        $tone = $snapshot['dead_letter_count'] > 0
            ? 'Critical'
            : ($snapshot['pending_count'] > 0 ? 'Warning' : 'Neutral');

        return $this->availableCard(
            key: 'outbox',
            label: 'Outbox',
            description: 'Messages en attente, en reprise ou placés en dead-letter.',
            source: 'platform.outbox_messages',
            now: $now,
            targetSeconds: 60,
            staleAfterSeconds: 300,
            tone: $tone,
            values: [
                ['key' => 'pending', 'label' => 'En attente', 'value' => $snapshot['pending_count']],
                ['key' => 'retrying', 'label' => 'En reprise', 'value' => $snapshot['retrying_count']],
                ['key' => 'dead-letter', 'label' => 'Dead-letter', 'value' => $snapshot['dead_letter_count']],
            ],
            context: $snapshot['oldest_pending_age_seconds'] !== null
                ? 'Plus ancien message : '.$this->humanDuration($snapshot['oldest_pending_age_seconds'])
                : 'Aucun message en attente.',
            href: '/backoffice/outbox',
            detailPermission: 'operations.outbox.read',
        );
    }

    private function emailCard(\DateTimeImmutable $now): array
    {
        try {
            $snapshot = $this->source->emailSnapshot();
        } catch (\Throwable) {
            return $this->unavailableCard(
                'emails',
                'Emails transactionnels',
                'Les registres de livraison n’ont pas pu être lus.',
                'platform.email_deliveries + platform.outbox_messages',
                300,
                900,
                '/backoffice/emails',
                'operations.email.read',
            );
        }

        $tone = $snapshot['failed_count'] > 0
            ? 'Critical'
            : ($snapshot['retrying_count'] > 0 ? 'Warning' : 'Neutral');

        return $this->availableCard(
            key: 'emails',
            label: 'Emails transactionnels',
            description: 'Remises acceptées et demandes encore en reprise ou en échec.',
            source: 'platform.email_deliveries + platform.outbox_messages',
            now: $now,
            targetSeconds: 300,
            staleAfterSeconds: 900,
            tone: $tone,
            values: [
                ['key' => 'accepted', 'label' => 'Acceptés', 'value' => $snapshot['accepted_count']],
                ['key' => 'retrying', 'label' => 'En reprise', 'value' => $snapshot['retrying_count']],
                ['key' => 'failed', 'label' => 'En échec', 'value' => $snapshot['failed_count']],
            ],
            context: 'Aucune adresse ou contenu de message n’est exposé.',
            href: '/backoffice/emails',
            detailPermission: 'operations.email.read',
        );
    }

    private function availableCard(
        string $key,
        string $label,
        string $description,
        string $source,
        \DateTimeImmutable $now,
        int $targetSeconds,
        int $staleAfterSeconds,
        string $tone,
        array $values,
        string $context,
        ?string $href,
        ?string $detailPermission,
    ): array {
        return [
            'key' => $key,
            'label' => $label,
            'description' => $description,
            'status' => 'Available',
            'tone' => $tone,
            'values' => $values,
            'context' => $context,
            'source' => $source,
            'measured_at' => $now->format(DATE_ATOM),
            'freshness' => [
                'target_seconds' => $targetSeconds,
                'stale_after_seconds' => $staleAfterSeconds,
                'state' => 'Current',
            ],
            'href' => $href,
            'detail_permission' => $detailPermission,
        ];
    }

    private function notCollectedCard(
        string $key,
        string $label,
        string $description,
        string $source,
        int $targetSeconds,
        int $staleAfterSeconds,
    ): array {
        return [
            'key' => $key,
            'label' => $label,
            'description' => $description,
            'status' => 'NotCollected',
            'tone' => 'Muted',
            'values' => [],
            'context' => 'Instrumentation non disponible — aucune valeur zéro n’est déduite.',
            'source' => $source,
            'measured_at' => null,
            'freshness' => [
                'target_seconds' => $targetSeconds,
                'stale_after_seconds' => $staleAfterSeconds,
                'state' => 'NotCollected',
            ],
            'href' => null,
            'detail_permission' => null,
        ];
    }

    private function unavailableCard(
        string $key,
        string $label,
        string $description,
        string $source,
        int $targetSeconds,
        int $staleAfterSeconds,
        ?string $href,
        ?string $detailPermission,
    ): array {
        return [
            'key' => $key,
            'label' => $label,
            'description' => $description,
            'status' => 'Unavailable',
            'tone' => 'Critical',
            'values' => [],
            'context' => 'Source indisponible au moment de cette lecture.',
            'source' => $source,
            'measured_at' => null,
            'freshness' => [
                'target_seconds' => $targetSeconds,
                'stale_after_seconds' => $staleAfterSeconds,
                'state' => 'Unavailable',
            ],
            'href' => $href,
            'detail_permission' => $detailPermission,
        ];
    }

    private function humanDuration(int $seconds): string
    {
        if ($seconds < 60) {
            return $seconds.' s';
        }
        if ($seconds < 3600) {
            return intdiv($seconds, 60).' min';
        }

        return intdiv($seconds, 3600).' h';
    }
}
