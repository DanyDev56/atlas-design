<?php

declare(strict_types=1);

namespace Atlas\Modules\Billing\Application;

use Atlas\Modules\Billing\Domain\Invoice;
use Atlas\Modules\Billing\Domain\InvoiceDeliveryRequested;
use Atlas\Modules\Billing\Domain\InvoiceId;
use Atlas\Modules\Billing\Infrastructure\Persistence\PostgresInvoiceRepository;
use Atlas\Modules\Billing\Infrastructure\PostgresBillingIdempotencyStore;
use Atlas\Platform\Messaging\EventId;
use Atlas\Platform\Messaging\OutboxWriter;
use Atlas\Platform\Messaging\OutgoingMessage;
use Atlas\Platform\Security\WorkspaceAuthorizer;
use Illuminate\Support\Facades\DB;

final class SendInvoiceHandler
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

            if ($invoice->isHistoricalImport()) {
                throw new \DomainException('Historical imports are read-only.');
            }

            if ($invoice->status() !== Invoice::STATUS_ISSUED) {
                throw new \DomainException('Invoice is not issued.');
            }

            if ($invoice->version() !== $expectedRevision) {
                throw new \DomainException('Invoice version conflict.');
            }

            if ($this->billingEmail($invoice->clientSnapshot()) === null) {
                throw new \DomainException('A client billing email is required before sending this invoice.');
            }

            $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
            $isResend = $invoice->sentAt() !== null;

            if (! $isResend) {
                $invoice->markSent($now);
                $this->invoices->update($invoice);
            }

            $this->outbox->append(OutgoingMessage::fromDomainEvent(new InvoiceDeliveryRequested(
                invoiceId: new InvoiceId($invoiceId),
                workspaceId: $workspaceId,
                documentVersion: $invoice->version(),
                eventId: EventId::generate(),
                occurredAt: $now,
            )));

            $response = [
                'invoice_id' => $invoiceId,
                'status' => $invoice->status(),
                'version' => $invoice->version(),
                'sent_at' => $invoice->sentAt()?->format(DATE_ATOM),
                'delivery_status' => 'Pending',
                'resent' => $isResend,
            ];

            $this->idempotency->store($scope, $requestId, $fingerprint, $response);

            return $response;
        });
    }

    /** @param array<string, mixed> $snapshot */
    private function billingEmail(array $snapshot): ?string
    {
        $email = $snapshot['billing_profile']['billing_email'] ?? null;
        $normalized = is_string($email) ? strtolower(trim($email)) : '';

        return filter_var($normalized, FILTER_VALIDATE_EMAIL) !== false ? $normalized : null;
    }
}
