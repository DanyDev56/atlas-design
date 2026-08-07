import { useState } from 'react';
import { NavLink, Outlet } from 'react-router-dom';
import { useAuth } from '@/hooks/useAuth';

const navItems = [
    { to: '/app', label: 'Dashboard', end: true },
    { to: '/app/crm', label: 'CRM', end: false },
    { to: '/app/billing', label: 'Billing', disabled: true },
];

function SidebarNav({ onNavigate }: { onNavigate?: () => void }) {
    const { session, logout } = useAuth();

    return (
        <>
            <div className="mb-8 px-2 md:mb-10">
                <p className="text-xs font-semibold uppercase tracking-[0.2em] text-white/50">Atlas</p>
                <h1 className="mt-1 text-xl font-semibold tracking-tight">Pilotage</h1>
            </div>

            <nav className="flex flex-1 flex-col gap-1">
                {navItems.map((item) =>
                    item.disabled ? (
                        <span
                            key={item.label}
                            className="rounded-lg px-3 py-2 text-sm text-white/35"
                            title="Bientôt disponible"
                        >
                            {item.label}
                        </span>
                    ) : (
                        <NavLink
                            key={item.to}
                            to={item.to}
                            end={item.end}
                            onClick={onNavigate}
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
        </>
    );
}

export function AppShell() {
    const [mobileNavOpen, setMobileNavOpen] = useState(false);

    return (
        <div className="flex min-h-screen">
            <aside className="hidden w-60 shrink-0 flex-col bg-atlas-sidebar px-4 py-6 text-white md:flex">
                <SidebarNav />
            </aside>

            {mobileNavOpen && (
                <div className="fixed inset-0 z-40 md:hidden">
                    <button
                        type="button"
                        aria-label="Fermer le menu"
                        className="absolute inset-0 bg-black/40"
                        onClick={() => setMobileNavOpen(false)}
                    />
                    <aside className="relative flex h-full w-72 max-w-[85vw] flex-col bg-atlas-sidebar px-4 py-6 text-white shadow-xl">
                        <SidebarNav onNavigate={() => setMobileNavOpen(false)} />
                    </aside>
                </div>
            )}

            <div className="flex min-w-0 flex-1 flex-col">
                <header className="flex items-center justify-between gap-4 border-b border-atlas-border bg-atlas-card px-4 py-4 md:px-8">
                    <div className="flex items-center gap-3">
                        <button
                            type="button"
                            aria-label="Ouvrir le menu"
                            className="rounded-lg border border-atlas-border px-3 py-2 text-sm font-medium text-atlas-ink md:hidden"
                            onClick={() => setMobileNavOpen(true)}
                        >
                            Menu
                        </button>
                        <div>
                            <p className="text-xs font-medium uppercase tracking-wide text-atlas-ink-muted">
                                Workspace
                            </p>
                            <p className="text-sm font-semibold text-atlas-ink">Actif</p>
                        </div>
                    </div>
                </header>

                <main className="flex-1 overflow-auto p-4 md:p-8">
                    <Outlet />
                </main>
            </div>
        </div>
    );
}
