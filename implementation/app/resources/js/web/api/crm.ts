import { apiRequest } from '@/api/client';
import type { ClientDetail, ClientSummary, ContactSummary, OpportunityDetail, OpportunitySummary } from '@/types/api';

function workspacePath(workspaceId: string, suffix: string): string {
    return `/workspaces/${workspaceId}${suffix}`;
}

export async function listClients(token: string, workspaceId: string): Promise<ClientSummary[]> {
    return apiRequest<ClientSummary[]>('GET', workspacePath(workspaceId, '/clients'), undefined, { token });
}

export async function getClient(token: string, workspaceId: string, clientId: string): Promise<ClientDetail> {
    return apiRequest<ClientDetail>('GET', workspacePath(workspaceId, `/clients/${clientId}`), undefined, { token });
}

export async function createClient(
    token: string,
    workspaceId: string,
    payload: { kind: 'Individual' | 'Organization'; display_name: string; profile?: Record<string, unknown> },
): Promise<{ client_id: string }> {
    return apiRequest('POST', workspacePath(workspaceId, '/clients'), payload, { token });
}

export async function listContacts(
    token: string,
    workspaceId: string,
    clientId: string,
): Promise<ContactSummary[]> {
    return apiRequest<ContactSummary[]>(
        'GET',
        workspacePath(workspaceId, `/clients/${clientId}/contacts`),
        undefined,
        { token },
    );
}

export async function addContact(
    token: string,
    workspaceId: string,
    clientId: string,
    payload: {
        profile: {
            display_name: string;
            email?: string;
            phone?: string;
            role?: string;
        };
        make_primary: boolean;
        expected_revision: number;
    },
): Promise<{ contact_id: string; client_id: string; is_primary: boolean; client_version: number }> {
    return apiRequest('POST', workspacePath(workspaceId, `/clients/${clientId}/contacts`), payload, { token });
}

export async function listOpportunities(
    token: string,
    workspaceId: string,
    status?: string,
): Promise<OpportunitySummary[]> {
    const query = status ? `?status=${encodeURIComponent(status)}` : '';
    return apiRequest<OpportunitySummary[]>(
        'GET',
        workspacePath(workspaceId, `/opportunities${query}`),
        undefined,
        { token },
    );
}

export async function getOpportunity(
    token: string,
    workspaceId: string,
    opportunityId: string,
): Promise<OpportunityDetail> {
    return apiRequest<OpportunityDetail>(
        'GET',
        workspacePath(workspaceId, `/opportunities/${opportunityId}`),
        undefined,
        { token },
    );
}

export async function createOpportunity(
    token: string,
    workspaceId: string,
    payload: {
        client_id: string;
        contact_id?: string;
        title: string;
        estimated_amount_cents?: number;
        currency?: string;
    },
): Promise<{ opportunity_id: string; status: string }> {
    return apiRequest('POST', workspacePath(workspaceId, '/opportunities'), payload, { token });
}

export async function qualifyOpportunity(
    token: string,
    workspaceId: string,
    opportunityId: string,
    expectedRevision: number,
): Promise<{ status: string; version: number }> {
    return apiRequest(
        'POST',
        workspacePath(workspaceId, `/opportunities/${opportunityId}/qualify`),
        { expected_revision: expectedRevision },
        { token },
    );
}
