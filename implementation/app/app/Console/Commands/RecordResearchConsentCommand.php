<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Atlas\Modules\Operations\Infrastructure\Persistence\PostgresOperatorAuditRepository;
use Atlas\Modules\Operations\Infrastructure\Persistence\PostgresSupportComplianceRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class RecordResearchConsentCommand extends Command
{
    protected $signature = 'atlas:compliance:consent
        {requester-email}
        {purpose : Interview, Recording or PublicQuote}
        {decision : Granted or Withdrawn}
        {evidence-ref : Opaque restricted evidence reference}
        {--workspace-id=}
        {--occurred-at=}
        {--reason= : Required administrative reason}';

    protected $description = 'Record an append-only optional research consent event';

    public function handle(PostgresSupportComplianceRegistry $registry, PostgresOperatorAuditRepository $audit): int
    {
        $reason = trim((string) $this->option('reason'));
        if ($reason === '') {
            $this->error('--reason is required.');

            return self::INVALID;
        }

        try {
            DB::transaction(function () use ($registry, $audit, $reason): void {
                $registry->recordResearchConsent(
                    (string) $this->argument('requester-email'),
                    $this->option('workspace-id') !== null ? (string) $this->option('workspace-id') : null,
                    (string) $this->argument('purpose'),
                    (string) $this->argument('decision'),
                    (string) $this->argument('evidence-ref'),
                    $this->option('occurred-at') !== null ? new \DateTimeImmutable((string) $this->option('occurred-at')) : new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
                );
                $audit->record(
                    action: 'compliance.research-consent.recorded',
                    result: 'Succeeded',
                    permission: 'operations.compliance.manage',
                    targetType: 'ResearchConsent',
                    targetIdHash: hash('sha256', (string) $this->argument('evidence-ref')),
                    reason: $reason,
                    metadata: ['purpose' => (string) $this->argument('purpose'), 'decision' => (string) $this->argument('decision')],
                );
            });
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Research consent event recorded separately from product access.');

        return self::SUCCESS;
    }
}
