<?php

declare(strict_types=1);

namespace Atlas\Composition\Identity;

use Atlas\Modules\Identity\Infrastructure\Persistence\PostgresAccountRecoveryRepository;
use Atlas\Modules\Identity\Infrastructure\Persistence\PostgresEmailVerificationRepository;
use Atlas\Modules\Identity\Infrastructure\Persistence\PostgresInvitationRepository;
use Atlas\Platform\Mail\EmailHtmlRenderer;
use Atlas\Platform\Mail\Infrastructure\PostgresEmailDeliveryRepository;
use Atlas\Platform\Mail\TransactionalEmail;
use Atlas\Platform\Mail\TransactionalEmailSender;
use Atlas\Platform\Messaging\OutboxConsumer;
use Atlas\Platform\Messaging\OutgoingMessage;

final class OutboxIdentityEmailConsumer implements OutboxConsumer
{
    public function __construct(
        private readonly PostgresEmailVerificationRepository $verificationTokens,
        private readonly PostgresAccountRecoveryRepository $recoveryTokens,
        private readonly PostgresInvitationRepository $invitations,
        private readonly TransactionalEmailSender $sender,
        private readonly PostgresEmailDeliveryRepository $deliveries,
        private readonly EmailHtmlRenderer $html,
    ) {}

    public function name(): string
    {
        return 'composition.identity_email';
    }

    public function handle(OutgoingMessage $message): void
    {
        if (! in_array($message->eventType, [
            'identity.email_verification_send_requested',
            'identity.account_recovery_send_requested',
            'identity.invitation_send_requested',
        ], true) || $this->deliveries->hasTerminalStatus($message->eventId->value)) {
            return;
        }

        match ($message->eventType) {
            'identity.email_verification_send_requested' => $this->sendVerification($message),
            'identity.account_recovery_send_requested' => $this->sendRecovery($message),
            'identity.invitation_send_requested' => $this->sendInvitation($message),
            default => null,
        };
    }

    private function sendVerification(OutgoingMessage $message): void
    {
        $secretHandle = (string) $message->payload['delivery_secret_handle'];
        $context = $this->verificationTokens->findDeliveryContext(
            $secretHandle,
            (string) $message->payload['user_id'],
        );

        if ($context === null) {
            $this->verificationTokens->discardDeliverySecret($secretHandle);
            $this->cancel($message, 'identity.email-verification');

            return;
        }

        $url = $this->link('/app/verify-email', [
            'user_id' => (string) $message->payload['user_id'],
            'token' => $context['token'],
        ]);
        $body = "Bonjour {$context['display_name']},\n\nConfirmez votre adresse email pour activer votre compte Atlas. Ce lien expire dans 24 heures.";

        $this->send(
            $message,
            'identity.email-verification',
            $context['email'],
            'Confirmez votre adresse email Atlas',
            $body."\n\n".$url,
            $this->html->render('Confirmez votre adresse email', $body, 'Confirmer mon adresse', $url),
        );
        $this->verificationTokens->discardDeliverySecret($secretHandle);
    }

    private function sendRecovery(OutgoingMessage $message): void
    {
        $secretHandle = (string) $message->payload['delivery_secret_handle'];
        $context = $this->recoveryTokens->findDeliveryContext(
            $secretHandle,
            (string) $message->payload['user_id'],
        );

        if ($context === null) {
            $this->recoveryTokens->discardDeliverySecret($secretHandle);
            $this->cancel($message, 'identity.account-recovery');

            return;
        }

        $url = $this->link('/app/reset-password', ['token' => $context['token']]);
        $body = "Bonjour {$context['display_name']},\n\nUne réinitialisation de votre mot de passe Atlas a été demandée. Ce lien est à usage unique et expire dans une heure. Si vous n'êtes pas à l'origine de cette demande, ignorez cet email.";

        $this->send(
            $message,
            'identity.account-recovery',
            $context['email'],
            'Réinitialisez votre mot de passe Atlas',
            $body."\n\n".$url,
            $this->html->render('Réinitialisez votre mot de passe', $body, 'Choisir un nouveau mot de passe', $url),
        );
        $this->recoveryTokens->discardDeliverySecret($secretHandle);
    }

    private function sendInvitation(OutgoingMessage $message): void
    {
        $invitationId = (string) $message->payload['invitation_id'];
        $context = $this->invitations->findDeliveryContext(
            $invitationId,
            (string) $message->payload['workspace_id'],
        );

        if ($context === null) {
            $this->invitations->discardDeliverySecret($invitationId);
            $this->cancel($message, 'identity.workspace-invitation');

            return;
        }

        $url = $this->link('/app/invitations/'.$invitationId.'/accept', ['token' => $context['token']]);
        $body = "Vous êtes invité à rejoindre l'espace {$context['workspace_name']} sur Atlas. Connectez-vous avec cette adresse email, puis acceptez l'invitation. Le lien expire dans 7 jours.";

        $this->send(
            $message,
            'identity.workspace-invitation',
            $context['email'],
            "Invitation à rejoindre {$context['workspace_name']} sur Atlas",
            $body."\n\n".$url,
            $this->html->render('Vous êtes invité sur Atlas', $body, "Voir l'invitation", $url),
        );
        $this->invitations->markDeliveryAccepted($invitationId);
        $this->invitations->discardDeliverySecret($invitationId);
    }

    private function send(
        OutgoingMessage $message,
        string $templateKey,
        string $recipient,
        string $subject,
        string $text,
        string $html,
    ): void {
        $acceptance = $this->sender->send(new TransactionalEmail(
            deliveryKey: $message->eventId->value,
            recipient: $recipient,
            subject: $subject,
            text: $text,
            html: $html,
        ));

        $this->deliveries->recordAccepted(
            $message->eventId->value,
            $message->eventType,
            $templateKey,
            $recipient,
            $acceptance,
        );
    }

    private function cancel(OutgoingMessage $message, string $templateKey): void
    {
        $this->deliveries->recordCancelled(
            $message->eventId->value,
            $message->eventType,
            $templateKey,
        );
    }

    /** @param array<string, string> $query */
    private function link(string $path, array $query = []): string
    {
        $url = rtrim((string) config('mail.links_url', config('app.url')), '/').$path;

        return $query === [] ? $url : $url.'?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }
}
