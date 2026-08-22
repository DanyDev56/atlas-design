import { useEffect, useState } from 'react';
import { Link, Navigate } from 'react-router-dom';
import { fetchDashboard } from '@/api/auth';
import { AdvisorPriorityWidget } from '@/components/dashboard/AdvisorPriorityWidget';
import { BillingWidget } from '@/components/dashboard/BillingWidget';
import { BusinessHealthWidget } from '@/components/dashboard/BusinessHealthWidget';
import { MeasuredActivityWidget } from '@/components/dashboard/MeasuredActivityWidget';
import { PipelineWidget } from '@/components/dashboard/PipelineWidget';
import { PageSkeleton } from '@/components/ui/PageSkeleton';
import { useAuth } from '@/hooks/useAuth';
import type { DashboardResponse } from '@/types/api';

export function DashboardPage() {
    const { session } = useAuth();
    const [dashboard, setDashboard] = useState<DashboardResponse | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [loading, setLoading] = useState(true);
    const [reloadKey, setReloadKey] = useState(0);

    const token = session?.token ?? null;
    const workspaceId = session?.workspaceId ?? null;

    useEffect(() => {
        if (!token || !workspaceId) {
            setLoading(false);
            return;
        }

        let cancelled = false;
        const activeToken = token;
        const activeWorkspaceId = workspaceId;

        async function load() {
            setLoading(true);
            setError(null);
            try {
                const data = await fetchDashboard(activeToken, activeWorkspaceId);
                if (!cancelled) setDashboard(data);
            } catch (err) {
                if (!cancelled) {
                    setError(err instanceof Error ? err.message : 'Chargement impossible');
                }
            } finally {
                if (!cancelled) setLoading(false);
            }
        }

        void load();

        return () => {
            cancelled = true;
        };
    }, [token, workspaceId, reloadKey]);

    if (!session) {
        return <Navigate to="/app/login" replace />;
    }

    if (!workspaceId) {
        return <Navigate to="/app/onboarding" replace />;
    }

    const unread = dashboard?.notifications.payload?.unread_count ?? 0;
    const firstName = session.email?.split('@')[0];
    const displayName = firstName ? firstName.charAt(0).toLocaleUpperCase('fr-FR') + firstName.slice(1) : null;
    const today = new Intl.DateTimeFormat('fr-FR', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
    }).format(new Date());

    return (
        <div className="mx-auto max-w-6xl">
            <div className="mb-8 flex flex-wrap items-end justify-between gap-5">
                <div>
                    <p className="text-sm font-medium capitalize text-atlas-accent">{today}</p>
                    <h2 className="mt-1 text-3xl font-semibold tracking-tight text-atlas-ink sm:text-4xl">
                        Bonjour{displayName ? `, ${displayName}` : ''}
                    </h2>
                    <p className="mt-2 max-w-xl text-sm leading-relaxed text-atlas-ink-muted">
                        Voici ce qui mérite votre attention et où en est votre activité aujourd’hui.
                    </p>
                </div>
                {unread > 0 && (
                    <Link
                        to="/app/notifications"
                        className="inline-flex min-h-11 items-center gap-2 rounded-full bg-atlas-accent-soft px-4 py-2 text-sm font-semibold text-atlas-accent transition-colors hover:bg-atlas-accent hover:text-white"
                    >
                        <span className="h-2 w-2 rounded-full bg-atlas-accent" aria-hidden="true" />
                        {unread} notification{unread > 1 ? 's' : ''} non lue{unread > 1 ? 's' : ''}
                    </Link>
                )}
            </div>

            {loading && <PageSkeleton rows={4} variant="cards" />}

            {error && (
                <div
                    role="alert"
                    className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"
                >
                    <span>{error}</span>
                    <button
                        type="button"
                        onClick={() => setReloadKey((value) => value + 1)}
                        className="font-semibold underline underline-offset-2"
                    >
                        Réessayer
                    </button>
                </div>
            )}

            {dashboard && !loading && (
                <div className="grid gap-6 md:grid-cols-2">
                    <div className="md:col-span-2">
                        <AdvisorPriorityWidget widget={dashboard.advisor_priority} />
                    </div>
                    <BusinessHealthWidget widget={dashboard.business_health} />
                    <PipelineWidget widget={dashboard.pipeline} />
                    <div className="md:col-span-2">
                        <MeasuredActivityWidget widget={dashboard.measured_activity} />
                    </div>
                    <div id="billing" className="scroll-mt-6 md:col-span-2">
                        <BillingWidget widget={dashboard.billing} />
                    </div>
                </div>
            )}
        </div>
    );
}
