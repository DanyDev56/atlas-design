<?php

declare(strict_types=1);

namespace Atlas\Modules\Operations\Contracts;

interface OperationsOverviewSource
{
    /** @return array{pending_count: int, retrying_count: int, dead_letter_count: int, oldest_pending_age_seconds: int|null} */
    public function outboxSnapshot(\DateTimeImmutable $now): array;

    /** @return array{accepted_count: int, retrying_count: int, failed_count: int} */
    public function emailSnapshot(): array;

    /** @return array{active_trial_count: int, expiring_trial_count: int, active_subscription_count: int, past_due_count: int, failed_webhook_count: int, environments: array{Sandbox: int, Live: int, Unknown: int}} */
    public function subscriptionSnapshot(\DateTimeImmutable $now): array;

    /** @return array{database_available: bool, roles: array<string, array{status: string, recorded_at: string|null, age_seconds: int|null}>} */
    public function runtimeSnapshot(\DateTimeImmutable $now): array;

    /** @return array{backup: array{status: string, completed_at: string, age_seconds: int, size_bytes: int|null}|null, restore_canary: array{status: string, completed_at: string, age_seconds: int}|null} */
    public function maintenanceSnapshot(\DateTimeImmutable $now): array;

    /** @return array{items: list<array<string, int|string|null>>, total: int, page: int, per_page: int, total_pages: int} */
    public function outboxPage(string $status, int $page, int $perPage): array;

    /** @return array{items: list<array<string, int|string|null>>, total: int, page: int, per_page: int, total_pages: int} */
    public function emailPage(string $status, int $page, int $perPage): array;

    /** @return array{items: list<array<string, int|string|bool|null>>, total: int, page: int, per_page: int, total_pages: int} */
    public function subscriptionPage(string $status, string $environment, int $page, int $perPage): array;

    /** @return array{items: list<array<string, int|string|null>>, total: int, page: int, per_page: int, total_pages: int} */
    public function webhookPage(string $status, string $environment, int $page, int $perPage): array;

    /** @return array{items: list<array<string, int|string|null>>, total: int, page: int, per_page: int, total_pages: int} */
    public function maintenancePage(string $kind, string $status, int $page, int $perPage): array;
}
