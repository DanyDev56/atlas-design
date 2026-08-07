import {
    createContext,
    useCallback,
    useContext,
    useEffect,
    useMemo,
    useState,
    type ReactNode,
} from 'react';
import { bootstrapWorkspace, fetchSessionContext, login, register, verifyEmail } from '@/api/auth';
import type { SessionState } from '@/types/api';

const STORAGE_KEY = 'atlas_web_session';

interface AuthContextValue {
    session: SessionState | null;
    isAuthenticated: boolean;
    isResolvingWorkspace: boolean;
    loginWithPassword: (email: string, password: string) => Promise<SessionState>;
    registerAccount: (email: string, displayName: string, password: string) => Promise<void>;
    createWorkspace: (name: string) => Promise<void>;
    logout: () => void;
    setWorkspaceId: (workspaceId: string) => void;
}

const AuthContext = createContext<AuthContextValue | null>(null);

function loadSession(): SessionState | null {
    try {
        const raw = localStorage.getItem(STORAGE_KEY);
        if (!raw) return null;
        const parsed = JSON.parse(raw) as SessionState;
        if (!parsed.token || !parsed.userId) return null;
        return parsed;
    } catch {
        return null;
    }
}

function persistSession(session: SessionState | null): void {
    if (session) {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(session));
    } else {
        localStorage.removeItem(STORAGE_KEY);
    }
}

async function resolveWorkspaceId(token: string, userId: string, current: string | null): Promise<string | null> {
    if (current) return current;

    const context = await fetchSessionContext(token);
    if (context.user_id !== userId) return null;

    return context.workspace_id;
}

export function AuthProvider({ children }: { children: ReactNode }) {
    const [session, setSession] = useState<SessionState | null>(() => loadSession());
    const [isResolvingWorkspace, setIsResolvingWorkspace] = useState(false);

    const setWorkspaceId = useCallback((workspaceId: string) => {
        setSession((current) => {
            if (!current) return current;
            const next = { ...current, workspaceId };
            persistSession(next);
            return next;
        });
    }, []);

    useEffect(() => {
        if (!session?.token || session.workspaceId) return;

        let cancelled = false;
        setIsResolvingWorkspace(true);

        void resolveWorkspaceId(session.token, session.userId, session.workspaceId)
            .then((workspaceId) => {
                if (!cancelled && workspaceId) {
                    setWorkspaceId(workspaceId);
                }
            })
            .finally(() => {
                if (!cancelled) setIsResolvingWorkspace(false);
            });

        return () => {
            cancelled = true;
        };
    }, [session?.token, session?.userId, session?.workspaceId, setWorkspaceId]);

    const loginWithPassword = useCallback(async (email: string, password: string): Promise<SessionState> => {
        const result = await login(email, password);
        const previous = loadSession();
        const preservedWorkspaceId =
            previous?.userId === result.user_id ? (previous.workspaceId ?? null) : null;

        const workspaceId = await resolveWorkspaceId(result.token, result.user_id, preservedWorkspaceId);

        const next: SessionState = {
            token: result.token,
            userId: result.user_id,
            workspaceId,
            email,
        };
        setSession(next);
        persistSession(next);
        return next;
    }, []);

    const registerAccount = useCallback(async (email: string, displayName: string, password: string) => {
        const result = await register(email, displayName, password);
        if (result.verification_token) {
            await verifyEmail(result.user_id, result.verification_token);
        }
        await loginWithPassword(email, password);
    }, [loginWithPassword]);

    const createWorkspace = useCallback(async (name: string) => {
        if (!session?.token) throw new Error('Session required');
        const result = await bootstrapWorkspace(session.token, name);
        const next: SessionState = { ...session, workspaceId: result.workspace_id };
        setSession(next);
        persistSession(next);
    }, [session]);

    const logout = useCallback(() => {
        setSession(null);
        persistSession(null);
    }, []);

    const value = useMemo<AuthContextValue>(() => ({
        session,
        isAuthenticated: session !== null,
        isResolvingWorkspace,
        loginWithPassword,
        registerAccount,
        createWorkspace,
        logout,
        setWorkspaceId,
    }), [session, isResolvingWorkspace, loginWithPassword, registerAccount, createWorkspace, logout, setWorkspaceId]);

    return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth(): AuthContextValue {
    const ctx = useContext(AuthContext);
    if (!ctx) throw new Error('useAuth must be used within AuthProvider');
    return ctx;
}

export function useSessionToken(): string | null {
    return useAuth().session?.token ?? null;
}

export function useWorkspaceId(): string | null {
    return useAuth().session?.workspaceId ?? null;
}
