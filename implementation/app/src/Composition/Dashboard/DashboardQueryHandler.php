<?php

declare(strict_types=1);

namespace Atlas\Composition\Dashboard;

use Atlas\Modules\Advisor\Application\AdvisorQueryHandler;
use Atlas\Modules\Analytics\Application\AnalyticsQueryHandler;
use Atlas\Modules\Billing\Application\BillingQueryHandler;
use Atlas\Modules\BusinessHealth\Application\BusinessHealthQueryHandler;
use Atlas\Modules\Crm\Application\CrmQueryHandler;
use Atlas\Modules\Notifications\Application\NotificationQueryHandler;

final class DashboardQueryHandler
{
    public function __construct(
        private readonly AdvisorQueryHandler $advisor,
        private readonly AnalyticsQueryHandler $analytics,
        private readonly BusinessHealthQueryHandler $businessHealth,
        private readonly CrmQueryHandler $crm,
        private readonly BillingQueryHandler $billing,
        private readonly NotificationQueryHandler $notifications,
    ) {}

    /** @return array<string, mixed> */
    public function getDashboard(string $actorUserId, string $workspaceId): array
    {
        return [
            'workspace_id' => $workspaceId,
            'advisor_priority' => $this->widget(fn () => $this->advisorWidget($actorUserId, $workspaceId)),
            'business_health' => $this->widget(fn () => $this->businessHealthWidget($actorUserId, $workspaceId)),
            'pipeline' => $this->widget(fn () => $this->pipelineWidget($actorUserId, $workspaceId)),
            'billing' => $this->widget(fn () => $this->billingWidget($actorUserId, $workspaceId)),
            'measured_activity' => $this->widget(fn () => $this->measuredActivityWidget($actorUserId, $workspaceId)),
            'notifications' => $this->widget(fn () => $this->notificationsWidget($actorUserId, $workspaceId)),
        ];
    }

    /** @return array<string, mixed> */
    private function advisorWidget(string $actorUserId, string $workspaceId): array
    {
        $overview = $this->advisor->getOverview($actorUserId, $workspaceId);

        return [
            'source_domain' => 'Advisor',
            'data_state' => 'Data',
            'observed_at' => $overview['updated_at'],
            'payload' => $overview,
        ];
    }

    /** @return array<string, mixed> */
    private function businessHealthWidget(string $actorUserId, string $workspaceId): array
    {
        $health = $this->businessHealth->getCurrent($actorUserId, $workspaceId);
        $dataState = match ($health['assessment_status'] ?? '') {
            'Available' => 'Data',
            'InsufficientData' => 'InsufficientData',
            default => 'NoData',
        };

        return [
            'source_domain' => 'BusinessHealth',
            'data_state' => $dataState,
            'observed_at' => $health['assessed_at'] ?? null,
            'payload' => $health,
        ];
    }

    /** @return array<string, mixed> */
    private function pipelineWidget(string $actorUserId, string $workspaceId): array
    {
        return [
            'source_domain' => 'CRM',
            'data_state' => 'Data',
            'observed_at' => (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:sP'),
            'payload' => $this->crm->getPipeline($actorUserId, $workspaceId),
        ];
    }

    /** @return array<string, mixed> */
    private function billingWidget(string $actorUserId, string $workspaceId): array
    {
        return [
            'source_domain' => 'Billing',
            'data_state' => 'Data',
            'observed_at' => (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:sP'),
            'payload' => [
                'recent_invoices' => $this->billing->listRecentInvoices($actorUserId, $workspaceId),
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function measuredActivityWidget(string $actorUserId, string $workspaceId): array
    {
        try {
            $overview = $this->analytics->getOverview($actorUserId, $workspaceId);
        } catch (\DomainException $exception) {
            if ($exception->getMessage() === 'Snapshot not found.') {
                return [
                    'source_domain' => 'Analytics',
                    'data_state' => 'NoData',
                    'observed_at' => null,
                    'payload' => null,
                ];
            }

            throw $exception;
        }

        return [
            'source_domain' => 'Analytics',
            'data_state' => 'Data',
            'observed_at' => is_string($overview['as_of'] ?? null) ? $overview['as_of'] : null,
            'payload' => $overview,
        ];
    }

    /** @return array<string, mixed> */
    private function notificationsWidget(string $actorUserId, string $workspaceId): array
    {
        return [
            'source_domain' => 'Notifications',
            'data_state' => 'Data',
            'observed_at' => (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:sP'),
            'payload' => $this->notifications->getUnreadCount($actorUserId, $workspaceId),
        ];
    }

    /** @param callable(): array<string, mixed> $loader */
    /** @return array<string, mixed> */
    private function widget(callable $loader): array
    {
        try {
            return $loader();
        } catch (\DomainException $exception) {
            if ($exception->getMessage() === 'Unauthorized.') {
                return [
                    'source_domain' => 'Unknown',
                    'data_state' => 'Unavailable',
                    'observed_at' => null,
                    'payload' => null,
                ];
            }

            if ($exception->getMessage() === 'Overview not found.' || $exception->getMessage() === 'Assessment not found.') {
                return [
                    'source_domain' => 'Unknown',
                    'data_state' => 'NoData',
                    'observed_at' => null,
                    'payload' => null,
                ];
            }

            throw $exception;
        }
    }
}
