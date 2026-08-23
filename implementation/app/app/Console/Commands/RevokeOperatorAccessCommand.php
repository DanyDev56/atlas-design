<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Atlas\Modules\Identity\Infrastructure\Persistence\PostgresUserRepository;
use Atlas\Modules\Operations\Infrastructure\Persistence\PostgresOperatorAuditRepository;
use Atlas\Modules\Operations\Infrastructure\Persistence\PostgresOperatorGrantRepository;
use Atlas\Modules\Operations\Infrastructure\Persistence\PostgresOperatorSessionRepository;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class RevokeOperatorAccessCommand extends Command
{
    protected $signature = 'atlas:operator:revoke
        {email : Existing Atlas account}
        {--reason= : Required revocation reason}';

    protected $description = 'Revoke an operator grant and all active operator sessions';

    public function handle(
        PostgresUserRepository $users,
        PostgresOperatorGrantRepository $grants,
        PostgresOperatorSessionRepository $sessions,
        PostgresOperatorAuditRepository $audit,
    ): int {
        $reason = trim((string) $this->option('reason'));
        if ($reason === '') {
            $this->error('--reason is required.');

            return self::INVALID;
        }

        $user = $users->findByEmail((string) $this->argument('email'));
        if ($user === null) {
            $this->error('Atlas account not found.');

            return self::FAILURE;
        }

        $revoked = DB::transaction(function () use ($grants, $sessions, $audit, $user, $reason): bool {
            $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
            $revoked = $grants->revokeForUser($user->id()->value, $now);
            $sessionCount = $sessions->revokeAllForUser($user->id()->value, $now);
            $audit->record(
                action: 'operator.grant.revoked',
                result: $revoked ? 'Succeeded' : 'NoChange',
                targetType: 'OperatorGrant',
                targetIdHash: hash('sha256', $user->id()->value),
                reason: $reason,
                metadata: ['revoked_session_count' => $sessionCount],
                occurredAt: $now,
            );

            return $revoked;
        });

        $this->info($revoked ? 'Operator access revoked.' : 'Operator access was already inactive.');

        return self::SUCCESS;
    }
}
