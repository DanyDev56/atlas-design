<?php

declare(strict_types=1);

namespace Tests\Unit\Subscriptions;

use Atlas\Modules\Subscriptions\Domain\Trial;
use Atlas\Modules\Subscriptions\Domain\TrialId;
use PHPUnit\Framework\TestCase;

final class TrialTest extends TestCase
{
    public function test_trial_is_active_for_thirty_days_then_expires(): void
    {
        $startedAt = new \DateTimeImmutable('2026-08-23T10:00:00+00:00');
        $trial = Trial::start(
            new TrialId('trial-1'),
            'workspace-1',
            'plan-1',
            $startedAt,
            30,
        );

        self::assertSame('Active', $trial->effectiveStatusAt($startedAt));
        self::assertSame(30, $trial->remainingDaysAt($startedAt));
        self::assertSame('Active', $trial->effectiveStatusAt($startedAt->add(new \DateInterval('P29D'))));
        self::assertSame('Expired', $trial->effectiveStatusAt($startedAt->add(new \DateInterval('P30D'))));
        self::assertSame(0, $trial->remainingDaysAt($startedAt->add(new \DateInterval('P30D'))));
    }

    public function test_trial_rejects_non_positive_duration(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Trial::start(
            new TrialId('trial-1'),
            'workspace-1',
            'plan-1',
            new \DateTimeImmutable('2026-08-23T10:00:00+00:00'),
            0,
        );
    }
}
