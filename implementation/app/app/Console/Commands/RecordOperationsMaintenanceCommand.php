<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Atlas\Modules\Operations\Infrastructure\Persistence\PostgresOperationsRuntimeRecorder;
use Illuminate\Console\Command;

final class RecordOperationsMaintenanceCommand extends Command
{
    protected $signature = 'atlas:operations:record-maintenance
        {kind : Backup or RestoreCanary}
        {status : Succeeded or Failed}
        {reference : Opaque run reference}
        {--started-at= : ISO-8601 start timestamp}
        {--size-bytes= : Optional backup size}';

    protected $description = 'Record a sanitized backup or restore-canary result';

    public function handle(PostgresOperationsRuntimeRecorder $recorder): int
    {
        try {
            $startedAt = new \DateTimeImmutable((string) ($this->option('started-at') ?: 'now'));
            $size = $this->option('size-bytes');
            $recorder->maintenance(
                (string) $this->argument('kind'),
                (string) $this->argument('status'),
                (string) $this->argument('reference'),
                $startedAt,
                $size !== null ? (int) $size : null,
            );
        } catch (\Throwable $exception) {
            $this->error($exception instanceof \DomainException ? $exception->getMessage() : 'Maintenance result could not be recorded.');

            return self::INVALID;
        }

        return self::SUCCESS;
    }
}
