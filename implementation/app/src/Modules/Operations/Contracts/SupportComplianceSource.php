<?php

declare(strict_types=1);

namespace Atlas\Modules\Operations\Contracts;

interface SupportComplianceSource
{
    /** @return array{open_count: int, overdue_count: int, urgent_count: int} */
    public function supportSnapshot(\DateTimeImmutable $now): array;

    /** @return array{open_count: int, overdue_count: int, verification_pending_count: int} */
    public function dataRequestSnapshot(\DateTimeImmutable $now): array;

    /** @return array{published_policy_count: int, policy_proof_count: int, active_consent_count: int} */
    public function complianceSnapshot(): array;

    /** @return array{items: list<array<string, int|string|bool|null>>, total: int, page: int, per_page: int, total_pages: int} */
    public function supportPage(string $status, string $severity, int $page, int $perPage): array;

    /** @return array{items: list<array<string, mixed>>, total: int, page: int, per_page: int, total_pages: int} */
    public function dataRequestPage(string $status, string $type, int $page, int $perPage): array;

    /** @return array{items: list<array<string, int|string|null>>, total: int, page: int, per_page: int, total_pages: int, consent_counts: array<string, int>} */
    public function policyPage(int $page, int $perPage): array;
}
