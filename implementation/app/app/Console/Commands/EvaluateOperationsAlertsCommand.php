<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Atlas\Modules\Operations\Application\EvaluateOperationsAlertsHandler;
use Illuminate\Console\Command;

final class EvaluateOperationsAlertsCommand extends Command
{
    protected $signature = 'atlas:operations:evaluate-alerts';

    protected $description = 'Evaluate durable operational alert states and notify state changes';

    public function handle(EvaluateOperationsAlertsHandler $handler): int
    {
        $result = $handler->handle();
        $this->info(sprintf(
            '%d alert(s) evaluated, %d firing, %d notification(s) sent.',
            $result['evaluated'],
            $result['firing'],
            $result['notifications_sent'],
        ));

        return self::SUCCESS;
    }
}
