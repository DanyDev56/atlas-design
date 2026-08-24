import {
    createContext,
    useCallback,
    useContext,
    useEffect,
    useMemo,
    useState,
    type ReactNode,
} from 'react';
import {
    fetchOperatorSessionContext,
    elevateOperatorSession,
    loginOperator as requestOperatorLogin,
    revokeOperatorSession,
} from '@/api/operator';

const STORAGE_KEY = 'atlas_operator_session';

export interface OperatorSessionState {
    sessionId: string;
    userId: string;
    displayName: string;
    token: string;
    expiresAt: string;
    permissions: string[];
    readOnly: boolean;
    actionsEnabled: boolean;
    authenticationStrength: string;
    mfaVerifiedAt: string | null;
    stepUpExpiresAt: string | null;
}

interface OperatorAuthContextValue {
    session: OperatorSessionState | null;
    resolving: boolean;
    login: (email: string, password: string, mfaCode: string) => Promise<void>;
    stepUp: (email: string, password: string, mfaCode: string) => Promise<void>;
    logout: () => Promise<void>;
}

const OperatorAuthContext = createContext<OperatorAuthContextValue | null>(null);

function loadSession(): OperatorSessionState | null {
    try {
        const raw = localStorage.getItem(STORAGE_KEY);
        if (!raw) return null;
        const parsed = JSON.parse(raw) as OperatorSessionState;
        if (!parsed.token || Date.parse(parsed.expiresAt) <= Date.now()) {
            localStorage.removeItem(STORAGE_KEY);
            return null;
        }

        return parsed;
    } catch {
        localStorage.removeItem(STORAGE_KEY);
        return null;
    }
}

function persistSession(session: OperatorSessionState | null): void {
    if (session) {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(session));
    } else {
        localStorage.removeItem(STORAGE_KEY);
    }
}

export function OperatorAuthProvider({ children }: { children: ReactNode }) {
    const [session, setSession] = useState<OperatorSessionState | null>(() => loadSession());
    const [resolving, setResolving] = useState(() => loadSession() !== null);

    useEffect(() => {
        if (!session?.token) {
            setResolving(false);
            return;
        }

        let cancelled = false;
        const current = session;
        setResolving(true);

        void fetchOperatorSessionContext(current.token)
            .then((context) => {
                if (cancelled) return;
                const next: OperatorSessionState = {
                    ...current,
                    sessionId: context.session_id,
                    userId: context.user_id,
                    expiresAt: context.expires_at,
                    permissions: context.permissions,
                    readOnly: context.read_only,
                    actionsEnabled: context.actions_enabled,
                    authenticationStrength: context.authentication_strength,
                    mfaVerifiedAt: context.mfa_verified_at,
                    stepUpExpiresAt: context.step_up_expires_at,
                };
                setSession(next);
                persistSession(next);
            })
            .catch(() => {
                if (cancelled) return;
                setSession(null);
                persistSession(null);
            })
            .finally(() => {
                if (!cancelled) setResolving(false);
            });

        return () => {
            cancelled = true;
        };
        // Validate only when the token changes. Updating the refreshed context
        // must not start an authentication loop.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [session?.token]);

    const login = useCallback(async (email: string, password: string, mfaCode: string): Promise<void> => {
        const result = await requestOperatorLogin(email, password, mfaCode);
        const next: OperatorSessionState = {
            sessionId: result.session_id,
            userId: result.user_id,
            displayName: result.display_name,
            token: result.token,
            expiresAt: result.expires_at,
            permissions: result.permissions,
            readOnly: true,
            actionsEnabled: false,
            authenticationStrength: result.authentication_strength,
            mfaVerifiedAt: result.mfa_verified_at,
            stepUpExpiresAt: result.step_up_expires_at,
        };
        setSession(next);
        persistSession(next);
    }, []);

    const stepUp = useCallback(async (email: string, password: string, mfaCode: string): Promise<void> => {
        if (!session?.token) throw new Error('Session opérateur absente.');
        const result = await elevateOperatorSession(session.token, email, password, mfaCode);
        const next: OperatorSessionState = {
            ...session,
            authenticationStrength: result.authentication_strength,
            mfaVerifiedAt: result.mfa_verified_at,
            stepUpExpiresAt: result.step_up_expires_at,
        };
        setSession(next);
        persistSession(next);
    }, [session]);

    const logout = useCallback(async (): Promise<void> => {
        const token = session?.token;
        try {
            if (token) await revokeOperatorSession(token);
        } finally {
            setSession(null);
            persistSession(null);
        }
    }, [session?.token]);

    const value = useMemo<OperatorAuthContextValue>(() => ({
        session,
        resolving,
        login,
        stepUp,
        logout,
    }), [session, resolving, login, stepUp, logout]);

    return <OperatorAuthContext.Provider value={value}>{children}</OperatorAuthContext.Provider>;
}

export function useOperatorAuth(): OperatorAuthContextValue {
    const context = useContext(OperatorAuthContext);
    if (!context) throw new Error('useOperatorAuth must be used within OperatorAuthProvider');

    return context;
}
