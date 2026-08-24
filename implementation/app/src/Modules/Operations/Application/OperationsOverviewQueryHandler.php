<?php

declare(strict_types=1);

namespace Atlas\Modules\Operations\Application;

use Atlas\Modules\Operations\Contracts\BetaCohortSource;
use Atlas\Modules\Operations\Contracts\OperationsOverviewSource;

final class OperationsOverviewQueryHandler
{
    public function __construct(
        private readonly OperationsOverviewSource $source,
        private readonly BetaCohortSource $betaSource,
        private readonly bool $readOnly,
        private readonly bool $actionsEnabled,
        private readonly int $betaBlockedAfterDays,
    ) {}

    /** @return array{generated_at: string, read_only: bool, actions_enabled: bool, attention_count: int, cards: list<array<string, mixed>>} */
    public function overview(): array
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $cards = [
            $this->outboxCard($now),
            $this->emailCard($now),
            $this->subscriptionCard($now),
            $this->notCollectedCard(
                'http',
                'Disponibilité API',
                'Les métriques HTTP RED attendent un backend de métriques durable.',
                'OpenTelemetry metrics',
                60,
                300,
            ),
            $this->runtimeCard($now),
            $this->maintenanceCard($now),
            $this->betaCard($now),
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

    public function subscriptions(string $status, string $environment, int $page, int $perPage): array
    {
        return $this->source->subscriptionPage($status, $environment, $page, $perPage);
    }

    public function webhooks(string $status, string $environment, int $page, int $perPage): array
    {
        return $this->source->webhookPage($status, $environment, $page, $perPage);
    }

    public function runtime(): array
    {
        return $this->source->runtimeSnapshot(new \DateTimeImmutable('now', new \DateTimeZone('UTC')));
    }

    public function maintenance(string $kind, string $status, int $page, int $perPage): array
    {
        return $this->source->maintenancePage($kind, $status, $page, $perPage);
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

    private function subscriptionCard(\DateTimeImmutable $now): array
    {
        try {
            $snapshot = $this->source->subscriptionSnapshot($now);
        } catch (\Throwable) {
            return $this->unavailableCard(
                'subscriptions',
                'Abonnements et webhooks',
                'Les registres de facturation récurrente n’ont pas pu être lus.',
                'subscriptions.trials + recurring_subscriptions + webhook_inbox',
                300,
                900,
                '/backoffice/subscriptions',
                'operations.subscriptions.read',
            );
        }

        $incidents = $snapshot['past_due_count'] + $snapshot['failed_webhook_count'];
        $environmentContext = implode(' · ', array_map(
            static fn (string $environment, int $count): string => $environment.' : '.$count,
            array_keys($snapshot['environments']),
            array_values($snapshot['environments']),
        ));

        return $this->availableCard(
            key: 'subscriptions',
            label: 'Abonnements et webhooks',
            description: 'Essais actifs, abonnements récurrents et incidents de synchronisation.',
            source: 'subscriptions.trials + recurring_subscriptions + webhook_inbox',
            now: $now,
            targetSeconds: 300,
            staleAfterSeconds: 900,
            tone: $incidents > 0 ? 'Critical' : ($snapshot['expiring_trial_count'] > 0 ? 'Warning' : 'Neutral'),
            values: [
                ['key' => 'trials', 'label' => 'Essais actifs', 'value' => $snapshot['active_trial_count']],
                ['key' => 'active', 'label' => 'Abonnements', 'value' => $snapshot['active_subscription_count']],
                ['key' => 'incidents', 'label' => 'À traiter', 'value' => $incidents],
            ],
            context: $environmentContext,
            href: '/backoffice/subscriptions',
            detailPermission: 'operations.subscriptions.read',
        );
    }

    private function runtimeCard(\DateTimeImmutable $now): array
    {
        try {
            $snapshot = $this->source->runtimeSnapshot($now);
        } catch (\Throwable) {
            return $this->unavailableCard(
                'runtime', 'API, worker et scheduler', 'La santé runtime ou PostgreSQL n’a pas pu être lue.',
                'operations.runtime_heartbeats + PostgreSQL probe', 60, 180,
                '/backoffice/runtime', 'operations.dashboard.read',
            );
        }

        $current = count(array_filter($snapshot['roles'], static fn (array $role): bool => $role['status'] === 'Current'));
        $stale = count(array_filter($snapshot['roles'], static fn (array $role): bool => $role['status'] === 'Stale'));
        $missing = count(array_filter($snapshot['roles'], static fn (array $role): bool => $role['status'] === 'NotCollected'));

        return $this->availableCard(
            key: 'runtime',
            label: 'API, worker et scheduler',
            description: 'Derniers signaux persistés des rôles et contrôle PostgreSQL à la lecture.',
            source: 'operations.runtime_heartbeats + PostgreSQL probe',
            now: $now,
            targetSeconds: 60,
            staleAfterSeconds: 180,
            tone: $stale > 0 ? 'Critical' : ($missing > 0 ? 'Warning' : 'Neutral'),
            values: [
                ['key' => 'current', 'label' => 'À jour', 'value' => $current],
                ['key' => 'stale', 'label' => 'Périmés', 'value' => $stale],
                ['key' => 'missing', 'label' => 'Sans signal', 'value' => $missing],
            ],
            context: $snapshot['database_available'] ? 'PostgreSQL répond à la sonde courante.' : 'PostgreSQL indisponible.',
            href: '/backoffice/runtime',
            detailPermission: 'operations.dashboard.read',
        );
    }

    private function maintenanceCard(\DateTimeImmutable $now): array
    {
        try {
            $snapshot = $this->source->maintenanceSnapshot($now);
        } catch (\Throwable) {
            return $this->unavailableCard(
                'backup', 'Sauvegardes et restauration', 'Le registre des opérations de sauvegarde n’a pas pu être lu.',
                'operations.maintenance_runs', 86400, 90000,
                '/backoffice/runtime', 'operations.dashboard.read',
            );
        }
        if ($snapshot['backup'] === null && $snapshot['restore_canary'] === null) {
            return $this->noDataCard(
                'backup', 'Sauvegardes et restauration', 'Aucune exécution instrumentée n’est encore enregistrée.',
                'operations.maintenance_runs', $now, 86400, 90000,
                '/backoffice/runtime', 'operations.dashboard.read',
            );
        }

        $backup = $snapshot['backup'];
        $canary = $snapshot['restore_canary'];
        $failed = ($backup !== null && $backup['status'] === 'Failed') || ($canary !== null && $canary['status'] === 'Failed');
        $stale = $backup !== null && $backup['age_seconds'] > 90000;
        $values = [];
        if ($backup !== null) {
            $values[] = ['key' => 'backup', 'label' => 'Backup OK', 'value' => $backup['status'] === 'Succeeded' ? 1 : 0];
        }
        if ($canary !== null) {
            $values[] = ['key' => 'canary', 'label' => 'Canary OK', 'value' => $canary['status'] === 'Succeeded' ? 1 : 0];
        }
        $values[] = ['key' => 'failures', 'label' => 'Dernier échec', 'value' => $failed ? 1 : 0];
        $measuredAt = new \DateTimeImmutable((string) ($backup['completed_at'] ?? $canary['completed_at']));

        return $this->availableCard(
            key: 'backup',
            label: 'Sauvegardes et restauration',
            description: 'Derniers résultats explicites des dumps et restaurations canary.',
            source: 'operations.maintenance_runs',
            now: $now,
            targetSeconds: 86400,
            staleAfterSeconds: 90000,
            tone: $failed ? 'Critical' : ($stale || $canary === null ? 'Warning' : 'Neutral'),
            values: $values,
            context: $backup !== null ? 'Dernière sauvegarde : '.$this->humanDuration($backup['age_seconds']) : 'Aucune sauvegarde instrumentée.',
            href: '/backoffice/runtime',
            detailPermission: 'operations.dashboard.read',
            measuredAt: $measuredAt,
            freshnessState: $stale ? 'Stale' : 'Current',
        );
    }

    private function betaCard(\DateTimeImmutable $now): array
    {
        try {
            $participants = $this->betaSource->participants($now, $this->betaBlockedAfterDays);
        } catch (\Throwable) {
            return $this->unavailableCard(
                'beta',
                'Cohorte beta',
                'La projection pseudonymisée n’a pas pu être reconstruite.',
                'operations.beta_participants + sources métier',
                900,
                3600,
                '/backoffice/beta',
                'operations.beta.read',
            );
        }
        if ($participants === []) {
            return $this->noDataCard(
                'beta',
                'Cohorte beta',
                'Aucun participant n’est inscrit dans le registre pseudonymisé.',
                'operations.beta_participants + sources métier',
                $now,
                900,
                3600,
                '/backoffice/beta',
                'operations.beta.read',
            );
        }

        $active = array_values(array_filter($participants, static fn (array $participant): bool => $participant['status'] === 'Active'));
        $blocked = count(array_filter($active, static fn (array $participant): bool => $participant['blocked']));
        $decisions = count(array_filter($participants, static fn (array $participant): bool => $participant['pricing_decision'] !== null));

        return $this->availableCard(
            key: 'beta',
            label: 'Cohorte beta',
            description: 'Activation E0–E6, blocages et décisions pricing pseudonymisées.',
            source: 'operations.beta_participants + sources métier',
            now: $now,
            targetSeconds: 900,
            staleAfterSeconds: 3600,
            tone: $blocked > 0 ? 'Warning' : 'Neutral',
            values: [
                ['key' => 'active', 'label' => 'Actifs', 'value' => count($active)],
                ['key' => 'blocked', 'label' => 'Bloqués', 'value' => $blocked],
                ['key' => 'decisions', 'label' => 'Décisions', 'value' => $decisions],
            ],
            context: $participants === [] ? 'Aucun participant inscrit dans le registre.' : count($participants).' participant(s) dans la cohorte.',
            href: '/backoffice/beta',
            detailPermission: 'operations.beta.read',
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
        ?\DateTimeImmutable $measuredAt = null,
        string $freshnessState = 'Current',
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
            'measured_at' => ($measuredAt ?? $now)->format(DATE_ATOM),
            'freshness' => [
                'target_seconds' => $targetSeconds,
                'stale_after_seconds' => $staleAfterSeconds,
                'state' => $freshnessState,
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

    private function noDataCard(
        string $key,
        string $label,
        string $description,
        string $source,
        \DateTimeImmutable $now,
        int $targetSeconds,
        int $staleAfterSeconds,
        ?string $href,
        ?string $detailPermission,
    ): array {
        return [
            'key' => $key,
            'label' => $label,
            'description' => $description,
            'status' => 'NoData',
            'tone' => 'Muted',
            'values' => [],
            'context' => 'Source disponible, mais aucune donnée admissible.',
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
