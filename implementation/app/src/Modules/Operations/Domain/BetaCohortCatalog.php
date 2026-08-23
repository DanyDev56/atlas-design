<?php

declare(strict_types=1);

namespace Atlas\Modules\Operations\Domain;

final class BetaCohortCatalog
{
    public const STAGES = ['E0', 'E1', 'E2', 'E3', 'E4', 'E5', 'E6'];

    public const MILESTONES = ['J2', 'J7', 'J14', 'J21', 'J30'];

    public const PRICING_CELLS = ['P19', 'P24', 'P29'];

    public const DECISIONS = [
        'PAID',
        'PREORDERED',
        'TRIAL_COMMITTED',
        'DECLINED_PRICE',
        'DECLINED_VALUE',
        'DECLINED_SCOPE',
        'DECLINED_TRUST',
        'INELIGIBLE',
    ];

    public const PREFERENCES = ['Monthly', 'Annual', 'Indifferent'];
}
