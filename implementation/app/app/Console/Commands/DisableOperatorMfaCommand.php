<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Atlas\Modules\Identity\Infrastructure\Persistence\PostgresUserRepository;
use Atlas\Modules\Operations\Application\DisableOperatorMfaHandler;
use Illuminate\Console\Command;

final class DisableOperatorMfaCommand extends Command
{
    protected $signature = 'atlas:operator:mfa:disable
        {email : Existing Atlas account}
        {--reason= : Required disable reason}';

    protected $description = 'Disable an operator MFA credential and revoke its operator sessions';

    public function handle(PostgresUserRepository $users, DisableOperatorMfaHandler $handler): int
    {
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

        if (! $handler->handle($user->id()->value, $reason)) {
            $this->error('No active operator MFA credential was found.');

            return self::FAILURE;
        }

        $this->info('Operator MFA disabled and active operator sessions revoked.');

        return self::SUCCESS;
    }
}
