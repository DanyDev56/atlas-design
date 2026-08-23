<?php

declare(strict_types=1);

namespace Atlas\Modules\Operations\Contracts;

interface OperationsOverviewSource
{
    /** @return array{pending_count: int, retrying_count: int, dead_letter_count: int, oldest_pending_age_seconds: int|null} */
    public function outboxSnapshot(\DateTimeImmutable $now): array;

    /** @return array{accepted_count: int, retrying_count: int, failed_count: int} */
    public function emailSnapshot(): array;

    /** @return array{items: list<array<string, int|string|null>>, total: int, page: int, per_page: int, total_pages: int} */
    public function outboxPage(string $status, int $page, int $perPage): array;

    /** @return array{items: list<array<string, int|string|null>>, total: int, page: int, per_page: int, total_pages: int} */
    public function emailPage(string $status, int $page, int $perPage): array;
}
