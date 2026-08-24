import { ReactNode, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { Brand } from '@/components/ui/Brand';
import { Icon } from '@/components/ui/Icon';
import { useOperatorAuth } from '@/hooks/useOperatorAuth';

export function OperatorFrame({ children }: { children: ReactNode }) {
    const { session, logout } = useOperatorAuth();
    const navigate = useNavigate();
    const [loggingOut, setLoggingOut] = useState(false);

    async function onLogout() {
        setLoggingOut(true);
        try {
            await logout();
        } finally {
            navigate('/backoffice/login', { replace: true });
        }
    }

    return (
        <div className="min-h-screen bg-atlas-surface text-atlas-ink">
            <header className="border-b border-atlas-border bg-atlas-sidebar text-white">
                <div className="mx-auto flex min-h-20 max-w-[90rem] items-center justify-between gap-4 px-5 sm:px-8 lg:px-10">
                    <div className="flex items-center gap-5">
                        <Link to="/backoffice" aria-label="Retour au tableau de bord opérateur"><Brand inverse /></Link>
                        <span className="hidden h-6 w-px bg-white/15 sm:block" />
                        <span className="hidden text-xs font-semibold uppercase tracking-[.18em] text-white/50 sm:block">Back-office</span>
                        <nav className="hidden items-center gap-1 lg:flex" aria-label="Navigation opérateur">
                            <Link to="/backoffice" className="rounded-lg px-3 py-2 text-sm font-medium text-white/70 hover:bg-white/10 hover:text-white">Vue d’ensemble</Link>
                            {session?.permissions.includes('operations.dashboard.read') && <Link to="/backoffice/runtime" className="rounded-lg px-3 py-2 text-sm font-medium text-white/70 hover:bg-white/10 hover:text-white">Exploitation</Link>}
                            {session?.permissions.includes('operations.beta.read') && <Link to="/backoffice/beta" className="rounded-lg px-3 py-2 text-sm font-medium text-white/70 hover:bg-white/10 hover:text-white">Cohorte beta</Link>}
                            {session?.permissions.includes('operations.subscriptions.read') && <Link to="/backoffice/subscriptions" className="rounded-lg px-3 py-2 text-sm font-medium text-white/70 hover:bg-white/10 hover:text-white">Abonnements</Link>}
                            <Link to="/backoffice/security" className="rounded-lg px-3 py-2 text-sm font-medium text-white/70 hover:bg-white/10 hover:text-white">Sécurité</Link>
                        </nav>
                    </div>
                    <div className="flex items-center gap-3">
                        <Link to="/backoffice/security" aria-label="Sécurité opérateur" className="grid size-10 place-items-center rounded-xl border border-white/15 text-white/75 hover:bg-white/10 lg:hidden"><Icon name="lock" className="size-4" /></Link>
                        <span className="hidden text-sm text-white/60 sm:inline">{session?.displayName}</span>
                        <button
                            type="button"
                            disabled={loggingOut}
                            onClick={() => void onLogout()}
                            className="inline-flex min-h-10 items-center gap-2 rounded-xl border border-white/15 px-3.5 py-2 text-sm font-semibold text-white/80 hover:bg-white/10 disabled:opacity-50"
                        >
                            <Icon name="logout" className="size-4" />
                            <span className="hidden sm:inline">Déconnexion</span>
                        </button>
                    </div>
                </div>
            </header>
            {children}
        </div>
    );
}
