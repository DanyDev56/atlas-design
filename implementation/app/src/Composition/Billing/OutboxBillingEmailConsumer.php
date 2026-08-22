<?php

declare(strict_types=1);

namespace Atlas\Composition\Billing;

use Atlas\Modules\Billing\Domain\InvoiceId;
use Atlas\Modules\Billing\Domain\QuoteId;
use Atlas\Modules\Billing\Infrastructure\Persistence\PostgresDocumentArtifactRepository;
use Atlas\Modules\Billing\Infrastructure\Persistence\PostgresInvoiceRepository;
use Atlas\Modules\Billing\Infrastructure\Persistence\PostgresPublicDocumentProofRepository;
use Atlas\Modules\Billing\Infrastructure\Persistence\PostgresQuoteRepository;
use Atlas\Platform\Mail\EmailAttachment;
use Atlas\Platform\Mail\EmailHtmlRenderer;
use Atlas\Platform\Mail\Infrastructure\PostgresEmailDeliveryRepository;
use Atlas\Platform\Mail\TransactionalEmail;
use Atlas\Platform\Mail\TransactionalEmailSender;
use Atlas\Platform\Messaging\OutboxConsumer;
use Atlas\Platform\Messaging\OutgoingMessage;
use Illuminate\Support\Facades\DB;

final class OutboxBillingEmailConsumer implements OutboxConsumer
{
    public function __construct(
        private readonly PostgresQuoteRepository $quotes,
        private readonly PostgresInvoiceRepository $invoices,
        private readonly PostgresPublicDocumentProofRepository $proofs,
        private readonly PostgresDocumentArtifactRepository $artifacts,
        private readonly TransactionalEmailSender $sender,
        private readonly PostgresEmailDeliveryRepository $deliveries,
        private readonly EmailHtmlRenderer $html,
    ) {}

    public function name(): string
    {
        return 'composition.billing_email';
    }

    public function handle(OutgoingMessage $message): void
    {
        if (! in_array($message->eventType, [
            'billing.quote_delivery_requested',
            'billing.invoice_delivery_requested',
            'billing.invoice_reminder_requested',
        ], true) || $this->deliveries->hasTerminalStatus($message->eventId->value)) {
            return;
        }

        match ($message->eventType) {
            'billing.quote_delivery_requested' => $this->sendQuote($message),
            'billing.invoice_delivery_requested' => $this->sendInvoice($message, false),
            'billing.invoice_reminder_requested' => $this->sendReminder($message),
            default => null,
        };
    }

    private function sendQuote(OutgoingMessage $message): void
    {
        $workspaceId = (string) $message->payload['workspace_id'];
        $quoteId = (string) $message->payload['quote_id'];
        $secretHandle = (string) $message->payload['delivery_secret_handle'];
        $quote = $this->quotes->findById($workspaceId, new QuoteId($quoteId));
        $recipient = $this->proofs->findDeliveryRecipient(
            $secretHandle,
            $workspaceId,
            $quoteId,
        );
        $recipient ??= $quote !== null ? $this->billingEmail($quote->clientSnapshot()) : null;
        $proof = $this->proofs->findDeliverySecret(
            $secretHandle,
            $workspaceId,
            $quoteId,
        );

        if ($quote === null || $recipient === null || $proof === null) {
            $this->proofs->discardDeliverySecret($secretHandle);
            $this->cancel($message, 'billing.quote-delivery', $recipient);

            return;
        }

        $artifact = $this->artifacts->latest($workspaceId, 'quote', $quoteId);
        if ($artifact === null) {
            throw new \RuntimeException('Quote artifact unavailable for email delivery.');
        }

        $workspaceName = $this->workspaceName($workspaceId);
        $url = $this->link('/app/quotes/accept/'.$workspaceId.'/'.$quoteId, ['token' => $proof]);
        $amount = $this->amount($quote->totalCents(), $quote->currency());
        $body = "Bonjour,\n\n{$workspaceName} vous adresse un devis d'un montant de {$amount}. Vous pouvez le consulter en pièce jointe et l'accepter depuis le lien sécurisé ci-dessous.";

        $this->send(
            $message,
            'billing.quote-delivery',
            $recipient,
            "Votre devis {$workspaceName}",
            $body."\n\n".$url,
            $this->html->render('Votre devis est disponible', $body, 'Consulter et accepter le devis', $url),
            $artifact,
        );
        $this->proofs->discardDeliverySecret($secretHandle);
    }

    private function sendInvoice(OutgoingMessage $message, bool $reminder): void
    {
        $workspaceId = (string) $message->payload['workspace_id'];
        $invoiceId = (string) $message->payload['invoice_id'];
        $invoice = $this->invoices->findById($workspaceId, new InvoiceId($invoiceId));
        $recipient = $invoice !== null ? $this->billingEmail($invoice->clientSnapshot()) : null;
        $template = $reminder ? 'billing.invoice-reminder' : 'billing.invoice-delivery';

        if ($invoice === null || $recipient === null) {
            $this->cancel($message, $template, $recipient);

            return;
        }

        $artifact = $this->artifacts->latest($workspaceId, 'invoice', $invoiceId);
        if ($artifact === null) {
            throw new \RuntimeException('Invoice artifact unavailable for email delivery.');
        }

        $workspaceName = $this->workspaceName($workspaceId);
        $number = $invoice->invoiceNumber() ?? $invoiceId;
        $amount = $this->amount(
            $reminder ? $invoice->balanceCents() : $invoice->totalCents(),
            $invoice->currency(),
        );
        $customMessage = $reminder ? trim((string) ($message->payload['message'] ?? '')) : '';
        $body = $reminder
            ? "Bonjour,\n\n{$workspaceName} vous rappelle que le solde de {$amount} de la facture {$number} reste dû."
            : "Bonjour,\n\n{$workspaceName} vous adresse la facture {$number}, d'un montant de {$amount}. Vous la trouverez en pièce jointe.";

        if ($customMessage !== '') {
            $body .= "\n\nMessage de {$workspaceName} :\n{$customMessage}";
        }

        $this->send(
            $message,
            $template,
            $recipient,
            $reminder ? "Relance — facture {$number}" : "Facture {$number} — {$workspaceName}",
            $body,
            $this->html->render($reminder ? 'Rappel de facture' : 'Votre facture', $body),
            $artifact,
        );
    }

    private function sendReminder(OutgoingMessage $message): void
    {
        if (($message->payload['delivery'] ?? null) !== 'EmailChannel') {
            return;
        }

        $this->sendInvoice($message, true);
    }

    /** @param array<string, mixed> $artifact */
    private function send(
        OutgoingMessage $message,
        string $templateKey,
        string $recipient,
        string $subject,
        string $text,
        string $html,
        array $artifact,
    ): void {
        $acceptance = $this->sender->send(new TransactionalEmail(
            deliveryKey: $message->eventId->value,
            recipient: $recipient,
            subject: $subject,
            text: $text,
            html: $html,
            attachments: [new EmailAttachment(
                name: (string) $artifact['filename'],
                content: (string) $artifact['content'],
                mediaType: (string) $artifact['media_type'],
            )],
        ));

        $this->deliveries->recordAccepted(
            $message->eventId->value,
            $message->eventType,
            $templateKey,
            $recipient,
            $acceptance,
        );
    }

    private function cancel(OutgoingMessage $message, string $templateKey, ?string $recipient): void
    {
        $this->deliveries->recordCancelled(
            $message->eventId->value,
            $message->eventType,
            $templateKey,
            $recipient,
        );
    }

    /** @param array<string, mixed> $snapshot */
    private function billingEmail(array $snapshot): ?string
    {
        $email = trim((string) ($snapshot['billing_profile']['billing_email'] ?? ''));

        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false ? strtolower($email) : null;
    }

    private function workspaceName(string $workspaceId): string
    {
        return (string) (DB::table('workspace.workspaces')->where('id', $workspaceId)->value('name') ?? 'Atlas');
    }

    private function amount(int $cents, string $currency): string
    {
        return number_format($cents / 100, 2, ',', ' ').' '.strtoupper($currency);
    }

    /** @param array<string, string> $query */
    private function link(string $path, array $query = []): string
    {
        $url = rtrim((string) config('mail.links_url', config('app.url')), '/').$path;

        return $query === [] ? $url : $url.'?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }
}
