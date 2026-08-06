<?php

declare(strict_types=1);

arch('domain modules do not depend on Laravel')
    ->expect('Atlas\Modules\Workspace\Domain')
    ->not->toUse('Illuminate')
    ->and('Atlas\Modules\Identity\Domain')
    ->not->toUse('Illuminate');

arch('workspace domain does not depend on identity domain')
    ->expect('Atlas\Modules\Workspace\Domain')
    ->not->toUse('Atlas\Modules\Identity');

arch('identity domain does not depend on workspace domain')
    ->expect('Atlas\Modules\Identity\Domain')
    ->not->toUse('Atlas\Modules\Workspace');
