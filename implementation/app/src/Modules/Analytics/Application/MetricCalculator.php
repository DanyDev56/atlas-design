<?php

declare(strict_types=1);

namespace Atlas\Modules\Analytics\Application;

use Atlas\Modules\Analytics\Domain\MetricKeys;
use Atlas\Modules\Crm\Domain\Opportunity;
use Illuminate\Support\Facades\DB;

final class MetricCalculator
{
    /** @return array<string, mixed> */
    public function calculateAll(string $workspaceId, \DateTimeImmutable $asOf): array
    {
        $metrics = [];

        foreach (MetricKeys::all() as $key) {
            $metrics[$key] = $this->calculateMetric($workspaceId, $key, $asOf);
        }

        return $metrics;
    }

    /** @return array<string, mixed> */
    public function calculateMetric(string $workspaceId, string $metricKey, \DateTimeImmutable $asOf): array
    {
        return match ($metricKey) {
            MetricKeys::PIPELINE_OPEN_AMOUNT => $this->pipelineOpenAmount($workspaceId),
            MetricKeys::QUOTES_PENDING_AMOUNT => $this->quotesPendingAmount($workspaceId),
            MetricKeys::QUOTES_ACCEPTANCE_RATE => $this->noDataMetric('Rolling90Days'),
            MetricKeys::QUOTES_AVERAGE_RESPONSE_TIME => $this->noDataMetric('Rolling90Days'),
            MetricKeys::BILLING_NET_INVOICED_AMOUNT => $this->netInvoicedAmount($workspaceId, $asOf),
            MetricKeys::BILLING_COLLECTED_AMOUNT => $this->collectedAmount($workspaceId, $asOf),
            MetricKeys::RECEIVABLES_OUTSTANDING_AMOUNT => $this->outstandingAmount($workspaceId),
            MetricKeys::RECEIVABLES_OVERDUE_AMOUNT => $this->overdueAmount($workspaceId, $asOf),
            MetricKeys::RECEIVABLES_OVERDUE_COUNT => $this->overdueCount($workspaceId, $asOf),
            MetricKeys::RECEIVABLES_DUE_SOON_AMOUNT => $this->dueSoonAmount($workspaceId, $asOf),
            MetricKeys::PAYMENTS_AVERAGE_TIME_TO_PAYMENT => $this->noDataMetric('Rolling90Days'),
            MetricKeys::PAYMENTS_ON_TIME_RATE => $this->noDataMetric('Rolling90Days'),
            MetricKeys::CLIENTS_TOP_COLLECTION_SHARE => $this->noDataMetric('Rolling365Days'),
            default => $this->noDataMetric('PointInTime'),
        };
    }

    /** @return array<string, mixed> */
    private function pipelineOpenAmount(string $workspaceId): array
    {
        $rows = DB::table('crm.opportunities')
            ->selectRaw('currency, SUM(estimated_amount_cents) as total')
            ->where('workspace_id', $workspaceId)
            ->whereIn('status', [Opportunity::STATUS_OPEN, Opportunity::STATUS_QUALIFIED])
            ->whereNotNull('estimated_amount_cents')
            ->groupBy('currency')
            ->get();

        if ($rows->isEmpty()) {
            return $this->noDataMetric('PointInTime');
        }

        return [
            'window_kind' => 'PointInTime',
            'value_status' => MetricKeys::VALUE_AVAILABLE,
            'values_by_currency' => $rows->mapWithKeys(fn ($row) => [
                $row->currency => (int) $row->total,
            ])->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function quotesPendingAmount(string $workspaceId): array
    {
        $rows = DB::table('billing.quotes')
            ->selectRaw('currency, SUM(total_cents) as total')
            ->where('workspace_id', $workspaceId)
            ->where('status', 'Sent')
            ->groupBy('currency')
            ->get();

        if ($rows->isEmpty()) {
            return $this->noDataMetric('PointInTime');
        }

        return [
            'window_kind' => 'PointInTime',
            'value_status' => MetricKeys::VALUE_AVAILABLE,
            'values_by_currency' => $rows->mapWithKeys(fn ($row) => [
                $row->currency => (int) $row->total,
            ])->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function netInvoicedAmount(string $workspaceId, \DateTimeImmutable $asOf): array
    {
        $from = $asOf->modify('-30 days');
        $rows = DB::table('billing.invoices')
            ->selectRaw('currency, SUM(total_cents) as total')
            ->where('workspace_id', $workspaceId)
            ->where('status', 'Issued')
            ->where('issued_at', '>=', $from->format('Y-m-d H:i:sP'))
            ->where('issued_at', '<', $asOf->format('Y-m-d H:i:sP'))
            ->groupBy('currency')
            ->get();

        if ($rows->isEmpty()) {
            return $this->noDataMetric('Rolling30Days');
        }

        return [
            'window_kind' => 'Rolling30Days',
            'value_status' => MetricKeys::VALUE_AVAILABLE,
            'values_by_currency' => $rows->mapWithKeys(fn ($row) => [
                $row->currency => (int) $row->total,
            ])->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function collectedAmount(string $workspaceId, \DateTimeImmutable $asOf): array
    {
        $from = $asOf->modify('-30 days');
        $rows = DB::table('billing.payments')
            ->selectRaw('currency, SUM(amount_cents) as total')
            ->where('workspace_id', $workspaceId)
            ->where('recorded_at', '>=', $from->format('Y-m-d H:i:sP'))
            ->where('recorded_at', '<', $asOf->format('Y-m-d H:i:sP'))
            ->groupBy('currency')
            ->get();

        if ($rows->isEmpty()) {
            return $this->noDataMetric('Rolling30Days');
        }

        return [
            'window_kind' => 'Rolling30Days',
            'value_status' => MetricKeys::VALUE_AVAILABLE,
            'values_by_currency' => $rows->mapWithKeys(fn ($row) => [
                $row->currency => (int) $row->total,
            ])->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function outstandingAmount(string $workspaceId): array
    {
        $rows = DB::table('billing.invoices')
            ->selectRaw('currency, SUM(balance_cents) as total')
            ->where('workspace_id', $workspaceId)
            ->where('status', 'Issued')
            ->where('balance_cents', '>', 0)
            ->groupBy('currency')
            ->get();

        if ($rows->isEmpty()) {
            return $this->noDataMetric('PointInTime');
        }

        return [
            'window_kind' => 'PointInTime',
            'value_status' => MetricKeys::VALUE_AVAILABLE,
            'values_by_currency' => $rows->mapWithKeys(fn ($row) => [
                $row->currency => (int) $row->total,
            ])->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function overdueAmount(string $workspaceId, \DateTimeImmutable $asOf): array
    {
        $rows = DB::table('billing.invoices')
            ->selectRaw('currency, SUM(balance_cents) as total')
            ->where('workspace_id', $workspaceId)
            ->where('status', 'Issued')
            ->where('balance_cents', '>', 0)
            ->whereNotNull('due_date')
            ->where('due_date', '<', $asOf->format('Y-m-d H:i:sP'))
            ->groupBy('currency')
            ->get();

        if ($rows->isEmpty()) {
            return $this->noDataMetric('PointInTime');
        }

        return [
            'window_kind' => 'PointInTime',
            'value_status' => MetricKeys::VALUE_AVAILABLE,
            'values_by_currency' => $rows->mapWithKeys(fn ($row) => [
                $row->currency => (int) $row->total,
            ])->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function overdueCount(string $workspaceId, \DateTimeImmutable $asOf): array
    {
        $count = (int) DB::table('billing.invoices')
            ->where('workspace_id', $workspaceId)
            ->where('status', 'Issued')
            ->where('balance_cents', '>', 0)
            ->whereNotNull('due_date')
            ->where('due_date', '<', $asOf->format('Y-m-d H:i:sP'))
            ->count();

        if ($count === 0) {
            return $this->noDataMetric('PointInTime');
        }

        return [
            'window_kind' => 'PointInTime',
            'value_status' => MetricKeys::VALUE_AVAILABLE,
            'count' => $count,
        ];
    }

    /** @return array<string, mixed> */
    private function dueSoonAmount(string $workspaceId, \DateTimeImmutable $asOf): array
    {
        $until = $asOf->modify('+7 days');
        $rows = DB::table('billing.invoices')
            ->selectRaw('currency, SUM(balance_cents) as total')
            ->where('workspace_id', $workspaceId)
            ->where('status', 'Issued')
            ->where('balance_cents', '>', 0)
            ->whereNotNull('due_date')
            ->where('due_date', '>=', $asOf->format('Y-m-d H:i:sP'))
            ->where('due_date', '<=', $until->format('Y-m-d H:i:sP'))
            ->groupBy('currency')
            ->get();

        if ($rows->isEmpty()) {
            return $this->noDataMetric('PointInTime');
        }

        return [
            'window_kind' => 'PointInTime',
            'value_status' => MetricKeys::VALUE_AVAILABLE,
            'values_by_currency' => $rows->mapWithKeys(fn ($row) => [
                $row->currency => (int) $row->total,
            ])->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function noDataMetric(string $windowKind): array
    {
        return [
            'window_kind' => $windowKind,
            'value_status' => MetricKeys::VALUE_NO_DATA,
        ];
    }
}
