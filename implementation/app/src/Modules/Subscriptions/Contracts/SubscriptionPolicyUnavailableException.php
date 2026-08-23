<?php

declare(strict_types=1);

namespace Atlas\Modules\Subscriptions\Contracts;

final class SubscriptionPolicyUnavailableException extends \DomainException
{
    public function __construct()
    {
        parent::__construct('Subscription access policy is not configured.');
    }
}
