<?php

declare(strict_types=1);

namespace Atlas\Composition\Analytics;

use Atlas\Modules\Crm\Domain\Opportunity;
use Illuminate\Support\Facades\DB;

final class SourceFactSummaryBuilder
{
    /** @return array<string, mixed> */
    public function build(string $workspaceId, \DateTimeImmutable $asOf): array
    {
        $rolling90Start = $asOf->modify('-90 days');
        $rolling30Start = $asOf->modify('-30 days');
        $rolling365Start = $asOf->modify('-365 days');
        $previous30Start = $asOf->modify('-60 days');
        $previous30End = $asOf->modify('-30 days');

        return [
            'pipeline' => [
                'current_amount_minor' => $this->pipelineAmount($workspaceId, $asOf),
                'baseline_amount_minor' => $this->pipelineAmount($workspaceId, $previous30End),
            ],
            'pending_quotes' => [
                'current_amount_minor' => $this->pendingQuotesAmount($workspaceId, $asOf),
                'baseline_amount_minor' => $this->pendingQuotesAmount($workspaceId, $previous30End),
            ],
            'quote_decisions_rolling_90_days' => [
                'current' => $this->quoteDecisionsPeriod($workspaceId, $rolling90Start, $asOf),
                'baseline' => null,
            ],
            'net_invoiced_rolling_30_days' => [
                'current_amount_minor' => $this->netInvoicedAmount($workspaceId, $rolling30Start, $asOf),
                'baseline_amount_minor' => $this->netInvoicedAmount($workspaceId, $previous30Start, $previous30End),
            ],
            'collected_rolling_30_days' => [
                'current_amount_minor' => $this->collectedAmount($workspaceId, $rolling30Start, $asOf),
                'baseline_amount_minor' => $this->collectedAmount($workspaceId, $previous30Start, $previous30End),
            ],
            'receivables' => [
                'current' => $this->receivablesSnapshot($workspaceId, $asOf),
                'baseline' => null,
            ],
            'settlements_rolling_90_days' => [
                'current' => $this->settlementsPeriod($workspaceId, $rolling90Start, $asOf),
                'baseline' => null,
            ],
            'collections_rolling_365_days' => [
                'current' => $this->collectionsPeriod($workspaceId, $rolling365Start, $asOf),
                'baseline' => null,
            ],
        ];
    }

    private function pipelineAmount(string $workspaceId, \DateTimeImmutable $asOf): ?int
    {
        $total = DB::table('crm.opportunities')
            ->where('workspace_id', $workspaceId)
            ->whereIn('status', [Opportunity::STATUS_OPEN, Opportunity::STATUS_QUALIFIED])
            ->where('created_at', '<=', $asOf->format('Y-m-d H:i:sP'))
            ->sum('estimated_amount_cents');

        return $total > 0 ? (int) $total : null;
    }

    private function pendingQuotesAmount(string $workspaceId, \DateTimeImmutable $asOf): ?int
    {
        $total = DB::table('billing.quotes')
            ->where('workspace_id', $workspaceId)
            ->where('status', 'Sent')
            ->where('updated_at', '<=', $asOf->format('Y-m-d H:i:sP'))
            ->sum('total_cents');

        return $total > 0 ? (int) $total : null;
    }

    /** @return array<string, int>|null */
    private function quoteDecisionsPeriod(
        string $workspaceId,
        \DateTimeImmutable $from,
        \DateTimeImmutable $to,
    ): ?array {
        $quotes = DB::table('billing.quotes')
            ->where('workspace_id', $workspaceId)
            ->whereIn('status', ['Accepted', 'Sent'])
            ->where(function ($query) use ($from, $to): void {
                $query->whereBetween('sent_at', [$from->format('Y-m-d H:i:sP'), $to->format('Y-m-d H:i:sP')])
                    ->orWhereBetween('accepted_at', [$from->format('Y-m-d H:i:sP'), $to->format('Y-m-d H:i:sP')]);
            })
            ->get(['status', 'sent_at', 'accepted_at']);

        if ($quotes->isEmpty()) {
            return null;
        }

        $accepted = 0;
        $rejected = 0;
        $expired = 0;

        foreach ($quotes as $quote) {
            if ($quote->status === 'Accepted') {
                $accepted++;
            } elseif ($quote->status === 'Sent') {
                $expired++;
            }
        }

        return [
            'accepted' => $accepted,
            'rejected' => $rejected,
            'expired' => $expired,
            'response_sample_size' => $accepted,
            'response_seconds_total' => 0,
        ];
    }

    private function netInvoicedAmount(
        string $workspaceId,
        \DateTimeImmutable $from,
        \DateTimeImmutable $to,
    ): ?int {
        $total = DB::table('billing.invoices')
            ->where('workspace_id', $workspaceId)
            ->where('status', 'Issued')
            ->whereBetween('issued_at', [$from->format('Y-m-d H:i:sP'), $to->format('Y-m-d H:i:sP')])
            ->sum('total_cents');

        return $total > 0 ? (int) $total : null;
    }

    private function collectedAmount(
        string $workspaceId,
        \DateTimeImmutable $from,
        \DateTimeImmutable $to,
    ): ?int {
        $total = DB::table('billing.payments')
            ->where('workspace_id', $workspaceId)
            ->whereBetween('recorded_at', [$from->format('Y-m-d H:i:sP'), $to->format('Y-m-d H:i:sP')])
            ->sum('amount_cents');

        return $total > 0 ? (int) $total : null;
    }

    /** @return array<string, int>|null */
    private function receivablesSnapshot(string $workspaceId, \DateTimeImmutable $asOf): ?array
    {
        $invoices = DB::table('billing.invoices')
            ->where('workspace_id', $workspaceId)
            ->where('status', 'Issued')
            ->where('issued_at', '<=', $asOf->format('Y-m-d H:i:sP'))
            ->get(['balance_cents', 'due_date']);

        if ($invoices->isEmpty()) {
            return null;
        }

        $outstanding = 0;
        $overdue = 0;
        $overdueCount = 0;

        foreach ($invoices as $invoice) {
            $balance = (int) $invoice->balance_cents;
            if ($balance <= 0) {
                continue;
            }

            $outstanding += $balance;

            if ($invoice->due_date !== null && new \DateTimeImmutable($invoice->due_date) < $asOf) {
                $overdue += $balance;
                $overdueCount++;
            }
        }

        if ($outstanding === 0 && $overdue === 0) {
            return [
                'outstanding_amount_minor' => 0,
                'overdue_amount_minor' => 0,
                'overdue_count' => 0,
            ];
        }

        return [
            'outstanding_amount_minor' => $outstanding,
            'overdue_amount_minor' => $overdue,
            'overdue_count' => $overdueCount,
        ];
    }

    /** @return array<string, int>|null */
    private function settlementsPeriod(
        string $workspaceId,
        \DateTimeImmutable $from,
        \DateTimeImmutable $to,
    ): ?array {
        $payments = DB::table('billing.payments as p')
            ->join('billing.invoices as i', 'i.id', '=', 'p.invoice_id')
            ->where('p.workspace_id', $workspaceId)
            ->whereBetween('p.recorded_at', [$from->format('Y-m-d H:i:sP'), $to->format('Y-m-d H:i:sP')])
            ->get(['p.recorded_at', 'i.due_date', 'i.paid_at']);

        if ($payments->isEmpty()) {
            return null;
        }

        $settledCount = 0;
        $onTimeCount = 0;

        foreach ($payments as $payment) {
            if ($payment->paid_at === null) {
                continue;
            }

            $settledCount++;

            if ($payment->due_date !== null
                && new \DateTimeImmutable($payment->paid_at) <= new \DateTimeImmutable($payment->due_date)) {
                $onTimeCount++;
            }
        }

        if ($settledCount === 0) {
            return null;
        }

        return [
            'settled_count' => $settledCount,
            'on_time_count' => $onTimeCount,
            'duration_seconds_total' => 0,
        ];
    }

    /** @return array<string, mixed>|null */
    private function collectionsPeriod(
        string $workspaceId,
        \DateTimeImmutable $from,
        \DateTimeImmutable $to,
    ): ?array {
        $rows = DB::table('billing.payments as p')
            ->join('billing.invoices as i', 'i.id', '=', 'p.invoice_id')
            ->where('p.workspace_id', $workspaceId)
            ->whereBetween('p.recorded_at', [$from->format('Y-m-d H:i:sP'), $to->format('Y-m-d H:i:sP')])
            ->selectRaw('i.client_id, SUM(p.amount_cents) as total')
            ->groupBy('i.client_id')
            ->get();

        if ($rows->isEmpty()) {
            return null;
        }

        $byClient = [];
        $total = 0;
        foreach ($rows as $row) {
            $amount = (int) $row->total;
            $byClient[] = $amount;
            $total += $amount;
        }

        return [
            'total_amount_minor' => $total,
            'by_client_minor' => $byClient,
        ];
    }
}
