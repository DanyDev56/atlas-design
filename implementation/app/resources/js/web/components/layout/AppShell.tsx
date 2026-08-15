import { useCallback, useEffect, useRef, useState } from 'react';
import { Link, NavLink, Outlet, useLocation } from 'react-router-dom';
import { fetchUnreadCount } from '@/api/auth';
import { useAuth } from '@/hooks/useAuth';

export interface AppShellOutletContext {
    refreshUnreadCount: () => Promise<void>;
}

const navItems = [
    { to: '/app', label: 'Dashboard', end: true, disabled: false },
    { to: '/app/crm', label: 'CRM', end: false, disabled: false },
    { to: '/app/billing', label: 'Facturation', end: false, disabled: false },
    { to: '/app/health', label: 'Santé', end: false, disabled: false },
    { to: '/app/advisor', label: 'Advisor', end: false, disabled: false },
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
                    onClick={() => void logout()}
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
    const [unreadCount, setUnreadCount] = useState<number | null>(null);
    const [notificationsUnavailable, setNotificationsUnavailable] = useState(false);
    const { session } = useAuth();
    const location = useLocation();
    const menuButtonRef = useRef<HTMLButtonElement>(null);
    const closeButtonRef = useRef<HTMLButtonElement>(null);
    const mobileDialogRef = useRef<HTMLElement>(null);
    const token = session?.token ?? null;
    const workspaceId = session?.workspaceId ?? null;

    const refreshUnreadCount = useCallback(async () => {
        if (!token || !workspaceId) {
            setUnreadCount(null);
            setNotificationsUnavailable(false);
            return;
        }

        try {
            const result = await fetchUnreadCount(token, workspaceId);
            setUnreadCount(result.unread_count);
            setNotificationsUnavailable(false);
        } catch {
            setUnreadCount(null);
            setNotificationsUnavailable(true);
        }
    }, [token, workspaceId]);

    const closeMobileNav = useCallback(() => {
        setMobileNavOpen(false);
        window.requestAnimationFrame(() => menuButtonRef.current?.focus());
    }, []);

    const completeMobileNavigation = useCallback(() => {
        setMobileNavOpen(false);
        window.requestAnimationFrame(() => document.getElementById('main-content')?.focus());
    }, []);

    useEffect(() => {
        setMobileNavOpen(false);
    }, [location.pathname]);

    useEffect(() => {
        void refreshUnreadCount();
    }, [location.pathname, refreshUnreadCount]);

    useEffect(() => {
        if (!mobileNavOpen) return;

        const previousOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        closeButtonRef.current?.focus();

        function onKeyDown(event: KeyboardEvent) {
            if (event.key === 'Escape') {
                closeMobileNav();
                return;
            }

            if (event.key !== 'Tab') return;

            const focusable = mobileDialogRef.current?.querySelectorAll<HTMLElement>(
                'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled])',
            );
            if (!focusable?.length) return;

            const first = focusable[0];
            const last = focusable[focusable.length - 1];

            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        }

        document.addEventListener('keydown', onKeyDown);

        return () => {
            document.body.style.overflow = previousOverflow;
            document.removeEventListener('keydown', onKeyDown);
        };
    }, [mobileNavOpen, closeMobileNav]);

    return (
        <div className="flex min-h-screen">
            <a
                href="#main-content"
                className="sr-only fixed left-4 top-4 z-50 rounded-lg bg-white px-4 py-2 font-semibold text-atlas-ink shadow-lg focus:not-sr-only"
            >
                Aller au contenu
            </a>
            <aside className="hidden w-60 shrink-0 flex-col bg-atlas-sidebar px-4 py-6 text-white md:flex">
                <SidebarNav />
            </aside>

            {mobileNavOpen && (
                <div className="fixed inset-0 z-40 md:hidden">
                    <button
                        type="button"
                        aria-label="Fermer le menu"
                        aria-hidden="true"
                        tabIndex={-1}
                        className="absolute inset-0 bg-black/40"
                        onClick={closeMobileNav}
                    />
                    <aside
                        ref={mobileDialogRef}
                        id="mobile-navigation"
                        role="dialog"
                        aria-modal="true"
                        aria-label="Navigation principale"
                        className="relative flex h-full w-72 max-w-[85vw] flex-col bg-atlas-sidebar px-4 py-6 text-white shadow-xl"
                    >
                        <button
                            ref={closeButtonRef}
                            type="button"
                            onClick={closeMobileNav}
                            className="mb-5 ml-auto rounded-lg border border-white/15 px-3 py-2 text-sm font-medium text-white/80 hover:bg-white/10 hover:text-white"
                        >
                            Fermer
                        </button>
                        <SidebarNav onNavigate={completeMobileNavigation} />
                    </aside>
                </div>
            )}

            <div className="flex min-w-0 flex-1 flex-col">
                <header className="flex items-center justify-between gap-4 border-b border-atlas-border bg-atlas-card px-4 py-4 md:px-8">
                    <div className="flex items-center gap-3">
                        <button
                            ref={menuButtonRef}
                            type="button"
                            aria-label="Ouvrir le menu"
                            aria-controls="mobile-navigation"
                            aria-expanded={mobileNavOpen}
                            className="rounded-lg border border-atlas-border px-3 py-2 text-sm font-medium text-atlas-ink md:hidden"
                            onClick={() => setMobileNavOpen(true)}
                        >
                            Menu
                        </button>
                        <div>
                            <p className="text-xs font-medium uppercase tracking-wide text-atlas-ink-muted">
                                Espace de travail
                            </p>
                            <p className="text-sm font-semibold text-atlas-ink">Actif</p>
                        </div>
                    </div>
                    <Link
                        to="/app/notifications"
                        aria-label={
                            notificationsUnavailable
                                ? 'Notifications temporairement indisponibles'
                                : unreadCount && unreadCount > 0
                                  ? `${unreadCount} notification${unreadCount > 1 ? 's' : ''} non lue${unreadCount > 1 ? 's' : ''}`
                                  : 'Notifications'
                        }
                        className="inline-flex min-h-11 items-center gap-2 rounded-xl border border-atlas-border bg-white px-3 py-2 text-sm font-semibold text-atlas-ink transition-colors hover:bg-slate-50"
                    >
                        <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" className="h-5 w-5" stroke="currentColor" strokeWidth="1.8">
                            <path strokeLinecap="round" strokeLinejoin="round" d="M15 17H9m9-2V11a6 6 0 0 0-12 0v4l-2 2h16l-2-2Zm-7 5h2" />
                        </svg>
                        <span className="hidden sm:inline">Notifications</span>
                        {unreadCount !== null && unreadCount > 0 && (
                            <span className="min-w-5 rounded-full bg-atlas-accent px-1.5 py-0.5 text-center text-xs font-bold tabular-nums text-white">
                                {unreadCount > 99 ? '99+' : unreadCount}
                            </span>
                        )}
                        {notificationsUnavailable && (
                            <span className="text-xs font-bold text-amber-700" title="Compteur indisponible">—</span>
                        )}
                    </Link>
                </header>

                <main id="main-content" tabIndex={-1} className="flex-1 overflow-auto p-4 md:p-8">
                    <Outlet context={{ refreshUnreadCount } satisfies AppShellOutletContext} />
                </main>
            </div>
        </div>
    );
}
