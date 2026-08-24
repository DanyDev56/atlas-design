<?php

declare(strict_types=1);

namespace Atlas\Modules\Operations\Infrastructure\Persistence;

use Atlas\Modules\Operations\Contracts\OperationsOverviewSource;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class PostgresOperationsOverviewSource implements OperationsOverviewSource
{
    private const EMAIL_EVENT_TYPES = [
        'identity.email_verification_send_requested',
        'identity.account_recovery_send_requested',
        'identity.invitation_send_requested',
        'billing.quote_delivery_requested',
        'billing.invoice_delivery_requested',
        'notifications.advisor_email_delivery_requested',
    ];

    public function outboxSnapshot(\DateTimeImmutable $now): array
    {
        $row = DB::selectOne(
            <<<'SQL'
            SELECT
                COUNT(*) FILTER (WHERE failed_at IS NULL AND attempts = 0)::int AS pending_count,
                COUNT(*) FILTER (WHERE failed_at IS NULL AND attempts > 0)::int AS retrying_count,
                COUNT(*) FILTER (WHERE failed_at IS NOT NULL)::int AS dead_letter_count,
                MIN(created_at) FILTER (WHERE failed_at IS NULL) AS oldest_pending_at
            FROM platform.outbox_messages
            WHERE dispatched_at IS NULL
            SQL,
        );

        $oldestPendingAt = $row->oldest_pending_at !== null
            ? new \DateTimeImmutable((string) $row->oldest_pending_at)
            : null;

        return [
            'pending_count' => (int) ($row->pending_count ?? 0),
            'retrying_count' => (int) ($row->retrying_count ?? 0),
            'dead_letter_count' => (int) ($row->dead_letter_count ?? 0),
            'oldest_pending_age_seconds' => $oldestPendingAt !== null
                ? max(0, $now->getTimestamp() - $oldestPendingAt->getTimestamp())
                : null,
        ];
    }

    public function emailSnapshot(): array
    {
        $row = DB::selectOne(
            <<<'SQL'
            SELECT
                COUNT(*) FILTER (WHERE delivery.status = 'Accepted')::int AS accepted_count,
                COUNT(*) FILTER (
                    WHERE delivery.event_id IS NULL
                    AND outbox.dispatched_at IS NULL
                    AND outbox.failed_at IS NULL
                    AND outbox.attempts > 0
                )::int AS retrying_count,
                COUNT(*) FILTER (
                    WHERE outbox.dispatched_at IS NULL
                    AND outbox.failed_at IS NOT NULL
                )::int AS failed_count
            FROM platform.outbox_messages AS outbox
            LEFT JOIN platform.email_deliveries AS delivery ON delivery.event_id = outbox.event_id
            WHERE outbox.event_type IN (?, ?, ?, ?, ?, ?)
            SQL,
            self::EMAIL_EVENT_TYPES,
        );

        return [
            'accepted_count' => (int) ($row->accepted_count ?? 0),
            'retrying_count' => (int) ($row->retrying_count ?? 0),
            'failed_count' => (int) ($row->failed_count ?? 0),
        ];
    }

    public function subscriptionSnapshot(\DateTimeImmutable $now): array
    {
        $trial = DB::selectOne(
            <<<'SQL'
            SELECT
                COUNT(*) FILTER (WHERE status = 'Active' AND ends_at > ?)::int AS active_trial_count,
                COUNT(*) FILTER (WHERE status = 'Active' AND ends_at > ? AND ends_at <= ?)::int AS expiring_trial_count
            FROM subscriptions.trials
            SQL,
            [
                $now->format('Y-m-d H:i:sP'),
                $now->format('Y-m-d H:i:sP'),
                $now->add(new \DateInterval('P7D'))->format('Y-m-d H:i:sP'),
            ],
        );
        $subscription = DB::selectOne(
            <<<'SQL'
            SELECT
                COUNT(*) FILTER (WHERE status = 'Active')::int AS active_subscription_count,
                COUNT(*) FILTER (WHERE status = 'PastDue')::int AS past_due_count,
                COUNT(*) FILTER (WHERE billing_environment = 'Sandbox')::int AS sandbox_count,
                COUNT(*) FILTER (WHERE billing_environment = 'Live')::int AS live_count,
                COUNT(*) FILTER (WHERE billing_environment = 'Unknown')::int AS unknown_count
            FROM subscriptions.recurring_subscriptions
            SQL,
        );
        $webhook = DB::selectOne(
            "SELECT COUNT(*) FILTER (WHERE status IN ('Failed', 'Deferred'))::int AS failed_webhook_count FROM subscriptions.webhook_inbox",
        );

        return [
            'active_trial_count' => (int) ($trial->active_trial_count ?? 0),
            'expiring_trial_count' => (int) ($trial->expiring_trial_count ?? 0),
            'active_subscription_count' => (int) ($subscription->active_subscription_count ?? 0),
            'past_due_count' => (int) ($subscription->past_due_count ?? 0),
            'failed_webhook_count' => (int) ($webhook->failed_webhook_count ?? 0),
            'environments' => [
                'Sandbox' => (int) ($subscription->sandbox_count ?? 0),
                'Live' => (int) ($subscription->live_count ?? 0),
                'Unknown' => (int) ($subscription->unknown_count ?? 0),
            ],
        ];
    }

    public function runtimeSnapshot(\DateTimeImmutable $now): array
    {
        DB::selectOne('SELECT 1 AS available');
        $rows = DB::table('operations.runtime_heartbeats')->get()->keyBy('role');
        $roles = [];
        foreach (['api', 'worker', 'scheduler'] as $role) {
            $recordedAt = isset($rows[$role]) ? new \DateTimeImmutable((string) $rows[$role]->recorded_at) : null;
            $age = $recordedAt !== null ? max(0, $now->getTimestamp() - $recordedAt->getTimestamp()) : null;
            $roles[$role] = [
                'status' => $age !== null && $age <= 120 ? 'Current' : ($age === null ? 'NotCollected' : 'Stale'),
                'recorded_at' => $recordedAt?->format(DATE_ATOM),
                'age_seconds' => $age,
            ];
        }

        return ['database_available' => true, 'roles' => $roles];
    }

    public function httpSnapshot(\DateTimeImmutable $now, int $windowMinutes): array
    {
        $cutoff = $now->sub(new \DateInterval('PT'.max(1, $windowMinutes).'M'));
        $row = DB::selectOne(
            <<<'SQL'
            SELECT
                COALESCE(SUM(request_count), 0)::bigint AS request_count,
                COALESCE(SUM(error_count), 0)::bigint AS error_count,
                COALESCE(SUM(duration_sum_ms), 0)::bigint AS duration_sum_ms,
                MAX(duration_max_ms)::int AS maximum_duration_ms,
                COUNT(DISTINCT method || ' ' || route_template)::int AS route_count,
                MAX(updated_at) AS measured_at
            FROM operations.http_red_minute_buckets
            WHERE bucket_started_at >= ?
            SQL,
            [$cutoff->format('Y-m-d H:i:sP')],
        );
        $requestCount = (int) ($row->request_count ?? 0);
        $errorCount = (int) ($row->error_count ?? 0);

        return [
            'request_count' => $requestCount,
            'error_count' => $errorCount,
            'error_rate_percent' => $requestCount > 0 ? round(($errorCount / $requestCount) * 100, 2) : null,
            'average_duration_ms' => $requestCount > 0 ? round(((int) $row->duration_sum_ms) / $requestCount, 1) : null,
            'maximum_duration_ms' => $row->maximum_duration_ms !== null ? (int) $row->maximum_duration_ms : null,
            'route_count' => (int) ($row->route_count ?? 0),
            'measured_at' => $row->measured_at !== null
                ? (new \DateTimeImmutable((string) $row->measured_at))->format(DATE_ATOM)
                : null,
        ];
    }

    public function maintenanceSnapshot(\DateTimeImmutable $now): array
    {
        $latest = DB::table('operations.maintenance_runs')
            ->whereIn('kind', ['Backup', 'RestoreCanary'])
            ->orderByDesc('completed_at')
            ->get()
            ->unique('kind')
            ->keyBy('kind');

        $backup = $latest->get('Backup');
        $canary = $latest->get('RestoreCanary');

        return [
            'backup' => $backup !== null ? [
                'status' => (string) $backup->status,
                'completed_at' => (new \DateTimeImmutable((string) $backup->completed_at))->format(DATE_ATOM),
                'age_seconds' => max(0, $now->getTimestamp() - (new \DateTimeImmutable((string) $backup->completed_at))->getTimestamp()),
                'size_bytes' => $backup->size_bytes !== null ? (int) $backup->size_bytes : null,
            ] : null,
            'restore_canary' => $canary !== null ? [
                'status' => (string) $canary->status,
                'completed_at' => (new \DateTimeImmutable((string) $canary->completed_at))->format(DATE_ATOM),
                'age_seconds' => max(0, $now->getTimestamp() - (new \DateTimeImmutable((string) $canary->completed_at))->getTimestamp()),
            ] : null,
        ];
    }

    public function outboxPage(string $status, int $page, int $perPage): array
    {
        $query = DB::table('platform.outbox_messages')
            ->select([
                'event_id',
                'event_type',
                'attempts',
                'created_at',
                'available_at',
                'dispatched_at',
                'failed_at',
            ]);

        $this->applyOutboxStatus($query, $status);
        $total = (clone $query)->count();
        $rows = $query
            ->orderByDesc('created_at')
            ->forPage($page, $perPage)
            ->get();

        $items = $rows->map(static fn (object $row): array => [
            'event_id' => (string) $row->event_id,
            'event_type' => (string) $row->event_type,
            'status' => self::outboxStatus($row),
            'attempts' => (int) $row->attempts,
            'created_at' => (string) $row->created_at,
            'available_at' => $row->available_at !== null ? (string) $row->available_at : null,
            'dispatched_at' => $row->dispatched_at !== null ? (string) $row->dispatched_at : null,
            'failed_at' => $row->failed_at !== null ? (string) $row->failed_at : null,
        ])->all();

        return $this->page($items, $total, $page, $perPage);
    }

    public function emailPage(string $status, int $page, int $perPage): array
    {
        $emailRows = DB::table('platform.outbox_messages as outbox')
            ->leftJoin('platform.email_deliveries as delivery', 'delivery.event_id', '=', 'outbox.event_id')
            ->whereIn('outbox.event_type', self::EMAIL_EVENT_TYPES)
            ->select([
                'outbox.event_id',
                'outbox.event_type',
                'outbox.attempts',
                'outbox.created_at',
                'delivery.template_key',
                'delivery.provider',
                'delivery.updated_at',
                DB::raw(<<<'SQL'
                    CASE
                        WHEN delivery.status IS NOT NULL THEN delivery.status
                        WHEN outbox.failed_at IS NOT NULL THEN 'Failed'
                        WHEN outbox.attempts > 0 THEN 'Retrying'
                        ELSE 'Pending'
                    END AS status
                    SQL),
            ]);

        $query = DB::query()->fromSub($emailRows, 'email_events');
        if ($status !== 'All') {
            $query->where('status', $status);
        }

        $total = (clone $query)->count();
        $rows = $query
            ->orderByDesc('created_at')
            ->forPage($page, $perPage)
            ->get();

        $items = $rows->map(static fn (object $row): array => [
            'event_id' => (string) $row->event_id,
            'event_type' => (string) $row->event_type,
            'template_key' => $row->template_key !== null ? (string) $row->template_key : null,
            'status' => (string) $row->status,
            'attempts' => (int) $row->attempts,
            'provider' => $row->provider !== null ? (string) $row->provider : null,
            'created_at' => (string) $row->created_at,
            'updated_at' => $row->updated_at !== null ? (string) $row->updated_at : null,
        ])->all();

        return $this->page($items, $total, $page, $perPage);
    }

    public function subscriptionPage(string $status, string $environment, int $page, int $perPage): array
    {
        $query = DB::table('subscriptions.recurring_subscriptions as subscription')
            ->join('subscriptions.plans as plan', 'plan.id', '=', 'subscription.plan_id')
            ->join('subscriptions.plan_prices as price', 'price.id', '=', 'subscription.plan_price_id')
            ->select([
                'subscription.workspace_id',
                'subscription.status',
                'subscription.billing_environment',
                'subscription.provider',
                'subscription.current_period_end',
                'subscription.cancel_at_period_end',
                'subscription.past_due_since',
                'subscription.last_provider_event_at',
                'plan.code as plan_code',
                'price.billing_interval',
            ]);

        if ($status !== 'All') {
            $query->where('subscription.status', $status);
        }
        if ($environment !== 'All') {
            $query->where('subscription.billing_environment', $environment);
        }

        $total = (clone $query)->count();
        $rows = $query->orderByDesc('subscription.updated_at')->forPage($page, $perPage)->get();
        $items = $rows->map(fn (object $row): array => [
            'reference' => $this->opaqueReference('SUB', (string) $row->workspace_id),
            'workspace_reference' => $this->opaqueReference('WS', (string) $row->workspace_id),
            'status' => (string) $row->status,
            'billing_environment' => (string) $row->billing_environment,
            'provider' => (string) $row->provider,
            'plan_code' => (string) $row->plan_code,
            'billing_interval' => (string) $row->billing_interval,
            'current_period_end' => (string) $row->current_period_end,
            'cancel_at_period_end' => (bool) $row->cancel_at_period_end,
            'past_due_since' => $row->past_due_since !== null ? (string) $row->past_due_since : null,
            'last_provider_event_at' => (string) $row->last_provider_event_at,
        ])->all();

        return $this->page($items, $total, $page, $perPage);
    }

    public function webhookPage(string $status, string $environment, int $page, int $perPage): array
    {
        $query = DB::table('subscriptions.webhook_inbox')->select([
            'provider_event_id',
            'event_type',
            'status',
            'billing_environment',
            'provider',
            'attempts',
            'occurred_at',
            'received_at',
            'processed_at',
        ]);
        if ($status !== 'All') {
            $query->where('status', $status);
        }
        if ($environment !== 'All') {
            $query->where('billing_environment', $environment);
        }

        $total = (clone $query)->count();
        $rows = $query->orderByDesc('received_at')->forPage($page, $perPage)->get();
        $items = $rows->map(fn (object $row): array => [
            'reference' => $this->opaqueReference('WH', (string) $row->provider_event_id),
            'event_type' => (string) $row->event_type,
            'status' => (string) $row->status,
            'billing_environment' => (string) $row->billing_environment,
            'provider' => (string) $row->provider,
            'attempts' => (int) $row->attempts,
            'occurred_at' => (string) $row->occurred_at,
            'received_at' => (string) $row->received_at,
            'processed_at' => $row->processed_at !== null ? (string) $row->processed_at : null,
        ])->all();

        return $this->page($items, $total, $page, $perPage);
    }

    public function maintenancePage(string $kind, string $status, int $page, int $perPage): array
    {
        $query = DB::table('operations.maintenance_runs')->select([
            'kind', 'run_reference', 'status', 'size_bytes', 'started_at', 'completed_at',
        ]);
        if ($kind !== 'All') {
            $query->where('kind', $kind);
        }
        if ($status !== 'All') {
            $query->where('status', $status);
        }

        $total = (clone $query)->count();
        $rows = $query->orderByDesc('completed_at')->forPage($page, $perPage)->get();
        $items = $rows->map(static fn (object $row): array => [
            'reference' => (string) $row->run_reference,
            'kind' => (string) $row->kind,
            'status' => (string) $row->status,
            'size_bytes' => $row->size_bytes !== null ? (int) $row->size_bytes : null,
            'started_at' => (string) $row->started_at,
            'completed_at' => (string) $row->completed_at,
        ])->all();

        return $this->page($items, $total, $page, $perPage);
    }

    public function httpPage(int $windowMinutes, int $page, int $perPage): array
    {
        $cutoff = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))
            ->sub(new \DateInterval('PT'.max(1, $windowMinutes).'M'));
        $aggregate = DB::table('operations.http_red_minute_buckets')
            ->where('bucket_started_at', '>=', $cutoff->format('Y-m-d H:i:sP'))
            ->groupBy('method', 'route_template')
            ->select([
                'method',
                'route_template',
                DB::raw('SUM(request_count)::bigint AS request_count'),
                DB::raw('SUM(error_count)::bigint AS error_count'),
                DB::raw('SUM(duration_sum_ms)::bigint AS duration_sum_ms'),
                DB::raw('MAX(duration_max_ms)::int AS maximum_duration_ms'),
                DB::raw('MAX(updated_at) AS measured_at'),
            ]);
        $query = DB::query()->fromSub($aggregate, 'http_routes');
        $total = (clone $query)->count();
        $rows = $query->orderByDesc('error_count')->orderByDesc('request_count')->forPage($page, $perPage)->get();
        $items = $rows->map(static function (object $row): array {
            $requestCount = (int) $row->request_count;
            $errorCount = (int) $row->error_count;

            return [
                'method' => (string) $row->method,
                'route_template' => (string) $row->route_template,
                'request_count' => $requestCount,
                'error_count' => $errorCount,
                'error_rate_percent' => $requestCount > 0 ? round(($errorCount / $requestCount) * 100, 2) : null,
                'average_duration_ms' => $requestCount > 0 ? round(((int) $row->duration_sum_ms) / $requestCount, 1) : null,
                'maximum_duration_ms' => (int) $row->maximum_duration_ms,
                'measured_at' => (new \DateTimeImmutable((string) $row->measured_at))->format(DATE_ATOM),
            ];
        })->all();

        return [...$this->page($items, $total, $page, $perPage), 'window_minutes' => $windowMinutes];
    }

    public function alertPage(string $state, int $page, int $perPage): array
    {
        $query = DB::table('operations.alert_states')->select([
            'alert_key', 'state', 'context', 'first_detected_at', 'last_evaluated_at', 'last_notified_at', 'resolved_at',
        ]);
        if ($state !== 'All') {
            $query->where('state', $state);
        }

        $total = (clone $query)->count();
        $rows = $query->orderByRaw("CASE WHEN state = 'Firing' THEN 0 ELSE 1 END")
            ->orderByDesc('last_evaluated_at')->forPage($page, $perPage)->get();
        $items = $rows->map(static function (object $row): array {
            $context = is_string($row->context) ? json_decode($row->context, true) : (array) $row->context;

            return [
                'key' => (string) $row->alert_key,
                'state' => (string) $row->state,
                'context' => is_array($context) ? $context : [],
                'first_detected_at' => $row->first_detected_at !== null ? (string) $row->first_detected_at : null,
                'last_evaluated_at' => (string) $row->last_evaluated_at,
                'last_notified_at' => $row->last_notified_at !== null ? (string) $row->last_notified_at : null,
                'resolved_at' => $row->resolved_at !== null ? (string) $row->resolved_at : null,
            ];
        })->all();

        return $this->page($items, $total, $page, $perPage);
    }

    private function applyOutboxStatus(Builder $query, string $status): void
    {
        match ($status) {
            'Pending' => $query->whereNull('dispatched_at')->whereNull('failed_at')->where('attempts', 0),
            'Retrying' => $query->whereNull('dispatched_at')->whereNull('failed_at')->where('attempts', '>', 0),
            'DeadLetter' => $query->whereNull('dispatched_at')->whereNotNull('failed_at'),
            'Dispatched' => $query->whereNotNull('dispatched_at'),
            default => null,
        };
    }

    private static function outboxStatus(object $row): string
    {
        if ($row->dispatched_at !== null) {
            return 'Dispatched';
        }
        if ($row->failed_at !== null) {
            return 'DeadLetter';
        }

        return (int) $row->attempts > 0 ? 'Retrying' : 'Pending';
    }

    private function opaqueReference(string $prefix, string $value): string
    {
        $key = (string) config('app.key', 'atlas-operations');

        return $prefix.'-'.strtoupper(substr(hash_hmac('sha256', $value, $key), 0, 10));
    }

    /** @param list<array<string, int|string|bool|null>> $items */
    private function page(array $items, int $total, int $page, int $perPage): array
    {
        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => max(1, (int) ceil($total / $perPage)),
        ];
    }
}
