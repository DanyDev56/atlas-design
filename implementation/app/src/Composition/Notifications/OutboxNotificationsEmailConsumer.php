<?php

declare(strict_types=1);

namespace Atlas\Composition\Notifications;

use Atlas\Modules\Identity\Domain\User;
use Atlas\Modules\Identity\Domain\UserId;
use Atlas\Modules\Identity\Infrastructure\Persistence\PostgresUserRepository;
use Atlas\Modules\Notifications\Domain\NotificationPolicy;
use Atlas\Modules\Notifications\Infrastructure\Persistence\PostgresNotificationPreferenceRepository;
use Atlas\Modules\Notifications\Infrastructure\Persistence\PostgresNotificationRepository;
use Atlas\Platform\Mail\EmailHtmlRenderer;
use Atlas\Platform\Mail\Infrastructure\PostgresEmailDeliveryRepository;
use Atlas\Platform\Mail\TransactionalEmail;
use Atlas\Platform\Mail\TransactionalEmailSender;
use Atlas\Platform\Messaging\OutboxConsumer;
use Atlas\Platform\Messaging\OutgoingMessage;
use Illuminate\Support\Facades\DB;

final class OutboxNotificationsEmailConsumer implements OutboxConsumer
{
    public function __construct(
        private readonly PostgresNotificationRepository $notifications,
        private readonly PostgresNotificationPreferenceRepository $preferences,
        private readonly PostgresUserRepository $users,
        private readonly TransactionalEmailSender $sender,
        private readonly PostgresEmailDeliveryRepository $deliveries,
        private readonly EmailHtmlRenderer $html,
    ) {}

    public function name(): string
    {
        return 'composition.notifications_email';
    }

    public function handle(OutgoingMessage $message): void
    {
        if ($message->eventType !== 'notifications.advisor_email_delivery_requested'
            || $this->deliveries->hasTerminalStatus($message->eventId->value)) {
            return;
        }

        $workspaceId = (string) $message->payload['workspace_id'];
        $userId = (string) $message->payload['recipient_user_id'];
        $notification = $this->notifications->findById(
            $workspaceId,
            $userId,
            (string) $message->payload['notification_id'],
        );
        $user = $this->users->findById(new UserId($userId));
        $preferences = $this->preferences->findOrDefault($workspaceId, $userId);

        if ($notification === null
            || $notification['status'] !== NotificationPolicy::STATUS_ACTIVE
            || ! in_array(NotificationPolicy::CHANNEL_EMAIL, $notification['selected_channels'], true)
            || $preferences['email_mode'] !== NotificationPolicy::EMAIL_IMPORTANT_ONLY
            || $user === null
            || $user->emailVerificationStatus() !== User::EMAIL_VERIFIED
            || ! $this->hasAudience($workspaceId, $userId)) {
            $this->deliveries->recordCancelled(
                $message->eventId->value,
                $message->eventType,
                'notifications.advisor-priority',
                $user?->email(),
            );

            return;
        }

        $priority = (string) ($notification['priority'] ?? 'High');
        $url = rtrim((string) config('mail.links_url', config('app.url')), '/').'/app/advisor';
        $body = "Une nouvelle priorité {$priority} est disponible dans votre espace Atlas. Ouvrez Atlas pour consulter son contexte et décider de la suite.";
        $acceptance = $this->sender->send(new TransactionalEmail(
            deliveryKey: $message->eventId->value,
            recipient: $user->email(),
            subject: "Une priorité {$priority} vous attend dans Atlas",
            text: $body."\n\n".$url,
            html: $this->html->render('Une nouvelle priorité est disponible', $body, 'Ouvrir Atlas', $url),
        ));

        $this->deliveries->recordAccepted(
            $message->eventId->value,
            $message->eventType,
            'notifications.advisor-priority',
            $user->email(),
            $acceptance,
        );
    }

    private function hasAudience(string $workspaceId, string $userId): bool
    {
        $row = DB::table('identity.memberships as membership')
            ->join('identity.roles as role', 'role.id', '=', 'membership.role_id')
            ->where('membership.workspace_id', $workspaceId)
            ->where('membership.user_id', $userId)
            ->where('membership.status', 'Active')
            ->first(['role.permissions']);

        if ($row === null) {
            return false;
        }

        $permissions = is_string($row->permissions)
            ? json_decode($row->permissions, true, 512, JSON_THROW_ON_ERROR)
            : (array) $row->permissions;

        return in_array('advisor.recommendations.read', $permissions, true)
            && in_array('notifications.inbox.read', $permissions, true);
    }
}
