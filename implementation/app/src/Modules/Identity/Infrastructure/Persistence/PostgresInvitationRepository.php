<?php

declare(strict_types=1);

namespace Atlas\Modules\Identity\Infrastructure\Persistence;

use Atlas\Platform\Support\UuidGenerator;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

final class PostgresInvitationRepository
{
    /** @return array{id: string, token: string} */
    public function create(
        string $workspaceId,
        string $recipientEmail,
        string $roleId,
        string $invitedBy,
        \DateTimeImmutable $expiresAt,
    ): array {
        $id = UuidGenerator::generate();
        $token = bin2hex(random_bytes(32));
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        DB::table('identity.invitations')->insert([
            'id' => $id,
            'workspace_id' => $workspaceId,
            'recipient_email' => self::normalizeEmail($recipientEmail),
            'recipient_email_fingerprint' => self::emailFingerprint($recipientEmail),
            'role_id' => $roleId,
            'invited_by' => $invitedBy,
            'status' => 'Pending',
            'delivery_status' => 'Requested',
            'token_hash' => self::hashToken($token),
            'delivery_secret' => Crypt::encryptString($token),
            'expires_at' => $expiresAt->format('Y-m-d H:i:sP'),
            'created_at' => $now->format('Y-m-d H:i:sP'),
            'updated_at' => $now->format('Y-m-d H:i:sP'),
            'version' => 1,
        ]);

        return ['id' => $id, 'token' => $token];
    }

    /** @return array{email: string, token: string, workspace_name: string, expires_at: string}|null */
    public function findDeliveryContext(string $invitationId, string $workspaceId): ?array
    {
        $row = DB::table('identity.invitations as invitation')
            ->join('workspace.workspaces as workspace', 'workspace.id', '=', 'invitation.workspace_id')
            ->where('invitation.id', $invitationId)
            ->where('invitation.workspace_id', $workspaceId)
            ->where('invitation.status', 'Pending')
            ->where('invitation.expires_at', '>', now())
            ->first([
                'invitation.recipient_email',
                'invitation.delivery_secret',
                'invitation.expires_at',
                'workspace.name as workspace_name',
            ]);

        if ($row === null || $row->delivery_secret === null) {
            return null;
        }

        return [
            'email' => (string) $row->recipient_email,
            'token' => Crypt::decryptString((string) $row->delivery_secret),
            'workspace_name' => (string) $row->workspace_name,
            'expires_at' => (string) $row->expires_at,
        ];
    }

    public function markDeliveryAccepted(string $invitationId): void
    {
        DB::table('identity.invitations')
            ->where('id', $invitationId)
            ->where('delivery_status', 'Requested')
            ->update([
                'delivery_status' => 'Accepted',
                'updated_at' => now()->toIso8601String(),
            ]);
    }

    public function discardDeliverySecret(string $invitationId): void
    {
        DB::table('identity.invitations')
            ->where('id', $invitationId)
            ->update(['delivery_secret' => null]);
    }

    /** @return array<string, mixed>|null */
    public function findPending(string $workspaceId, string $recipientEmail): ?array
    {
        $row = DB::table('identity.invitations')
            ->where('workspace_id', $workspaceId)
            ->where('recipient_email', self::normalizeEmail($recipientEmail))
            ->where('status', 'Pending')
            ->first();

        return $row !== null ? (array) $row : null;
    }

    public function countPendingActive(string $workspaceId, \DateTimeImmutable $now): int
    {
        return (int) DB::table('identity.invitations')
            ->where('workspace_id', $workspaceId)
            ->where('status', 'Pending')
            ->where('expires_at', '>', $now->format('Y-m-d H:i:sP'))
            ->count();
    }

    /** @return array<string, mixed>|null */
    public function findByIdForUpdate(string $invitationId): ?array
    {
        $row = DB::table('identity.invitations')
            ->where('id', $invitationId)
            ->lockForUpdate()
            ->first();

        return $row !== null ? (array) $row : null;
    }

    /** @return list<array<string, mixed>> */
    public function listForWorkspace(string $workspaceId): array
    {
        return DB::table('identity.invitations as invitation')
            ->join('identity.roles as role', 'role.id', '=', 'invitation.role_id')
            ->where('invitation.workspace_id', $workspaceId)
            ->orderByDesc('invitation.created_at')
            ->get([
                'invitation.id',
                'invitation.recipient_email',
                'invitation.status',
                'invitation.delivery_status',
                'invitation.expires_at',
                'invitation.created_at',
                'role.name as role',
            ])
            ->map(fn ($row): array => [
                'invitation_id' => (string) $row->id,
                'recipient_email' => (string) $row->recipient_email,
                'role' => (string) $row->role,
                'status' => (string) $row->status,
                'delivery_status' => (string) $row->delivery_status,
                'expires_at' => (string) $row->expires_at,
                'created_at' => (string) $row->created_at,
            ])
            ->all();
    }

    public function accept(
        string $invitationId,
        string $acceptedBy,
        string $membershipId,
        \DateTimeImmutable $acceptedAt,
    ): void {
        DB::table('identity.invitations')
            ->where('id', $invitationId)
            ->where('status', 'Pending')
            ->update([
                'status' => 'Accepted',
                'token_hash' => hash('sha256', 'consumed:'.$invitationId.':'.$acceptedAt->format('U.u')),
                'delivery_secret' => null,
                'accepted_by' => $acceptedBy,
                'membership_id' => $membershipId,
                'accepted_at' => $acceptedAt->format('Y-m-d H:i:sP'),
                'updated_at' => $acceptedAt->format('Y-m-d H:i:sP'),
                'version' => DB::raw('version + 1'),
            ]);
    }

    public function revoke(
        string $invitationId,
        \DateTimeImmutable $revokedAt,
    ): void {
        DB::table('identity.invitations')
            ->where('id', $invitationId)
            ->where('status', 'Pending')
            ->update([
                'status' => 'Revoked',
                'token_hash' => hash('sha256', 'revoked:'.$invitationId.':'.$revokedAt->format('U.u')),
                'delivery_secret' => null,
                'updated_at' => $revokedAt->format('Y-m-d H:i:sP'),
                'version' => DB::raw('version + 1'),
            ]);
    }

    public static function normalizeEmail(string $email): string
    {
        return strtolower(trim($email));
    }

    public static function emailFingerprint(string $email): string
    {
        return hash('sha256', self::normalizeEmail($email));
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }
}
