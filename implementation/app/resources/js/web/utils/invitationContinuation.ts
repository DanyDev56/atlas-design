const STORAGE_KEY = 'atlas_invitation_continuation';
const MAX_AGE_MS = 8 * 24 * 60 * 60 * 1000;

interface StoredInvitationContinuation {
    url: string;
    savedAt: number;
}

export function normalizeInvitationContinuation(value: unknown): string | null {
    if (typeof value !== 'string' || value.trim() === '') return null;

    try {
        const url = new URL(value, window.location.origin);
        const invitationPath = /^\/app\/invitations\/[0-9a-f-]{36}\/accept$/i;

        if (
            url.origin !== window.location.origin
            || !invitationPath.test(url.pathname)
            || !url.searchParams.get('token')?.trim()
        ) {
            return null;
        }

        return `${url.pathname}${url.search}${url.hash}`;
    } catch {
        return null;
    }
}

export function rememberInvitationContinuation(value: unknown): string | null {
    const url = normalizeInvitationContinuation(value);
    if (!url) return null;

    try {
        const stored: StoredInvitationContinuation = { url, savedAt: Date.now() };
        localStorage.setItem(STORAGE_KEY, JSON.stringify(stored));
    } catch {
        // Navigation state still preserves the flow when browser storage is unavailable.
    }

    return url;
}

export function readInvitationContinuation(): string | null {
    try {
        const raw = localStorage.getItem(STORAGE_KEY);
        if (!raw) return null;

        const stored = JSON.parse(raw) as StoredInvitationContinuation;
        if (!Number.isFinite(stored.savedAt) || Date.now() - stored.savedAt > MAX_AGE_MS) {
            localStorage.removeItem(STORAGE_KEY);
            return null;
        }

        const url = normalizeInvitationContinuation(stored.url);
        if (!url) localStorage.removeItem(STORAGE_KEY);

        return url;
    } catch {
        return null;
    }
}

export function clearInvitationContinuation(): void {
    try {
        localStorage.removeItem(STORAGE_KEY);
    } catch {
        // Nothing else is required when browser storage is unavailable.
    }
}
