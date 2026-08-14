import { apiRequest } from '@/api/client';
import type {
    InvoiceDetail,
    InvoiceSummary,
    PaymentResponse,
    PublicQuoteDetail,
    QuoteDetail,
    QuoteLine,
    QuoteSummary,
    SendQuoteResponse,
} from '@/types/api';

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

export async function updateQuote(
    token: string,
    workspaceId: string,
    quoteId: string,
    lines: QuoteLine[],
    expectedRevision: number,
): Promise<{ quote_id: string; status: string; total_cents: number; version: number }> {
    return apiRequest(
        'PATCH',
        workspacePath(workspaceId, `/quotes/${quoteId}`),
        { lines, expected_revision: expectedRevision },
        { token },
    );
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

export async function createInvoiceFromQuote(
    token: string,
    workspaceId: string,
    quoteId: string,
): Promise<InvoiceSummary> {
    return apiRequest('POST', workspacePath(workspaceId, `/quotes/${quoteId}/invoices`), {}, { token });
}

export async function listInvoices(token: string, workspaceId: string): Promise<InvoiceSummary[]> {
    return apiRequest<InvoiceSummary[]>('GET', workspacePath(workspaceId, '/invoices'), undefined, { token });
}

export async function getInvoice(token: string, workspaceId: string, invoiceId: string): Promise<InvoiceDetail> {
    return apiRequest<InvoiceDetail>('GET', workspacePath(workspaceId, `/invoices/${invoiceId}`), undefined, {
        token,
    });
}

export async function issueInvoice(
    token: string,
    workspaceId: string,
    invoiceId: string,
    expectedRevision: number,
): Promise<{ invoice_id: string; status: string; invoice_number: string; version: number }> {
    return apiRequest(
        'POST',
        workspacePath(workspaceId, `/invoices/${invoiceId}/issue`),
        { expected_revision: expectedRevision },
        { token },
    );
}

export async function sendInvoice(
    token: string,
    workspaceId: string,
    invoiceId: string,
    expectedRevision: number,
): Promise<{ invoice_id: string; status: string; version: number }> {
    return apiRequest(
        'POST',
        workspacePath(workspaceId, `/invoices/${invoiceId}/send`),
        { expected_revision: expectedRevision },
        { token },
    );
}

export async function recordPayment(
    token: string,
    workspaceId: string,
    invoiceId: string,
    amountCents: number,
    reference?: string,
): Promise<PaymentResponse> {
    return apiRequest(
        'POST',
        workspacePath(workspaceId, `/invoices/${invoiceId}/payments`),
        { amount_cents: amountCents, reference: reference || undefined },
        { token },
    );
}
