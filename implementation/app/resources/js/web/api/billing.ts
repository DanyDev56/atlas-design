import { apiRequest } from '@/api/client';
import type {
    BillingHistoryImportPreview,
    BillingHistoryImportRun,
    ApplyCreditNoteResponse,
    CreditNote,
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

export async function requestInvoiceReminder(
    token: string,
    workspaceId: string,
    invoiceId: string,
    expectedRevision: number,
    message?: string,
): Promise<{
    invoice_id: string;
    status: string;
    version: number;
    reminder_count: number;
    last_reminded_at: string | null;
    delivery: string;
    message: string | null;
}> {
    return apiRequest(
        'POST',
        workspacePath(workspaceId, `/invoices/${invoiceId}/remind`),
        {
            expected_revision: expectedRevision,
            delivery: 'ManualChannel',
            message: message || undefined,
        },
        { token, idempotency: true },
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

export async function createCreditNote(
    token: string,
    workspaceId: string,
    invoiceId: string,
    lines: QuoteLine[],
    reason?: string,
): Promise<CreditNote> {
    return apiRequest(
        'POST',
        workspacePath(workspaceId, `/invoices/${invoiceId}/credit-notes`),
        { lines, reason: reason || undefined },
        { token, idempotency: true },
    );
}

export async function issueCreditNote(
    token: string,
    workspaceId: string,
    creditNoteId: string,
    expectedRevision: number,
): Promise<CreditNote> {
    return apiRequest(
        'POST',
        workspacePath(workspaceId, `/credit-notes/${creditNoteId}/issue`),
        { expected_revision: expectedRevision },
        { token, idempotency: true },
    );
}

export async function applyCreditNote(
    token: string,
    workspaceId: string,
    creditNoteId: string,
    amountCents: number,
    expectedCreditNoteRevision: number,
    expectedInvoiceRevision: number,
    remainderDisposition?: 'RefundDue' | 'ClientCredit',
): Promise<ApplyCreditNoteResponse> {
    return apiRequest(
        'POST',
        workspacePath(workspaceId, `/credit-notes/${creditNoteId}/apply`),
        {
            amount_cents: amountCents,
            remainder_disposition: remainderDisposition,
            expected_credit_note_revision: expectedCreditNoteRevision,
            expected_invoice_revision: expectedInvoiceRevision,
        },
        { token, idempotency: true },
    );
}

export async function downloadBillingDocument(
    token: string,
    workspaceId: string,
    documentType: 'quote' | 'invoice' | 'credit_note',
    documentId: string,
): Promise<void> {
    const response = await fetch(
        `/api${workspacePath(workspaceId, `/documents/${documentType}/${documentId}/artifact`)}`,
        { headers: { Authorization: `Bearer ${token}` } },
    );
    if (!response.ok) {
        const body = await response.json().catch(() => null) as { messages?: string[] } | null;
        throw new Error(body?.messages?.[0] ?? 'Téléchargement du document impossible.');
    }

    const disposition = response.headers.get('Content-Disposition') ?? '';
    const filename = disposition.match(/filename="([^"]+)"/)?.[1] ?? `${documentType}.pdf`;
    const url = URL.createObjectURL(await response.blob());
    const anchor = document.createElement('a');
    anchor.href = url;
    anchor.download = filename;
    document.body.appendChild(anchor);
    anchor.click();
    anchor.remove();
    URL.revokeObjectURL(url);
}

export async function previewHistoricalBilling(
    token: string,
    workspaceId: string,
    input: {
        sourceSystem: string;
        sourceExportedAt: string;
        quotesFile: File;
        invoicesFile: File;
        paymentsFile: File;
    },
): Promise<BillingHistoryImportPreview> {
    const form = new FormData();
    form.append('source_system', input.sourceSystem);
    form.append('source_exported_at', input.sourceExportedAt);
    form.append('quotes_file', input.quotesFile);
    form.append('invoices_file', input.invoicesFile);
    form.append('payments_file', input.paymentsFile);

    return apiRequest(
        'POST',
        workspacePath(workspaceId, '/billing-history-imports/preview'),
        form,
        { token, idempotency: false },
    );
}

export async function confirmHistoricalBillingImport(
    token: string,
    workspaceId: string,
    previewId: string,
    packageHash: string,
    sourceSystem?: string,
    sourceExportedAt?: string,
): Promise<BillingHistoryImportRun> {
    return apiRequest(
        'POST',
        workspacePath(workspaceId, '/billing-history-imports/confirm'),
        {
            preview_id: previewId,
            package_hash: packageHash,
            source_system: sourceSystem,
            source_exported_at: sourceExportedAt,
        },
        { token, idempotency: true },
    );
}

export async function getHistoricalBillingImport(
    token: string,
    workspaceId: string,
    runId: string,
): Promise<BillingHistoryImportRun> {
    return apiRequest(
        'GET',
        workspacePath(workspaceId, `/billing-history-imports/${runId}`),
        undefined,
        { token },
    );
}
