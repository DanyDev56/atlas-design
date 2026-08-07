<?php

declare(strict_types=1);

namespace Atlas\Modules\Billing\Infrastructure\Persistence;

use Atlas\Platform\Support\UuidGenerator;
use Illuminate\Support\Facades\DB;

final class PostgresPublicDocumentProofRepository
{
    public function create(
        string $workspaceId,
        string $documentType,
        string $documentId,
        string $tokenHash,
        array $capabilities,
        \DateTimeImmutable $expiresAt,
    ): string {
        $id = UuidGenerator::generate();

        DB::table('billing.public_document_proofs')->insert([
            'id' => $id,
            'workspace_id' => $workspaceId,
            'document_type' => $documentType,
            'document_id' => $documentId,
            'token_hash' => $tokenHash,
            'capabilities' => json_encode($capabilities, JSON_THROW_ON_ERROR),
            'expires_at' => $expiresAt->format('Y-m-d H:i:sP'),
            'created_at' => now()->toIso8601String(),
        ]);

        return $id;
    }

    /** @return array<string, mixed>|null */
    public function findValidByToken(string $documentType, string $plainToken): ?array
    {
        $row = DB::table('billing.public_document_proofs')
            ->where('document_type', $documentType)
            ->where('token_hash', hash('sha256', $plainToken))
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->first();

        return $row !== null ? (array) $row : null;
    }

    public function consume(string $proofId): void
    {
        DB::table('billing.public_document_proofs')
            ->where('id', $proofId)
            ->update(['consumed_at' => now()->toIso8601String()]);
    }

    public static function generatePlainToken(): string
    {
        return bin2hex(random_bytes(32));
    }
}
