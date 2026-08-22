<?php

declare(strict_types=1);

namespace Tests\Support;

use Atlas\Platform\Mail\EmailAcceptance;
use Atlas\Platform\Mail\TransactionalEmail;
use Atlas\Platform\Mail\TransactionalEmailSender;

final class FakeTransactionalEmailSender implements TransactionalEmailSender
{
    /** @var list<TransactionalEmail> */
    public array $sent = [];

    public function send(TransactionalEmail $email): EmailAcceptance
    {
        $this->sent[] = $email;

        return new EmailAcceptance('fake-smtp', $email->deliveryKey.'@test.atlas.local');
    }

    public function reset(): void
    {
        $this->sent = [];
    }
}
