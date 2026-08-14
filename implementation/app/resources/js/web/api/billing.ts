import { apiRequest } from '@/api/client';
import type { PublicQuoteDetail, QuoteDetail, QuoteLine, QuoteSummary, SendQuoteResponse } from '@/types/api';

function workspacePath(workspaceId: string, suffix: string): string {
    return `/workspaces/${workspaceId}${suffix}`;
}

export async function listQuotes(token: string, workspaceId: string): Promise<QuoteSummary[]> {
    return apiRequest<QuoteSummary[]>('GET', workspacePath(workspaceId, '/quotes'), undefined, { token });
}

export async function getQuote(token: string, workspaceId: string, quoteId: string): Promise<QuoteDetail> {
    return apiRequest<QuoteDetail>('GET', workspacePath(workspaceId, `/quotes/${quoteId}`), undefined, { token });
}

export async function createQuote(
    token: string,
    workspaceId: string,
    payload: {
        client_id: string;
        opportunity_id?: string;
        currency?: string;
        lines: QuoteLine[];
    },
): Promise<{ quote_id: string; status: string; version: number }> {
    return apiRequest('POST', workspacePath(workspaceId, '/quotes'), payload, { token });
}

export async function sendQuote(
    token: string,
    workspaceId: string,
    quoteId: string,
    expectedRevision: number,
): Promise<SendQuoteResponse> {
    return apiRequest(
        'POST',
        workspacePath(workspaceId, `/quotes/${quoteId}/send`),
        { expected_revision: expectedRevision },
        { token },
    );
}

export async function acceptQuotePublic(
    workspaceId: string,
    quoteId: string,
    publicToken: string,
    expectedRevision: number,
): Promise<{ quote_id: string; status: string }> {
    return apiRequest(
        'POST',
        `/public/workspaces/${workspaceId}/quotes/${quoteId}/accept`,
        { public_token: publicToken, expected_revision: expectedRevision },
        { auth: false },
    );
}

export async function getPublicQuote(
    workspaceId: string,
    quoteId: string,
    publicToken: string,
): Promise<PublicQuoteDetail> {
    const query = new URLSearchParams({ public_token: publicToken });

    return apiRequest('GET', `/public/workspaces/${workspaceId}/quotes/${quoteId}?${query}`, undefined, {
        auth: false,
    });
}
