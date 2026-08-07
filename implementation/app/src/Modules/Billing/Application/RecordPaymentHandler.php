<?php

declare(strict_types=1);

namespace Atlas\Modules\Billing\Application;

use Atlas\Modules\Billing\Domain\Invoice;
use Atlas\Modules\Billing\Domain\InvoiceId;
use Atlas\Modules\Billing\Domain\PaymentId;
use Atlas\Modules\Billing\Domain\PaymentRecorded;
use Atlas\Modules\Billing\Infrastructure\Persistence\PostgresInvoiceRepository;
use Atlas\Modules\Billing\Infrastructure\Persistence\PostgresPaymentRepository;
use Atlas\Modules\Billing\Infrastructure\PostgresBillingIdempotencyStore;
use Atlas\Platform\Messaging\EventId;
use Atlas\Platform\Messaging\OutgoingMessage;
use Atlas\Platform\Messaging\OutboxWriter;
use Atlas\Platform\Security\WorkspaceAuthorizer;
use Illuminate\Support\Facades\DB;

final class RecordPaymentHandler
{
    public function __construct(
        private readonly WorkspaceAuthorizer $authorizer,
        private readonly PostgresInvoiceRepository $invoices,
        private readonly PostgresPaymentRepository $payments,
        private readonly PostgresBillingIdempotencyStore $idempotency,
        private readonly OutboxWriter $outbox,
    ) {}

    public function handle(
        string $actorUserId,
        string $workspaceId,
        string $invoiceId,
        int $amountCents,
        ?string $reference,
        string $requestId,
        ?string $correlationId = null,
    ): array {
        $this->authorizer->authorize($actorUserId, $workspaceId, 'billing.payments.record');

        $scope = 'billing.record_payment';
        $fingerprint = hash('sha256', json_encode([
            $workspaceId, $invoiceId, $amountCents, $reference,
        ], JSON_THROW_ON_ERROR));
        $cached = $this->idempotency->find($scope, $requestId);

        if ($cached !== null) {
            if ($cached['fingerprint'] !== $fingerprint) {
                throw new \DomainException('Idempotency conflict.');
            }

            return $cached['response_payload'];
        }

        return DB::transaction(function () use (
            $workspaceId, $invoiceId, $amountCents, $reference,
            $requestId, $scope, $fingerprint, $correlationId,
        ): array {
            $invoice = $this->invoices->findById($workspaceId, new InvoiceId($invoiceId));

            if ($invoice === null) {
                throw new \DomainException('Invoice not found.');
            }

            if ($invoice->status() !== Invoice::STATUS_ISSUED) {
                throw new \DomainException('Invoice is not issued.');
            }

            $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
            $paymentId = PaymentId::generate();
            $invoice->applyPayment($amountCents, $now);

            $event = new PaymentRecorded(
                paymentId: $paymentId,
                invoiceId: new InvoiceId($invoiceId),
                workspaceId: $workspaceId,
                amountCents: $amountCents,
                remainingBalanceCents: $invoice->balanceCents(),
                eventId: EventId::generate(),
                occurredAt: $now,
            );

            $this->payments->insert(
                paymentId: $paymentId,
                workspaceId: $workspaceId,
                invoiceId: $invoiceId,
                amountCents: $amountCents,
                currency: $invoice->currency(),
                reference: $reference,
                recordedAt: $now,
            );
            $this->invoices->update($invoice);
            $this->outbox->append(OutgoingMessage::fromDomainEvent($event, correlationId: $correlationId));

            $response = [
                'payment_id' => $paymentId->value,
                'invoice_id' => $invoiceId,
                'amount_cents' => $amountCents,
                'balance_cents' => $invoice->balanceCents(),
                'settlement_status' => $invoice->settlementStatus(),
                'version' => $invoice->version(),
            ];

            $this->idempotency->store($scope, $requestId, $fingerprint, $response);

            return $response;
        });
    }
}
