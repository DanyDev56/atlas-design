<?php

declare(strict_types=1);

namespace Atlas\Modules\Subscriptions\Domain;

final class SubscriptionReconciliationException extends \RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly int $httpStatus,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
