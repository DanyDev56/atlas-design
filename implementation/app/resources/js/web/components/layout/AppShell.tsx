import { NavLink, Outlet } from 'react-router-dom';
import { useAuth } from '@/hooks/useAuth';

const navItems = [
    { to: '/app', label: 'Dashboard', end: true },
    { to: '/app/crm', label: 'CRM', disabled: true },
    { to: '/app/billing', label: 'Billing', disabled: true },
];

export function AppShell() {
    const { session, logout } = useAuth();

    return (
        <div className="flex min-h-screen">
            <aside className="flex w-60 shrink-0 flex-col bg-atlas-sidebar px-4 py-6 text-white">
                <div className="mb-10 px-2">
                    <p className="text-xs font-semibold uppercase tracking-[0.2em] text-white/50">Atlas</p>
                    <h1 className="mt-1 text-xl font-semibold tracking-tight">Pilotage</h1>
                </div>

                <nav className="flex flex-1 flex-col gap-1">
                    {navItems.map((item) =>
                        item.disabled ? (
                            <span
                                key={item.label}
                                className="rounded-lg px-3 py-2 text-sm text-white/35"
                                title="Lot 2 — bientôt"
                            >
                                {item.label}
                            </span>
                        ) : (
                            <NavLink
                                key={item.to}
                                to={item.to}
                                end={item.end}
                                className={({ isActive }) =>
                                    `rounded-lg px-3 py-2 text-sm font-medium transition-colors ${
                                        isActive
                                            ? 'bg-white/12 text-white'
                                            : 'text-white/70 hover:bg-white/8 hover:text-white'
                                    }`
                                }
                            >
                                {item.label}
                            </NavLink>
                        ),
                    )}
                </nav>

                <div className="mt-auto border-t border-white/10 pt-4 text-xs text-white/50">
                    <p className="truncate px-2">{session?.email}</p>
                    <button
                        type="button"
                        onClick={logout}
                        className="mt-2 w-full rounded-lg px-3 py-2 text-left text-sm text-white/70 hover:bg-white/8 hover:text-white"
                    >
                        Déconnexion
                    </button>
                </div>
            </aside>

            <div className="flex min-w-0 flex-1 flex-col">
                <header className="flex items-center justify-between border-b border-atlas-border bg-atlas-card px-8 py-4">
                    <div>
                        <p className="text-xs font-medium uppercase tracking-wide text-atlas-ink-muted">Workspace</p>
                        <p className="text-sm font-semibold text-atlas-ink">
                            {session?.workspaceId ? 'Actif' : 'Non configuré'}
                        </p>
                    </div>
                </header>

                <main className="flex-1 overflow-auto p-8">
                    <Outlet />
                </main>
            </div>
        </div>
    );
}
