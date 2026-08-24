import { useCallback, useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { ApiClientError } from '@/api/client';
import {
    fetchOperatorEmails,
    fetchOperatorOutbox,
    previewOperatorOutboxRetry,
    retryOperatorOutboxMessage,
    type OperatorEmailItem,
    type OperatorOutboxItem,
    type OperatorOutboxRetryPreview,
    type OperatorPage,
} from '@/api/operator';
import { OperatorFrame } from '@/components/operator/OperatorFrame';
import { Icon } from '@/components/ui/Icon';
import { useOperatorAuth } from '@/hooks/useOperatorAuth';

type RegistryKind = 'outbox' | 'emails';
type RegistryItem = OperatorOutboxItem | OperatorEmailItem;

const definitions = {
    outbox: {
        kicker: 'Exploitation',
        title: 'Registre Outbox',
        description: 'Événements techniques, tentatives et état de distribution. Les payloads et erreurs brutes restent masqués.',
        statuses: ['All', 'Pending', 'Retrying', 'DeadLetter', 'Dispatched'],
    },
    emails: {
        kicker: 'Messagerie',
        title: 'Registre des emails',
        description: 'État des demandes de livraison sans adresse destinataire ni contenu du message.',
        statuses: ['All', 'Pending', 'Retrying', 'Failed', 'Accepted', 'Cancelled'],
    },
};

const statusLabels: Record<string, string> = {
    All: 'Tous', Pending: 'En attente', Retrying: 'En reprise', DeadLetter: 'Dead-letter',
    Dispatched: 'Distribué', Failed: 'Échec', Accepted: 'Accepté', Cancelled: 'Annulé',
};

function formatDate(value: string | null): string {
    if (!value) return '—';
    return new Intl.DateTimeFormat('fr-FR', { dateStyle: 'short', timeStyle: 'short' }).format(new Date(value));
}

function statusClass(status: string): string {
    if (['Failed', 'DeadLetter'].includes(status)) return 'border-red-200 bg-red-50 text-red-700';
    if (['Pending', 'Retrying'].includes(status)) return 'border-amber-200 bg-amber-50 text-amber-700';
    return 'border-emerald-200 bg-emerald-50 text-emerald-700';
}

export function OperatorRegistryPage({ kind }: { kind: RegistryKind }) {
    const { session } = useOperatorAuth();
    const definition = definitions[kind];
    const [status, setStatus] = useState('All');
    const [page, setPage] = useState(1);
    const [result, setResult] = useState<OperatorPage<RegistryItem> | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [retryTarget, setRetryTarget] = useState<OperatorOutboxItem | null>(null);
    const [retryReason, setRetryReason] = useState('outbox.cause-corrected');
    const [retryPreview, setRetryPreview] = useState<OperatorOutboxRetryPreview | null>(null);
    const [retryKey, setRetryKey] = useState<string | null>(null);
    const [retryBusy, setRetryBusy] = useState(false);
    const [retryError, setRetryError] = useState<string | null>(null);
    const [retryMessage, setRetryMessage] = useState<string | null>(null);

    const hasRetryPermission = session?.permissions.includes('operations.outbox.retry') ?? false;
    const retryActionsEnabled = Boolean(session?.actionsEnabled && !session.readOnly);
    const stepUpActive = Boolean(session?.stepUpExpiresAt && Date.parse(session.stepUpExpiresAt) > Date.now());
    const canRetry = hasRetryPermission && retryActionsEnabled && stepUpActive;

    const load = useCallback(async () => {
        if (!session?.token) return;
        setLoading(true);
        setError(null);
        try {
            const next = kind === 'outbox'
                ? await fetchOperatorOutbox(session.token, status, page)
                : await fetchOperatorEmails(session.token, status, page);
            setResult(next);
        } catch (caught) {
            setError(caught instanceof ApiClientError ? caught.message : 'Le registre n’a pas pu être chargé.');
        } finally {
            setLoading(false);
        }
    }, [kind, page, session?.token, status]);

    useEffect(() => {
        const previousTitle = document.title;
        document.title = `${definition.title} — Atlas`;
        void load();
        return () => { document.title = previousTitle; };
    }, [definition.title, load]);

    useEffect(() => {
        setRetryTarget(null);
        setRetryPreview(null);
        setRetryKey(null);
        setRetryError(null);
        setRetryMessage(null);
    }, [kind, page, status]);

    const selectRetryTarget = (item: RegistryItem) => {
        if (kind !== 'outbox' || item.status !== 'DeadLetter' || !canRetry) return;
        setRetryTarget(item as OperatorOutboxItem);
        setRetryPreview(null);
        setRetryKey(null);
        setRetryError(null);
        setRetryMessage(null);
    };

    const prepareRetry = async () => {
        if (!session?.token || !retryTarget) return;
        setRetryBusy(true);
        setRetryError(null);
        setRetryMessage(null);
        try {
            const preview = await previewOperatorOutboxRetry(session.token, retryTarget.event_id, retryReason);
            setRetryPreview(preview);
            setRetryKey(crypto.randomUUID());
        } catch (caught) {
            setRetryPreview(null);
            setRetryKey(null);
            setRetryError(caught instanceof ApiClientError ? caught.message : 'La prévisualisation a échoué.');
        } finally {
            setRetryBusy(false);
        }
    };

    const confirmRetry = async () => {
        if (!session?.token || !retryTarget || !retryPreview) return;
        const idempotencyKey = retryKey ?? crypto.randomUUID();
        setRetryKey(idempotencyKey);
        setRetryBusy(true);
        setRetryError(null);
        try {
            const outcome = await retryOperatorOutboxMessage(
                session.token,
                retryTarget.event_id,
                retryReason,
                retryPreview.preview_fingerprint,
                idempotencyKey,
            );
            setRetryMessage(outcome.replayed
                ? 'La confirmation précédente a été retrouvée : aucun second rejeu n’a été créé.'
                : 'Le message a été remis en attente. Le worker Outbox le traitera au prochain cycle.');
            setRetryTarget(null);
            setRetryPreview(null);
            setRetryKey(null);
            await load();
        } catch (caught) {
            setRetryError(caught instanceof ApiClientError ? caught.message : 'La confirmation est incertaine. Réessayez sans modifier la proposition.');
        } finally {
            setRetryBusy(false);
        }
    };

    return (
        <OperatorFrame>
            <main className="mx-auto max-w-[90rem] px-5 py-10 sm:px-8 lg:px-10 lg:py-14">
                <Link to="/backoffice" className="inline-flex items-center gap-2 text-sm font-semibold text-atlas-accent"><span aria-hidden="true">←</span> Retour à la vue d’ensemble</Link>
                <div className="mt-8 flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                    <div><p className="atlas-kicker">{definition.kicker}</p><h1 className="mt-3 text-4xl font-semibold tracking-[-0.045em]">{definition.title}</h1><p className="mt-4 max-w-3xl text-base leading-7 text-atlas-ink-muted">{definition.description}</p></div>
                    <label className="text-sm font-medium">État
                        <select value={status} onChange={(event) => { setStatus(event.target.value); setPage(1); }} className="mt-2 block min-h-11 min-w-48 rounded-xl border border-atlas-border bg-white px-3.5">
                            {definition.statuses.map((item) => <option key={item} value={item}>{statusLabels[item]}</option>)}
                        </select>
                    </label>
                </div>

                {kind === 'outbox' && retryMessage && <div role="status" className="mt-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{retryMessage}</div>}
                {kind === 'outbox' && hasRetryPermission && !retryActionsEnabled && <div className="mt-6 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">Les reprises sont verrouillées par la configuration du back-office.</div>}
                {kind === 'outbox' && hasRetryPermission && retryActionsEnabled && !stepUpActive && <div className="mt-6 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">Renouvelez l’authentification renforcée depuis l’en-tête avant de préparer une reprise.</div>}

                {kind === 'outbox' && retryTarget && (
                    <section className="mt-6 rounded-2xl border border-amber-200 bg-amber-50/70 p-5 shadow-sm" aria-labelledby="outbox-retry-title">
                        <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                            <div>
                                <p className="atlas-kicker">Action sensible bornée</p>
                                <h2 id="outbox-retry-title" className="mt-2 text-xl font-semibold">Préparer la reprise</h2>
                                <p className="mt-2 text-sm text-atlas-ink-muted">{retryTarget.event_type} · <span className="font-mono text-xs">{retryTarget.event_id}</span></p>
                            </div>
                            <button type="button" className="text-sm font-semibold text-atlas-ink-muted underline" onClick={() => { setRetryTarget(null); setRetryPreview(null); setRetryKey(null); setRetryError(null); }}>Annuler</button>
                        </div>
                        <label className="mt-5 block text-sm font-semibold">Motif vérifié
                            <select
                                value={retryReason}
                                onChange={(event) => { setRetryReason(event.target.value); setRetryPreview(null); setRetryKey(null); setRetryError(null); }}
                                className="mt-2 block min-h-11 w-full rounded-xl border border-atlas-border bg-white px-3.5 sm:max-w-md"
                            >
                                <option value="outbox.cause-corrected">Cause technique corrigée</option>
                                <option value="outbox.configuration-restored">Configuration restaurée</option>
                                <option value="outbox.provider-recovered">Prestataire de nouveau disponible</option>
                                <option value="outbox.false-positive-reviewed">Dead-letter vérifiée sans anomalie persistante</option>
                            </select>
                        </label>
                        {!retryPreview && <button type="button" disabled={retryBusy} onClick={() => void prepareRetry()} className="mt-5 min-h-11 rounded-xl bg-atlas-ink px-4 font-semibold text-white disabled:opacity-50">{retryBusy ? 'Vérification…' : 'Prévisualiser la reprise'}</button>}
                        {retryPreview && (
                            <div className="mt-5 rounded-xl border border-atlas-border bg-white p-4">
                                <p className="font-semibold">Dead-letter → En attente</p>
                                <p className="mt-1 text-sm text-atlas-ink-muted">Tentatives : {retryPreview.current.attempts} → 0 · disponibilité immédiate</p>
                                <ul className="mt-3 list-disc space-y-1 pl-5 text-sm text-atlas-ink-muted">{retryPreview.effects.map((effect) => <li key={effect}>{effect}</li>)}</ul>
                                <p className="mt-3 text-sm font-medium text-amber-800">Confirmez uniquement après avoir corrigé ou écarté la cause. Une remise externe dont l’issue était incertaine peut produire un doublon chez le destinataire.</p>
                                <button type="button" disabled={retryBusy} onClick={() => void confirmRetry()} className="mt-4 min-h-11 rounded-xl bg-atlas-ink px-4 font-semibold text-white disabled:opacity-50">{retryBusy ? 'Confirmation…' : 'Confirmer la remise en attente'}</button>
                            </div>
                        )}
                        {retryError && <div role="alert" className="mt-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{retryError}</div>}
                    </section>
                )}

                {error && <div role="alert" className="mt-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{error} <button type="button" className="ml-2 font-semibold underline" onClick={() => void load()}>Réessayer</button></div>}
                <section className="mt-8 overflow-hidden rounded-2xl border border-atlas-border bg-white shadow-sm">
                    <div className="flex items-center justify-between border-b border-atlas-border px-5 py-4"><p className="text-sm text-atlas-ink-muted">{result ? `${result.total} résultat${result.total > 1 ? 's' : ''}` : 'Chargement…'}</p>{loading && <span className="text-xs font-semibold text-atlas-accent">Actualisation…</span>}</div>
                    {!loading && result?.items.length === 0 && <div className="p-10 text-center text-sm text-atlas-ink-muted">Aucun événement pour ce filtre.</div>}
                    {result && result.items.length > 0 && (
                        <div className="divide-y divide-atlas-border md:hidden">
                            {result.items.map((item) => (
                                <article key={item.event_id} className="p-5">
                                    <div className="flex items-start justify-between gap-3">
                                        <p className="min-w-0 break-words font-medium">{item.event_type}</p>
                                        <span className={`shrink-0 rounded-full border px-2.5 py-1 text-xs font-semibold ${statusClass(item.status)}`}>{statusLabels[item.status]}</span>
                                    </div>
                                    <dl className="mt-4 grid grid-cols-2 gap-4 text-sm">
                                        <div><dt className="text-xs text-atlas-ink-muted">Tentatives</dt><dd className="mt-1 font-medium tabular-nums">{item.attempts}</dd></div>
                                        <div><dt className="text-xs text-atlas-ink-muted">Créé le</dt><dd className="mt-1 font-medium">{formatDate(item.created_at)}</dd></div>
                                        <div className="col-span-2"><dt className="text-xs text-atlas-ink-muted">Contexte</dt><dd className="mt-1">{'template_key' in item ? (item.template_key ?? 'Template non enregistré') : `Disponible ${formatDate(item.available_at)}`}</dd></div>
                                    </dl>
                                    <p className="mt-4 break-all font-mono text-[10px] text-atlas-ink-muted">{item.event_id}</p>
                                    {kind === 'outbox' && item.status === 'DeadLetter' && (
                                        <button type="button" disabled={!canRetry || retryBusy} onClick={() => selectRetryTarget(item)} className="mt-4 min-h-10 rounded-xl border border-atlas-border px-3.5 text-sm font-semibold disabled:cursor-not-allowed disabled:opacity-40">Préparer la reprise</button>
                                    )}
                                </article>
                            ))}
                        </div>
                    )}
                    {result && result.items.length > 0 && (
                        <div className="hidden overflow-x-auto md:block">
                            <table className="w-full min-w-[760px] text-left text-sm">
                                <thead className="bg-atlas-surface text-xs uppercase tracking-[.08em] text-atlas-ink-muted"><tr><th className="px-5 py-3 font-semibold">Événement</th><th className="px-5 py-3 font-semibold">État</th><th className="px-5 py-3 font-semibold">Tentatives</th><th className="px-5 py-3 font-semibold">Contexte</th><th className="px-5 py-3 font-semibold">Créé le</th>{kind === 'outbox' && <th className="px-5 py-3 font-semibold">Action</th>}</tr></thead>
                                <tbody className="divide-y divide-atlas-border">
                                    {result.items.map((item) => (
                                        <tr key={item.event_id}>
                                            <td className="px-5 py-4"><p className="font-medium">{item.event_type}</p><p className="mt-1 font-mono text-[11px] text-atlas-ink-muted">{item.event_id}</p></td>
                                            <td className="px-5 py-4"><span className={`rounded-full border px-2.5 py-1 text-xs font-semibold ${statusClass(item.status)}`}>{statusLabels[item.status]}</span></td>
                                            <td className="px-5 py-4 tabular-nums">{item.attempts}</td>
                                            <td className="px-5 py-4 text-atlas-ink-muted">{'template_key' in item ? (item.template_key ?? 'Template non enregistré') : `Disponible ${formatDate(item.available_at)}`}</td>
                                            <td className="px-5 py-4 text-atlas-ink-muted">{formatDate(item.created_at)}</td>
                                            {kind === 'outbox' && <td className="px-5 py-4">{item.status === 'DeadLetter' && <button type="button" disabled={!canRetry || retryBusy} onClick={() => selectRetryTarget(item)} className="min-h-10 whitespace-nowrap rounded-xl border border-atlas-border px-3.5 text-sm font-semibold disabled:cursor-not-allowed disabled:opacity-40">Préparer</button>}</td>}
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                    {result && result.total_pages > 1 && (
                        <div className="flex items-center justify-between border-t border-atlas-border px-5 py-4">
                            <button type="button" disabled={page <= 1 || loading} onClick={() => setPage((current) => current - 1)} className="min-h-10 rounded-xl border border-atlas-border px-3.5 text-sm font-semibold disabled:opacity-40">Précédent</button>
                            <span className="text-sm text-atlas-ink-muted">Page {result.page} sur {result.total_pages}</span>
                            <button type="button" disabled={page >= result.total_pages || loading} onClick={() => setPage((current) => current + 1)} className="inline-flex min-h-10 items-center gap-2 rounded-xl border border-atlas-border px-3.5 text-sm font-semibold disabled:opacity-40">Suivant <Icon name="chevron-right" className="size-4" /></button>
                        </div>
                    )}
                </section>
            </main>
        </OperatorFrame>
    );
}
