<?php

declare(strict_types=1);

namespace Atlas\Modules\Subscriptions\Contracts;

final class SubscriptionLimitExceededException extends \DomainException
{
    public function __construct(
        public readonly string $limitName,
        public readonly int $limit,
        public readonly int $current,
    ) {
        parent::__construct('Workspace subscription limit reached.');
    }
}
