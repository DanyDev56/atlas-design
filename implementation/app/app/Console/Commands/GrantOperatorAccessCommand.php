<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Atlas\Modules\Identity\Infrastructure\Persistence\PostgresUserRepository;
use Atlas\Modules\Operations\Domain\OperatorPermissionCatalog;
use Atlas\Modules\Operations\Infrastructure\Persistence\PostgresOperatorAuditRepository;
use Atlas\Modules\Operations\Infrastructure\Persistence\PostgresOperatorGrantRepository;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class GrantOperatorAccessCommand extends Command
{
    protected $signature = 'atlas:operator:grant
        {email : Existing verified Atlas account}
        {--permissions= : Comma-separated operator permissions}
        {--expires= : ISO-8601 expiration; omit for no automatic expiration}
        {--reason= : Required provisioning reason}';

    protected $description = 'Provision or replace an operator grant outside public registration';

    public function handle(
        PostgresUserRepository $users,
        PostgresOperatorGrantRepository $grants,
        PostgresOperatorAuditRepository $audit,
    ): int {
        $reason = trim((string) $this->option('reason'));
        if ($reason === '') {
            $this->error('--reason is required.');

            return self::INVALID;
        }

        $user = $users->findByEmail((string) $this->argument('email'));
        if ($user === null || ! $user->canAuthenticate()) {
            $this->error('A verified active Atlas account is required.');

            return self::FAILURE;
        }

        $rawPermissions = trim((string) $this->option('permissions'));
        $permissions = $rawPermissions === ''
            ? OperatorPermissionCatalog::initialReadOnly()
            : array_values(array_filter(array_map('trim', explode(',', $rawPermissions))));

        try {
            $permissions = OperatorPermissionCatalog::normalize($permissions);
            $expiresAt = $this->parseExpiration((string) $this->option('expires'));
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::INVALID;
        }

        $grant = DB::transaction(function () use ($grants, $audit, $user, $permissions, $expiresAt, $reason): array {
            $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
            $grant = $grants->provision($user->id()->value, $permissions, $expiresAt, $now);
            $audit->record(
                action: 'operator.grant.provisioned',
                result: 'Succeeded',
                targetType: 'OperatorGrant',
                targetIdHash: hash('sha256', (string) $grant['id']),
                reason: $reason,
                metadata: [
                    'permission_count' => count($permissions),
                    'grant_version' => (int) $grant['version'],
                ],
                occurredAt: $now,
            );

            return $grant;
        });

        $this->info('Operator grant active.');
        $this->line('User: '.$user->id()->value);
        $this->line('Permissions: '.implode(', ', $grant['permissions']));
        $this->line('Expires: '.($grant['expires_at'] ?? 'never'));
        $this->comment('Back-office login remains unavailable unless its server-side feature gates allow it.');

        return self::SUCCESS;
    }

    private function parseExpiration(string $value): ?\DateTimeImmutable
    {
        if (trim($value) === '') {
            return null;
        }

        $expiresAt = new \DateTimeImmutable($value);
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        if ($expiresAt <= $now) {
            throw new \DomainException('Operator grant expiration must be in the future.');
        }

        return $expiresAt->setTimezone(new \DateTimeZone('UTC'));
    }
}
