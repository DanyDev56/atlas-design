<?php

declare(strict_types=1);

namespace Atlas\Platform\Mail;

interface TransactionalEmailSender
{
    public function send(TransactionalEmail $email): EmailAcceptance;
}
