<?php

declare(strict_types=1);

namespace Tests\Unit\BusinessHealth;

use Atlas\Modules\BusinessHealth\Application\HealthPolicyEvaluator;
use Atlas\Modules\BusinessHealth\Domain\HealthPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class HealthPolicyEvaluatorTest extends TestCase
{
    private HealthPolicyEvaluator $evaluator;

    /** @var array<string, mixed> */
    private static array $fixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->evaluator = new HealthPolicyEvaluator();

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
            if (! str_starts_with($case['fixture_id'], 'FIX-00') || (int) substr($case['fixture_id'], 4) > 8) {
                continue;
            }

            yield $case['fixture_id'] => [$case['fixture_id']];
        }
    }

    #[DataProvider('fixtureCases')]
    public function test_reference_fixture_matches_policy_oracle(string $fixtureId): void
    {
        $case = $this->caseById($fixtureId);
        $snapshot = $case['expected']['analytics']['snapshot'];
        $expected = $case['expected']['business_health'];

        $calculated = $this->evaluator->evaluate(
            $case['source_fact_summary'],
            $snapshot['freshness'],
            $snapshot['completeness'],
        );

        $this->assertArrayContainsSubset($expected, $calculated, $fixtureId);
    }

    /** @param array<string, mixed> $expected @param array<string, mixed> $actual */
    private function assertArrayContainsSubset(array $expected, array $actual, string $fixtureId): void
    {
        foreach ($expected as $key => $expectedValue) {
            $this->assertArrayHasKey($key, $actual, "{$fixtureId}: missing key {$key}");

            if (is_array($expectedValue) && is_array($actual[$key])) {
                $this->assertArrayContainsSubset($expectedValue, $actual[$key], "{$fixtureId}.{$key}");
                continue;
            }

            $this->assertSame(
                $expectedValue,
                $actual[$key],
                "{$fixtureId}: mismatch on {$key}",
            );
        }
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

    public function test_stale_snapshot_produces_insufficient_data(): void
    {
        $case = $this->caseById('FIX-007');

        $result = $this->evaluator->evaluate(
            $case['source_fact_summary'],
            'Lagging',
            HealthPolicy::COMPLETENESS_COMPLETE,
        );

        $this->assertSame(HealthPolicy::STATUS_INSUFFICIENT_DATA, $result['assessment_status']);
        $this->assertSame(HealthPolicy::RELIABILITY_INSUFFICIENT, $result['reliability']);
        $this->assertNull($result['overall_score']);
    }
}
