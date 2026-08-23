<?php

declare(strict_types=1);

namespace Atlas\Modules\Subscriptions\Domain;

enum AccessLevel: string
{
    case Full = 'Full';
    case Restricted = 'Restricted';
}
