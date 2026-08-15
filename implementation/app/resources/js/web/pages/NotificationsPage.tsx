import { useEffect, useState } from 'react';
import { Link, useOutletContext } from 'react-router-dom';
import { listNotifications, markNotificationRead } from '@/api/notifications';
import { ErrorBanner } from '@/components/auth/AuthLayout';
import type { AppShellOutletContext } from '@/components/layout/AppShell';
import { RequireAuth } from '@/components/layout/RequireAuth';
import { EmptyState } from '@/components/ui/EmptyState';
import { PageSkeleton } from '@/components/ui/PageSkeleton';
import { useAuth } from '@/hooks/useAuth';
import type { NotificationSummary } from '@/types/api';
import { getRecommendationAction } from '@/utils/advisor';
import { formatPriority } from '@/utils/format';

type InboxFilter = 'all' | 'unread';

interface NotificationCopy {
    title: string;
    description: string;
}

const recommendationCopy: Record<string, NotificationCopy> = {
    'advisor.collect-overdue-invoices': {
        title: 'Des factures en retard méritent votre attention',
        description: 'Consultez les encours les plus anciens pour préparer vos prochaines relances.',
    },
    'advisor.reduce-client-concentration': {
        title: 'Votre activité dépend fortement d’un client',
        description: 'Développez une nouvelle opportunité pour mieux répartir votre chiffre d’affaires.',
    },
    'advisor.rebuild-commercial-pipeline': {
        title: 'Votre pipeline commercial doit être relancé',
        description: 'Ajoutez une opportunité qualifiée pour préparer les prochaines ventes.',
    },
    'advisor.restore-billing-momentum': {
        title: 'La facturation doit être remise en mouvement',
        description: 'Vérifiez les devis et factures qui attendent votre prochaine action.',
    },
    'advisor.address-primary-attention': {
        title: 'Un point fragile mérite votre attention',
        description: 'Consultez le module concerné avant de choisir la prochaine action utile.',
    },
};

function getNotificationCopy(notification: NotificationSummary): NotificationCopy {
    const recommendationKey = notification.content.recommendation_key;

    if (recommendationKey && recommendationCopy[recommendationKey]) {
        return recommendationCopy[recommendationKey];
    }

    return {
        title: 'Une nouvelle priorité est disponible',
        description: 'Consultez votre dashboard pour comprendre la prochaine action proposée.',
    };
}

function formatNotificationDate(value: string): string {
    const date = new Date(value);

    if (Number.isNaN(date.getTime())) return 'Date indisponible';

    return new Intl.DateTimeFormat('fr-FR', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(date);
}

function priorityClasses(priority: string): string {
    if (priority === 'Critical') return 'bg-red-50 text-red-800 ring-red-200';
    if (priority === 'High') return 'bg-amber-50 text-amber-900 ring-amber-200';

    return 'bg-slate-50 text-atlas-ink-muted ring-atlas-border';
}

export function NotificationsPage() {
    return (
        <RequireAuth>
            <NotificationsInbox />
        </RequireAuth>
    );
}

function NotificationsInbox() {
    const { session } = useAuth();
    const { refreshUnreadCount } = useOutletContext<AppShellOutletContext>();
    const token = session!.token;
    const workspaceId = session!.workspaceId!;
    const [notifications, setNotifications] = useState<NotificationSummary[]>([]);
    const [filter, setFilter] = useState<InboxFilter>('all');
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [actionError, setActionError] = useState<string | null>(null);
    const [markingId, setMarkingId] = useState<string | null>(null);

    async function loadInbox() {
        setLoading(true);
        setError(null);

        try {
            setNotifications(await listNotifications(token, workspaceId));
        } catch (err) {
            setError(err instanceof Error ? err.message : 'Chargement des notifications impossible');
        } finally {
            setLoading(false);
        }
    }

    useEffect(() => {
        void loadInbox();
    }, [token, workspaceId]);

    async function handleMarkRead(notification: NotificationSummary) {
        setMarkingId(notification.notification_id);
        setActionError(null);

        try {
            const result = await markNotificationRead(
                token,
                workspaceId,
                notification.notification_id,
                notification.revision,
            );
            setNotifications((current) => current.map((item) => (
                item.notification_id === notification.notification_id
                    ? { ...item, read_state: 'Read', revision: result.revision }
                    : item
            )));
            await refreshUnreadCount();
        } catch (err) {
            setActionError(err instanceof Error ? err.message : 'Mise à jour de la notification impossible');
        } finally {
            setMarkingId(null);
        }
    }

    const unreadCount = notifications.filter((notification) => notification.read_state === 'Unread').length;
    const visibleNotifications = filter === 'unread'
        ? notifications.filter((notification) => notification.read_state === 'Unread')
        : notifications;

    return (
            <div className="mx-auto max-w-4xl">
                <div>
                    <p className="text-xs font-semibold uppercase tracking-[0.18em] text-atlas-accent">Inbox</p>
                    <h2 className="mt-2 text-3xl font-semibold tracking-tight text-atlas-ink">Notifications</h2>
                    <p className="mt-2 max-w-2xl text-sm leading-relaxed text-atlas-ink-muted">
                        Retrouvez les priorités qui demandent votre attention et ouvrez directement le bon espace de travail.
                    </p>
                </div>

                {!loading && !error && notifications.length > 0 && (
                    <div className="mt-7 flex flex-wrap items-center justify-between gap-3 border-b border-atlas-border">
                        <div className="flex gap-1" role="group" aria-label="Filtrer les notifications">
                            <button
                                type="button"
                                aria-pressed={filter === 'all'}
                                onClick={() => setFilter('all')}
                                className={`border-b-2 px-3 py-3 text-sm font-semibold transition-colors ${
                                    filter === 'all'
                                        ? 'border-atlas-accent text-atlas-accent'
                                        : 'border-transparent text-atlas-ink-muted hover:text-atlas-ink'
                                }`}
                            >
                                Toutes <span className="ml-1 tabular-nums">{notifications.length}</span>
                            </button>
                            <button
                                type="button"
                                aria-pressed={filter === 'unread'}
                                onClick={() => setFilter('unread')}
                                className={`border-b-2 px-3 py-3 text-sm font-semibold transition-colors ${
                                    filter === 'unread'
                                        ? 'border-atlas-accent text-atlas-accent'
                                        : 'border-transparent text-atlas-ink-muted hover:text-atlas-ink'
                                }`}
                            >
                                Non lues <span className="ml-1 tabular-nums">{unreadCount}</span>
                            </button>
                        </div>
                    </div>
                )}

                {error && (
                    <div className="mt-7">
                        <ErrorBanner message={error} />
                        <button
                            type="button"
                            onClick={() => void loadInbox()}
                            className="mt-2 text-sm font-semibold text-atlas-accent hover:underline"
                        >
                            Réessayer
                        </button>
                    </div>
                )}

                {actionError && (
                    <div className="mt-7">
                        <ErrorBanner message={actionError} />
                    </div>
                )}

                {loading && <div className="mt-7"><PageSkeleton rows={4} /></div>}

                {!loading && !error && notifications.length === 0 && (
                    <div className="mt-7">
                        <EmptyState
                            title="Aucune notification"
                            description="Vos prochaines priorités apparaîtront ici lorsqu’une action utile sera identifiée."
                            action={
                                <Link
                                    to="/app"
                                    className="rounded-xl bg-atlas-accent px-4 py-2.5 text-sm font-semibold text-white hover:opacity-90"
                                >
                                    Retour au dashboard
                                </Link>
                            }
                        />
                    </div>
                )}

                {!loading && !error && notifications.length > 0 && visibleNotifications.length === 0 && (
                    <div className="mt-7">
                        <EmptyState
                            title="Tout est lu"
                            description="Vous n’avez aucune notification non lue pour le moment."
                            action={
                                <button
                                    type="button"
                                    onClick={() => setFilter('all')}
                                    className="rounded-xl border border-atlas-border bg-white px-4 py-2.5 text-sm font-semibold text-atlas-ink hover:bg-slate-50"
                                >
                                    Voir l’historique
                                </button>
                            }
                        />
                    </div>
                )}

                {!loading && !error && visibleNotifications.length > 0 && (
                    <ul className="mt-7 space-y-3" aria-label="Liste des notifications">
                        {visibleNotifications.map((notification) => {
                            const copy = getNotificationCopy(notification);
                            const action = getRecommendationAction(notification.content.route_key);
                            const unread = notification.read_state === 'Unread';

                            return (
                                <li
                                    key={notification.notification_id}
                                    className={`relative overflow-hidden rounded-2xl border bg-atlas-card p-5 shadow-sm sm:p-6 ${
                                        unread ? 'border-atlas-accent/30' : 'border-atlas-border'
                                    }`}
                                >
                                    {unread && (
                                        <span className="absolute inset-y-0 left-0 w-1 bg-atlas-accent" aria-hidden="true" />
                                    )}
                                    <div className="flex flex-wrap items-start justify-between gap-4">
                                        <div className="min-w-0 flex-1">
                                            <div className="flex flex-wrap items-center gap-2">
                                                {unread && (
                                                    <span className="inline-flex items-center gap-1.5 text-xs font-semibold text-atlas-accent">
                                                        <span className="h-2 w-2 rounded-full bg-atlas-accent" aria-hidden="true" />
                                                        Non lue
                                                    </span>
                                                )}
                                                <span className={`rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ${priorityClasses(notification.priority)}`}>
                                                    Priorité {formatPriority(notification.priority).toLocaleLowerCase('fr-FR')}
                                                </span>
                                                {notification.status !== 'Active' && (
                                                    <span className="rounded-full bg-slate-50 px-2.5 py-1 text-xs font-medium text-atlas-ink-muted ring-1 ring-atlas-border">
                                                        Terminée
                                                    </span>
                                                )}
                                            </div>
                                            <h3 className="mt-3 text-base font-semibold text-atlas-ink">{copy.title}</h3>
                                            <p className="mt-1 max-w-2xl text-sm leading-relaxed text-atlas-ink-muted">
                                                {copy.description}
                                            </p>
                                            <p className="mt-3 text-xs text-atlas-ink-muted">
                                                <time dateTime={notification.created_at}>{formatNotificationDate(notification.created_at)}</time>
                                            </p>
                                        </div>
                                        <div className="flex w-full flex-wrap gap-2 sm:w-auto sm:justify-end">
                                            {unread && (
                                                <button
                                                    type="button"
                                                    disabled={markingId === notification.notification_id}
                                                    onClick={() => void handleMarkRead(notification)}
                                                    className="min-h-11 rounded-xl border border-atlas-border bg-white px-4 py-2.5 text-sm font-semibold text-atlas-ink hover:bg-slate-50 disabled:cursor-wait disabled:opacity-60"
                                                >
                                                    {markingId === notification.notification_id ? 'Mise à jour…' : 'Marquer comme lue'}
                                                </button>
                                            )}
                                            {action && (
                                                <Link
                                                    to={action.to}
                                                    className="inline-flex min-h-11 items-center justify-center rounded-xl bg-atlas-ink px-4 py-2.5 text-sm font-semibold text-white hover:bg-atlas-sidebar"
                                                >
                                                    {action.label} <span className="ml-2" aria-hidden="true">→</span>
                                                </Link>
                                            )}
                                        </div>
                                    </div>
                                </li>
                            );
                        })}
                    </ul>
                )}
            </div>
    );
}
