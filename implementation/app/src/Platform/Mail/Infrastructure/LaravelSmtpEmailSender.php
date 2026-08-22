<?php

declare(strict_types=1);

namespace Atlas\Platform\Mail\Infrastructure;

use Atlas\Platform\Mail\EmailAcceptance;
use Atlas\Platform\Mail\TransactionalEmail;
use Atlas\Platform\Mail\TransactionalEmailSender;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Mail\Message;

final class LaravelSmtpEmailSender implements TransactionalEmailSender
{
    public function __construct(private readonly Mailer $mailer) {}

    public function send(TransactionalEmail $email): EmailAcceptance
    {
        $messageId = $email->deliveryKey.'@'.(string) config('mail.message_id_domain', 'atlas.local');

        $this->mailer->send([], [], function (Message $message) use ($email, $messageId): void {
            $message->to($email->recipient)->subject($email->subject);

            $symfonyMessage = $message->getSymfonyMessage();
            $symfonyMessage->text($email->text)->html($email->html);
            $symfonyMessage->getHeaders()->addIdHeader('Message-ID', $messageId);
            $symfonyMessage->getHeaders()->addTextHeader(
                'X-Atlas-Delivery-Key',
                $email->deliveryKey,
            );

            foreach ($email->attachments as $attachment) {
                $message->attachData(
                    $attachment->content,
                    $attachment->name,
                    ['mime' => $attachment->mediaType],
                );
            }
        });

        return new EmailAcceptance(
            provider: (string) config('mail.default', 'smtp'),
            providerMessageId: $messageId,
        );
    }
}
