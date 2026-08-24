<?php

declare(strict_types=1);

namespace Atlas\Modules\Operations\Infrastructure\Persistence;

use Atlas\Platform\Support\UuidGenerator;
use Illuminate\Support\Facades\DB;

final class PostgresOperationsRuntimeRecorder
{
    private const array ROLES = ['api', 'worker', 'scheduler'];

    private const array KINDS = ['Backup', 'RestoreCanary'];

    private const array STATUSES = ['Succeeded', 'Failed'];

    /** @var array<string, int> */
    private array $lastHeartbeatAt = [];

    public function heartbeat(string $role): void
    {
        if (! in_array($role, self::ROLES, true)) {
            throw new \DomainException('Unsupported runtime role.');
        }

        $timestamp = time();
        if (($this->lastHeartbeatAt[$role] ?? 0) > $timestamp - 30) {
            return;
        }

        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        DB::table('operations.runtime_heartbeats')->upsert([[
            'role' => $role,
            'recorded_at' => $now->format('Y-m-d H:i:sP'),
            'updated_at' => $now->format('Y-m-d H:i:sP'),
        ]], ['role'], ['recorded_at', 'updated_at']);
        $this->lastHeartbeatAt[$role] = $timestamp;
    }

    public function maintenance(
        string $kind,
        string $status,
        string $reference,
        \DateTimeImmutable $startedAt,
        ?int $sizeBytes = null,
    ): void {
        if (! in_array($kind, self::KINDS, true) || ! in_array($status, self::STATUSES, true)) {
            throw new \DomainException('Unsupported maintenance result.');
        }
        if (! preg_match('/^[a-zA-Z0-9._:-]{1,96}$/', $reference)) {
            throw new \DomainException('Invalid maintenance reference.');
        }
        if ($sizeBytes !== null && $sizeBytes < 0) {
            throw new \DomainException('Invalid maintenance size.');
        }

        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        DB::table('operations.maintenance_runs')->insertOrIgnore([
            'id' => UuidGenerator::generate(),
            'kind' => $kind,
            'run_reference' => $reference,
            'status' => $status,
            'size_bytes' => $sizeBytes,
            'started_at' => $startedAt->format('Y-m-d H:i:sP'),
            'completed_at' => $now->format('Y-m-d H:i:sP'),
            'recorded_at' => $now->format('Y-m-d H:i:sP'),
        ]);
    }
}
