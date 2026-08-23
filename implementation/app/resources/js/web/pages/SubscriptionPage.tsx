import { useEffect, useMemo, useState } from 'react';
import { Link, useNavigate, useSearchParams } from 'react-router-dom';
import { createSubscriptionCheckout, getSubscriptionOverview } from '@/api/subscriptions';
import { ErrorBanner, SuccessBanner } from '@/components/auth/AuthLayout';
import { RequireAuth } from '@/components/layout/RequireAuth';
import { Icon } from '@/components/ui/Icon';
import { PageHeader } from '@/components/ui/PageHeader';
import { PageSkeleton } from '@/components/ui/PageSkeleton';
import { useAuth } from '@/hooks/useAuth';
import type {
    SubscriptionBillingInterval,
    SubscriptionOverviewResponse,
    SubscriptionPrice,
} from '@/types/api';
import { formatMoney } from '@/utils/format';

const capabilityLabels: Record<string, string> = {
    'workspace.read': 'Consulter l’espace et son historique',
    'workspace.mutate': 'Piloter l’activité au quotidien',
    'documents.send': 'Envoyer devis, factures et relances',
    'analytics.evaluate': 'Actualiser les analyses et la santé de l’activité',
    'members.invite': 'Inviter les membres de votre équipe',
    'data.export': 'Exporter les données de votre espace',
    'subscription.manage': 'Gérer l’abonnement de l’espace',
};

const checkoutPreviewMessage = 'Simulation terminée. Aucun paiement n’a été effectué et votre accès reste inchangé.';

function formatDate(value: string): string {
    return new Intl.DateTimeFormat('fr-FR', { dateStyle: 'long' }).format(new Date(value));
}

function intervalLabel(interval: SubscriptionBillingInterval): string {
    return interval === 'Annual' ? 'Annuel' : 'Mensuel';
}

function priceDescription(price: SubscriptionPrice): string {
    if (price.billing_interval === 'Annual') {
        return `${formatMoney(Math.round(price.amount_minor / 12), price.currency)} / mois, facturé ${formatMoney(price.amount_minor, price.currency)} par an`;
    }

    return `${formatMoney(price.amount_minor, price.currency)} par mois`;
}

export function SubscriptionPage() {
    const { session } = useAuth();
    const token = session!.token;
    const workspaceId = session!.workspaceId!;
    const navigate = useNavigate();
    const [searchParams] = useSearchParams();
    const checkoutResult = searchParams.get('checkout');
    const [overview, setOverview] = useState<SubscriptionOverviewResponse | null>(null);
    const [selectedInterval, setSelectedInterval] = useState<SubscriptionBillingInterval>('Annual');
    const [loading, setLoading] = useState(true);
    const [submitting, setSubmitting] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [previewMessage, setPreviewMessage] = useState<string | null>(() => (
        checkoutResult === 'preview' ? checkoutPreviewMessage : null
    ));

    useEffect(() => {
        if (checkoutResult !== 'preview') return;

        setPreviewMessage(checkoutPreviewMessage);
        const timeout = window.setTimeout(() => {
            setPreviewMessage(null);
            navigate('/app/settings/subscription', { replace: true });
        }, 12_000);

        return () => window.clearTimeout(timeout);
    }, [checkoutResult, navigate]);

    useEffect(() => {
        let cancelled = false;

        async function load() {
            setLoading(true);
            setError(null);
            try {
                const nextOverview = await getSubscriptionOverview(token, workspaceId);
                if (!cancelled) setOverview(nextOverview);
            } catch (err) {
                if (!cancelled) {
                    setError(err instanceof Error ? err.message : 'Chargement de l’abonnement impossible');
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

    const selectedPrice = useMemo(() => overview?.catalog.plan.prices.find(
        (price) => price.billing_interval === selectedInterval,
    ) ?? null, [overview, selectedInterval]);

    async function onCheckout() {
        if (!overview?.commercialization.checkout_enabled || !selectedPrice) return;

        setSubmitting(true);
        setError(null);
        setPreviewMessage(null);
        try {
            const checkout = await createSubscriptionCheckout(token, workspaceId, selectedInterval);
            if (checkout.provider === 'fake') {
                const target = new URL(checkout.checkout_url, window.location.origin);
                window.location.assign(`${target.pathname}${target.search}${target.hash}`);
            } else {
                window.location.assign(checkout.checkout_url);
            }
        } catch (err) {
            setError(err instanceof Error ? err.message : 'Ouverture du checkout impossible');
            setSubmitting(false);
        }
    }

    const trialActive = overview?.trial?.status === 'Active';
    const trialDuration = overview?.trial
        ? Math.max(1, Math.ceil((Date.parse(overview.trial.ends_at) - Date.parse(overview.trial.started_at)) / 86_400_000))
        : 30;
    const trialProgress = overview?.trial
        ? Math.max(0, Math.min(100, ((trialDuration - overview.trial.remaining_days) / trialDuration) * 100))
        : 0;
    const memberLimit = overview?.catalog.plan.limits.members_total;

    return (
        <RequireAuth>
            <div className="atlas-page max-w-5xl">
                <Link
                    to="/app/settings"
                    className="mb-5 inline-flex items-center gap-2 text-sm font-semibold text-atlas-accent hover:underline"
                >
                    <span aria-hidden="true">←</span>
                    Retour à la gestion de l’espace
                </Link>

                <PageHeader
                    eyebrow="Gestion de l’espace"
                    title="Abonnement"
                    description="Consultez votre période d’essai, le périmètre de l’offre Atlas Solo et les options tarifaires en cours de validation."
                />

                <SuccessBanner message={previewMessage} />
                <ErrorBanner message={error} />
                {loading && <PageSkeleton rows={4} />}

                {!loading && overview && (
                    <div className="space-y-6">
                        <section className="relative overflow-hidden rounded-[1.5rem] bg-atlas-sidebar p-6 text-white shadow-[0_18px_45px_rgb(16_28_26/0.16)] sm:p-8">
                            <div className="pointer-events-none absolute -right-20 -top-24 size-72 rounded-full border border-white/[0.08]" />
                            <div className="pointer-events-none absolute -right-4 -top-10 size-48 rounded-full bg-atlas-accent/25 blur-3xl" />
                            <div className="relative grid gap-8 md:grid-cols-[minmax(0,1fr)_15rem] md:items-end">
                                <div>
                                    <span className="inline-flex items-center gap-2 rounded-full bg-white/[0.08] px-3 py-1.5 text-xs font-semibold text-white/80">
                                        <span className={`size-1.5 rounded-full ${trialActive ? 'bg-[#58c8ac]' : 'bg-amber-300'}`} />
                                        {trialActive ? 'Essai en cours' : overview.access.level === 'Restricted' ? 'Accès restreint' : 'Initialisation'}
                                    </span>
                                    <h3 className="mt-5 text-2xl font-semibold tracking-[-0.025em] sm:text-[1.75rem]">
                                        {trialActive && overview.trial
                                            ? `${overview.trial.remaining_days} jour${overview.trial.remaining_days > 1 ? 's' : ''} pour découvrir Atlas`
                                            : overview.access.level === 'Restricted'
                                                ? 'Votre période d’essai est terminée'
                                                : 'Votre accès commercial est en préparation'}
                                    </h3>
                                    <p className="mt-2 max-w-2xl text-sm leading-6 text-white/60">
                                        {overview.trial
                                            ? `Votre essai se termine le ${formatDate(overview.trial.ends_at)}. Aucun prélèvement automatique n’est programmé.`
                                            : 'Les données de votre espace restent disponibles. Aucun paiement ni aucune restriction ne sont appliqués pendant cette phase.'}
                                    </p>
                                </div>
                                <div className="rounded-2xl border border-white/[0.08] bg-white/[0.06] p-4">
                                    <div className="flex items-center justify-between gap-3 text-xs text-white/55">
                                        <span>Progression de l’essai</span>
                                        <span>{overview.trial ? `${overview.trial.remaining_days} j restants` : 'À initialiser'}</span>
                                    </div>
                                    <div className="mt-3 h-2 overflow-hidden rounded-full bg-white/10">
                                        <div className="h-full rounded-full bg-[#58c8ac]" style={{ width: `${trialProgress}%` }} />
                                    </div>
                                    <p className="mt-3 text-xs leading-5 text-white/45">
                                        Niveau d’accès : {overview.access.level === 'Full' ? 'complet' : overview.access.level === 'Restricted' ? 'lecture et export' : 'en préparation'}
                                    </p>
                                </div>
                            </div>
                        </section>

                        <div className="grid gap-6 lg:grid-cols-[minmax(0,1.2fr)_minmax(18rem,0.8fr)]">
                            <section className="rounded-2xl border border-atlas-border bg-atlas-card p-6 shadow-sm sm:p-7">
                                <div className="flex flex-wrap items-start justify-between gap-4">
                                    <div>
                                        <div className="flex flex-wrap items-center gap-2">
                                            <h3 className="text-xl font-semibold text-atlas-ink">{overview.catalog.plan.display_name}</h3>
                                            <span className="rounded-full bg-amber-50 px-2.5 py-1 text-[11px] font-semibold text-amber-800 ring-1 ring-inset ring-amber-200">
                                                Tarif en validation
                                            </span>
                                        </div>
                                        <p className="mt-1 text-sm text-atlas-ink-muted">Une offre unique pour piloter une petite activité sans modules à choisir.</p>
                                    </div>
                                    <span className="text-xs font-medium text-atlas-ink-muted">Version {overview.catalog.plan.version}</span>
                                </div>

                                <div className="mt-6 grid grid-cols-2 gap-2 rounded-xl bg-atlas-surface p-1.5" role="group" aria-label="Fréquence de facturation">
                                    {overview.catalog.plan.prices.map((price) => (
                                        <button
                                            key={price.id}
                                            type="button"
                                            aria-pressed={selectedInterval === price.billing_interval}
                                            onClick={() => setSelectedInterval(price.billing_interval)}
                                            className={`rounded-lg px-3 py-2.5 text-sm font-semibold ${selectedInterval === price.billing_interval ? 'bg-white text-atlas-ink shadow-sm' : 'text-atlas-ink-muted hover:text-atlas-ink'}`}
                                        >
                                            {intervalLabel(price.billing_interval)}
                                        </button>
                                    ))}
                                </div>

                                {selectedPrice && (
                                    <div className="mt-6 border-b border-atlas-border pb-6">
                                        <p className="text-3xl font-semibold tracking-[-0.035em] text-atlas-ink">
                                            {selectedPrice.billing_interval === 'Annual'
                                                ? formatMoney(Math.round(selectedPrice.amount_minor / 12), selectedPrice.currency)
                                                : formatMoney(selectedPrice.amount_minor, selectedPrice.currency)}
                                            <span className="ml-1 text-sm font-medium tracking-normal text-atlas-ink-muted">/ mois</span>
                                        </p>
                                        <p className="mt-1.5 text-sm text-atlas-ink-muted">{priceDescription(selectedPrice)}</p>
                                    </div>
                                )}

                                <ul className="mt-6 grid gap-3 sm:grid-cols-2">
                                    {overview.catalog.plan.capabilities.map((capability) => (
                                        <li key={capability} className="flex items-start gap-2.5 text-sm leading-5 text-atlas-ink">
                                            <span className="mt-0.5 grid size-5 shrink-0 place-items-center rounded-full bg-atlas-accent-soft text-atlas-accent">
                                                <Icon name="check" className="size-3" />
                                            </span>
                                            {capabilityLabels[capability] ?? capability}
                                        </li>
                                    ))}
                                </ul>

                                <button
                                    type="button"
                                    disabled={!overview.commercialization.checkout_enabled || submitting || !selectedPrice}
                                    onClick={() => void onCheckout()}
                                    className="mt-7 inline-flex min-h-12 w-full items-center justify-center gap-2 rounded-xl bg-atlas-accent px-5 py-3 text-sm font-semibold text-white hover:bg-[#066557] disabled:cursor-not-allowed disabled:opacity-45"
                                >
                                    {submitting ? 'Ouverture…' : overview.commercialization.checkout_enabled
                                        ? overview.commercialization.gateway === 'fake' ? 'Simuler ce choix' : 'Choisir cette offre'
                                        : 'Souscription bientôt disponible'}
                                    {!submitting && overview.commercialization.checkout_enabled && <Icon name="arrow-right" className="size-4" />}
                                </button>
                                <p className="mt-3 text-center text-xs leading-5 text-atlas-ink-muted">
                                    {overview.commercialization.checkout_enabled
                                        ? overview.commercialization.gateway === 'fake'
                                            ? 'Mode développement : cette action simule le parcours sans paiement.'
                                            : 'Vous pourrez vérifier le récapitulatif avant tout paiement.'
                                        : 'Le checkout reste fermé pendant la validation du tarif.'}
                                </p>
                            </section>

                            <aside className="space-y-6">
                                <section className="rounded-2xl border border-atlas-border bg-atlas-card p-6 shadow-sm">
                                    <p className="atlas-kicker">Inclus dans l’offre</p>
                                    <h3 className="mt-2 text-lg font-semibold text-atlas-ink">Une équipe volontairement compacte</h3>
                                    <p className="mt-2 text-sm leading-6 text-atlas-ink-muted">
                                        {memberLimit ? `Jusqu’à ${memberLimit} personnes dans l’espace, propriétaire inclus.` : 'Les limites de membres sont en cours de définition.'}
                                    </p>
                                    <dl className="mt-5 space-y-3 border-t border-atlas-border pt-5 text-sm">
                                        <div className="flex items-center justify-between gap-4">
                                            <dt className="text-atlas-ink-muted">Propriétaire</dt>
                                            <dd className="font-semibold text-atlas-ink">{overview.catalog.plan.limits.owners ?? 1}</dd>
                                        </div>
                                        <div className="flex items-center justify-between gap-4">
                                            <dt className="text-atlas-ink-muted">Membres supplémentaires</dt>
                                            <dd className="font-semibold text-atlas-ink">{overview.catalog.plan.limits.members ?? Math.max(0, (memberLimit ?? 1) - 1)}</dd>
                                        </div>
                                        <div className="flex items-center justify-between gap-4">
                                            <dt className="text-atlas-ink-muted">Engagement</dt>
                                            <dd className="font-semibold text-atlas-ink">Aucun pendant l’essai</dd>
                                        </div>
                                    </dl>
                                </section>

                                <section className="rounded-2xl border border-atlas-border bg-atlas-accent-soft/55 p-6">
                                    <h3 className="text-sm font-semibold text-atlas-ink">Un tarif encore candidat</h3>
                                    <p className="mt-2 text-sm leading-6 text-atlas-ink-muted">
                                        Cette page sert à valider la compréhension de l’offre. Elle ne constitue ni une facture, ni une preuve d’abonnement actif.
                                    </p>
                                    {!overview.commercialization.enforcement_enabled && (
                                        <p className="mt-3 flex items-start gap-2 text-xs leading-5 text-atlas-accent">
                                            <Icon name="check" className="mt-0.5 size-3.5 shrink-0" />
                                            Aucune limite commerciale n’est appliquée actuellement.
                                        </p>
                                    )}
                                </section>
                            </aside>
                        </div>
                    </div>
                )}
            </div>
        </RequireAuth>
    );
}
