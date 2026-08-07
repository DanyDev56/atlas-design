<?php

declare(strict_types=1);

namespace Tests\Unit\Notifications;

use Atlas\Modules\Advisor\Domain\RecommendationPolicy as AdvisorPolicy;
use Atlas\Modules\Notifications\Application\NotificationPlanEvaluator;
use Atlas\Modules\Notifications\Domain\NotificationPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class NotificationPlanEvaluatorTest extends TestCase
{
    private NotificationPlanEvaluator $evaluator;

    /** @var array<string, mixed> */
    private static array $fixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->evaluator = new NotificationPlanEvaluator();

        if (! isset(self::$fixtures)) {
            $path = dirname(__DIR__, 5).'/evolution/reference-fixtures/mvp-v1.json';
            self::$fixtures = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        }
    }

    /** @return iterable<string, array{0: string}> */
    public static function fixtureCases(): iterable
    {
        $path = dirname(__DIR__, 5).'/evolution/reference-fixtures/mvp-v1.json';
        $fixtures = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

        foreach ($fixtures['cases'] as $case) {
            if (! str_starts_with($case['fixture_id'], 'FIX-') || (int) substr($case['fixture_id'], 4) > 10) {
                continue;
            }

            yield $case['fixture_id'] => [$case['fixture_id']];
        }
    }

    #[DataProvider('fixtureCases')]
    public function test_reference_fixture_matches_policy_oracle(string $fixtureId): void
    {
        $case = $this->caseById($fixtureId);
        $advisor = $case['expected']['advisor'];
        $expected = $case['expected']['notifications'];
        $primary = $advisor['recommendations'][0] ?? null;

        $result = $this->evaluator->evaluate(
            sourceEligibility: $advisor['source_eligibility'],
            primaryRecommendation: $primary !== null ? ['priority' => $primary['priority']] : null,
            inAppMode: NotificationPolicy::IN_APP_ENABLED,
            emailMode: $expected['email_mode'] ?? NotificationPolicy::EMAIL_DISABLED,
            audienceAtPlan: $expected['audience_at_plan'] ?? NotificationPolicy::AUDIENCE_AUTHORIZED,
            audienceAtDispatch: $expected['audience_at_dispatch'] ?? NotificationPolicy::AUDIENCE_AUTHORIZED,
            endpointVerified: in_array('Email', $expected['channels'] ?? [], true),
        );

        foreach ($expected as $key => $expectedValue) {
            $this->assertArrayHasKey($key, $result, "{$fixtureId}: missing key {$key}");
            $this->assertSame($expectedValue, $result[$key], "{$fixtureId}: mismatch on {$key}");
        }
    }

    public function test_ineligible_advisor_source_is_ignored(): void
    {
        $result = $this->evaluator->evaluate(
            sourceEligibility: AdvisorPolicy::ELIGIBILITY_INSUFFICIENT,
            primaryRecommendation: ['priority' => 'Critical'],
        );

        $this->assertSame('SourceIgnored', $result['plan_decision']);
        $this->assertSame([], $result['channels']);
    }

    /** @return array<string, mixed> */
    private function caseById(string $fixtureId): array
    {
        foreach (self::$fixtures['cases'] as $case) {
            if ($case['fixture_id'] === $fixtureId) {
                return $case;
            }
        }

        throw new \RuntimeException("Fixture {$fixtureId} not found.");
    }
}
