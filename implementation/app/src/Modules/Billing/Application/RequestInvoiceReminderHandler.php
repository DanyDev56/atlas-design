<?php

declare(strict_types=1);

namespace Atlas\Modules\Billing\Application;

use Atlas\Modules\Billing\Domain\InvoiceId;
use Atlas\Modules\Billing\Domain\InvoiceReminderRequested;
use Atlas\Modules\Billing\Infrastructure\Persistence\PostgresInvoiceRepository;
use Atlas\Modules\Billing\Infrastructure\PostgresBillingIdempotencyStore;
use Atlas\Platform\Messaging\EventId;
use Atlas\Platform\Messaging\OutboxWriter;
use Atlas\Platform\Messaging\OutgoingMessage;
use Atlas\Platform\Security\WorkspaceAuthorizer;
use Illuminate\Support\Facades\DB;

final class RequestInvoiceReminderHandler
{
    public const DELIVERY_MANUAL = 'ManualChannel';

    public const DELIVERY_EMAIL = 'EmailChannel';

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
        string $delivery,
        ?string $message,
        int $expectedRevision,
        string $requestId,
        ?string $correlationId = null,
    ): array {
        $this->authorizer->authorize($actorUserId, $workspaceId, 'billing.invoices.remind');

        if (! in_array($delivery, [self::DELIVERY_MANUAL, self::DELIVERY_EMAIL], true)) {
            throw new \DomainException('Unsupported reminder delivery.');
        }

        $scope = 'billing.request_invoice_reminder';
        $fingerprint = hash('sha256', json_encode([
            $workspaceId, $invoiceId, $delivery, $message, $expectedRevision,
        ], JSON_THROW_ON_ERROR));
        $cached = $this->idempotency->find($scope, $requestId);

        if ($cached !== null) {
            if ($cached['fingerprint'] !== $fingerprint) {
                throw new \DomainException('Idempotency conflict.');
            }

            return $cached['response_payload'];
        }

        return DB::transaction(function () use (
            $workspaceId, $invoiceId, $delivery, $message, $expectedRevision,
            $requestId, $scope, $fingerprint, $correlationId,
        ): array {
            $invoice = $this->invoices->findById($workspaceId, new InvoiceId($invoiceId), true);
            if ($invoice === null) {
                throw new \DomainException('Invoice not found.');
            }

            $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
            $invoice->requestReminder($expectedRevision, $now);
            $this->invoices->update($invoice);

            $this->outbox->append(OutgoingMessage::fromDomainEvent(new InvoiceReminderRequested(
                invoiceId: new InvoiceId($invoiceId),
                workspaceId: $workspaceId,
                delivery: $delivery,
                reminderCount: $invoice->reminderCount(),
                aggregateVersion: $invoice->version(),
                message: $message,
                eventId: EventId::generate(),
                occurredAt: $now,
            ), correlationId: $correlationId));

            $response = [
                'invoice_id' => $invoiceId,
                'status' => $invoice->status(),
                'version' => $invoice->version(),
                'reminder_count' => $invoice->reminderCount(),
                'last_reminded_at' => $invoice->lastRemindedAt()?->format(DATE_ATOM),
                'delivery' => $delivery,
                'message' => $message,
            ];
            $this->idempotency->store($scope, $requestId, $fingerprint, $response);

            return $response;
        });
    }
}
