<?php

declare(strict_types=1);

namespace Atlas\Platform\Security;

final class StepUpRequiredException extends \DomainException
{
    public function __construct()
    {
        parent::__construct('Step-up authentication required.');
    }
}
