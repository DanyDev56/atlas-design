<?php

declare(strict_types=1);

namespace Atlas\Modules\Operations\Application;

use Atlas\Modules\Operations\Contracts\BetaCohortSource;
use Atlas\Modules\Operations\Domain\BetaCohortCatalog;

final class BetaCohortQueryHandler
{
    public function __construct(
        private readonly BetaCohortSource $source,
        private readonly int $blockedAfterDays,
    ) {}

    /** @return array<string, mixed> */
    public function overview(): array
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $participants = $this->source->participants($now, $this->blockedAfterDays);
        $funnel = [];
        foreach (BetaCohortCatalog::STAGES as $index => $stage) {
            $reached = count(array_filter($participants, static fn (array $participant): bool => $participant['stage_dates'][$stage] !== null));
            $denominator = $index === 0 ? count($participants) : $funnel[$index - 1]['reached'];
            $durations = $index === 0 ? [] : $this->durations($participants, BetaCohortCatalog::STAGES[$index - 1], $stage);
            $funnel[] = [
                'stage' => $stage,
                'reached' => $reached,
                'denominator' => $denominator,
                'rate_percent' => $denominator > 0 ? (int) round(($reached / $denominator) * 100) : null,
                'median_duration_hours' => $this->median($durations),
            ];
        }

        return [
            'generated_at' => $now->format(DATE_ATOM),
            'status' => count($participants) === 0 ? 'NoData' : 'Available',
            'participant_count' => count($participants),
            'active_count' => count(array_filter($participants, static fn (array $participant): bool => $participant['status'] === 'Active')),
            'blocked_count' => count(array_filter($participants, static fn (array $participant): bool => $participant['status'] === 'Active' && $participant['blocked'])),
            'decision_count' => count(array_filter($participants, static fn (array $participant): bool => $participant['pricing_decision'] !== null)),
            'funnel' => $funnel,
            'pricing_cells' => array_map(static function (string $cell) use ($participants): array {
                $cellParticipants = array_values(array_filter($participants, static fn (array $participant): bool => $participant['pricing_cell'] === $cell));

                return [
                    'cell' => $cell,
                    'participants' => count($cellParticipants),
                    'decisions' => count(array_filter($cellParticipants, static fn (array $participant): bool => $participant['pricing_decision'] !== null)),
                    'positive' => count(array_filter($cellParticipants, static fn (array $participant): bool => in_array($participant['pricing_decision']['decision'] ?? null, ['PAID', 'PREORDERED'], true))),
                ];
            }, BetaCohortCatalog::PRICING_CELLS),
            'source' => 'operations.beta_participants + sources métier propriétaires',
        ];
    }

    /** @return array<string, mixed> */
    public function page(string $stage, string $cell, string $status, int $page, int $perPage): array
    {
        $participants = $this->source->participants(
            new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
            $this->blockedAfterDays,
        );
        $filtered = array_values(array_filter($participants, static function (array $participant) use ($stage, $cell, $status): bool {
            return ($stage === 'All' || $participant['current_stage'] === $stage)
                && ($cell === 'All' || $participant['pricing_cell'] === $cell)
                && ($status === 'All' || $participant['status'] === $status);
        }));
        $total = count($filtered);

        return [
            'items' => array_slice($filtered, ($page - 1) * $perPage, $perPage),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => max(1, (int) ceil($total / $perPage)),
        ];
    }

    /** @return array<string, mixed>|null */
    public function diagnostic(string $betaCode): ?array
    {
        $matches = array_values(array_filter(
            $this->source->participants(new \DateTimeImmutable('now', new \DateTimeZone('UTC')), $this->blockedAfterDays),
            static fn (array $item): bool => $item['beta_code'] === strtoupper($betaCode),
        ));
        $participant = $matches[0] ?? null;
        if ($participant === null) {
            return null;
        }

        return [
            'participant' => $participant,
            'diagnostic' => $this->source->diagnostic($betaCode),
        ];
    }

    /** @param list<array<string, mixed>> $participants @return list<float> */
    private function durations(array $participants, string $from, string $to): array
    {
        $durations = [];
        foreach ($participants as $participant) {
            $fromAt = $participant['stage_dates'][$from];
            $toAt = $participant['stage_dates'][$to];
            if ($fromAt !== null && $toAt !== null) {
                $seconds = (new \DateTimeImmutable($toAt))->getTimestamp() - (new \DateTimeImmutable($fromAt))->getTimestamp();
                $durations[] = max(0, $seconds / 3600);
            }
        }

        return $durations;
    }

    /** @param list<float> $values */
    private function median(array $values): ?float
    {
        if ($values === []) {
            return null;
        }
        sort($values);
        $middle = intdiv(count($values), 2);
        $value = count($values) % 2 === 1 ? $values[$middle] : ($values[$middle - 1] + $values[$middle]) / 2;

        return round($value, 1);
    }
}
