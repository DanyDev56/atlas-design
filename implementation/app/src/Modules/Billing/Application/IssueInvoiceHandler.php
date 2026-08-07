<?php

declare(strict_types=1);

namespace Atlas\Modules\Billing\Application;

use Atlas\Modules\Billing\Domain\Invoice;
use Atlas\Modules\Billing\Domain\InvoiceId;
use Atlas\Modules\Billing\Domain\InvoiceIssued;
use Atlas\Modules\Billing\Infrastructure\Persistence\PostgresInvoiceRepository;
use Atlas\Modules\Billing\Infrastructure\PostgresBillingIdempotencyStore;
use Atlas\Platform\Messaging\EventId;
use Atlas\Platform\Messaging\OutgoingMessage;
use Atlas\Platform\Messaging\OutboxWriter;
use Atlas\Platform\Security\WorkspaceAuthorizer;
use Illuminate\Support\Facades\DB;

final class IssueInvoiceHandler
{
    public function __construct(
        private readonly WorkspaceAuthorizer $authorizer,
        private readonly PostgresInvoiceRepository $invoices,
        private readonly PostgresBillingIdempotencyStore $idempotency,
        private readonly OutboxWriter $outbox,
    ) {}

    public function handle(
        string $actorUserId,
        string $workspaceId,
        string $invoiceId,
        int $expectedRevision,
        string $requestId,
        ?string $correlationId = null,
    ): array {
        $this->authorizer->authorize($actorUserId, $workspaceId, 'billing.invoices.issue');

        $scope = 'billing.issue_invoice';
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
            $requestId, $scope, $fingerprint, $correlationId,
        ): array {
            $invoice = $this->invoices->findById($workspaceId, new InvoiceId($invoiceId));

            if ($invoice === null) {
                throw new \DomainException('Invoice not found.');
            }

            if ($invoice->status() === Invoice::STATUS_ISSUED) {
                return [
                    'invoice_id' => $invoiceId,
                    'status' => $invoice->status(),
                    'invoice_number' => $invoice->invoiceNumber(),
                    'version' => $invoice->version(),
                ];
            }

            if ($invoice->version() !== $expectedRevision) {
                throw new \DomainException('Invoice version conflict.');
            }

            $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
            $invoiceNumber = $this->invoices->nextInvoiceNumber($workspaceId);
            $invoice->issue($invoiceNumber, $now);

            $event = new InvoiceIssued(
                invoiceId: new InvoiceId($invoiceId),
                workspaceId: $workspaceId,
                eventId: EventId::generate(),
                occurredAt: $now,
            );

            $this->invoices->update($invoice);
            $this->outbox->append(OutgoingMessage::fromDomainEvent($event, correlationId: $correlationId));

            $response = [
                'invoice_id' => $invoiceId,
                'status' => $invoice->status(),
                'invoice_number' => $invoice->invoiceNumber(),
                'version' => $invoice->version(),
            ];

            $this->idempotency->store($scope, $requestId, $fingerprint, $response);

            return $response;
        });
    }
}
