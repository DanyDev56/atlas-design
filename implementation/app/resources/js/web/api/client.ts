import type { ApiError } from '@/types/api';

const API_BASE = '/api';

export class ApiClientError extends Error {
    constructor(
        message: string,
        public readonly status: number,
        public readonly body: ApiError,
    ) {
        super(message);
        this.name = 'ApiClientError';
    }
}

function newIdempotencyKey(): string {
    return crypto.randomUUID();
}

export interface RequestOptions {
    auth?: boolean;
    token?: string | null;
    idempotency?: boolean;
}

export async function apiRequest<T>(
    method: string,
    path: string,
    body?: unknown,
    options: RequestOptions = {},
): Promise<T> {
    const { auth = true, token = null, idempotency = body !== undefined && method !== 'GET' } = options;
    const isFormData = typeof FormData !== 'undefined' && body instanceof FormData;

    const headers: Record<string, string> = {
        Accept: 'application/json',
    };

    if (body !== undefined && !isFormData) {
        headers['Content-Type'] = 'application/json';
    }

    if (idempotency) {
        headers['Idempotency-Key'] = newIdempotencyKey();
    }

    if (auth && token) {
        headers.Authorization = `Bearer ${token}`;
    }

    const response = await fetch(`${API_BASE}${path}`, {
        method,
        headers,
        body: body !== undefined
            ? isFormData
                ? body
                : JSON.stringify(body)
            : undefined,
    });

    let data: unknown;
    try {
        data = await response.json();
    } catch {
        data = { error: 'ParseError', messages: ['Invalid JSON response'] };
    }

    if (!response.ok) {
        const err = data as ApiError;
        if (response.status === 402 && err.error === 'SubscriptionAccessRestricted') {
            window.dispatchEvent(new CustomEvent('atlas:subscription-access-restricted', { detail: err }));
        }
        throw new ApiClientError(
            err.messages?.[0] ?? err.error ?? `HTTP ${response.status}`,
            response.status,
            err,
        );
    }

    return data as T;
}
