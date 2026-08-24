<?php

declare(strict_types=1);

namespace Atlas\Modules\Operations\Application;

final class DataExportActionException extends \RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly int $httpStatus,
    ) {
        parent::__construct($message);
    }
}
