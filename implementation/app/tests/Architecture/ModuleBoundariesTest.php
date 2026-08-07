<?php

declare(strict_types=1);

arch('domain modules do not depend on Laravel')
    ->expect('Atlas\Modules\Workspace\Domain')
    ->not->toUse('Illuminate')
    ->and('Atlas\Modules\Identity\Domain')
    ->not->toUse('Illuminate')
    ->and('Atlas\Modules\Crm\Domain')
    ->not->toUse('Illuminate');

arch('workspace domain does not depend on identity domain')
    ->expect('Atlas\Modules\Workspace\Domain')
    ->not->toUse('Atlas\Modules\Identity');

arch('identity domain does not depend on workspace domain')
    ->expect('Atlas\Modules\Identity\Domain')
    ->not->toUse('Atlas\Modules\Workspace');

arch('crm domain does not depend on identity or workspace domains')
    ->expect('Atlas\Modules\Crm\Domain')
    ->not->toUse('Atlas\Modules\Identity')
    ->and('Atlas\Modules\Crm\Domain')
    ->not->toUse('Atlas\Modules\Workspace');

arch('billing domain does not depend on Laravel')
    ->expect('Atlas\Modules\Billing\Domain')
    ->not->toUse('Illuminate');

arch('billing domain does not depend on identity or workspace domains')
    ->expect('Atlas\Modules\Billing\Domain')
    ->not->toUse('Atlas\Modules\Identity')
    ->and('Atlas\Modules\Billing\Domain')
    ->not->toUse('Atlas\Modules\Workspace');

arch('analytics domain does not depend on Laravel')
    ->expect('Atlas\Modules\Analytics\Domain')
    ->not->toUse('Illuminate');

arch('analytics domain does not depend on identity or workspace domains')
    ->expect('Atlas\Modules\Analytics\Domain')
    ->not->toUse('Atlas\Modules\Identity')
    ->and('Atlas\Modules\Analytics\Domain')
    ->not->toUse('Atlas\Modules\Workspace');

arch('business health domain does not depend on Laravel')
    ->expect('Atlas\Modules\BusinessHealth\Domain')
    ->not->toUse('Illuminate');

arch('business health domain does not depend on identity or workspace domains')
    ->expect('Atlas\Modules\BusinessHealth\Domain')
    ->not->toUse('Atlas\Modules\Identity')
    ->and('Atlas\Modules\BusinessHealth\Domain')
    ->not->toUse('Atlas\Modules\Workspace');

arch('advisor domain does not depend on Laravel')
    ->expect('Atlas\Modules\Advisor\Domain')
    ->not->toUse('Illuminate');

arch('advisor domain does not depend on identity or workspace domains')
    ->expect('Atlas\Modules\Advisor\Domain')
    ->not->toUse('Atlas\Modules\Identity')
    ->and('Atlas\Modules\Advisor\Domain')
    ->not->toUse('Atlas\Modules\Workspace');
