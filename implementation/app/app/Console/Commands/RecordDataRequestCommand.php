<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Atlas\Modules\Operations\Infrastructure\Persistence\PostgresOperatorAuditRepository;
use Atlas\Modules\Operations\Infrastructure\Persistence\PostgresSupportComplianceRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class RecordDataRequestCommand extends Command
{
    protected $signature = 'atlas:compliance:request
        {workspace-id}
        {requester-email}
        {type : Access, Rectification, Erasure, Restriction, Objection or Portability}
        {summary-code : Structured code without personal data}
        {--received-at= : ISO-8601 reception date}
        {--reason= : Required administrative reason}';

    protected $description = 'Record a verified data request without starting export or deletion';

    public function handle(PostgresSupportComplianceRegistry $registry, PostgresOperatorAuditRepository $audit): int
    {
        $reason = trim((string) $this->option('reason'));
        if ($reason === '') {
            $this->error('--reason is required.');

            return self::INVALID;
        }

        try {
            $result = DB::transaction(function () use ($registry, $audit, $reason): array {
                $result = $registry->recordDataRequest(
                    (string) $this->argument('workspace-id'),
                    (string) $this->argument('requester-email'),
                    (string) $this->argument('type'),
                    (string) $this->argument('summary-code'),
                    $this->option('received-at') !== null
                        ? new \DateTimeImmutable((string) $this->option('received-at'))
                        : new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
                );
                $audit->record(
                    action: 'compliance.data-request.recorded',
                    result: 'Succeeded',
                    permission: 'operations.compliance.manage',
                    targetType: 'DataRequest',
                    targetIdHash: hash('sha256', $result['reference']),
                    reason: $reason,
                    metadata: ['request_type' => (string) $this->argument('type')],
                );

                return $result;
            });
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Data request '.$result['reference'].' recorded and linked to '.$result['support_reference'].'.');
        $this->warn('No export, delivery, restriction or deletion has been started.');

        return self::SUCCESS;
    }
}
