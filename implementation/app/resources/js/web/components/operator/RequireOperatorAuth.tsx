import type { ReactNode } from 'react';
import { Navigate, useLocation } from 'react-router-dom';
import { Brand } from '@/components/ui/Brand';
import { useOperatorAuth } from '@/hooks/useOperatorAuth';

export function RequireOperatorAuth({ children }: { children: ReactNode }) {
    const { session, resolving } = useOperatorAuth();
    const location = useLocation();

    if (resolving) {
        return (
            <div className="grid min-h-screen place-items-center bg-atlas-sidebar text-white">
                <div className="text-center">
                    <Brand inverse />
                    <p className="mt-5 text-sm text-white/55">Vérification de la session opérateur…</p>
                </div>
            </div>
        );
    }

    if (!session) {
        return <Navigate to="/backoffice/login" replace state={{ from: location.pathname }} />;
    }

    return children;
}

