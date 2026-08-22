<?php

declare(strict_types=1);

namespace Atlas\Modules\Identity\Infrastructure\Persistence;

use Atlas\Modules\Identity\Domain\User;
use Atlas\Modules\Identity\Domain\UserId;
use Illuminate\Support\Facades\DB;

final class PostgresUserRepository
{
    public function insert(User $user, string $passwordHash): void
    {
        DB::table('identity.users')->insert([
            'id' => $user->id()->value,
            'email' => $user->email(),
            'display_name' => $user->displayName(),
            'password_hash' => $passwordHash,
            'status' => $user->status(),
            'email_verification_status' => $user->emailVerificationStatus(),
            'security_version' => $user->securityVersion(),
            'version' => $user->version(),
            'created_at' => $user->createdAt()->format('Y-m-d H:i:sP'),
            'updated_at' => $user->updatedAt()->format('Y-m-d H:i:sP'),
        ]);
    }

    public function update(User $user): void
    {
        DB::table('identity.users')
            ->where('id', $user->id()->value)
            ->update([
                'status' => $user->status(),
                'email_verification_status' => $user->emailVerificationStatus(),
                'password_hash' => $user->passwordHash(),
                'security_version' => $user->securityVersion(),
                'version' => $user->version(),
                'updated_at' => $user->updatedAt()->format('Y-m-d H:i:sP'),
            ]);
    }

    public function findById(UserId $id): ?User
    {
        $row = DB::table('identity.users')->where('id', $id->value)->first();

        return $row !== null ? User::reconstitute((array) $row) : null;
    }

    public function findByEmail(string $email): ?User
    {
        $row = DB::table('identity.users')
            ->where('email', strtolower(trim($email)))
            ->first();

        return $row !== null ? User::reconstitute((array) $row) : null;
    }
}
