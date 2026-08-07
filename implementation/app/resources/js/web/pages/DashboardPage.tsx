import { useEffect, useState } from 'react';
import { Navigate } from 'react-router-dom';
import { fetchDashboard } from '@/api/auth';
import { AdvisorPriorityWidget } from '@/components/dashboard/AdvisorPriorityWidget';
import { BillingWidget } from '@/components/dashboard/BillingWidget';
import { BusinessHealthWidget } from '@/components/dashboard/BusinessHealthWidget';
import { PipelineWidget } from '@/components/dashboard/PipelineWidget';
import { useAuth } from '@/hooks/useAuth';
import type { DashboardResponse } from '@/types/api';

export function DashboardPage() {
    const { session, isAuthenticated } = useAuth();
    const [dashboard, setDashboard] = useState<DashboardResponse | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [loading, setLoading] = useState(true);

    const token = session?.token ?? null;
    const workspaceId = session?.workspaceId ?? null;

    useEffect(() => {
        if (!token || !workspaceId) {
            setLoading(false);
            return;
        }

        let cancelled = false;

        async function load() {
            setLoading(true);
            setError(null);
            try {
                const data = await fetchDashboard(token!, workspaceId!);
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
    }, [token, workspaceId]);

    if (!isAuthenticated) {
        return <Navigate to="/app/login" replace />;
    }

    if (!workspaceId) {
        return <Navigate to="/app/onboarding" replace />;
    }

    const unread = dashboard?.notifications.payload?.unread_count ?? 0;

    return (
        <div className="mx-auto max-w-6xl">
            <div className="mb-8 flex flex-wrap items-end justify-between gap-4">
                <div>
                    <p className="text-sm font-medium text-atlas-accent">Tableau de bord</p>
                    <h2 className="mt-1 text-3xl font-semibold tracking-tight text-atlas-ink">
                        Bonjour{session.email ? `, ${session.email.split('@')[0]}` : ''}
                    </h2>
                    <p className="mt-2 max-w-xl text-sm leading-relaxed text-atlas-ink-muted">
                        Priorité, santé et cycle commercial — composés depuis vos modules, sans recalcul
                        côté interface.
                    </p>
                </div>
                {unread > 0 && (
                    <span className="rounded-full bg-atlas-accent px-3 py-1 text-sm font-medium text-white">
                        {unread} notification{unread > 1 ? 's' : ''} non lue{unread > 1 ? 's' : ''}
                    </span>
                )}
            </div>

            {loading && (
                <div className="grid gap-6 md:grid-cols-2">
                    {[1, 2, 3, 4].map((i) => (
                        <div key={i} className="h-48 animate-pulse rounded-2xl bg-white shadow-sm" />
                    ))}
                </div>
            )}

            {error && (
                <div className="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                    {error}
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
                        <BillingWidget widget={dashboard.billing} />
                    </div>
                </div>
            )}
        </div>
    );
}
