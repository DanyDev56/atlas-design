<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Atlas\Modules\Operations\Infrastructure\Persistence\PostgresOperationsRuntimeRecorder;
use Illuminate\Console\Command;

final class RecordOperationsHeartbeatCommand extends Command
{
    protected $signature = 'atlas:operations:heartbeat {role : api, worker or scheduler}';

    protected $description = 'Record a durable runtime role heartbeat';

    public function handle(PostgresOperationsRuntimeRecorder $recorder): int
    {
        try {
            $recorder->heartbeat((string) $this->argument('role'));
        } catch (\DomainException $exception) {
            $this->error($exception->getMessage());

            return self::INVALID;
        }

        return self::SUCCESS;
    }
}
