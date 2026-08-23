<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Atlas\Modules\Identity\Infrastructure\Persistence\PostgresUserRepository;
use Atlas\Modules\Operations\Application\EnrollOperatorMfaHandler;
use Illuminate\Console\Command;

final class EnrollOperatorMfaCommand extends Command
{
    protected $signature = 'atlas:operator:mfa:enroll
        {email : Existing verified Atlas account with an active operator grant}
        {--reason= : Required enrollment or rotation reason}';

    protected $description = 'Enroll or rotate an operator TOTP credential outside public registration';

    public function handle(PostgresUserRepository $users, EnrollOperatorMfaHandler $handler): int
    {
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

        try {
            $enrollment = $handler->handle(
                $user->id()->value,
                $user->email(),
                (string) config('operations.backoffice.totp_issuer', 'Atlas Back-office'),
                $reason,
            );
        } catch (\DomainException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Operator MFA credential active. Existing operator sessions were revoked.');
        $this->newLine();
        $this->line('Authenticator URI (contains the secret; do not share it):');
        $this->line($enrollment['provisioning_uri']);
        $this->newLine();
        $this->line('Manual secret: '.$enrollment['secret']);
        $this->newLine();
        $this->warn('Store these one-time recovery codes offline. They will not be shown again:');
        foreach ($enrollment['recovery_codes'] as $code) {
            $this->line('  '.$code);
        }

        return self::SUCCESS;
    }
}
