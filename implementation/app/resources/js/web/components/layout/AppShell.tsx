import { useCallback, useEffect, useRef, useState } from 'react';
import { Link, NavLink, Outlet, useLocation } from 'react-router-dom';
import { fetchUnreadCount } from '@/api/auth';
import { getWorkspaceSummary } from '@/api/workspace';
import { Brand } from '@/components/ui/Brand';
import { Icon, type IconName } from '@/components/ui/Icon';
import { useAuth } from '@/hooks/useAuth';

export interface AppShellOutletContext {
    refreshUnreadCount: () => Promise<void>;
    refreshWorkspaceSummary: () => Promise<void>;
}

const navItems = [
    { to: '/app', label: 'Vue d’ensemble', shortLabel: 'Accueil', icon: 'dashboard' as IconName, end: true },
    { to: '/app/crm', label: 'Clients & ventes', shortLabel: 'CRM', icon: 'crm' as IconName, end: false },
    { to: '/app/billing', label: 'Facturation', shortLabel: 'Facturation', icon: 'billing' as IconName, end: false },
    { to: '/app/health', label: 'Santé de l’activité', shortLabel: 'Santé', icon: 'activity' as IconName, end: false },
    { to: '/app/advisor', label: 'Conseils Atlas', shortLabel: 'Advisor', icon: 'advisor' as IconName, end: false },
    { to: '/app/settings', label: 'Paramètres', shortLabel: 'Paramètres', icon: 'settings' as IconName, end: false },
];

function SidebarNav({ onNavigate, workspaceName }: { onNavigate?: () => void; workspaceName: string | null }) {
    const { session, logout } = useAuth();
    const userInitial = session?.email?.charAt(0).toLocaleUpperCase('fr-FR') ?? 'A';

    return (
        <>
            <div className="px-3 pb-7 pt-2">
                <Brand inverse />
            </div>

            <p className="mb-2 px-3 text-[10px] font-semibold uppercase tracking-[0.18em] text-white/35">Navigation</p>
            <nav className="flex flex-1 flex-col gap-1" aria-label="Navigation principale">
                {navItems.map((item) => (
                    <NavLink
                        key={item.to}
                        to={item.to}
                        end={item.end}
                        aria-label={item.shortLabel}
                        onClick={onNavigate}
                        className={({ isActive }) =>
                            `group flex min-h-11 items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium ${
                                isActive
                                    ? 'bg-white text-atlas-sidebar shadow-[0_8px_24px_rgb(0_0_0/0.16)]'
                                    : 'text-white/62 hover:bg-white/[0.07] hover:text-white'
                            }`
                        }
                    >
                        {({ isActive }) => (
                            <>
                                <Icon name={item.icon} className={`size-[19px] shrink-0 ${isActive ? 'text-atlas-accent' : 'text-white/46 group-hover:text-white/80'}`} />
                                <span className="truncate">{item.label}</span>
                                {isActive && <span className="ml-auto size-1.5 rounded-full bg-atlas-accent" aria-hidden="true" />}
                            </>
                        )}
                    </NavLink>
                ))}
            </nav>

            <div className="mt-6 rounded-2xl border border-white/[0.08] bg-white/[0.045] p-3">
                <Link to="/app/settings" onClick={onNavigate} className="group flex min-w-0 items-center gap-3">
                    <span className="grid size-9 shrink-0 place-items-center rounded-xl bg-atlas-accent text-xs font-bold text-white">
                        {userInitial}
                    </span>
                    <span className="min-w-0">
                        <span className="block truncate text-xs font-semibold text-white/90">Espace · {workspaceName ?? 'Atlas'}</span>
                        <span className="mt-0.5 block truncate text-[11px] text-white/42">{session?.email}</span>
                    </span>
                    <Icon name="chevron-right" className="ml-auto size-4 shrink-0 text-white/25 group-hover:text-white/70" />
                </Link>
                <button
                    type="button"
                    onClick={() => void logout()}
                    className="mt-3 flex w-full items-center gap-2 rounded-xl border-t border-white/[0.07] px-2 pt-3 text-left text-xs font-medium text-white/45 hover:text-white"
                >
                    <Icon name="logout" className="size-4" />
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
    const [workspaceName, setWorkspaceName] = useState<string | null>(null);
    const [workspaceNameUnavailable, setWorkspaceNameUnavailable] = useState(false);
    const { session } = useAuth();
    const location = useLocation();
    const menuButtonRef = useRef<HTMLButtonElement>(null);
    const closeButtonRef = useRef<HTMLButtonElement>(null);
    const mobileDialogRef = useRef<HTMLElement>(null);
    const workspaceRequestIdRef = useRef(0);
    const token = session?.token ?? null;
    const workspaceId = session?.workspaceId ?? null;
    const currentSection = navItems.find((item) => item.end
        ? location.pathname === item.to
        : location.pathname.startsWith(item.to));

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

    const refreshWorkspaceSummary = useCallback(async () => {
        const requestId = workspaceRequestIdRef.current + 1;
        workspaceRequestIdRef.current = requestId;

        if (!token || !workspaceId) {
            setWorkspaceName(null);
            setWorkspaceNameUnavailable(false);
            return;
        }

        try {
            const summary = await getWorkspaceSummary(token, workspaceId);
            if (workspaceRequestIdRef.current !== requestId) return;

            setWorkspaceName(summary.display_name);
            setWorkspaceNameUnavailable(false);
        } catch {
            if (workspaceRequestIdRef.current === requestId) setWorkspaceNameUnavailable(true);
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
        setWorkspaceName(null);
        setWorkspaceNameUnavailable(false);
        void refreshWorkspaceSummary();

        return () => { workspaceRequestIdRef.current += 1; };
    }, [refreshWorkspaceSummary]);

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
        <div className="flex min-h-screen bg-atlas-surface">
            <a
                href="#main-content"
                className="sr-only fixed left-4 top-4 z-[60] rounded-xl bg-white px-4 py-2 font-semibold text-atlas-ink shadow-lg focus:not-sr-only"
            >
                Aller au contenu
            </a>
            <aside className="hidden w-[17rem] shrink-0 flex-col bg-atlas-sidebar p-3 text-white md:flex">
                <SidebarNav workspaceName={workspaceName} />
            </aside>

            {mobileNavOpen && (
                <div className="fixed inset-0 z-40 md:hidden">
                    <button
                        type="button"
                        aria-label="Fermer le menu"
                        aria-hidden="true"
                        tabIndex={-1}
                        className="absolute inset-0 bg-atlas-sidebar/65 backdrop-blur-sm"
                        onClick={closeMobileNav}
                    />
                    <aside
                        ref={mobileDialogRef}
                        id="mobile-navigation"
                        role="dialog"
                        aria-modal="true"
                        aria-label="Navigation principale"
                        className="relative flex h-full w-[18rem] max-w-[88vw] flex-col bg-atlas-sidebar p-3 text-white shadow-2xl"
                    >
                        <button
                            ref={closeButtonRef}
                            type="button"
                            onClick={closeMobileNav}
                            className="mb-2 ml-auto grid size-10 place-items-center rounded-xl border border-white/10 text-white/65 hover:bg-white/10 hover:text-white"
                        >
                            <Icon name="close" className="size-5" />
                        </button>
                        <SidebarNav onNavigate={completeMobileNavigation} workspaceName={workspaceName} />
                    </aside>
                </div>
            )}

            <div className="atlas-shell-main flex min-w-0 flex-1 flex-col">
                <header className="sticky top-0 z-30 flex min-h-[4.5rem] items-center justify-between gap-4 border-b border-atlas-border/80 bg-white/80 px-4 backdrop-blur-xl sm:px-6 lg:px-10">
                    <div className="flex items-center gap-3">
                        <button
                            ref={menuButtonRef}
                            type="button"
                            aria-label="Ouvrir le menu"
                            aria-controls="mobile-navigation"
                            aria-expanded={mobileNavOpen}
                            className="grid size-10 place-items-center rounded-xl border border-atlas-border bg-white text-atlas-ink shadow-sm md:hidden"
                            onClick={() => setMobileNavOpen(true)}
                        >
                            <Icon name="menu" className="size-5" />
                        </button>
                        <div className="hidden min-w-0 sm:block">
                            <p className="text-[11px] font-medium text-atlas-ink-muted">Atlas Pilotage</p>
                            <p className="truncate text-sm font-semibold text-atlas-ink">{currentSection?.shortLabel ?? 'Votre espace'}</p>
                        </div>
                        <div className="md:hidden"><Brand compact /></div>
                    </div>
                    <div className="flex items-center gap-2">
                        <Link
                            to="/app/settings"
                            className="hidden max-w-52 items-center gap-2 rounded-xl border border-atlas-border/80 bg-white/70 px-3 py-2 text-sm hover:border-atlas-accent/30 hover:bg-white lg:flex"
                            title={workspaceName ?? undefined}
                        >
                            <span className="size-2 rounded-full bg-atlas-accent" aria-hidden="true" />
                            <span className="truncate font-medium text-atlas-ink">
                                {workspaceName ?? (workspaceNameUnavailable ? 'Espace indisponible' : 'Espace Atlas')}
                            </span>
                            <Icon name="chevron-right" className="size-3.5 text-atlas-ink-muted" />
                        </Link>
                        <Link
                            to="/app/notifications"
                            aria-label={
                                notificationsUnavailable
                                    ? 'Notifications temporairement indisponibles'
                                    : unreadCount && unreadCount > 0
                                      ? `${unreadCount} notification${unreadCount > 1 ? 's' : ''} non lue${unreadCount > 1 ? 's' : ''}`
                                      : 'Notifications'
                            }
                            className="relative grid size-11 place-items-center rounded-xl border border-atlas-border/80 bg-white text-atlas-ink shadow-sm hover:border-atlas-accent/30 hover:text-atlas-accent"
                        >
                            <Icon name="bell" className="size-5" />
                            {unreadCount !== null && unreadCount > 0 && (
                                <span className="absolute -right-1 -top-1 grid min-w-5 place-items-center rounded-full border-2 border-white bg-atlas-accent px-1 text-[10px] font-bold leading-4 text-white">
                                    {unreadCount > 99 ? '99+' : unreadCount}
                                </span>
                            )}
                            {notificationsUnavailable && (
                                <span className="absolute right-1 top-1 size-2 rounded-full bg-amber-500" title="Compteur indisponible" />
                            )}
                        </Link>
                    </div>
                </header>

                <main id="main-content" tabIndex={-1} className="flex-1 overflow-auto p-4 sm:p-6 lg:p-10 xl:p-12">
                    <Outlet context={{ refreshUnreadCount, refreshWorkspaceSummary } satisfies AppShellOutletContext} />
                </main>
            </div>
        </div>
    );
}
