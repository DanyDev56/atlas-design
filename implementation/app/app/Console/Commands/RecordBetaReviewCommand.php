<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Atlas\Modules\Operations\Infrastructure\Persistence\PostgresBetaCohortRegistry;
use Atlas\Modules\Operations\Infrastructure\Persistence\PostgresOperatorAuditRepository;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class RecordBetaReviewCommand extends Command
{
    protected $signature = 'atlas:beta:review
        {beta-code}
        {milestone : J2, J7, J14, J21 or J30}
        {--blockage= : Structured blockage code, never a free-form personal note}
        {--support-minutes=0 : Cumulative support time}
        {--next-action= : Required structured next action}
        {--reviewed-at= : ISO-8601 review date}
        {--reason= : Required administrative reason}';

    protected $description = 'Record one bounded beta cohort review';

    public function handle(PostgresBetaCohortRegistry $registry, PostgresOperatorAuditRepository $audit): int
    {
        $reason = trim((string) $this->option('reason'));
        $nextAction = trim((string) $this->option('next-action'));
        if ($reason === '' || $nextAction === '') {
            $this->error('--reason and --next-action are required.');

            return self::INVALID;
        }

        try {
            DB::transaction(function () use ($registry, $audit, $reason, $nextAction): void {
                $betaCode = strtoupper((string) $this->argument('beta-code'));
                $registry->review(
                    $betaCode,
                    (string) $this->argument('milestone'),
                    $this->option('blockage') !== null ? (string) $this->option('blockage') : null,
                    (int) $this->option('support-minutes'),
                    $nextAction,
                    $this->option('reviewed-at') !== null ? new \DateTimeImmutable((string) $this->option('reviewed-at')) : new \DateTimeImmutable('now'),
                );
                $audit->record(
                    action: 'beta.review.recorded',
                    result: 'Succeeded',
                    permission: 'operations.beta.manage',
                    targetType: 'BetaParticipant',
                    targetIdHash: hash('sha256', $betaCode),
                    reason: $reason,
                    metadata: ['milestone' => strtoupper((string) $this->argument('milestone'))],
                );
            });
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Beta review recorded.');

        return self::SUCCESS;
    }
}
