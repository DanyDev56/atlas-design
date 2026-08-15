<?php

declare(strict_types=1);

namespace Atlas\Modules\Notifications\Application;

use Atlas\Modules\Advisor\Domain\RecommendationPolicy as AdvisorPolicy;
use Atlas\Modules\Notifications\Domain\NotificationPolicy;

final class NotificationPlanEvaluator
{
    /**
     * @param  array{priority: string}|null  $primaryRecommendation
     * @return array<string, mixed>
     */
    public function evaluate(
        string $sourceEligibility,
        ?array $primaryRecommendation,
        string $inAppMode = NotificationPolicy::IN_APP_ENABLED,
        string $emailMode = NotificationPolicy::EMAIL_DISABLED,
        string $audienceAtPlan = NotificationPolicy::AUDIENCE_AUTHORIZED,
        string $audienceAtDispatch = NotificationPolicy::AUDIENCE_AUTHORIZED,
        bool $endpointVerified = false,
        bool $frequencyAvailable = true,
    ): array {
        if ($sourceEligibility !== AdvisorPolicy::ELIGIBILITY_ELIGIBLE) {
            return $this->ignoredPlan();
        }

        if ($primaryRecommendation === null) {
            return $this->noPrimaryPlan();
        }

        $channels = [];
        if ($inAppMode === NotificationPolicy::IN_APP_ENABLED
            && $audienceAtPlan === NotificationPolicy::AUDIENCE_AUTHORIZED) {
            $channels[] = NotificationPolicy::CHANNEL_IN_APP;
        }

        $emailEligible = $emailMode === NotificationPolicy::EMAIL_IMPORTANT_ONLY
            && in_array($primaryRecommendation['priority'], ['High', 'Critical'], true)
            && $endpointVerified
            && $frequencyAvailable;

        if ($emailEligible && $audienceAtPlan === NotificationPolicy::AUDIENCE_AUTHORIZED) {
            $channels[] = NotificationPolicy::CHANNEL_EMAIL;
        }

        $planDecision = $audienceAtDispatch === NotificationPolicy::AUDIENCE_REVOKED
            ? 'NotificationCreatedThenRecipientRevoked'
            : 'NotificationCreated';

        $notificationState = NotificationPolicy::STATUS_ACTIVE;
        $emailDeliveryState = NotificationPolicy::DELIVERY_SUPPRESSED;
        $providerCount = 0;

        if (in_array(NotificationPolicy::CHANNEL_EMAIL, $channels, true)) {
            if ($audienceAtDispatch === NotificationPolicy::AUDIENCE_REVOKED) {
                $notificationState = NotificationPolicy::STATUS_RESOLVED;
                $emailDeliveryState = NotificationPolicy::DELIVERY_CANCELLED;
            } else {
                $emailDeliveryState = NotificationPolicy::DELIVERY_ACCEPTED;
                $providerCount = 1;
            }
        }

        $result = [
            'plan_decision' => $planDecision,
            'audience_at_plan' => $audienceAtPlan,
            'audience_at_dispatch' => $audienceAtDispatch,
            'email_mode' => $emailMode,
            'channels' => $channels,
            'notification_state' => $notificationState,
            'email_delivery_state' => $emailDeliveryState,
            'provider_submission_count' => $providerCount,
        ];

        if (in_array(NotificationPolicy::CHANNEL_EMAIL, $channels, true)
            && $audienceAtDispatch === NotificationPolicy::AUDIENCE_AUTHORIZED) {
            $result['endpoint'] = 'VerifiedOpaqueReference';
            $result['frequency'] = 'Available';
            $result['email_content_class'] = 'MinimalPriorityAvailable';
        }

        if ($audienceAtDispatch === NotificationPolicy::AUDIENCE_REVOKED) {
            $result['revocation_kind'] = 'MembershipOrRoleInactive';
            $result['endpoint'] = 'InvalidatedOpaqueReference';
        }

        return $result;
    }

    /** @return array<string, mixed> */
    private function ignoredPlan(): array
    {
        return [
            'plan_decision' => 'SourceIgnored',
            'channels' => [],
            'notification_state' => 'Absent',
            'email_delivery_state' => 'Absent',
            'provider_submission_count' => 0,
        ];
    }

    /** @return array<string, mixed> */
    private function noPrimaryPlan(): array
    {
        return [
            'plan_decision' => 'NoPrimaryRecommendation',
            'channels' => [],
            'notification_state' => 'Absent',
            'email_delivery_state' => 'Absent',
            'provider_submission_count' => 0,
        ];
    }
}
