<?php

declare(strict_types=1);

namespace Atlas\Modules\Billing\Application;

use Atlas\Modules\Billing\Infrastructure\Persistence\PostgresInvoiceRepository;

final class MarkDueInvoicesOverdueHandler
{
    public function __construct(
        private readonly PostgresInvoiceRepository $invoices,
        private readonly MarkInvoiceOverdueHandler $markOverdue,
    ) {}

    /** @return array{scanned: int, marked: int, skipped: int} */
    public function handle(int $limit = 100): array
    {
        $clock = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $candidates = $this->invoices->listDueForOverdueMark($clock, $limit);
        $marked = 0;
        $skipped = 0;

        foreach ($candidates as $candidate) {
            try {
                $this->markOverdue->handle(
                    workspaceId: $candidate['workspace_id'],
                    invoiceId: $candidate['invoice_id'],
                    clock: $clock,
                    expectedRevision: $candidate['version'],
                );
                $marked++;
            } catch (\DomainException) {
                $skipped++;
            }
        }

        return [
            'scanned' => count($candidates),
            'marked' => $marked,
            'skipped' => $skipped,
        ];
    }
}
