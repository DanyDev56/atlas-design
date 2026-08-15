<?php

declare(strict_types=1);

namespace Atlas\Composition\Billing;

use Atlas\Platform\Messaging\OutboxConsumer;
use Atlas\Platform\Messaging\OutgoingMessage;

final class QuoteAcceptedWinOpportunityConsumer implements OutboxConsumer
{
    public function __construct(
        private readonly WinOpportunityFromQuoteHandler $winOpportunity,
    ) {}

    public function name(): string
    {
        return 'composition.quote_accepted_win_opportunity';
    }

    public function handle(OutgoingMessage $message): void
    {
        if ($message->eventType !== 'billing.quote_accepted') {
            return;
        }

        $payload = $message->payload;

        $this->winOpportunity->handle(
            workspaceId: $payload['workspace_id'],
            quoteId: $payload['quote_id'],
            opportunityId: $payload['opportunity_id'] ?? null,
            correlationId: $message->correlationId,
        );
    }
}
