<?php

declare(strict_types=1);

namespace Atlas\Modules\Advisor\Application;

use Atlas\Modules\Advisor\Domain\RecommendationPolicy;
use Atlas\Modules\BusinessHealth\Domain\HealthPolicy;

final class RecommendationPolicyEvaluator
{
    /** @return array<string, mixed> */
    public function evaluate(array $assessment, \DateTimeImmutable $asOf, \DateTimeImmutable $evaluatedAt): array
    {
        $eligibility = $this->sourceEligibility($assessment, $evaluatedAt);

        if ($eligibility !== RecommendationPolicy::ELIGIBILITY_ELIGIBLE) {
            return [
                'source_eligibility' => $eligibility,
                'recommendations' => [],
                'primary_recommendation_key' => null,
            ];
        }

        $candidates = $this->buildCandidates($assessment, $asOf);
        usort($candidates, function (array $a, array $b): int {
            return [
                -$this->priorityOrder($a['priority']),
                -$a['rank_score'],
                $a['valid_until'],
                $this->rulePrecedence($a['rule_key']),
                $a['recommendation_key'],
            ] <=> [
                -$this->priorityOrder($b['priority']),
                -$b['rank_score'],
                $b['valid_until'],
                $this->rulePrecedence($b['rule_key']),
                $b['recommendation_key'],
            ];
        });

        $published = array_slice($candidates, 0, 3);

        return [
            'source_eligibility' => RecommendationPolicy::ELIGIBILITY_ELIGIBLE,
            'recommendations' => $published,
            'primary_recommendation_key' => $published[0]['recommendation_key'] ?? null,
        ];
    }

    /** @param array<string, mixed> $assessment */
    private function sourceEligibility(array $assessment, \DateTimeImmutable $evaluatedAt): string
    {
        if (($assessment['assessment_status'] ?? '') !== HealthPolicy::STATUS_AVAILABLE) {
            return RecommendationPolicy::ELIGIBILITY_INSUFFICIENT;
        }

        $reliability = $assessment['assessment_reliability'] ?? '';
        if (! in_array($reliability, [HealthPolicy::RELIABILITY_RELIABLE, HealthPolicy::RELIABILITY_LIMITED], true)) {
            return RecommendationPolicy::ELIGIBILITY_INSUFFICIENT;
        }

        $asOf = new \DateTimeImmutable($assessment['as_of']);
        if ($evaluatedAt->getTimestamp() - $asOf->getTimestamp() > 7 * 86400) {
            return RecommendationPolicy::ELIGIBILITY_STALE;
        }

        return RecommendationPolicy::ELIGIBILITY_ELIGIBLE;
    }

    /** @param array<string, mixed> $assessment */
    /** @return list<array<string, mixed>> */
    private function buildCandidates(array $assessment, \DateTimeImmutable $asOf): array
    {
        $candidates = [];
        $risks = $assessment['risks'] ?? [];
        $riskFactors = [];

        foreach ($risks as $risk) {
            $ruleKey = $this->riskRule($risk['risk_key']);
            if ($ruleKey === null) {
                continue;
            }

            $riskFactors[] = $this->riskFactor($risk['risk_key']);
            $spec = $this->ruleSpec($ruleKey);
            $impactUrgency = $this->impactUrgencyFromSeverity($risk['severity']);
            $confidence = ($assessment['assessment_reliability'] ?? '') === HealthPolicy::RELIABILITY_RELIABLE
                ? 'High'
                : 'Moderate';

            $candidates[] = $this->buildRecommendation(
                ruleKey: $ruleKey,
                spec: $spec,
                impact: $impactUrgency['impact'],
                urgency: $impactUrgency['urgency'],
                confidence: $confidence,
                asOf: $asOf,
            );
        }

        $primaryAttention = $assessment['primary_attention'] ?? null;
        $healthBand = $assessment['health_band'] ?? null;
        $attentionFactor = is_array($primaryAttention) ? ($primaryAttention['factor_key'] ?? null) : null;

        if ($attentionFactor !== null
            && $healthBand !== 'Strong'
            && ! in_array($attentionFactor, $riskFactors, true)) {
            $fallback = $this->primaryAttentionFallback($attentionFactor, $healthBand);
            $confidence = ($assessment['assessment_reliability'] ?? '') === HealthPolicy::RELIABILITY_RELIABLE
                ? 'High'
                : 'Moderate';

            $candidates[] = $this->buildRecommendation(
                ruleKey: RecommendationPolicy::RULE_PRIMARY_ATTENTION,
                spec: $this->ruleSpec(RecommendationPolicy::RULE_PRIMARY_ATTENTION),
                impact: $fallback['impact'],
                urgency: $fallback['urgency'],
                confidence: $confidence,
                asOf: $asOf,
                actionModule: $fallback['action_module'],
                routeKey: $fallback['route_key'],
                effort: $fallback['effort'],
                validityDays: $fallback['validity_days'],
            );
        }

        return $candidates;
    }

    /** @param array<string, mixed> $spec */
    private function buildRecommendation(
        string $ruleKey,
        array $spec,
        string $impact,
        string $urgency,
        string $confidence,
        \DateTimeImmutable $asOf,
        ?string $actionModule = null,
        ?string $routeKey = null,
        ?string $effort = null,
        ?int $validityDays = null,
    ): array {
        $effort ??= $spec['effort'];
        $rankScore = $this->rankScore($impact, $urgency, $confidence, $effort);
        $validUntil = $asOf->modify('+'.($validityDays ?? $spec['validity_days']).' days');

        return [
            'rule_key' => $ruleKey,
            'recommendation_key' => $spec['recommendation_key'],
            'action_module' => $actionModule ?? $spec['action_module'],
            'route_key' => $routeKey ?? $spec['route_key'],
            'impact' => $impact,
            'urgency' => $urgency,
            'confidence' => $confidence,
            'effort' => $effort,
            'rank_score' => $rankScore,
            'priority' => $this->priorityFromScore($rankScore),
            'valid_until' => $validUntil->format('Y-m-d\TH:i:s\Z'),
        ];
    }

    /** @return array{impact: string, urgency: string} */
    private function impactUrgencyFromSeverity(string $severity): array
    {
        return match ($severity) {
            'Low' => ['impact' => 'Moderate', 'urgency' => 'ThisWeek'],
            'Medium' => ['impact' => 'Significant', 'urgency' => 'ThisWeek'],
            'High' => ['impact' => 'Major', 'urgency' => 'Today'],
            default => ['impact' => 'Major', 'urgency' => 'Immediate'],
        };
    }

    /** @return array{impact: string, urgency: string, action_module: string, route_key: string, effort: string, validity_days: int} */
    private function primaryAttentionFallback(string $factorKey, ?string $healthBand): array
    {
        $bandImpact = match ($healthBand) {
            'Stable' => 'Moderate',
            'Watch' => 'Significant',
            'AtRisk', 'Critical' => 'Major',
            default => 'Moderate',
        };
        $bandUrgency = match ($healthBand) {
            'Critical' => 'Immediate',
            'AtRisk' => 'Today',
            default => 'ThisWeek',
        };

        return match ($factorKey) {
            RecommendationPolicy::FACTOR_COMMERCIAL => [
                'impact' => $bandImpact,
                'urgency' => $bandUrgency,
                'action_module' => 'CRM',
                'route_key' => 'OpportunityPipeline',
                'effort' => 'Small',
                'validity_days' => 14,
            ],
            RecommendationPolicy::FACTOR_BILLING => [
                'impact' => $bandImpact,
                'urgency' => $bandUrgency,
                'action_module' => 'Billing',
                'route_key' => 'RecentInvoices',
                'effort' => 'Small',
                'validity_days' => 14,
            ],
            RecommendationPolicy::FACTOR_RECEIVABLES => [
                'impact' => $bandImpact,
                'urgency' => $bandUrgency,
                'action_module' => 'Billing',
                'route_key' => 'OutstandingInvoices',
                'effort' => 'Small',
                'validity_days' => 14,
            ],
            default => [
                'impact' => $bandImpact,
                'urgency' => $bandUrgency,
                'action_module' => 'CRM',
                'route_key' => 'NewOpportunity',
                'effort' => 'Medium',
                'validity_days' => 30,
            ],
        };
    }

    private function riskRule(string $riskKey): ?string
    {
        return match ($riskKey) {
            RecommendationPolicy::RISK_OVERDUE => RecommendationPolicy::RULE_COLLECT_OVERDUE,
            RecommendationPolicy::RISK_CONCENTRATION => RecommendationPolicy::RULE_REDUCE_CONCENTRATION,
            RecommendationPolicy::RISK_COMMERCIAL => RecommendationPolicy::RULE_REBUILD_PIPELINE,
            RecommendationPolicy::RISK_BILLING => RecommendationPolicy::RULE_RESTORE_BILLING,
            default => null,
        };
    }

    private function riskFactor(string $riskKey): string
    {
        return match ($riskKey) {
            RecommendationPolicy::RISK_OVERDUE => RecommendationPolicy::FACTOR_RECEIVABLES,
            RecommendationPolicy::RISK_CONCENTRATION => RecommendationPolicy::FACTOR_DIVERSIFICATION,
            RecommendationPolicy::RISK_COMMERCIAL => RecommendationPolicy::FACTOR_COMMERCIAL,
            default => RecommendationPolicy::FACTOR_BILLING,
        };
    }

    /** @return array<string, mixed> */
    private function ruleSpec(string $ruleKey): array
    {
        return match ($ruleKey) {
            RecommendationPolicy::RULE_COLLECT_OVERDUE => [
                'recommendation_key' => 'advisor.collect-overdue-invoices',
                'action_module' => 'Billing',
                'route_key' => 'OverdueInvoices',
                'effort' => 'Small',
                'validity_days' => 7,
            ],
            RecommendationPolicy::RULE_REDUCE_CONCENTRATION => [
                'recommendation_key' => 'advisor.reduce-client-concentration',
                'action_module' => 'CRM',
                'route_key' => 'NewOpportunity',
                'effort' => 'Medium',
                'validity_days' => 30,
            ],
            RecommendationPolicy::RULE_REBUILD_PIPELINE => [
                'recommendation_key' => 'advisor.rebuild-commercial-pipeline',
                'action_module' => 'CRM',
                'route_key' => 'NewOpportunity',
                'effort' => 'Medium',
                'validity_days' => 14,
            ],
            RecommendationPolicy::RULE_RESTORE_BILLING => [
                'recommendation_key' => 'advisor.restore-billing-momentum',
                'action_module' => 'Billing',
                'route_key' => 'RecentInvoices',
                'effort' => 'Small',
                'validity_days' => 14,
            ],
            default => [
                'recommendation_key' => 'advisor.address-primary-attention',
                'validity_days' => 14,
            ],
        };
    }

    private function rulePrecedence(string $ruleKey): int
    {
        return match ($ruleKey) {
            RecommendationPolicy::RULE_COLLECT_OVERDUE => 1,
            RecommendationPolicy::RULE_REDUCE_CONCENTRATION => 2,
            RecommendationPolicy::RULE_REBUILD_PIPELINE => 3,
            RecommendationPolicy::RULE_RESTORE_BILLING => 4,
            default => 5,
        };
    }

    private function rankScore(string $impact, string $urgency, string $confidence, string $effort): int
    {
        $score = 0.4 * $this->impactValue($impact)
            + 0.3 * $this->urgencyValue($urgency)
            + 0.2 * $this->confidenceValue($confidence)
            + 0.1 * $this->easeValue($effort);

        return (int) floor($score + 0.5);
    }

    private function priorityFromScore(int $score): string
    {
        return match (true) {
            $score >= 85 => 'Critical',
            $score >= 65 => 'High',
            $score >= 40 => 'Medium',
            default => 'Low',
        };
    }

    private function priorityOrder(string $priority): int
    {
        return match ($priority) {
            'Critical' => 4,
            'High' => 3,
            'Medium' => 2,
            default => 1,
        };
    }

    private function impactValue(string $level): int
    {
        return match ($level) {
            'Major' => 100,
            'Significant' => 65,
            'Moderate' => 35,
            'Minor' => 10,
            default => 0,
        };
    }

    private function urgencyValue(string $level): int
    {
        return match ($level) {
            'Immediate' => 100,
            'Today' => 75,
            'ThisWeek' => 50,
            'NoDeadline' => 20,
            default => 0,
        };
    }

    private function confidenceValue(string $level): int
    {
        return match ($level) {
            'High' => 100,
            'Moderate' => 60,
            'Low' => 20,
            default => 0,
        };
    }

    private function easeValue(string $effort): int
    {
        return match ($effort) {
            'Small' => 100,
            'Medium' => 60,
            'Large' => 20,
            default => 0,
        };
    }
}
