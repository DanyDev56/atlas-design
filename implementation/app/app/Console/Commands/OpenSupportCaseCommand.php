<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Atlas\Modules\Operations\Infrastructure\Persistence\PostgresOperatorAuditRepository;
use Atlas\Modules\Operations\Infrastructure\Persistence\PostgresSupportComplianceRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class OpenSupportCaseCommand extends Command
{
    protected $signature = 'atlas:support:open
        {workspace-id}
        {requester-email}
        {category : Access, Security, Billing, Product or Other}
        {severity : P0, P1, P2 or P3}
        {summary-code : Structured code without personal data}
        {--opened-at= : ISO-8601 reception date}
        {--reason= : Required administrative reason}';

    protected $description = 'Open a minimized support case for a verified Workspace member';

    public function handle(PostgresSupportComplianceRegistry $registry, PostgresOperatorAuditRepository $audit): int
    {
        $reason = trim((string) $this->option('reason'));
        if ($reason === '') {
            $this->error('--reason is required.');

            return self::INVALID;
        }

        try {
            $result = DB::transaction(function () use ($registry, $audit, $reason): array {
                $result = $registry->openCase(
                    (string) $this->argument('workspace-id'),
                    (string) $this->argument('requester-email'),
                    (string) $this->argument('category'),
                    (string) $this->argument('severity'),
                    (string) $this->argument('summary-code'),
                    $this->option('opened-at') !== null
                        ? new \DateTimeImmutable((string) $this->option('opened-at'))
                        : new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
                );
                $audit->record(
                    action: 'support.case.opened',
                    result: 'Succeeded',
                    permission: 'operations.support.manage',
                    targetType: 'SupportCase',
                    targetIdHash: hash('sha256', $result['reference']),
                    reason: $reason,
                    metadata: [
                        'severity' => strtoupper((string) $this->argument('severity')),
                        'category' => (string) $this->argument('category'),
                    ],
                );

                return $result;
            });
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Support case '.$result['reference'].' opened; response target '.$result['response_due_at'].'.');

        return self::SUCCESS;
    }
}
