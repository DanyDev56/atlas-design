<?php

declare(strict_types=1);

namespace Atlas\Modules\Billing\Application;

use Atlas\Modules\Billing\Domain\BillingHistoryImportRequested;
use Atlas\Modules\Billing\Infrastructure\Persistence\PostgresBillingHistoryImportPreviewRepository;
use Atlas\Modules\Billing\Infrastructure\Persistence\PostgresBillingHistoryImportRunRepository;
use Atlas\Modules\Billing\Infrastructure\PostgresBillingIdempotencyStore;
use Atlas\Platform\Messaging\EventId;
use Atlas\Platform\Messaging\OutboxWriter;
use Atlas\Platform\Messaging\OutgoingMessage;
use Atlas\Platform\Security\WorkspaceAuthorizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ConfirmHistoricalBillingHistoryImportHandler
{
    public function __construct(
        private readonly WorkspaceAuthorizer $authorizer,
        private readonly PostgresBillingHistoryImportPreviewRepository $previews,
        private readonly PostgresBillingHistoryImportRunRepository $runs,
        private readonly PostgresBillingIdempotencyStore $idempotency,
        private readonly OutboxWriter $outbox,
    ) {}

    /** @return array<string, mixed> */
    public function handle(
        string $actorUserId,
        string $workspaceId,
        string $sessionId,
        string $previewId,
        string $packageHash,
        string $sourceSystem,
        string $sourceExportedAt,
        string $requestId,
        ?string $correlationId = null,
    ): array {
        $this->authorizer->authorizeElevated(
            $actorUserId,
            $workspaceId,
            'billing.history.import',
            $sessionId,
        );
        $packageHash = preg_replace('/^sha256:/i', '', $packageHash) ?? $packageHash;
        $fingerprint = hash('sha256', json_encode([$workspaceId, $previewId, $packageHash, trim($sourceSystem)], JSON_THROW_ON_ERROR));
        $scope = 'billing.confirm_historical_billing_import';
        $cached = $this->idempotency->find($scope, $requestId);
        if ($cached !== null) {
            if ($cached['fingerprint'] !== $fingerprint) {
                throw new \DomainException('Idempotency conflict.');
            }

            return $cached['response_payload'];
        }

        $existing = $this->runs->findByPackageHash($workspaceId, $packageHash);
        if ($existing !== null) {
            $response = $this->serialize($existing, $requestId);
            $this->idempotency->store($scope, $requestId, $fingerprint, $response);

            return $response;
        }

        $preview = $this->previews->findById($workspaceId, $previewId);
        if ($preview === null) {
            throw new \DomainException('Preview not found.');
        }
        if (! $preview['valid_for_confirmation']) {
            throw new \DomainException(new \DateTimeImmutable((string) $preview['expires_at']) <= new \DateTimeImmutable('now')
                ? 'Preview expired.'
                : 'Preview is not valid for confirmation.');
        }
        if ($preview['package_hash'] !== $packageHash) {
            throw new \DomainException('Package identity mismatch.');
        }
        if ($sourceSystem !== '' && $preview['source_system'] !== trim($sourceSystem)) {
            throw new \DomainException('Package identity mismatch.');
        }
        if ($sourceExportedAt !== '' && (new \DateTimeImmutable((string) $preview['source_exported_at']))->format('U')
            !== (new \DateTimeImmutable($sourceExportedAt))->format('U')) {
            throw new \DomainException('Source export date mismatch.');
        }

        $runId = (string) Str::uuid();
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $run = DB::transaction(function () use ($runId, $workspaceId, $previewId, $preview, $packageHash, $actorUserId, $now, $correlationId): array {
            $data = [
                'id' => $runId,
                'workspace_id' => $workspaceId,
                'preview_id' => $previewId,
                'source_system' => $preview['source_system'],
                'source_exported_at' => $preview['source_exported_at'],
                'package_hash' => $packageHash,
                'status' => 'Processing',
                'checkpoint' => 'Quotes',
                'quote_count' => $preview['quote_count'],
                'invoice_count' => $preview['invoice_count'],
                'payment_count' => $preview['payment_count'],
                'processed_quotes' => 0,
                'processed_invoices' => 0,
                'processed_payments' => 0,
                'created_by' => $actorUserId,
                'created_at' => $now->format('Y-m-d H:i:sP'),
                'updated_at' => $now->format('Y-m-d H:i:sP'),
            ];
            $this->runs->insert($data);
            $this->outbox->append(OutgoingMessage::fromDomainEvent(new BillingHistoryImportRequested(
                $runId, $workspaceId, (string) $preview['source_system'], $packageHash,
                (int) $preview['quote_count'], (int) $preview['invoice_count'], (int) $preview['payment_count'],
                EventId::generate(), $now,
            ), correlationId: $correlationId));

            return $data;
        });
        $response = $this->serialize($run, $requestId);
        $this->idempotency->store($scope, $requestId, $fingerprint, $response);

        return $response;
    }

    /** @param array<string, mixed> $run */
    private function serialize(array $run, string $requestId): array
    {
        return [
            'import_run_id' => $run['id'],
            'status' => $run['status'],
            'checkpoint' => $run['checkpoint'],
            'quote_count' => (int) $run['quote_count'],
            'invoice_count' => (int) $run['invoice_count'],
            'payment_count' => (int) $run['payment_count'],
            'processed_quotes' => (int) $run['processed_quotes'],
            'processed_invoices' => (int) $run['processed_invoices'],
            'processed_payments' => (int) $run['processed_payments'],
            'request_id' => $requestId,
        ];
    }
}
