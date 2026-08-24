<?php

declare(strict_types=1);

namespace Tests\Unit\Operations;

use Atlas\Modules\Operations\Application\OperationsOverviewQueryHandler;
use Atlas\Modules\Operations\Contracts\BetaCohortSource;
use Atlas\Modules\Operations\Contracts\OperationsOverviewSource;
use PHPUnit\Framework\TestCase;

final class OperationsOverviewQueryHandlerTest extends TestCase
{
    public function test_it_preserves_real_zeroes_and_marks_missing_instrumentation_explicitly(): void
    {
        $overview = (new OperationsOverviewQueryHandler($this->source(), $this->betaSource(), true, false, 7))->overview();

        self::assertTrue($overview['read_only']);
        self::assertFalse($overview['actions_enabled']);
        self::assertSame('Available', $overview['cards'][0]['status']);
        self::assertSame(0, $overview['cards'][0]['values'][0]['value']);
        self::assertSame('Available', $overview['cards'][2]['status']);
        self::assertSame(0, $overview['cards'][2]['values'][2]['value']);
        self::assertSame('NotCollected', $overview['cards'][3]['status']);
        self::assertSame([], $overview['cards'][3]['values']);
        self::assertSame('NoData', $overview['cards'][6]['status']);
        self::assertSame([], $overview['cards'][6]['values']);
    }

    public function test_it_marks_a_failed_source_unavailable_instead_of_returning_zero(): void
    {
        $source = $this->source();
        $source->failOutbox = true;

        $overview = (new OperationsOverviewQueryHandler($source, $this->betaSource(), true, false, 7))->overview();

        self::assertSame('Unavailable', $overview['cards'][0]['status']);
        self::assertSame([], $overview['cards'][0]['values']);
        self::assertSame('Critical', $overview['cards'][0]['tone']);
    }

    private function source(): object
    {
        return new class implements OperationsOverviewSource
        {
            public bool $failOutbox = false;

            public function outboxSnapshot(\DateTimeImmutable $now): array
            {
                if ($this->failOutbox) {
                    throw new \RuntimeException('Unavailable');
                }

                return ['pending_count' => 0, 'retrying_count' => 0, 'dead_letter_count' => 0, 'oldest_pending_age_seconds' => null];
            }

            public function emailSnapshot(): array
            {
                return ['accepted_count' => 0, 'retrying_count' => 0, 'failed_count' => 0];
            }

            public function subscriptionSnapshot(\DateTimeImmutable $now): array
            {
                return [
                    'active_trial_count' => 0,
                    'expiring_trial_count' => 0,
                    'active_subscription_count' => 0,
                    'past_due_count' => 0,
                    'failed_webhook_count' => 0,
                    'environments' => ['Sandbox' => 0, 'Live' => 0, 'Unknown' => 0],
                ];
            }

            public function runtimeSnapshot(\DateTimeImmutable $now): array
            {
                return [
                    'database_available' => true,
                    'roles' => [
                        'api' => ['status' => 'Current', 'recorded_at' => $now->format(DATE_ATOM), 'age_seconds' => 0],
                        'worker' => ['status' => 'NotCollected', 'recorded_at' => null, 'age_seconds' => null],
                        'scheduler' => ['status' => 'NotCollected', 'recorded_at' => null, 'age_seconds' => null],
                    ],
                ];
            }

            public function maintenanceSnapshot(\DateTimeImmutable $now): array
            {
                return ['backup' => null, 'restore_canary' => null];
            }

            public function outboxPage(string $status, int $page, int $perPage): array
            {
                return ['items' => [], 'total' => 0, 'page' => $page, 'per_page' => $perPage, 'total_pages' => 1];
            }

            public function emailPage(string $status, int $page, int $perPage): array
            {
                return ['items' => [], 'total' => 0, 'page' => $page, 'per_page' => $perPage, 'total_pages' => 1];
            }

            public function subscriptionPage(string $status, string $environment, int $page, int $perPage): array
            {
                return ['items' => [], 'total' => 0, 'page' => $page, 'per_page' => $perPage, 'total_pages' => 1];
            }

            public function webhookPage(string $status, string $environment, int $page, int $perPage): array
            {
                return ['items' => [], 'total' => 0, 'page' => $page, 'per_page' => $perPage, 'total_pages' => 1];
            }

            public function maintenancePage(string $kind, string $status, int $page, int $perPage): array
            {
                return ['items' => [], 'total' => 0, 'page' => $page, 'per_page' => $perPage, 'total_pages' => 1];
            }
        };
    }

    private function betaSource(): BetaCohortSource
    {
        return new class implements BetaCohortSource
        {
            public function participants(\DateTimeImmutable $now, int $blockedAfterDays): array
            {
                return [];
            }

            public function diagnostic(string $betaCode): ?array
            {
                return null;
            }
        };
    }
}
