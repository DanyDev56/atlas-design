<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Atlas\Modules\Operations\Infrastructure\Persistence\PostgresBetaCohortRegistry;
use Atlas\Modules\Operations\Infrastructure\Persistence\PostgresOperatorAuditRepository;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class EnrollBetaParticipantCommand extends Command
{
    protected $signature = 'atlas:beta:enroll
        {beta-code : Pseudonym such as BETA-001}
        {user-id : Exact verified user UUID}
        {workspace-id : Exact active Workspace UUID}
        {--cell= : P19, P24 or P29; balanced automatically when omitted}
        {--packaging=Atlas-Solo@1 : Presented packaging version}
        {--segment=primary : Research segment code}
        {--channel=other : Recruitment channel code}
        {--invited-at= : ISO-8601 invitation date}
        {--reason= : Required administrative reason}';

    protected $description = 'Enroll one pseudonymized participant in the closed beta registry';

    public function handle(PostgresBetaCohortRegistry $registry, PostgresOperatorAuditRepository $audit): int
    {
        $reason = trim((string) $this->option('reason'));
        if ($reason === '') {
            $this->error('--reason is required.');

            return self::INVALID;
        }

        try {
            $result = DB::transaction(function () use ($registry, $audit, $reason): array {
                $result = $registry->enroll(
                    (string) $this->argument('beta-code'),
                    (string) $this->argument('user-id'),
                    (string) $this->argument('workspace-id'),
                    $this->option('cell') !== null ? (string) $this->option('cell') : null,
                    (string) $this->option('packaging'),
                    (string) $this->option('segment'),
                    (string) $this->option('channel'),
                    $this->option('invited-at') !== null ? new \DateTimeImmutable((string) $this->option('invited-at')) : new \DateTimeImmutable('now'),
                );
                $audit->record(
                    action: 'beta.participant.enrolled',
                    result: 'Succeeded',
                    permission: 'operations.beta.manage',
                    targetType: 'BetaParticipant',
                    targetIdHash: hash('sha256', $result['beta_code']),
                    reason: $reason,
                    metadata: ['pricing_cell' => $result['pricing_cell']],
                );

                return $result;
            });
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info($result['beta_code'].' enrolled in '.$result['pricing_cell'].'.');

        return self::SUCCESS;
    }
}
