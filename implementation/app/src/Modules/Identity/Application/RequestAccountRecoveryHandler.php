<?php

declare(strict_types=1);

namespace Atlas\Modules\Identity\Application;

use Atlas\Modules\Identity\Domain\User;
use Atlas\Modules\Identity\Infrastructure\Persistence\PostgresAccountRecoveryRepository;
use Atlas\Modules\Identity\Infrastructure\Persistence\PostgresUserRepository;
use Atlas\Modules\Identity\Infrastructure\PostgresIdempotencyStore;
use Illuminate\Support\Facades\DB;

final class RequestAccountRecoveryHandler
{
    public function __construct(
        private readonly PostgresUserRepository $users,
        private readonly PostgresAccountRecoveryRepository $tokens,
        private readonly PostgresIdempotencyStore $idempotency,
    ) {}

    /** @return array{status: string, recovery_token?: string} */
    public function handle(string $email, string $requestId): array
    {
        $normalized = strtolower(trim($email));
        $scope = 'identity.request_account_recovery';
        $fingerprint = hash('sha256', json_encode([$normalized], JSON_THROW_ON_ERROR));
        $cached = $this->idempotency->find($scope, $requestId);

        if ($cached !== null) {
            if ($cached['fingerprint'] !== $fingerprint) {
                throw new \DomainException('Idempotency conflict.');
            }

            return ['status' => 'accepted'];
        }

        return DB::transaction(function () use ($normalized, $requestId, $scope, $fingerprint): array {
            $user = $this->users->findByEmail($normalized);
            $response = ['status' => 'accepted'];

            if ($user !== null && $user->status() === User::STATUS_ACTIVE) {
                $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
                $plainToken = PostgresAccountRecoveryRepository::generatePlainToken();
                $this->tokens->createToken(
                    $user->id()->value,
                    PostgresAccountRecoveryRepository::hashToken($plainToken),
                    $now->modify('+1 hour'),
                );
                $response['recovery_token'] = $plainToken;
            }

            $this->idempotency->store($scope, $requestId, $fingerprint, ['status' => 'accepted']);

            return $response;
        });
    }
}
