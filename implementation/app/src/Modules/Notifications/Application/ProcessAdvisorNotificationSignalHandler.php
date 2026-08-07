<?php

declare(strict_types=1);

namespace Atlas\Modules\Notifications\Application;

use Atlas\Modules\Advisor\Domain\RecommendationPolicy as AdvisorPolicy;
use Atlas\Modules\Advisor\Infrastructure\Persistence\PostgresAdvisorOverviewRepository;
use Atlas\Modules\Advisor\Infrastructure\Persistence\PostgresRecommendationRepository;
use Atlas\Modules\Identity\Domain\User;
use Atlas\Modules\Identity\Infrastructure\Persistence\PostgresUserRepository;
use Atlas\Modules\Notifications\Domain\NotificationPolicy;
use Atlas\Modules\Notifications\Infrastructure\Persistence\PostgresNotificationPreferenceRepository;
use Atlas\Modules\Notifications\Infrastructure\Persistence\PostgresNotificationRepository;
use Atlas\Modules\Notifications\Infrastructure\Persistence\PostgresNotificationTopicCursorRepository;
use Atlas\Modules\Notifications\Infrastructure\PostgresNotificationsIdempotencyStore;
use Illuminate\Support\Facades\DB;

final class ProcessAdvisorNotificationSignalHandler
{
    public function __construct(
        private readonly PostgresAdvisorOverviewRepository $overviews,
        private readonly PostgresRecommendationRepository $recommendations,
        private readonly PostgresUserRepository $users,
        private readonly PostgresNotificationPreferenceRepository $preferences,
        private readonly PostgresNotificationRepository $notifications,
        private readonly PostgresNotificationTopicCursorRepository $cursors,
        private readonly PostgresNotificationsIdempotencyStore $idempotency,
        private readonly NotificationPlanEvaluator $evaluator,
    ) {}

    /** @return array<string, mixed> */
    public function handle(
        string $workspaceId,
        int $advisorOverviewVersion,
        string $sourceEventId,
        string $requestId,
    ): array {
        $scope = 'notifications.process_advisor_signal';
        $fingerprint = hash('sha256', json_encode([
            $workspaceId, $advisorOverviewVersion, NotificationPolicy::VERSION, $sourceEventId,
        ], JSON_THROW_ON_ERROR));
        $cached = $this->idempotency->find($scope, $requestId);

        if ($cached !== null) {
            if ($cached['fingerprint'] !== $fingerprint) {
                throw new \DomainException('Idempotency conflict.');
            }

            return $cached['response_payload'];
        }

        return DB::transaction(function () use (
            $workspaceId, $advisorOverviewVersion, $sourceEventId, $scope, $fingerprint, $requestId,
        ): array {
            $lastVersion = $this->cursors->lastOverviewVersion(
                $workspaceId,
                NotificationPolicy::TOPIC_ADVISOR_PRIORITY,
            );

            if ($advisorOverviewVersion < $lastVersion) {
                $response = ['plan_decision' => 'SourceIgnored'];

                $this->idempotency->store($scope, $requestId, $fingerprint, $response);

                return $response;
            }

            $overview = $this->overviews->findCurrent($workspaceId);

            if ($overview === null || $overview['advisor_overview_version'] !== $advisorOverviewVersion) {
                throw new \DomainException('Overview not found.');
            }

            $primary = null;
            if ($overview['primary_recommendation_id'] !== null) {
                $primary = $this->recommendations->findById(
                    $workspaceId,
                    $overview['primary_recommendation_id'],
                );
            }

            $recipients = $this->resolveRecipients($workspaceId);
            $createdIds = [];

            foreach ($recipients as $recipient) {
                $prefs = $this->preferences->findOrDefault($workspaceId, $recipient['user_id']);
                $endpointVerified = $prefs['email_mode'] === NotificationPolicy::EMAIL_IMPORTANT_ONLY
                    && $this->users->findById(new \Atlas\Modules\Identity\Domain\UserId($recipient['user_id']))?->emailVerificationStatus() === User::EMAIL_VERIFIED;

                $plan = $this->evaluator->evaluate(
                    sourceEligibility: $overview['source_eligibility'],
                    primaryRecommendation: $primary !== null ? ['priority' => $primary['priority']] : null,
                    inAppMode: $prefs['in_app_mode'],
                    emailMode: $prefs['email_mode'],
                    audienceAtPlan: NotificationPolicy::AUDIENCE_AUTHORIZED,
                    audienceAtDispatch: NotificationPolicy::AUDIENCE_AUTHORIZED,
                    endpointVerified: $endpointVerified,
                );

                if ($plan['plan_decision'] === 'SourceIgnored' || $plan['plan_decision'] === 'NoPrimaryRecommendation') {
                    continue;
                }

                if ($plan['notification_state'] === NotificationPolicy::STATUS_RESOLVED) {
                    $this->notifications->resolveActiveForRecipient($workspaceId, $recipient['user_id']);
                    continue;
                }

                if ($plan['notification_state'] !== NotificationPolicy::STATUS_ACTIVE) {
                    continue;
                }

                $this->notifications->resolveActiveForRecipient($workspaceId, $recipient['user_id']);

                $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
                $validUntil = $primary !== null && isset($primary['valid_until'])
                    ? new \DateTimeImmutable($primary['valid_until'])
                    : null;

                $notificationId = $this->notifications->create(
                    workspaceId: $workspaceId,
                    recipientUserId: $recipient['user_id'],
                    recommendationId: $primary['recommendation_id'] ?? null,
                    priority: $primary['priority'] ?? 'Medium',
                    content: [
                        'template_key' => 'advisor.priority-available',
                        'template_version' => '1.0.0',
                        'recommendation_key' => $primary['recommendation_key'] ?? null,
                        'action_module' => $primary['action_module'] ?? null,
                        'route_key' => $primary['route_key'] ?? null,
                    ],
                    channels: $plan['channels'],
                    createdAt: $now,
                    displayUntil: $validUntil,
                );
                $createdIds[] = $notificationId;
            }

            $this->cursors->advance(
                $workspaceId,
                NotificationPolicy::TOPIC_ADVISOR_PRIORITY,
                $advisorOverviewVersion,
            );

            $response = [
                'plan_decision' => match (true) {
                    $overview['source_eligibility'] !== AdvisorPolicy::ELIGIBILITY_ELIGIBLE => 'SourceIgnored',
                    $primary === null => 'NoPrimaryRecommendation',
                    default => 'NotificationCreated',
                },
                'notification_ids' => $createdIds,
                'advisor_overview_version' => $advisorOverviewVersion,
            ];

            $this->idempotency->store($scope, $requestId, $fingerprint, $response);

            return $response;
        });
    }

    /** @return list<array{user_id: string}> */
    private function resolveRecipients(string $workspaceId): array
    {
        $members = DB::table('identity.memberships as m')
            ->join('identity.roles as r', 'r.id', '=', 'm.role_id')
            ->where('m.workspace_id', $workspaceId)
            ->where('m.status', 'Active')
            ->get(['m.user_id', 'r.permissions']);

        $recipients = [];
        foreach ($members as $member) {
            $permissions = json_decode($member->permissions, true, 512, JSON_THROW_ON_ERROR);
            if (in_array('advisor.recommendations.read', $permissions, true)
                && in_array('notifications.inbox.read', $permissions, true)) {
                $recipients[] = ['user_id' => $member->user_id];
            }
        }

        return $recipients;
    }
}
