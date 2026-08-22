<?php

declare(strict_types=1);

namespace Atlas\Modules\Billing\Application;

use Atlas\Modules\Billing\Domain\InvoiceId;
use Atlas\Modules\Billing\Domain\InvoiceOverdue;
use Atlas\Modules\Billing\Infrastructure\Persistence\PostgresInvoiceRepository;
use Atlas\Modules\Billing\Infrastructure\PostgresBillingIdempotencyStore;
use Atlas\Platform\Messaging\EventId;
use Atlas\Platform\Messaging\OutgoingMessage;
use Atlas\Platform\Messaging\OutboxWriter;
use Illuminate\Support\Facades\DB;

final class MarkInvoiceOverdueHandler
{
    public function __construct(
        private readonly PostgresInvoiceRepository $invoices,
        private readonly PostgresBillingIdempotencyStore $idempotency,
        private readonly OutboxWriter $outbox,
    ) {}

    /** @return array<string, mixed> */
    public function handle(
        string $workspaceId,
        string $invoiceId,
        \DateTimeImmutable $clock,
        int $expectedRevision,
        ?string $correlationId = null,
    ): array {
        $this->assertWorkspaceUsable($workspaceId);

        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        if ($clock > $now->modify('+2 minutes')) {
            throw new \DomainException('Clock proof invalid.');
        }

        $invoice = $this->invoices->findById($workspaceId, new InvoiceId($invoiceId));
        if ($invoice === null) {
            throw new \DomainException('Invoice not found.');
        }

        $dueDateKey = $invoice->dueDate()?->format('Y-m-d') ?? 'none';
        $scope = 'billing.mark_invoice_overdue';
        $requestId = 'mark-overdue:'.$invoiceId.':'.$dueDateKey;
        $fingerprint = hash('sha256', json_encode([
            $workspaceId, $invoiceId, $dueDateKey,
        ], JSON_THROW_ON_ERROR));
        $cached = $this->idempotency->find($scope, $requestId);

        if ($cached !== null) {
            if ($cached['fingerprint'] !== $fingerprint) {
                throw new \DomainException('Idempotency conflict.');
            }

            return $cached['response_payload'];
        }

        if ($invoice->alreadyMarkedOverdueForCurrentDueDate()) {
            return $this->response($invoice);
        }

        return DB::transaction(function () use (
            $workspaceId, $invoiceId, $clock, $expectedRevision,
            $requestId, $scope, $fingerprint, $correlationId,
        ): array {
            $invoice = $this->invoices->findById($workspaceId, new InvoiceId($invoiceId), true);
            if ($invoice === null) {
                throw new \DomainException('Invoice not found.');
            }

            if ($invoice->alreadyMarkedOverdueForCurrentDueDate()) {
                $response = $this->response($invoice);
                $this->idempotency->store($scope, $requestId, $fingerprint, $response);

                return $response;
            }

            $invoice->markOverdue($expectedRevision, $clock);
            $this->invoices->update($invoice);

            $this->outbox->append(OutgoingMessage::fromDomainEvent(new InvoiceOverdue(
                invoiceId: new InvoiceId($invoiceId),
                workspaceId: $workspaceId,
                dueDate: $invoice->dueDate()?->format(DATE_ATOM) ?? '',
                outstandingBalanceCents: $invoice->balanceCents(),
                aggregateVersion: $invoice->version(),
                eventId: EventId::generate(),
                occurredAt: $clock,
            ), correlationId: $correlationId));

            $response = $this->response($invoice);
            $this->idempotency->store($scope, $requestId, $fingerprint, $response);

            return $response;
        });
    }

    /** @return array<string, mixed> */
    private function response(\Atlas\Modules\Billing\Domain\Invoice $invoice): array
    {
        return [
            'invoice_id' => $invoice->id()->value,
            'status' => $invoice->status(),
            'settlement_status' => $invoice->settlementStatus(),
            'overdue' => $invoice->isOverdue(),
            'overdue_at' => $invoice->overdueAt()?->format(DATE_ATOM),
            'version' => $invoice->version(),
            'balance_cents' => $invoice->balanceCents(),
        ];
    }

    private function assertWorkspaceUsable(string $workspaceId): void
    {
        $workspace = DB::table('workspace.workspaces')->where('id', $workspaceId)->first();

        if (
            $workspace === null
            || $workspace->status !== 'Active'
            || $workspace->access_state !== 'Active'
        ) {
            throw new \DomainException('Unauthorized.');
        }
    }
}
