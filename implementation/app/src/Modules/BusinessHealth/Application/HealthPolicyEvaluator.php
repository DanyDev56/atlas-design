<?php

declare(strict_types=1);

namespace Atlas\Modules\BusinessHealth\Application;

use Atlas\Modules\BusinessHealth\Domain\HealthPolicy;

final class HealthPolicyEvaluator
{
    /** @return array<string, mixed> */
    public function evaluate(
        array $sourceFactSummary,
        string $freshness,
        string $completeness,
    ): array {
        $components = $this->calculateComponents($sourceFactSummary, $freshness, $completeness);
        $factors = $this->calculateFactors($components);
        $coverage = $this->globalCoverage($factors);
        $overall = $coverage >= 70 ? $this->overallScore($factors) : null;
        $attention = $overall !== null ? $this->primaryAttention($factors) : null;

        return [
            'assessment_status' => $overall === null
                ? HealthPolicy::STATUS_INSUFFICIENT_DATA
                : HealthPolicy::STATUS_AVAILABLE,
            'reliability' => $this->reliability($overall, $components),
            'components' => $components,
            'factors' => $factors,
            'global_coverage_percent' => $coverage,
            'overall_score' => $overall,
            'health_band' => $this->healthBand($overall),
            'primary_attention' => $attention,
            'risks' => $this->calculateRisks($sourceFactSummary, $freshness, $completeness),
        ];
    }

    /** @return array<string, array<string, mixed>> */
    private function calculateComponents(array $source, string $freshness, string $completeness): array
    {
        if ($freshness !== HealthPolicy::FRESHNESS_CURRENT
            || $completeness !== HealthPolicy::COMPLETENESS_COMPLETE) {
            return $this->staleComponents();
        }

        return [
            HealthPolicy::COMPONENT_QUOTE_ACCEPTANCE => $this->acceptanceComponent($source),
            HealthPolicy::COMPONENT_PIPELINE_EVOLUTION => $this->pipelineComponent($source),
            HealthPolicy::COMPONENT_NET_INVOICED_EVOLUTION => $this->billingComponent(
                $source['net_invoiced_rolling_30_days']['current_amount_minor'] ?? null,
                $source['net_invoiced_rolling_30_days']['baseline_amount_minor'] ?? null,
            ),
            HealthPolicy::COMPONENT_COLLECTED_EVOLUTION => $this->billingComponent(
                $source['collected_rolling_30_days']['current_amount_minor'] ?? null,
                $source['collected_rolling_30_days']['baseline_amount_minor'] ?? null,
            ),
            HealthPolicy::COMPONENT_OVERDUE_LOAD => $this->overdueComponent($source),
            HealthPolicy::COMPONENT_ON_TIME_PAYMENT => $this->onTimeComponent($source),
            HealthPolicy::COMPONENT_TOP_CLIENT_SHARE => $this->diversificationComponent($source),
        ];
    }

    /** @return array<string, array<string, mixed>> */
    private function staleComponents(): array
    {
        $stale = ['status' => 'Unavailable', 'reason' => 'StaleSnapshot'];

        return [
            HealthPolicy::COMPONENT_QUOTE_ACCEPTANCE => $stale,
            HealthPolicy::COMPONENT_PIPELINE_EVOLUTION => $stale,
            HealthPolicy::COMPONENT_NET_INVOICED_EVOLUTION => $stale,
            HealthPolicy::COMPONENT_COLLECTED_EVOLUTION => $stale,
            HealthPolicy::COMPONENT_OVERDUE_LOAD => $stale,
            HealthPolicy::COMPONENT_ON_TIME_PAYMENT => $stale,
            HealthPolicy::COMPONENT_TOP_CLIENT_SHARE => $stale,
        ];
    }

    /** @return array<string, mixed> */
    private function acceptanceComponent(array $source): array
    {
        $period = $source['quote_decisions_rolling_90_days']['current'] ?? null;
        $sample = $this->quoteDenominator($period);

        if ($sample === null || $sample === 0) {
            return ['status' => 'Unavailable', 'reason' => 'NoData'];
        }

        if ($sample < 3) {
            return ['status' => 'Unavailable', 'reason' => 'SampleBelowMinimum'];
        }

        $rate = $this->quoteRate($period);

        return [
            'status' => 'Available',
            'score' => match (true) {
                $rate >= 0.7 => 100,
                $rate >= 0.5 => 80,
                $rate >= 0.3 => 55,
                $rate >= 0.15 => 30,
                default => 10,
            },
        ];
    }

    /** @return array<string, mixed> */
    private function pipelineComponent(array $source): array
    {
        $score = $this->evolutionScore(
            $source['pipeline']['current_amount_minor'] ?? null,
            $source['pipeline']['baseline_amount_minor'] ?? null,
            10,
            false,
        );

        return $score === null
            ? ['status' => 'Unavailable', 'reason' => 'NoData']
            : ['status' => 'Available', 'score' => $score];
    }

    /** @return array<string, mixed> */
    private function billingComponent(?int $current, ?int $baseline): array
    {
        $score = $this->evolutionScore($current, $baseline, 20, true);

        return $score === null
            ? ['status' => 'Unavailable', 'reason' => 'NoData']
            : ['status' => 'Available', 'score' => $score];
    }

    /** @return array<string, mixed> */
    private function overdueComponent(array $source): array
    {
        $current = $source['receivables']['current'] ?? null;

        if ($current === null) {
            return ['status' => 'Unavailable', 'reason' => 'NoData'];
        }

        $overdue = (int) ($current['overdue_amount_minor'] ?? 0);
        $outstanding = (int) ($current['outstanding_amount_minor'] ?? 0);

        if ($overdue > 0 && $outstanding === 0) {
            return ['status' => 'Unavailable', 'reason' => 'InconsistentReceivables'];
        }

        if ($outstanding === 0 && $overdue === 0) {
            return ['status' => 'Available', 'score' => 100];
        }

        if ($overdue === 0) {
            return ['status' => 'Available', 'score' => 100];
        }

        $ratio = $overdue / $outstanding;

        return [
            'status' => 'Available',
            'score' => match (true) {
                $ratio <= 0.1 => 80,
                $ratio <= 0.25 => 55,
                $ratio <= 0.5 => 25,
                default => 0,
            },
        ];
    }

    /** @return array<string, mixed> */
    private function onTimeComponent(array $source): array
    {
        $period = $source['settlements_rolling_90_days']['current'] ?? null;
        $settled = (int) ($period['settled_count'] ?? 0);

        if ($period === null || $settled === 0) {
            return ['status' => 'Unavailable', 'reason' => 'NoData'];
        }

        if ($settled < 3) {
            return ['status' => 'Unavailable', 'reason' => 'SampleBelowMinimum'];
        }

        $rate = $this->settlementRate($period);

        return [
            'status' => 'Available',
            'score' => match (true) {
                $rate >= 0.9 => 100,
                $rate >= 0.75 => 80,
                $rate >= 0.5 => 50,
                $rate >= 0.25 => 25,
                default => 0,
            },
        ];
    }

    /** @return array<string, mixed> */
    private function diversificationComponent(array $source): array
    {
        $share = $this->collectionShare($source['collections_rolling_365_days']['current'] ?? null);

        if ($share === null) {
            return ['status' => 'Unavailable', 'reason' => 'NoData'];
        }

        return [
            'status' => 'Available',
            'score' => match (true) {
                $share <= 0.35 => 100,
                $share <= 0.5 => 75,
                $share <= 0.7 => 45,
                $share <= 0.9 => 20,
                default => 5,
            },
        ];
    }

    /** @param array<string, array<string, mixed>> $components */
    /** @return array<string, array<string, mixed>> */
    private function calculateFactors(array $components): array
    {
        return [
            HealthPolicy::FACTOR_COMMERCIAL_MOMENTUM => $this->calculateFactor(
                $components,
                [
                    ['key' => HealthPolicy::COMPONENT_QUOTE_ACCEPTANCE, 'weight' => 60],
                    ['key' => HealthPolicy::COMPONENT_PIPELINE_EVOLUTION, 'weight' => 40],
                ],
                40,
            ),
            HealthPolicy::FACTOR_BILLING_MOMENTUM => $this->calculateFactor(
                $components,
                [
                    ['key' => HealthPolicy::COMPONENT_NET_INVOICED_EVOLUTION, 'weight' => 50],
                    ['key' => HealthPolicy::COMPONENT_COLLECTED_EVOLUTION, 'weight' => 50],
                ],
                50,
            ),
            HealthPolicy::FACTOR_RECEIVABLES_DISCIPLINE => $this->calculateFactor(
                $components,
                [
                    ['key' => HealthPolicy::COMPONENT_OVERDUE_LOAD, 'weight' => 60],
                    ['key' => HealthPolicy::COMPONENT_ON_TIME_PAYMENT, 'weight' => 40],
                ],
                60,
            ),
            HealthPolicy::FACTOR_CLIENT_DIVERSIFICATION => $this->calculateFactor(
                $components,
                [
                    ['key' => HealthPolicy::COMPONENT_TOP_CLIENT_SHARE, 'weight' => 100],
                ],
                100,
            ),
        ];
    }

    /**
     * @param array<string, array<string, mixed>> $components
     * @param list<array{key: string, weight: int}> $parts
     *
     * @return array<string, mixed>
     */
    private function calculateFactor(array $components, array $parts, int $minimum): array
    {
        $available = array_values(array_filter(
            $parts,
            fn (array $part): bool => ($components[$part['key']]['status'] ?? '') === 'Available',
        ));

        $coverage = array_sum(array_map(fn (array $part): int => $part['weight'], $available));

        if ($coverage < $minimum) {
            return ['status' => 'Unavailable', 'coverage_percent' => $coverage];
        }

        $weighted = 0;
        foreach ($available as $part) {
            $weighted += $part['weight'] * (int) $components[$part['key']]['score'];
        }

        return [
            'status' => 'Available',
            'score' => $this->roundHalfUp($weighted / $coverage),
            'coverage_percent' => $coverage,
        ];
    }

    /** @param array<string, array<string, mixed>> $factors */
    private function globalCoverage(array $factors): int
    {
        $weights = [
            HealthPolicy::FACTOR_COMMERCIAL_MOMENTUM => 30,
            HealthPolicy::FACTOR_BILLING_MOMENTUM => 20,
            HealthPolicy::FACTOR_RECEIVABLES_DISCIPLINE => 35,
            HealthPolicy::FACTOR_CLIENT_DIVERSIFICATION => 15,
        ];

        $coverage = 0;
        foreach ($weights as $key => $weight) {
            if (($factors[$key]['status'] ?? '') === 'Available') {
                $coverage += $weight;
            }
        }

        return $coverage;
    }

    /** @param array<string, array<string, mixed>> $factors */
    private function overallScore(array $factors): int
    {
        $weights = [
            HealthPolicy::FACTOR_COMMERCIAL_MOMENTUM => 30,
            HealthPolicy::FACTOR_BILLING_MOMENTUM => 20,
            HealthPolicy::FACTOR_RECEIVABLES_DISCIPLINE => 35,
            HealthPolicy::FACTOR_CLIENT_DIVERSIFICATION => 15,
        ];

        $weighted = 0;
        $coverage = 0;
        foreach ($weights as $key => $weight) {
            if (($factors[$key]['status'] ?? '') === 'Available') {
                $weighted += $weight * (int) $factors[$key]['score'];
                $coverage += $weight;
            }
        }

        return $this->roundHalfUp($weighted / $coverage);
    }

    /** @param array<string, array<string, mixed>> $factors */
    /** @return array{factor_key: string, deficit_contribution: float}|null */
    private function primaryAttention(array $factors): ?array
    {
        $weights = [
            HealthPolicy::FACTOR_COMMERCIAL_MOMENTUM => 30,
            HealthPolicy::FACTOR_BILLING_MOMENTUM => 20,
            HealthPolicy::FACTOR_RECEIVABLES_DISCIPLINE => 35,
            HealthPolicy::FACTOR_CLIENT_DIVERSIFICATION => 15,
        ];

        $candidates = [];
        foreach ($weights as $key => $weight) {
            if (($factors[$key]['status'] ?? '') !== 'Available') {
                continue;
            }

            $score = (int) $factors[$key]['score'];
            $candidates[] = [
                'factor_key' => $key,
                'deficit_contribution' => $weight * (100 - $score) / 100,
                'original_weight' => $weight,
            ];
        }

        if ($candidates === []) {
            return null;
        }

        usort($candidates, function (array $a, array $b): int {
            return [$b['deficit_contribution'], $b['original_weight'], $a['factor_key']]
                <=> [$a['deficit_contribution'], $a['original_weight'], $b['factor_key']];
        });

        return [
            'factor_key' => $candidates[0]['factor_key'],
            'deficit_contribution' => $candidates[0]['deficit_contribution'],
        ];
    }

    /** @param array<string, array<string, mixed>> $components */
    private function reliability(?int $overall, array $components): string
    {
        if ($overall === null) {
            return HealthPolicy::RELIABILITY_INSUFFICIENT;
        }

        $availableCount = count(array_filter(
            $components,
            fn (array $component): bool => ($component['status'] ?? '') === 'Available',
        ));

        return $availableCount === 7
            ? HealthPolicy::RELIABILITY_RELIABLE
            : HealthPolicy::RELIABILITY_LIMITED;
    }

    private function healthBand(?int $score): ?string
    {
        if ($score === null) {
            return null;
        }

        return match (true) {
            $score >= 80 => 'Strong',
            $score >= 60 => 'Stable',
            $score >= 40 => 'Watch',
            $score >= 20 => 'AtRisk',
            default => 'Critical',
        };
    }

    /** @return list<array{risk_key: string, severity: string}> */
    private function calculateRisks(array $source, string $freshness, string $completeness): array
    {
        if ($freshness !== HealthPolicy::FRESHNESS_CURRENT
            || $completeness !== HealthPolicy::COMPLETENESS_COMPLETE) {
            return [];
        }

        $risks = [];
        $receivables = $source['receivables']['current'] ?? null;

        if ($receivables !== null && (int) ($receivables['overdue_amount_minor'] ?? 0) > 0) {
            $outstanding = (int) ($receivables['outstanding_amount_minor'] ?? 0);
            $ratio = $outstanding > 0
                ? (int) $receivables['overdue_amount_minor'] / $outstanding
                : 1.0;
            $risks[] = [
                'risk_key' => HealthPolicy::RISK_OVERDUE_EXPOSURE,
                'severity' => match (true) {
                    $ratio <= 0.1 => 'Low',
                    $ratio <= 0.25 => 'Medium',
                    $ratio <= 0.5 => 'High',
                    default => 'Critical',
                },
            ];
        }

        $share = $this->collectionShare($source['collections_rolling_365_days']['current'] ?? null);
        if ($share !== null && $share > 0.5) {
            $risks[] = [
                'risk_key' => HealthPolicy::RISK_CLIENT_CONCENTRATION,
                'severity' => match (true) {
                    $share <= 0.7 => 'Medium',
                    $share <= 0.9 => 'High',
                    default => 'Critical',
                },
            ];
        }

        $pipelineSignal = $this->declineSignal(
            $source['pipeline']['current_amount_minor'] ?? null,
            $source['pipeline']['baseline_amount_minor'] ?? null,
        );
        $quoteSample = $this->quoteDenominator($source['quote_decisions_rolling_90_days']['current'] ?? null);
        $acceptanceSignal = ($quoteSample !== null && $quoteSample >= 3
            && $this->quoteRate($source['quote_decisions_rolling_90_days']['current']) < 0.3)
            ? ['strong' => false]
            : null;

        $commercialSignals = array_values(array_filter([$pipelineSignal, $acceptanceSignal]));
        if ($commercialSignals !== []) {
            $strong = false;
            foreach ($commercialSignals as $signal) {
                if ($signal['strong']) {
                    $strong = true;
                    break;
                }
            }
            $risks[] = [
                'risk_key' => HealthPolicy::RISK_COMMERCIAL_MOMENTUM,
                'severity' => ($strong || count($commercialSignals) === 2) ? 'High' : 'Medium',
            ];
        }

        $netSignal = $this->declineSignal(
            $source['net_invoiced_rolling_30_days']['current_amount_minor'] ?? null,
            $source['net_invoiced_rolling_30_days']['baseline_amount_minor'] ?? null,
        );
        $collectedSignal = $this->declineSignal(
            $source['collected_rolling_30_days']['current_amount_minor'] ?? null,
            $source['collected_rolling_30_days']['baseline_amount_minor'] ?? null,
        );
        $billingSignals = array_values(array_filter([$netSignal, $collectedSignal]));
        if ($billingSignals !== []) {
            $strongCount = count(array_filter($billingSignals, fn (array $s): bool => $s['strong']));
            $risks[] = [
                'risk_key' => HealthPolicy::RISK_BILLING_MOMENTUM,
                'severity' => match (true) {
                    $strongCount === 2 => 'Critical',
                    $strongCount === 1 => 'High',
                    default => 'Medium',
                },
            ];
        }

        return $risks;
    }

    /** @return array{strong: bool}|null */
    private function declineSignal(?int $current, ?int $baseline): ?array
    {
        if ($current === null || $baseline === null || $baseline <= 0) {
            return null;
        }

        if ($current === 0) {
            return ['strong' => true];
        }

        $delta = ($current - $baseline) / $baseline;

        return match (true) {
            $delta <= -0.3 => ['strong' => true],
            $delta <= -0.1 => ['strong' => false],
            default => null,
        };
    }

    private function evolutionScore(?int $current, ?int $baseline, int $zeroScore, bool $rejectNegative): ?int
    {
        if ($current === null || $baseline === null) {
            return null;
        }

        if ($rejectNegative && $current < 0) {
            return null;
        }

        if ($current > 0 && $baseline === 0) {
            return 80;
        }

        if ($current === 0 && $baseline > 0) {
            return 0;
        }

        if ($current === 0 && $baseline === 0) {
            return $zeroScore;
        }

        $delta = ($current - $baseline) / $baseline;

        return match (true) {
            $delta >= 0.1 => 100,
            $delta >= 0 => 80,
            $delta > -0.1 => 60,
            $delta > -0.3 => 35,
            default => 10,
        };
    }

    /** @param array<string, mixed>|null $period */
    private function quoteDenominator(?array $period): ?int
    {
        if ($period === null) {
            return null;
        }

        return (int) ($period['accepted'] ?? 0)
            + (int) ($period['rejected'] ?? 0)
            + (int) ($period['expired'] ?? 0);
    }

    /** @param array<string, mixed>|null $period */
    private function quoteRate(?array $period): float
    {
        $denominator = $this->quoteDenominator($period);

        if ($denominator === null || $denominator === 0) {
            return 0.0;
        }

        return (int) ($period['accepted'] ?? 0) / $denominator;
    }

    /** @param array<string, mixed>|null $period */
    private function settlementRate(?array $period): float
    {
        $settled = (int) ($period['settled_count'] ?? 0);

        if ($settled === 0) {
            return 0.0;
        }

        return (int) ($period['on_time_count'] ?? 0) / $settled;
    }

    /** @param array<string, mixed>|null $period */
    private function collectionShare(?array $period): ?float
    {
        if ($period === null) {
            return null;
        }

        $total = (int) ($period['total_amount_minor'] ?? 0);
        if ($total <= 0) {
            return null;
        }

        $byClient = $period['by_client_minor'] ?? [];
        if (! is_array($byClient) || $byClient === []) {
            return null;
        }

        return max(array_map('intval', $byClient)) / $total;
    }

    private function roundHalfUp(float $value): int
    {
        return (int) floor($value + 0.5);
    }
}
