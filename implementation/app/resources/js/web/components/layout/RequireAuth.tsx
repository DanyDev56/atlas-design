import { Navigate } from 'react-router-dom';
import type { ReactNode } from 'react';
import { useAuth } from '@/hooks/useAuth';

export function RequireAuth({ children }: { children: ReactNode }) {
    const { isAuthenticated, session } = useAuth();

    if (!isAuthenticated) {
        return <Navigate to="/app/login" replace />;
    }

    if (!session?.workspaceId) {
        return <Navigate to="/app/onboarding" replace />;
    }

    return children;
}
