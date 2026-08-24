<?php

declare(strict_types=1);

namespace Atlas\Modules\Operations\Application;

use Atlas\Modules\Operations\Contracts\OperationsOverviewSource;
use Atlas\Modules\Operations\Infrastructure\Alerts\OperationsAlertNotifier;
use Illuminate\Support\Facades\DB;

final class EvaluateOperationsAlertsHandler
{
    public function __construct(
        private readonly OperationsOverviewSource $source,
        private readonly OperationsAlertNotifier $notifier,
        private readonly int $httpWindowMinutes,
        private readonly int $httpMinimumRequests,
        private readonly float $httpErrorRateThresholdPercent,
        private readonly int $runtimeStaleSeconds,
        private readonly int $backupStaleSeconds,
        private readonly int $repeatMinutes,
        private readonly int $retentionDays,
    ) {}

    /** @return array{evaluated: int, firing: int, notifications_sent: int} */
    public function handle(): array
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $signals = [];

        try {
            $http = $this->source->httpSnapshot($now, $this->httpWindowMinutes);
            $thresholdReached = $http['request_count'] >= $this->httpMinimumRequests;
            $signals['http_high_error_rate'] = [
                'firing' => $thresholdReached
                    && (float) ($http['error_rate_percent'] ?? 0) >= $this->httpErrorRateThresholdPercent,
                'context' => [
                    'window_minutes' => $this->httpWindowMinutes,
                    'request_count' => $http['request_count'],
                    'error_count' => $http['error_count'],
                    'error_rate_percent' => $http['error_rate_percent'],
                    'threshold_percent' => $this->httpErrorRateThresholdPercent,
                    'minimum_requests' => $this->httpMinimumRequests,
                ],
            ];
        } catch (\Throwable) {
            // An unreadable source must not overwrite the last known alert state.
        }

        try {
            $runtime = $this->source->runtimeSnapshot($now);
            foreach (['api', 'worker', 'scheduler'] as $role) {
                $signal = $runtime['roles'][$role];
                $signals['runtime_'.$role.'_stale'] = [
                    'firing' => $signal['age_seconds'] !== null && $signal['age_seconds'] > $this->runtimeStaleSeconds,
                    'context' => [
                        'role' => $role,
                        'age_seconds' => $signal['age_seconds'],
                        'threshold_seconds' => $this->runtimeStaleSeconds,
                    ],
                ];
            }
        } catch (\Throwable) {
            // The missing database probe is handled outside this database-backed evaluator.
        }

        try {
            $subscriptions = $this->source->subscriptionSnapshot($now);
            $signals['subscription_webhook_failures'] = [
                'firing' => $subscriptions['failed_webhook_count'] > 0,
                'context' => ['failed_or_deferred_count' => $subscriptions['failed_webhook_count']],
            ];
        } catch (\Throwable) {
            // Keep the previous state when the source cannot be read.
        }

        try {
            $maintenance = $this->source->maintenanceSnapshot($now);
            $backup = $maintenance['backup'];
            $canary = $maintenance['restore_canary'];
            $signals['backup_failed_or_stale'] = [
                'firing' => ($backup !== null && ($backup['status'] === 'Failed' || $backup['age_seconds'] > $this->backupStaleSeconds))
                    || ($canary !== null && $canary['status'] === 'Failed'),
                'context' => [
                    'backup_status' => $backup['status'] ?? 'NotCollected',
                    'backup_age_seconds' => $backup['age_seconds'] ?? null,
                    'backup_stale_after_seconds' => $this->backupStaleSeconds,
                    'restore_canary_status' => $canary['status'] ?? 'NotCollected',
                ],
            ];
        } catch (\Throwable) {
            // Keep the previous state when the source cannot be read.
        }

        $firing = 0;
        $notifications = 0;
        foreach ($signals as $key => $signal) {
            $firing += $signal['firing'] ? 1 : 0;
            $notifications += $this->transition($key, $signal['firing'], $signal['context'], $now) ? 1 : 0;
        }

        DB::table('operations.http_red_minute_buckets')
            ->where('bucket_started_at', '<', $now->sub(new \DateInterval('P'.max(1, $this->retentionDays).'D'))->format('Y-m-d H:i:sP'))
            ->delete();

        return ['evaluated' => count($signals), 'firing' => $firing, 'notifications_sent' => $notifications];
    }

    /** @param array<string, int|float|string|bool|null> $context */
    private function transition(string $key, bool $firing, array $context, \DateTimeImmutable $now): bool
    {
        $previous = DB::table('operations.alert_states')->where('alert_key', $key)->first();
        $previousState = $previous !== null ? (string) $previous->state : null;
        $desiredState = $firing ? 'Firing' : 'Healthy';
        $notificationState = $firing ? 'Firing' : 'Resolved';
        $lastNotifiedAt = $previous?->last_notified_at !== null
            ? new \DateTimeImmutable((string) $previous->last_notified_at)
            : null;
        $repeatDue = $firing && $lastNotifiedAt !== null
            && $lastNotifiedAt <= $now->sub(new \DateInterval('PT'.max(1, $this->repeatMinutes).'M'));
        $needsNotification = $firing
            ? ($previousState !== 'Firing' || $previous?->last_notification_state !== 'Firing' || $repeatDue)
            : ($previousState === 'Firing' || ($previous?->resolved_at !== null && $previous?->last_notification_state !== 'Resolved'));
        $timestamp = $now->format('Y-m-d H:i:sP');
        $encodedContext = json_encode($context, JSON_THROW_ON_ERROR);

        DB::table('operations.alert_states')->updateOrInsert(
            ['alert_key' => $key],
            [
                'state' => $desiredState,
                'fingerprint' => hash('sha256', $encodedContext),
                'context' => $encodedContext,
                'first_detected_at' => $firing
                    ? ($previousState === 'Firing' ? $previous->first_detected_at : $timestamp)
                    : ($previous?->first_detected_at),
                'last_evaluated_at' => $timestamp,
                'last_notified_at' => $previous?->last_notified_at,
                'last_notification_state' => $previous?->last_notification_state,
                'resolved_at' => ! $firing && $previousState === 'Firing' ? $timestamp : ($firing ? null : $previous?->resolved_at),
                'updated_at' => $timestamp,
            ],
        );

        if (! $needsNotification || ! $this->notifier->notify($key, $notificationState, $context)) {
            return false;
        }

        DB::table('operations.alert_states')->where('alert_key', $key)->update([
            'last_notified_at' => $timestamp,
            'last_notification_state' => $notificationState,
            'updated_at' => $timestamp,
        ]);

        return true;
    }
}
