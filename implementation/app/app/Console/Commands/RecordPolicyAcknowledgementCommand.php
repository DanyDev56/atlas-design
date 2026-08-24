<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Atlas\Modules\Operations\Infrastructure\Persistence\PostgresOperatorAuditRepository;
use Atlas\Modules\Operations\Infrastructure\Persistence\PostgresSupportComplianceRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class RecordPolicyAcknowledgementCommand extends Command
{
    protected $signature = 'atlas:compliance:policy-proof
        {kind : BetaTerms or PrivacyNotice}
        {version}
        {requester-email}
        {proof-type : Informed or Accepted}
        {evidence-ref : Opaque restricted evidence reference}
        {--workspace-id=}
        {--occurred-at=}
        {--reason= : Required administrative reason}';

    protected $description = 'Record an immutable policy information or acceptance proof';

    public function handle(PostgresSupportComplianceRegistry $registry, PostgresOperatorAuditRepository $audit): int
    {
        $reason = trim((string) $this->option('reason'));
        if ($reason === '') {
            $this->error('--reason is required.');

            return self::INVALID;
        }

        try {
            DB::transaction(function () use ($registry, $audit, $reason): void {
                $registry->recordPolicyAcknowledgement(
                    (string) $this->argument('kind'),
                    (string) $this->argument('version'),
                    (string) $this->argument('requester-email'),
                    $this->option('workspace-id') !== null ? (string) $this->option('workspace-id') : null,
                    (string) $this->argument('proof-type'),
                    (string) $this->argument('evidence-ref'),
                    $this->option('occurred-at') !== null ? new \DateTimeImmutable((string) $this->option('occurred-at')) : new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
                );
                $audit->record(
                    action: 'compliance.policy-proof.recorded',
                    result: 'Succeeded',
                    permission: 'operations.compliance.manage',
                    targetType: 'PolicyAcknowledgement',
                    targetIdHash: hash('sha256', (string) $this->argument('evidence-ref')),
                    reason: $reason,
                    metadata: ['kind' => (string) $this->argument('kind'), 'proof_type' => (string) $this->argument('proof-type')],
                );
            });
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Policy proof recorded without storing its raw evidence reference.');

        return self::SUCCESS;
    }
}
