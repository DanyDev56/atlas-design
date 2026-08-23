<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Atlas\Modules\Operations\Infrastructure\Persistence\PostgresBetaCohortRegistry;
use Atlas\Modules\Operations\Infrastructure\Persistence\PostgresOperatorAuditRepository;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class RecordBetaPricingDecisionCommand extends Command
{
    protected $signature = 'atlas:beta:decision
        {beta-code}
        {decision : Canonical pricing decision code}
        {--primary-reason=context : price, value, scope, trust or context}
        {--preference=Indifferent : Monthly, Annual or Indifferent}
        {--evidence-ref= : Opaque internal reference, never raw evidence}
        {--observed-on= : Observation date}
        {--reason= : Required administrative reason}';

    protected $description = 'Record the single pricing decision for a beta participant';

    public function handle(PostgresBetaCohortRegistry $registry, PostgresOperatorAuditRepository $audit): int
    {
        $reason = trim((string) $this->option('reason'));
        if ($reason === '') {
            $this->error('--reason is required.');

            return self::INVALID;
        }

        try {
            DB::transaction(function () use ($registry, $audit, $reason): void {
                $betaCode = strtoupper((string) $this->argument('beta-code'));
                $decision = strtoupper((string) $this->argument('decision'));
                $registry->recordDecision(
                    $betaCode,
                    $decision,
                    (string) $this->option('primary-reason'),
                    (string) $this->option('preference'),
                    $this->option('evidence-ref') !== null ? (string) $this->option('evidence-ref') : null,
                    $this->option('observed-on') !== null ? new \DateTimeImmutable((string) $this->option('observed-on')) : new \DateTimeImmutable('today'),
                );
                $audit->record(
                    action: 'beta.pricing-decision.recorded',
                    result: 'Succeeded',
                    permission: 'operations.beta.manage',
                    targetType: 'BetaParticipant',
                    targetIdHash: hash('sha256', $betaCode),
                    reason: $reason,
                    metadata: ['decision' => $decision],
                );
            });
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Pricing decision recorded.');

        return self::SUCCESS;
    }
}
