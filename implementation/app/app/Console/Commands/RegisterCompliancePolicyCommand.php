<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Atlas\Modules\Operations\Infrastructure\Persistence\PostgresOperatorAuditRepository;
use Atlas\Modules\Operations\Infrastructure\Persistence\PostgresSupportComplianceRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class RegisterCompliancePolicyCommand extends Command
{
    protected $signature = 'atlas:compliance:policy
        {kind : BetaTerms or PrivacyNotice}
        {version : Immutable policy version}
        {lifecycle : Draft or Published}
        {sha256 : SHA-256 of the reviewed document}
        {--effective-at= : Required ISO-8601 date for Published}
        {--approval-ref= : Required opaque Legal approval reference for Published}
        {--reason= : Required administrative reason}';

    protected $description = 'Register an immutable policy fingerprint without storing its content';

    public function handle(PostgresSupportComplianceRegistry $registry, PostgresOperatorAuditRepository $audit): int
    {
        $reason = trim((string) $this->option('reason'));
        if ($reason === '') {
            $this->error('--reason is required.');

            return self::INVALID;
        }

        try {
            $result = DB::transaction(function () use ($registry, $audit, $reason): array {
                $result = $registry->registerPolicy(
                    (string) $this->argument('kind'),
                    (string) $this->argument('version'),
                    (string) $this->argument('lifecycle'),
                    strtolower((string) $this->argument('sha256')),
                    $this->option('approval-ref') !== null ? (string) $this->option('approval-ref') : null,
                    $this->option('effective-at') !== null ? new \DateTimeImmutable((string) $this->option('effective-at')) : null,
                );
                $audit->record(
                    action: 'compliance.policy.recorded',
                    result: 'Succeeded',
                    permission: 'operations.compliance.manage',
                    targetType: 'PolicyVersion',
                    targetIdHash: hash('sha256', $result['id']),
                    reason: $reason,
                    metadata: ['kind' => (string) $this->argument('kind'), 'lifecycle' => $result['lifecycle']],
                );

                return $result;
            });
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info($result['lifecycle'].' policy '.$result['version'].' recorded.');

        return self::SUCCESS;
    }
}
