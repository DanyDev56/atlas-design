<?php

declare(strict_types=1);

namespace Tests\Unit\Advisor;

use Atlas\Modules\Advisor\Application\RecommendationPolicyEvaluator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class RecommendationPolicyEvaluatorTest extends TestCase
{
    private RecommendationPolicyEvaluator $evaluator;

    /** @var array<string, mixed> */
    private static array $fixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->evaluator = new RecommendationPolicyEvaluator;

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
        $expected = $case['expected']['advisor'];
        $assessment = $this->assessmentFromCase($case);
        $asOf = new \DateTimeImmutable(self::$fixtures['as_of']);
        $evaluatedAt = $asOf->modify('+1 hour');

        $result = $this->evaluator->evaluate($assessment, $asOf, $evaluatedAt);

        $this->assertSame($expected['source_eligibility'], $result['source_eligibility'], $fixtureId);
        $this->assertSame(
            $expected['primary_recommendation_key'],
            $result['primary_recommendation_key'],
            $fixtureId,
        );
        $this->assertCount(count($expected['recommendations']), $result['recommendations'], $fixtureId);

        foreach ($expected['recommendations'] as $index => $expectedRecommendation) {
            $this->assertArrayContainsSubset(
                $expectedRecommendation,
                $result['recommendations'][$index],
                "{$fixtureId}[{$index}]",
            );
        }
    }

    /** @param array<string, mixed> $case */
    /** @return array<string, mixed> */
    private function assessmentFromCase(array $case): array
    {
        $businessHealth = $case['expected']['business_health'];

        return [
            'assessment_status' => $businessHealth['assessment_status'],
            'assessment_reliability' => $businessHealth['reliability'],
            'health_band' => $businessHealth['health_band'],
            'risks' => $businessHealth['risks'],
            'primary_attention' => $businessHealth['primary_attention'],
            'as_of' => self::$fixtures['as_of'],
        ];
    }

    /** @param array<string, mixed> $expected @param array<string, mixed> $actual */
    private function assertArrayContainsSubset(array $expected, array $actual, string $fixtureId): void
    {
        foreach ($expected as $key => $expectedValue) {
            $this->assertArrayHasKey($key, $actual, "{$fixtureId}: missing key {$key}");
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
}
