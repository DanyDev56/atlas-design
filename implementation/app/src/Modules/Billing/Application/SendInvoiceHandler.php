<?php

declare(strict_types=1);

namespace Atlas\Modules\Billing\Application;

use Atlas\Modules\Billing\Domain\Invoice;
use Atlas\Modules\Billing\Domain\InvoiceId;
use Atlas\Modules\Billing\Infrastructure\Persistence\PostgresInvoiceRepository;
use Atlas\Modules\Billing\Infrastructure\PostgresBillingIdempotencyStore;
use Atlas\Platform\Security\WorkspaceAuthorizer;
use Illuminate\Support\Facades\DB;

final class SendInvoiceHandler
{
    public function __construct(
        private readonly WorkspaceAuthorizer $authorizer,
        private readonly PostgresInvoiceRepository $invoices,
        private readonly PostgresBillingIdempotencyStore $idempotency,
    ) {}

    public function handle(
        string $actorUserId,
        string $workspaceId,
        string $invoiceId,
        int $expectedRevision,
        string $requestId,
    ): array {
        $this->authorizer->authorize($actorUserId, $workspaceId, 'billing.invoices.send');

        $scope = 'billing.send_invoice';
        $fingerprint = hash('sha256', json_encode([
            $workspaceId, $invoiceId, $expectedRevision,
        ], JSON_THROW_ON_ERROR));
        $cached = $this->idempotency->find($scope, $requestId);

        if ($cached !== null) {
            if ($cached['fingerprint'] !== $fingerprint) {
                throw new \DomainException('Idempotency conflict.');
            }

            return $cached['response_payload'];
        }

        return DB::transaction(function () use (
            $workspaceId, $invoiceId, $expectedRevision,
            $requestId, $scope, $fingerprint,
        ): array {
            $invoice = $this->invoices->findById($workspaceId, new InvoiceId($invoiceId));

            if ($invoice === null) {
                throw new \DomainException('Invoice not found.');
            }

            if ($invoice->status() !== Invoice::STATUS_ISSUED) {
                throw new \DomainException('Invoice is not issued.');
            }

            if ($invoice->version() !== $expectedRevision) {
                throw new \DomainException('Invoice version conflict.');
            }

            $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
            $invoice->markSent($now);
            $this->invoices->update($invoice);

            $response = [
                'invoice_id' => $invoiceId,
                'status' => $invoice->status(),
                'version' => $invoice->version(),
            ];

            $this->idempotency->store($scope, $requestId, $fingerprint, $response);

            return $response;
        });
    }
}
