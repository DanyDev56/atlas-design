<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Atlas\Modules\Billing\Application\MarkDueInvoicesOverdueHandler;
use Illuminate\Console\Command;

final class MarkDueInvoicesOverdueCommand extends Command
{
    protected $signature = 'atlas:billing:mark-overdue {--limit=100 : Maximum invoices to inspect}';

    protected $description = 'Materialize InvoiceOverdue for issued invoices past due with a positive balance';

    public function handle(MarkDueInvoicesOverdueHandler $handler): int
    {
        $result = $handler->handle(max(1, (int) $this->option('limit')));

        $this->info(sprintf(
            'Scanned %d invoice(s); marked %d overdue; skipped %d.',
            $result['scanned'],
            $result['marked'],
            $result['skipped'],
        ));

        return self::SUCCESS;
    }
}
