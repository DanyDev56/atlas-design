import { FormEvent, useCallback, useEffect, useState } from 'react';
import { Link, useLocation, useNavigate } from 'react-router-dom';
import { ApiClientError } from '@/api/client';
import { fetchManagedOperatorSessions, previewOperatorSessionRevocation, revokeManagedOperatorSession, type OperatorManagedSessionItem, type OperatorPage, type OperatorSessionRevocationPreview } from '@/api/operator';
import { Brand } from '@/components/ui/Brand';
import { Icon, type IconName } from '@/components/ui/Icon';
import { useOperatorAuth } from '@/hooks/useOperatorAuth';

const modules: Array<{
    title: string;
    description: string;
    icon: IconName;
    permission: string;
}> = [
    {
        title: 'Cohorte beta',
        description: 'Activation, jalons, blocages et cellules pricing pseudonymisées.',
        icon: 'crm',
        permission: 'operations.beta.read',
    },
    {
        title: 'Métriques produit',
        description: 'Entonnoir, première valeur, réutilisation et qualité des données.',
        icon: 'activity',
        permission: 'operations.metrics.read-product',
    },
    {
        title: 'Exploitation',
        description: 'API, worker, scheduler, outbox, emails et sauvegardes.',
        icon: 'dashboard',
        permission: 'operations.outbox.read',
    },
    {
        title: 'Support',
        description: 'Dossiers, objectifs de réponse, exports et fermetures.',
        icon: 'advisor',
        permission: 'operations.support.read',
    },
    {
        title: 'Abonnements',
        description: 'Essais, statuts normalisés et séparation sandbox/live.',
        icon: 'billing',
        permission: 'operations.subscriptions.read',
    },
    {
        title: 'Conformité et audit',
        description: 'Consentements, demandes de données et accès privilégiés.',
        icon: 'settings',
        permission: 'operations.compliance.read',
    },
];

function formatSessionDate(value: string | null): string {
    return value ? new Intl.DateTimeFormat('fr-FR', { dateStyle: 'short', timeStyle: 'short' }).format(new Date(value)) : '—';
}

function sessionStatusLabel(status: OperatorManagedSessionItem['status']): string {
    return { Active: 'Active', Expired: 'Expirée', Revoked: 'Révoquée' }[status];
}

export function OperatorShellPage() {
    const { session, logout, stepUp } = useOperatorAuth();
    const navigate = useNavigate();
    const location = useLocation();
    const securityView = location.pathname === '/backoffice/security';
    const canReadSessions = session?.permissions.includes('operations.sessions.read') ?? false;
    const canRevokeSessions = session?.permissions.includes('operations.sessions.revoke') ?? false;
    const [loggingOut, setLoggingOut] = useState(false);
    const [stepUpOpen, setStepUpOpen] = useState(false);
    const [stepUpLoading, setStepUpLoading] = useState(false);
    const [stepUpError, setStepUpError] = useState<string | null>(null);
    const [stepUpSucceeded, setStepUpSucceeded] = useState(false);
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [mfaCode, setMfaCode] = useState('');
    const [managedSessions, setManagedSessions] = useState<OperatorPage<OperatorManagedSessionItem> | null>(null);
    const [sessionStatus, setSessionStatus] = useState('Active');
    const [sessionPage, setSessionPage] = useState(1);
    const [sessionsLoading, setSessionsLoading] = useState(false);
    const [sessionsError, setSessionsError] = useState<string | null>(null);
    const [revokedSuccess, setRevokedSuccess] = useState<string | null>(null);
    const [revokedTarget, setRevokedTarget] = useState<OperatorManagedSessionItem | null>(null);
    const [revocationReason, setRevocationReason] = useState('session.security-review');
    const [revocationPreview, setRevocationPreview] = useState<OperatorSessionRevocationPreview | null>(null);
    const [revocationIdempotencyKey, setRevocationIdempotencyKey] = useState<string | null>(null);
    const [revocationLoading, setRevocationLoading] = useState(false);
    const [revocationError, setRevocationError] = useState<string | null>(null);
    const stepUpActive = session?.stepUpExpiresAt
        ? Date.parse(session.stepUpExpiresAt) > Date.now()
        : false;

    const loadManagedSessions = useCallback(async () => {
        if (!securityView || !canReadSessions || !session?.token) return;
        setSessionsLoading(true);
        setSessionsError(null);
        try {
            setManagedSessions(await fetchManagedOperatorSessions(session.token, sessionStatus, sessionPage));
        } catch (caught) {
            setSessionsError(caught instanceof ApiClientError ? caught.message : 'Le registre des sessions n’a pas pu être chargé.');
        } finally {
            setSessionsLoading(false);
        }
    }, [canReadSessions, securityView, session?.token, sessionPage, sessionStatus]);

    useEffect(() => {
        const previousTitle = document.title;
        document.title = securityView ? 'Sécurité opérateur — Atlas' : 'Back-office — Atlas';
        return () => {
            document.title = previousTitle;
        };
    }, [securityView]);

    useEffect(() => {
        void loadManagedSessions();
    }, [loadManagedSessions]);

    async function requestRevocationPreview() {
        if (!session?.token || !revokedTarget) return;
        setRevocationLoading(true);
        setRevocationError(null);
        try {
            setRevocationPreview(await previewOperatorSessionRevocation(session.token, revokedTarget.reference, revokedTarget.revision, revocationReason));
            setRevocationIdempotencyKey(crypto.randomUUID());
        } catch (caught) {
            setRevocationError(caught instanceof ApiClientError ? caught.message : 'La prévisualisation de la révocation a échoué.');
        } finally {
            setRevocationLoading(false);
        }
    }

    async function confirmRevocation() {
        if (!session?.token || !revokedTarget || !revocationPreview || !revocationIdempotencyKey) return;
        setRevocationLoading(true);
        setRevocationError(null);
        try {
            const result = await revokeManagedOperatorSession(
                session.token,
                revokedTarget.reference,
                revokedTarget.revision,
                revocationReason,
                revocationPreview.preview_fingerprint,
                revocationIdempotencyKey,
            );
            setRevokedSuccess(`La session ${result.reference} est révoquée.`);
            setRevokedTarget(null);
            setRevocationPreview(null);
            setRevocationIdempotencyKey(null);
            await loadManagedSessions();
        } catch (caught) {
            if (caught instanceof ApiClientError) {
                setRevocationPreview(null);
                setRevocationIdempotencyKey(null);
                setRevocationError(`${caught.message} Prévisualisez à nouveau.`);
            } else {
                setRevocationError('La révocation n’a pas pu être vérifiée. Réessayez : la même clé d’idempotence sera utilisée.');
            }
        } finally {
            setRevocationLoading(false);
        }
    }

    async function onLogout() {
        setLoggingOut(true);
        try {
            await logout();
        } finally {
            navigate('/backoffice/login', { replace: true });
        }
    }

    async function onStepUp(event: FormEvent) {
        event.preventDefault();
        setStepUpLoading(true);
        setStepUpError(null);
        setStepUpSucceeded(false);
        try {
            await stepUp(email, password, mfaCode);
            setPassword('');
            setMfaCode('');
            setStepUpOpen(false);
            setStepUpSucceeded(true);
        } catch (error) {
            setStepUpError(error instanceof ApiClientError
                ? error.message
                : 'La vérification renforcée a échoué.');
        } finally {
            setStepUpLoading(false);
        }
    }

    return (
        <div className="min-h-screen bg-atlas-surface text-atlas-ink">
            <header className="border-b border-atlas-border bg-atlas-sidebar text-white">
                <div className="mx-auto flex min-h-20 max-w-[90rem] items-center justify-between gap-5 px-5 sm:px-8 lg:px-10">
                    <div className="flex items-center gap-5">
                        <Link to="/backoffice" aria-label="Retour à la vue d’ensemble"><Brand inverse /></Link>
                        <span className="hidden h-6 w-px bg-white/15 sm:block" />
                        <span className="hidden text-xs font-semibold uppercase tracking-[.18em] text-white/50 sm:block">Back-office</span>
                        <Link to="/backoffice" className="hidden rounded-lg px-3 py-2 text-sm font-medium text-white/70 hover:bg-white/10 hover:text-white lg:inline-flex">Vue d’ensemble</Link>
                    </div>
                    <div className="flex items-center gap-3">
                        <span className="hidden text-sm text-white/60 sm:inline">{session?.displayName}</span>
                        <button
                            type="button"
                            disabled={loggingOut}
                            onClick={() => void onLogout()}
                            className="inline-flex min-h-10 items-center gap-2 rounded-xl border border-white/15 px-3.5 py-2 text-sm font-semibold text-white/80 hover:bg-white/10 disabled:opacity-50"
                        >
                            <Icon name="logout" className="size-4" />
                            Déconnexion
                        </button>
                    </div>
                </div>
            </header>

            <main className="mx-auto max-w-[90rem] px-5 py-10 sm:px-8 lg:px-10 lg:py-14">
                <div className="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <p className="atlas-kicker">{securityView ? 'Contrôle des accès' : 'Plan de contrôle'}</p>
                        <h1 className="mt-3 text-4xl font-semibold tracking-[-0.045em] sm:text-5xl">{securityView ? 'Sécurité opérateur' : 'Socle opérateur'}</h1>
                        <p className="mt-4 max-w-2xl text-base leading-7 text-atlas-ink-muted">
                            {securityView
                                ? 'Contrôlez le niveau d’authentification et les sessions du back-office sans affecter les accès Workspace.'
                                : 'L’audience, les grants, les sessions et l’audit sont séparés des Workspaces. Les registres métier sont raccordés progressivement avec une autorité explicite par capacité.'}
                        </p>
                    </div>
                    <div className="rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm text-emerald-900">
                        <div className="flex items-center gap-2 font-semibold">
                            <span className="size-2 rounded-full bg-emerald-500" />
                            {session?.actionsEnabled ? 'Actions bornées actives' : 'Mode lecture seule'}
                        </div>
                        <p className="mt-1 text-xs text-emerald-800/70">{session?.actionsEnabled ? 'Seules les actions explicitement autorisées et protégées par step-up sont disponibles.' : 'Aucune action métier ou destructive disponible.'}</p>
                    </div>
                </div>

                <section className="mt-10 rounded-[1.5rem] bg-atlas-sidebar p-6 text-white shadow-[0_18px_45px_rgb(16_28_26/0.16)] sm:p-8">
                    <div className="grid gap-7 md:grid-cols-3">
                        <div>
                            <p className="text-xs uppercase tracking-[.16em] text-white/45">Audience</p>
                            <p className="mt-2 text-xl font-semibold">Operator</p>
                        </div>
                        <div>
                            <p className="text-xs uppercase tracking-[.16em] text-white/45">Permissions actives</p>
                            <p className="mt-2 text-xl font-semibold">{session?.permissions.length ?? 0}</p>
                        </div>
                        <div>
                            <p className="text-xs uppercase tracking-[.16em] text-white/45">Expiration</p>
                            <p className="mt-2 text-xl font-semibold">
                                {session ? new Intl.DateTimeFormat('fr-FR', { hour: '2-digit', minute: '2-digit' }).format(new Date(session.expiresAt)) : '—'}
                            </p>
                        </div>
                    </div>
                </section>

                <section className="mt-6 rounded-2xl border border-atlas-border bg-white p-5 shadow-sm sm:p-6">
                    <div className="flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
                        <div className="flex items-start gap-4">
                            <span className={`grid size-11 shrink-0 place-items-center rounded-xl ${stepUpActive ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700'}`}>
                                <Icon name={stepUpActive ? 'check' : 'lock'} className="size-5" />
                            </span>
                            <div>
                                <p className="font-semibold">Authentification renforcée</p>
                                <p className="mt-1 text-sm leading-6 text-atlas-ink-muted">
                                    {stepUpActive && session?.stepUpExpiresAt
                                        ? `Step-up valide jusqu’à ${new Intl.DateTimeFormat('fr-FR', { hour: '2-digit', minute: '2-digit' }).format(new Date(session.stepUpExpiresAt))}.`
                                        : 'Aucun step-up récent. Les futures opérations sensibles seront refusées.'}
                                </p>
                                <p className="mt-1 text-xs text-atlas-ink-muted/75">
                                    Niveau de session : {session?.authenticationStrength ?? 'inconnu'}
                                </p>
                            </div>
                        </div>
                        <button
                            type="button"
                            onClick={() => {
                                setStepUpOpen((current) => !current);
                                setStepUpError(null);
                                setStepUpSucceeded(false);
                            }}
                            className="inline-flex min-h-11 items-center justify-center rounded-xl border border-atlas-border px-4 py-2 text-sm font-semibold hover:bg-atlas-surface"
                        >
                            Renouveler le step-up
                        </button>
                    </div>

                    {stepUpSucceeded && (
                        <p role="status" className="mt-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                            Authentification renforcée renouvelée.
                        </p>
                    )}

                    {stepUpOpen && (
                        <form onSubmit={onStepUp} className="mt-6 grid gap-4 border-t border-atlas-border pt-6 md:grid-cols-3">
                            <label className="text-sm font-medium">
                                Email opérateur
                                <input
                                    type="email"
                                    required
                                    autoComplete="username"
                                    value={email}
                                    onChange={(event) => setEmail(event.target.value)}
                                    className="mt-2 min-h-11 w-full rounded-xl border border-atlas-border bg-white px-3.5 outline-none focus:border-atlas-accent focus:ring-2 focus:ring-atlas-accent/15"
                                />
                            </label>
                            <label className="text-sm font-medium">
                                Mot de passe
                                <input
                                    type="password"
                                    required
                                    autoComplete="current-password"
                                    value={password}
                                    onChange={(event) => setPassword(event.target.value)}
                                    className="mt-2 min-h-11 w-full rounded-xl border border-atlas-border bg-white px-3.5 outline-none focus:border-atlas-accent focus:ring-2 focus:ring-atlas-accent/15"
                                />
                            </label>
                            <label className="text-sm font-medium">
                                Code MFA
                                <input
                                    type="text"
                                    required
                                    inputMode="numeric"
                                    autoComplete="one-time-code"
                                    value={mfaCode}
                                    onChange={(event) => setMfaCode(event.target.value)}
                                    className="mt-2 min-h-11 w-full rounded-xl border border-atlas-border bg-white px-3.5 outline-none focus:border-atlas-accent focus:ring-2 focus:ring-atlas-accent/15"
                                />
                            </label>
                            {stepUpError && (
                                <p role="alert" className="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 md:col-span-3">
                                    {stepUpError}
                                </p>
                            )}
                            <button
                                type="submit"
                                disabled={stepUpLoading}
                                className="inline-flex min-h-11 items-center justify-center rounded-xl bg-atlas-sidebar px-4 py-2 text-sm font-semibold text-white hover:bg-atlas-sidebar/90 disabled:opacity-50 md:col-span-3 md:justify-self-end"
                            >
                                {stepUpLoading ? 'Vérification…' : 'Vérifier maintenant'}
                            </button>
                        </form>
                    )}
                </section>

                {securityView && (
                    <section className="mt-10" aria-labelledby="operator-sessions-title">
                        <div className="flex flex-wrap items-end justify-between gap-4">
                            <div>
                                <p className="atlas-kicker">Accès privilégiés</p>
                                <h2 id="operator-sessions-title" className="mt-2 text-2xl font-semibold">Sessions opérateur</h2>
                                <p className="mt-2 max-w-3xl text-sm leading-6 text-atlas-ink-muted">
                                    Registre pseudonymisé des accès au back-office. La révocation cible uniquement le jeton Operator sélectionné.
                                </p>
                            </div>
                            {canReadSessions && (
                                <select
                                    aria-label="Statut des sessions opérateur"
                                    value={sessionStatus}
                                    onChange={(event) => { setSessionStatus(event.target.value); setSessionPage(1); }}
                                    className="min-h-10 rounded-xl border border-atlas-border bg-white px-3 text-sm"
                                >
                                    <option value="Active">Sessions actives</option>
                                    <option value="Revoked">Sessions révoquées</option>
                                    <option value="Expired">Sessions expirées</option>
                                    <option value="All">Toutes les sessions</option>
                                </select>
                            )}
                        </div>

                        {!canReadSessions && (
                            <div className="mt-5 rounded-2xl border border-atlas-border bg-white p-6 text-sm text-atlas-ink-muted">
                                Votre grant ne permet pas de consulter le registre des sessions.
                            </div>
                        )}
                        {canRevokeSessions && !session?.actionsEnabled && (
                            <div className="mt-5 rounded-2xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-900">
                                <p className="font-semibold">Révocation ciblée désactivée</p>
                                <p className="mt-1">Les actions de cet environnement sont verrouillées côté serveur.</p>
                            </div>
                        )}
                        {canRevokeSessions && session?.actionsEnabled && !stepUpActive && (
                            <div className="mt-5 rounded-2xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-900">
                                <p className="font-semibold">Step-up requis</p>
                                <p className="mt-1">Renouvelez l’authentification renforcée ci-dessus avant toute révocation.</p>
                            </div>
                        )}
                        {sessionsError && <p role="alert" className="mt-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{sessionsError} <button type="button" onClick={() => void loadManagedSessions()} className="font-semibold underline">Réessayer</button></p>}
                        {revokedSuccess && <p role="status" className="mt-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{revokedSuccess}</p>}

                        {revokedTarget && (
                            <div className="mt-5 rounded-2xl border border-atlas-accent/30 bg-white p-5 shadow-sm sm:p-6">
                                <div className="flex flex-wrap items-start justify-between gap-4">
                                    <div>
                                        <p className="atlas-kicker">Action bornée</p>
                                        <h3 className="mt-2 text-xl font-semibold">Révoquer {revokedTarget.reference}</h3>
                                        <p className="mt-1 text-sm text-atlas-ink-muted">{revokedTarget.operator_reference} · révision {revokedTarget.revision}</p>
                                    </div>
                                    <button type="button" onClick={() => { setRevokedTarget(null); setRevocationPreview(null); setRevocationIdempotencyKey(null); }} className="text-sm font-semibold text-atlas-ink-muted">Annuler</button>
                                </div>
                                <label className="mt-5 block max-w-md text-sm font-medium">
                                    Motif structuré
                                    <select
                                        value={revocationReason}
                                        onChange={(event) => { setRevocationReason(event.target.value); setRevocationPreview(null); setRevocationIdempotencyKey(null); }}
                                        className="mt-2 min-h-11 w-full rounded-xl border border-atlas-border bg-white px-3"
                                    >
                                        <option value="session.security-review">Revue de sécurité</option>
                                        <option value="session.device-lost">Appareil perdu</option>
                                        <option value="session.compromised">Suspicion de compromission</option>
                                        <option value="session.access-ended">Accès devenu inutile</option>
                                    </select>
                                </label>
                                {revocationError && <p role="alert" className="mt-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{revocationError}</p>}
                                {!revocationPreview && <button type="button" disabled={revocationLoading} onClick={() => void requestRevocationPreview()} className="mt-5 inline-flex min-h-11 items-center justify-center rounded-xl bg-atlas-sidebar px-5 text-sm font-semibold text-white disabled:opacity-50">{revocationLoading ? 'Préparation…' : 'Prévisualiser la révocation'}</button>}
                                {revocationPreview && (
                                    <div className="mt-6 rounded-xl border border-atlas-border bg-atlas-surface p-5">
                                        <div className="grid gap-4 sm:grid-cols-2">
                                            <div><p className="text-xs font-semibold uppercase tracking-wider text-atlas-ink-muted">Avant</p><p className="mt-2 font-semibold">Active · révision {revocationPreview.current.revision}</p></div>
                                            <div><p className="text-xs font-semibold uppercase tracking-wider text-atlas-ink-muted">Après</p><p className="mt-2 font-semibold text-red-700">Révoquée · révision {revocationPreview.proposed.revision}</p></div>
                                        </div>
                                        <ul className="mt-4 space-y-1 text-sm text-atlas-ink-muted">{revocationPreview.effects.map((effect) => <li key={effect}>✓ {effect}</li>)}</ul>
                                        <div className="mt-5 flex flex-wrap gap-3">
                                            <button type="button" disabled={revocationLoading} onClick={() => void confirmRevocation()} className="inline-flex min-h-11 items-center justify-center rounded-xl bg-red-700 px-5 text-sm font-semibold text-white disabled:opacity-50">{revocationLoading ? 'Révocation…' : 'Confirmer la révocation'}</button>
                                            <button type="button" disabled={revocationLoading} onClick={() => { setRevocationPreview(null); setRevocationIdempotencyKey(null); }} className="inline-flex min-h-11 items-center justify-center rounded-xl border border-atlas-border bg-white px-5 text-sm font-semibold">Modifier</button>
                                        </div>
                                    </div>
                                )}
                            </div>
                        )}

                        {canReadSessions && (
                            <div className="mt-5 overflow-hidden rounded-2xl border border-atlas-border bg-white shadow-sm">
                                {sessionsLoading && <p className="p-6 text-sm font-semibold text-atlas-accent">Actualisation des sessions…</p>}
                                {!sessionsLoading && managedSessions?.items.length === 0 && <p className="p-8 text-center text-sm text-atlas-ink-muted">Aucune session pour ce filtre.</p>}
                                {managedSessions && (
                                    <>
                                        <div className="divide-y divide-atlas-border">
                                            {managedSessions.items.map((item) => (
                                                <article key={item.reference} className="grid gap-4 p-5 lg:grid-cols-[1fr_.8fr_1fr_auto] lg:items-center">
                                                    <div>
                                                        <p className="font-mono text-sm font-semibold">{item.reference}</p>
                                                        <p className="mt-1 text-xs text-atlas-ink-muted">{item.operator_reference}{item.current ? ' · session actuelle' : ''}</p>
                                                    </div>
                                                    <div>
                                                        <span className={`inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold ${item.status === 'Active' ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-atlas-border bg-atlas-surface text-atlas-ink-muted'}`}>{sessionStatusLabel(item.status)}</span>
                                                        <p className="mt-2 text-xs text-atlas-ink-muted">{item.authentication_strength} · MFA {item.mfa_verified ? 'vérifiée' : 'absente'}</p>
                                                    </div>
                                                    <div className="text-sm text-atlas-ink-muted">
                                                        <p>Ouverte : {formatSessionDate(item.created_at)}</p>
                                                        <p className="mt-1">Expiration : {formatSessionDate(item.expires_at)}</p>
                                                    </div>
                                                    {canRevokeSessions && session?.actionsEnabled && stepUpActive && item.status === 'Active' && !item.current ? (
                                                        <button type="button" onClick={() => { setRevokedTarget(item); setRevocationReason('session.security-review'); setRevocationPreview(null); setRevocationIdempotencyKey(null); setRevocationError(null); setRevokedSuccess(null); }} className="min-h-10 rounded-xl border border-red-200 px-3 text-sm font-semibold text-red-700 hover:bg-red-50">Révoquer</button>
                                                    ) : <span className="text-xs text-atlas-ink-muted">{item.current ? 'Utilisez Déconnexion' : ''}</span>}
                                                </article>
                                            ))}
                                        </div>
                                        {managedSessions.total_pages > 1 && (
                                            <nav aria-label="Pagination des sessions" className="flex items-center justify-between border-t border-atlas-border px-5 py-4 text-sm">
                                                <button type="button" disabled={sessionPage <= 1} onClick={() => setSessionPage((value) => value - 1)} className="font-semibold text-atlas-accent disabled:opacity-40">← Précédent</button>
                                                <span className="text-atlas-ink-muted">Page {managedSessions.page} sur {managedSessions.total_pages}</span>
                                                <button type="button" disabled={sessionPage >= managedSessions.total_pages} onClick={() => setSessionPage((value) => value + 1)} className="font-semibold text-atlas-accent disabled:opacity-40">Suivant →</button>
                                            </nav>
                                        )}
                                    </>
                                )}
                            </div>
                        )}
                    </section>
                )}

                {!securityView && <section className="mt-10">
                    <div>
                        <p className="text-lg font-semibold">Surfaces préparées</p>
                        <p className="mt-1 text-sm text-atlas-ink-muted">Elles seront activées incrément par incrément après leurs preuves d’isolation.</p>
                    </div>
                    <div className="mt-5 grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                        {modules.map((module) => {
                            const granted = session?.permissions.includes(module.permission) ?? false;
                            return (
                                <article key={module.title} className="rounded-2xl border border-atlas-border bg-white p-6 shadow-sm">
                                    <div className="flex items-start justify-between gap-4">
                                        <span className="grid size-11 place-items-center rounded-xl bg-atlas-accent-soft text-atlas-accent">
                                            <Icon name={module.icon} className="size-5" />
                                        </span>
                                        <span className={`rounded-full px-2.5 py-1 text-[11px] font-semibold ${granted ? 'bg-emerald-50 text-emerald-700' : 'bg-atlas-surface text-atlas-ink-muted'}`}>
                                            {granted ? 'Grant prêt' : 'Non accordé'}
                                        </span>
                                    </div>
                                    <h2 className="mt-5 text-lg font-semibold">{module.title}</h2>
                                    <p className="mt-2 text-sm leading-6 text-atlas-ink-muted">{module.description}</p>
                                    <p className="mt-5 break-all text-[11px] font-medium text-atlas-ink-muted/70">{module.permission}</p>
                                </article>
                            );
                        })}
                    </div>
                </section>}
            </main>
        </div>
    );
}
