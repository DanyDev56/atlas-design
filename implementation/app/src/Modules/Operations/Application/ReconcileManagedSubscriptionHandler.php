<?php

declare(strict_types=1);

namespace Atlas\Modules\Operations\Application;

use Atlas\Modules\Operations\Domain\OperatorPermissionCatalog;
use Atlas\Modules\Operations\Infrastructure\Persistence\PostgresOperatorAuditRepository;
use Atlas\Modules\Subscriptions\Application\ReconcileSubscriptionHandler;
use Atlas\Modules\Subscriptions\Domain\SubscriptionReconciliationException;
use Atlas\Modules\Subscriptions\Infrastructure\Payment\ConfiguredBillingEnvironment;
use Atlas\Platform\Support\UuidGenerator;
use Illuminate\Support\Facades\DB;

final class ReconcileManagedSubscriptionHandler
{
    public function __construct(
        private readonly ReconcileSubscriptionHandler $subscriptions,
        private readonly ConfiguredBillingEnvironment $environment,
        private readonly PostgresOperatorAuditRepository $audit,
    ) {}

    /** @return array<string, mixed> */
    public function preview(string $reference, int $expectedVersion, string $reasonCode): array
    {
        $this->assertReason($reasonCode);
        $target = $this->target($reference);
        $configuredEnvironment = $this->assertEnvironment($target);
        try {
            $inspection = $this->subscriptions->inspect((string) $target->id, $expectedVersion);
        } catch (SubscriptionReconciliationException $exception) {
            throw $this->translate($exception);
        }

        return $this->previewResponse($reference, $expectedVersion, $reasonCode, (string) $target->billing_environment, $configuredEnvironment, $inspection);
    }

    /** @return array<string, mixed> */
    public function apply(
        string $reference,
        int $expectedVersion,
        string $reasonCode,
        string $previewFingerprint,
        string $operatorUserId,
        string $operatorSessionId,
        string $idempotencyKey,
        ?string $correlationId,
    ): array {
        $this->assertReason($reasonCode);
        $scope = 'subscription.reconcile:'.$reference;
        $requestFingerprint = hash('sha256', implode('|', [$reference, (string) $expectedVersion, $reasonCode, $previewFingerprint, $operatorUserId]));

        $replay = DB::transaction(function () use ($scope, $idempotencyKey, $requestFingerprint, $operatorUserId, $operatorSessionId): ?array {
            DB::selectOne('SELECT pg_advisory_xact_lock(hashtextextended(?, 0))', [$scope.'|'.$idempotencyKey]);
            $this->assertCurrentAuthority($operatorUserId, $operatorSessionId);
            $row = DB::table('operations.operator_action_idempotency')->where('action_scope', $scope)->where('idempotency_key', $idempotencyKey)->lockForUpdate()->first();
            if ($row === null) {
                return null;
            }
            if (! hash_equals((string) $row->request_fingerprint, $requestFingerprint)) {
                throw new SubscriptionReconciliationActionException('OperatorIdempotencyConflict', 'Cette clé d’idempotence a déjà été utilisée avec une autre action.', 409);
            }
            $response = is_array($row->response) ? $row->response : json_decode((string) $row->response, true, 512, JSON_THROW_ON_ERROR);

            return [...$response, 'replayed' => true];
        });
        if ($replay !== null) {
            return $replay;
        }

        $target = $this->target($reference);
        $configuredEnvironment = $this->assertEnvironment($target);
        $sourceEnvironment = (string) $target->billing_environment;
        try {
            $inspection = $this->subscriptions->inspect((string) $target->id, $expectedVersion);
        } catch (SubscriptionReconciliationException $exception) {
            throw $this->translate($exception);
        }
        $preview = $this->previewResponse($reference, $expectedVersion, $reasonCode, $sourceEnvironment, $configuredEnvironment, $inspection);
        if (! hash_equals((string) $preview['preview_fingerprint'], $previewFingerprint)) {
            throw new SubscriptionReconciliationActionException('SubscriptionReconciliationPreviewStale', 'L’état Stripe a changé depuis la prévisualisation.', 409);
        }

        return DB::transaction(function () use ($reference, $expectedVersion, $reasonCode, $operatorUserId, $operatorSessionId, $idempotencyKey, $correlationId, $scope, $requestFingerprint, $inspection, $sourceEnvironment, $configuredEnvironment): array {
            DB::selectOne('SELECT pg_advisory_xact_lock(hashtextextended(?, 0))', [$scope.'|'.$idempotencyKey]);
            $replay = DB::table('operations.operator_action_idempotency')->where('action_scope', $scope)->where('idempotency_key', $idempotencyKey)->lockForUpdate()->first();
            if ($replay !== null) {
                if (! hash_equals((string) $replay->request_fingerprint, $requestFingerprint)) {
                    throw new SubscriptionReconciliationActionException('OperatorIdempotencyConflict', 'Cette clé d’idempotence a déjà été utilisée avec une autre action.', 409);
                }
                $response = is_array($replay->response) ? $replay->response : json_decode((string) $replay->response, true, 512, JSON_THROW_ON_ERROR);

                return [...$response, 'replayed' => true];
            }
            $this->assertCurrentAuthority($operatorUserId, $operatorSessionId);
            $target = $this->target($reference, true);
            $this->assertEnvironment($target);
            if ((string) $target->billing_environment !== $sourceEnvironment) {
                throw new SubscriptionReconciliationActionException('SubscriptionReconciliationPreviewStale', 'L’environnement de l’abonnement a changé depuis la prévisualisation.', 409);
            }
            try {
                $applied = $this->subscriptions->apply(
                    (string) $target->id,
                    $expectedVersion,
                    $inspection['provider'],
                    recordVerifiedObservation: $sourceEnvironment === 'Unknown',
                );
            } catch (SubscriptionReconciliationException $exception) {
                throw $this->translate($exception);
            }
            $subscription = $applied['subscription'];
            $changes = $this->withEnvironmentChange($sourceEnvironment, $configuredEnvironment, $applied['changes']);
            $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
            $response = [
                'reference' => $reference,
                'status' => $subscription->status(),
                'version' => $subscription->version(),
                'reconciled_at' => $inspection['provider']->observedAt->format(DATE_ATOM),
                'changes' => $changes,
                'replayed' => false,
            ];
            DB::table('operations.operator_action_idempotency')->insert([
                'id' => UuidGenerator::generate(), 'action_scope' => $scope, 'idempotency_key' => $idempotencyKey,
                'request_fingerprint' => $requestFingerprint, 'response' => json_encode($response, JSON_THROW_ON_ERROR),
                'created_at' => $now->format('Y-m-d H:i:sP'),
            ]);
            $this->audit->record(
                action: 'operator.subscription.reconciled', result: 'Succeeded', operatorUserId: $operatorUserId,
                operatorSessionId: $operatorSessionId, permission: OperatorPermissionCatalog::SUBSCRIPTIONS_RECONCILE,
                targetType: 'Subscription', targetIdHash: hash('sha256', (string) $target->id), correlationId: $correlationId,
                reason: $reasonCode, metadata: ['environment' => $configuredEnvironment, 'fields' => array_column($changes, 'field')], occurredAt: $now,
            );

            return $response;
        });
    }

    /** @param array{subscription:mixed,provider:mixed,changes:list<array<string,mixed>>} $inspection @return array<string,mixed> */
    private function previewResponse(
        string $reference,
        int $expectedVersion,
        string $reasonCode,
        string $sourceEnvironment,
        string $configuredEnvironment,
        array $inspection,
    ): array
    {
        $provider = $inspection['provider'];
        $changes = $this->withEnvironmentChange($sourceEnvironment, $configuredEnvironment, $inspection['changes']);

        return [
            'reference' => $reference,
            'version' => $expectedVersion,
            'aligned' => $changes === [],
            'changes' => $changes,
            'provider_observed_at' => $provider->observedAt->format(DATE_ATOM),
            'reason_code' => $reasonCode,
            'preview_fingerprint' => hash('sha256', implode('|', [$reference, (string) $expectedVersion, $sourceEnvironment, $configuredEnvironment, $provider->fingerprint(), $reasonCode])),
            'effects' => ['Mise à jour ciblée des champs divergents', 'Recalcul des droits de l’espace', 'Aucune mutation effectuée chez Stripe'],
        ];
    }

    private function target(string $reference, bool $lock = false): object
    {
        $query = DB::table('subscriptions.recurring_subscriptions')->select(['id', 'workspace_id', 'provider', 'billing_environment', 'version']);
        $rows = ($lock ? $query->lockForUpdate() : $query)->get();
        foreach ($rows as $row) {
            if (hash_equals($reference, $this->reference((string) $row->workspace_id))) {
                return $row;
            }
        }
        throw new SubscriptionReconciliationActionException('SubscriptionNotFound', 'Abonnement introuvable.', 404);
    }

    private function reference(string $workspaceId): string
    {
        return 'SUB-'.strtoupper(substr(hash_hmac('sha256', $workspaceId, (string) config('app.key')), 0, 10));
    }

    /** @param list<array<string, mixed>> $changes @return list<array<string, mixed>> */
    private function withEnvironmentChange(string $source, string $configured, array $changes): array
    {
        if ($source !== 'Unknown') {
            return $changes;
        }

        return [[
            'field' => 'billing_environment',
            'current' => 'Unknown',
            'provider' => $configured,
        ], ...$changes];
    }

    private function assertEnvironment(object $target): string
    {
        $configured = $this->environment->current();
        if ((string) $target->provider !== (string) config('subscriptions.gateway', 'fake')
            || $configured === 'Unknown'
            || ! in_array((string) $target->billing_environment, [$configured, 'Unknown'], true)) {
            throw new SubscriptionReconciliationActionException('SubscriptionEnvironmentMismatch', 'La configuration active ne correspond pas à l’environnement de cet abonnement.', 409);
        }

        return $configured;
    }

    private function assertReason(string $reasonCode): void
    {
        if (preg_match('/^[a-z0-9._-]{3,64}$/', $reasonCode) !== 1) {
            throw new SubscriptionReconciliationActionException('SubscriptionReconciliationReasonInvalid', 'Un motif structuré sans donnée personnelle est requis.', 422);
        }
    }

    private function assertCurrentAuthority(string $operatorUserId, string $operatorSessionId): void
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $row = DB::table('operations.operator_sessions as session')->join('operations.operator_grants as grant', 'grant.id', '=', 'session.grant_id')
            ->where('session.id', $operatorSessionId)->where('session.user_id', $operatorUserId)->lockForUpdate()
            ->select(['session.status as session_status', 'session.expires_at as session_expires_at', 'session.step_up_at', 'grant.status as grant_status', 'grant.expires_at as grant_expires_at', 'grant.permissions'])->first();
        $permissions = $row !== null ? (is_array($row->permissions) ? $row->permissions : json_decode((string) $row->permissions, true, 512, JSON_THROW_ON_ERROR)) : [];
        $minutes = max(1, min(30, (int) config('operations.backoffice.step_up_minutes', 10)));
        if ($row === null || (string) $row->session_status !== 'Active' || new \DateTimeImmutable((string) $row->session_expires_at) <= $now
            || (string) $row->grant_status !== 'Active' || ($row->grant_expires_at !== null && new \DateTimeImmutable((string) $row->grant_expires_at) <= $now)
            || ! in_array(OperatorPermissionCatalog::SUBSCRIPTIONS_RECONCILE, $permissions, true) || $row->step_up_at === null
            || (new \DateTimeImmutable((string) $row->step_up_at))->modify('+'.$minutes.' minutes') <= $now) {
            throw new SubscriptionReconciliationActionException('OperatorAuthorityChanged', 'L’autorité ou le step-up de la session a changé avant confirmation.', 409);
        }
    }

    private function translate(SubscriptionReconciliationException $exception): SubscriptionReconciliationActionException
    {
        return new SubscriptionReconciliationActionException($exception->errorCode, $exception->getMessage(), $exception->httpStatus, $exception);
    }
}
