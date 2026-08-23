<?php

declare(strict_types=1);

namespace Tests\Unit\Operations;

use Atlas\Modules\Operations\Application\BetaCohortQueryHandler;
use Atlas\Modules\Operations\Contracts\BetaCohortSource;
use PHPUnit\Framework\TestCase;

final class BetaCohortQueryHandlerTest extends TestCase
{
    public function test_it_keeps_absolute_denominators_and_computes_the_observed_median(): void
    {
        $overview = (new BetaCohortQueryHandler($this->source([
            $this->participant('BETA-001', 'P19', ['E0' => '2026-08-01T08:00:00+00:00', 'E1' => '2026-08-01T10:00:00+00:00']),
            $this->participant('BETA-002', 'P24', ['E0' => '2026-08-01T08:00:00+00:00', 'E1' => '2026-08-01T14:00:00+00:00']),
            $this->participant('BETA-003', 'P29', ['E0' => '2026-08-01T08:00:00+00:00']),
        ]), 7))->overview();

        self::assertSame(3, $overview['participant_count']);
        self::assertSame(2, $overview['funnel'][1]['reached']);
        self::assertSame(3, $overview['funnel'][1]['denominator']);
        self::assertSame(67, $overview['funnel'][1]['rate_percent']);
        self::assertSame(4.0, $overview['funnel'][1]['median_duration_hours']);
        self::assertSame(0, $overview['funnel'][2]['reached']);
        self::assertSame(2, $overview['funnel'][2]['denominator']);
    }

    public function test_it_does_not_invent_a_rate_when_the_cohort_is_empty(): void
    {
        $overview = (new BetaCohortQueryHandler($this->source([]), 7))->overview();

        self::assertSame('NoData', $overview['status']);
        self::assertSame(0, $overview['participant_count']);
        self::assertNull($overview['funnel'][0]['rate_percent']);
        self::assertNull($overview['funnel'][6]['median_duration_hours']);
    }

    /** @param list<array<string, mixed>> $participants */
    private function source(array $participants): BetaCohortSource
    {
        return new class($participants) implements BetaCohortSource
        {
            public function __construct(private readonly array $items) {}

            public function participants(\DateTimeImmutable $now, int $blockedAfterDays): array
            {
                return $this->items;
            }

            public function diagnostic(string $betaCode): ?array
            {
                return null;
            }
        };
    }

    /** @param array<string, string> $reached */
    private function participant(string $betaCode, string $cell, array $reached): array
    {
        $dates = array_fill_keys(['E0', 'E1', 'E2', 'E3', 'E4', 'E5', 'E6'], null);

        return [
            'beta_code' => $betaCode,
            'status' => 'Active',
            'pricing_cell' => $cell,
            'blocked' => false,
            'pricing_decision' => null,
            'stage_dates' => array_replace($dates, $reached),
        ];
    }
}
