import { FormEvent, useEffect, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { createQuote, listQuotes } from '@/api/billing';
import { getClient, getOpportunity, listContacts, loseOpportunity, qualifyOpportunity, updateOpportunity } from '@/api/crm';
import { StatusBadge } from '@/components/crm/StatusBadge';
import { RequireAuth } from '@/components/layout/RequireAuth';
import { ErrorBanner, FormField, SubmitButton, SuccessBanner, inputClassName } from '@/components/auth/AuthLayout';
import { useAuth } from '@/hooks/useAuth';
import type { ClientDetail, ContactSummary, OpportunityDetail, QuoteSummary } from '@/types/api';
import { formatMoney } from '@/utils/format';

type LossReasonCode = NonNullable<OpportunityDetail['loss_reason_code']>;

const lossReasons: Array<{ code: LossReasonCode; label: string }> = [
    { code: 'Budget', label: 'Budget insuffisant' },
    { code: 'Timing', label: 'Calendrier ou priorité reportée' },
    { code: 'Competitor', label: 'Concurrent retenu' },
    { code: 'NoDecision', label: 'Aucune décision' },
    { code: 'Other', label: 'Autre raison' },
];

function lossReasonLabel(code: OpportunityDetail['loss_reason_code']): string | null {
    return lossReasons.find((reason) => reason.code === code)?.label ?? null;
}

function formatDate(value: string): string {
    return new Intl.DateTimeFormat('fr-FR', { dateStyle: 'long' }).format(new Date(value));
}

export function OpportunityDetailPage() {
    const { opportunityId } = useParams<{ opportunityId: string }>();
    const navigate = useNavigate();
    const { session } = useAuth();
    const token = session!.token;
    const workspaceId = session!.workspaceId!;

    const [opportunity, setOpportunity] = useState<OpportunityDetail | null>(null);
    const [client, setClient] = useState<ClientDetail | null>(null);
    const [contact, setContact] = useState<ContactSummary | null>(null);
    const [contacts, setContacts] = useState<ContactSummary[]>([]);
    const [quotes, setQuotes] = useState<QuoteSummary[]>([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [actionLoading, setActionLoading] = useState(false);
    const [showQuoteForm, setShowQuoteForm] = useState(false);
    const [lineDescription, setLineDescription] = useState('');
    const [lineAmount, setLineAmount] = useState('');
    const [success, setSuccess] = useState<string | null>(null);
    const [showEditForm, setShowEditForm] = useState(false);
    const [editTitle, setEditTitle] = useState('');
    const [editContactId, setEditContactId] = useState('');
    const [editAmount, setEditAmount] = useState('');
    const [editCurrency, setEditCurrency] = useState('EUR');
    const [showLossForm, setShowLossForm] = useState(false);
    const [lossReasonCode, setLossReasonCode] = useState<LossReasonCode | ''>('');
    const [lossNote, setLossNote] = useState('');

    async function reload() {
        if (!opportunityId) return;

        setLoading(true);
        setError(null);
        try {
            const opp = await getOpportunity(token, workspaceId, opportunityId);
            const [clientData, contacts, allQuotes] = await Promise.all([
                getClient(token, workspaceId, opp.client_id),
                listContacts(token, workspaceId, opp.client_id),
                listQuotes(token, workspaceId),
            ]);
            setOpportunity(opp);
            setClient(clientData);
            setContacts(contacts);
            setContact(contacts.find((candidate) => candidate.contact_id === opp.contact_id) ?? null);
            setQuotes(allQuotes.filter((q) => q.opportunity_id === opportunityId));
        } catch (err) {
            setError(err instanceof Error ? err.message : 'Chargement impossible');
        } finally {
            setLoading(false);
        }
    }

    useEffect(() => {
        void reload();
    }, [token, workspaceId, opportunityId]);

    async function onQualify() {
        if (!opportunity) return;
        setActionLoading(true);
        setError(null);
        setSuccess(null);
        try {
            await qualifyOpportunity(token, workspaceId, opportunity.opportunity_id, opportunity.version);
            await reload();
            setSuccess('L’opportunité est qualifiée. Vous pouvez maintenant préparer un devis.');
        } catch (err) {
            setError(err instanceof Error ? err.message : 'Qualification impossible');
        } finally {
            setActionLoading(false);
        }
    }

    function openEditForm() {
        if (!opportunity) return;

        setEditTitle(opportunity.title);
        setEditContactId(opportunity.contact_id ?? '');
        setEditAmount(opportunity.estimated_amount_cents != null
            ? (opportunity.estimated_amount_cents / 100).toFixed(2).replace('.', ',')
            : '');
        setEditCurrency(opportunity.currency);
        setShowLossForm(false);
        setShowEditForm(true);
        setError(null);
        setSuccess(null);
    }

    function openLossForm() {
        setShowEditForm(false);
        setShowQuoteForm(false);
        setLossReasonCode('');
        setLossNote('');
        setShowLossForm(true);
        setError(null);
        setSuccess(null);
    }

    async function onLoseOpportunity(event: FormEvent) {
        event.preventDefault();
        if (!opportunity || lossReasonCode === '') return;

        setActionLoading(true);
        setError(null);
        setSuccess(null);
        try {
            await loseOpportunity(
                token,
                workspaceId,
                opportunity.opportunity_id,
                lossReasonCode,
                lossNote.trim() || null,
                opportunity.version,
            );
            setShowLossForm(false);
            await reload();
            setSuccess('L’opportunité est clôturée comme perdue.');
        } catch (err) {
            setError(err instanceof Error ? err.message : 'Clôture impossible');
        } finally {
            setActionLoading(false);
        }
    }

    async function onUpdateOpportunity(event: FormEvent) {
        event.preventDefault();
        if (!opportunity) return;

        const estimatedAmountCents = editAmount.trim() === ''
            ? null
            : Math.round(parseFloat(editAmount.replace(',', '.')) * 100);

        if (estimatedAmountCents !== null && (!Number.isFinite(estimatedAmountCents) || estimatedAmountCents < 0)) {
            setError('Montant invalide');
            return;
        }

        setActionLoading(true);
        setError(null);
        setSuccess(null);
        try {
            await updateOpportunity(
                token,
                workspaceId,
                opportunity.opportunity_id,
                {
                    contact_id: editContactId || null,
                    title: editTitle.trim(),
                    estimated_amount_cents: estimatedAmountCents,
                    currency: editCurrency.trim().toUpperCase(),
                },
                opportunity.version,
            );
            setShowEditForm(false);
            await reload();
            setSuccess('Les informations de l’opportunité sont enregistrées.');
        } catch (err) {
            setError(err instanceof Error ? err.message : 'Modification impossible');
        } finally {
            setActionLoading(false);
        }
    }

    async function onCreateQuote(event: FormEvent) {
        event.preventDefault();
        if (!opportunity) return;

        const unitPriceCents = Math.round(parseFloat(lineAmount.replace(',', '.')) * 100);
        if (!Number.isFinite(unitPriceCents) || unitPriceCents < 0) {
            setError('Montant invalide');
            return;
        }

        setActionLoading(true);
        setError(null);
        setSuccess(null);
        try {
            const result = await createQuote(token, workspaceId, {
                client_id: opportunity.client_id,
                opportunity_id: opportunity.opportunity_id,
                currency: opportunity.currency,
                lines: [{ description: lineDescription, quantity: 1, unit_price_cents: unitPriceCents }],
            });
            navigate(`/app/billing/quotes/${result.quote_id}`);
        } catch (err) {
            setError(err instanceof Error ? err.message : 'Création du devis impossible');
        } finally {
            setActionLoading(false);
        }
    }

    if (!opportunityId) {
        return (
            <RequireAuth>
                <p className="text-sm text-red-800">Opportunité introuvable.</p>
            </RequireAuth>
        );
    }

    return (
        <RequireAuth>
            <div className="mx-auto max-w-4xl">
                {client && (
                    <Link
                        to={`/app/crm/clients/${client.client_id}`}
                        className="text-sm font-medium text-atlas-accent hover:underline"
                    >
                        ← {client.display_name}
                    </Link>
                )}

                {loading && <div className="mt-8 h-40 animate-pulse rounded-2xl bg-white shadow-sm" />}

                {error && (
                    <div className="mt-6">
                        <ErrorBanner message={error} />
                    </div>
                )}

                {success && (
                    <div className="mt-6">
                        <SuccessBanner message={success} />
                    </div>
                )}

                {opportunity && client && !loading && (
                    <>
                        <div className="mt-6 flex flex-wrap items-start justify-between gap-4">
                            <div>
                                <h2 className="text-3xl font-semibold tracking-tight text-atlas-ink">
                                    {opportunity.title}
                                </h2>
                                <p className="mt-2 text-sm text-atlas-ink-muted">
                                    Client : {client.display_name}
                                    {opportunity.estimated_amount_cents != null &&
                                        ` · ${formatMoney(opportunity.estimated_amount_cents, opportunity.currency)}`}
                                </p>
                                {contact && typeof contact.profile.display_name === 'string' && (
                                    <p className="mt-1 text-sm text-atlas-ink-muted">
                                        Contact : {contact.profile.display_name}
                                    </p>
                                )}
                                {opportunity.contact_id && !contact && (
                                    <p className="mt-1 text-sm text-atlas-ink-muted">
                                        Contact associé indisponible
                                    </p>
                                )}
                            </div>
                            <div className="flex flex-col items-end gap-3">
                                <StatusBadge status={opportunity.status} />
                                {['Open', 'Qualified'].includes(opportunity.status) && !showEditForm && !showLossForm && (
                                    <div className="flex flex-col items-end gap-2">
                                        <button
                                            type="button"
                                            onClick={openEditForm}
                                            className="text-sm font-semibold text-atlas-accent hover:underline"
                                        >
                                            Modifier l’opportunité
                                        </button>
                                        <button
                                            type="button"
                                            onClick={openLossForm}
                                            className="text-sm font-semibold text-red-700 hover:underline"
                                        >
                                            Marquer comme perdue
                                        </button>
                                    </div>
                                )}
                            </div>
                        </div>

                        {opportunity.status === 'Lost' && opportunity.loss_reason_code && (
                            <div className="mt-6 rounded-xl border border-atlas-border bg-atlas-card px-5 py-4">
                                <p className="text-sm font-semibold text-atlas-ink">
                                    Raison : {lossReasonLabel(opportunity.loss_reason_code) ?? opportunity.loss_reason_code}
                                </p>
                                {opportunity.lost_at && (
                                    <p className="mt-1 text-xs text-atlas-ink-muted">
                                        Clôturée le {formatDate(opportunity.lost_at)}
                                    </p>
                                )}
                                {opportunity.loss_note && (
                                    <p className="mt-3 whitespace-pre-wrap text-sm text-atlas-ink-muted">
                                        {opportunity.loss_note}
                                    </p>
                                )}
                            </div>
                        )}

                        {showEditForm && (
                            <form
                                aria-label="Modifier l’opportunité"
                                onSubmit={onUpdateOpportunity}
                                className="mt-6 rounded-2xl border border-atlas-border bg-atlas-card p-6 shadow-sm"
                            >
                                <fieldset disabled={actionLoading} className="space-y-4">
                                    <legend className="text-base font-semibold text-atlas-ink">
                                        Informations commerciales
                                    </legend>
                                    <FormField label="Titre">
                                        <input
                                            required
                                            minLength={2}
                                            maxLength={200}
                                            className={inputClassName}
                                            value={editTitle}
                                            onChange={(event) => setEditTitle(event.target.value)}
                                        />
                                    </FormField>
                                    <FormField
                                        label="Contact associé (optionnel)"
                                        hint="Retirez ou remplacez ce contact avant de l’archiver."
                                    >
                                        <select
                                            className={inputClassName}
                                            value={editContactId}
                                            onChange={(event) => setEditContactId(event.target.value)}
                                        >
                                            <option value="">Aucun contact associé</option>
                                            {contacts
                                                .filter((candidate) => candidate.status === 'Active')
                                                .map((candidate) => (
                                                    <option key={candidate.contact_id} value={candidate.contact_id}>
                                                        {typeof candidate.profile.display_name === 'string'
                                                            ? candidate.profile.display_name
                                                            : 'Contact sans nom'}
                                                        {candidate.is_primary ? ' — principal' : ''}
                                                    </option>
                                                ))}
                                        </select>
                                    </FormField>
                                    <div className="grid gap-4 sm:grid-cols-[1fr_8rem]">
                                        <FormField label="Montant estimé (optionnel)">
                                            <input
                                                inputMode="decimal"
                                                className={inputClassName}
                                                value={editAmount}
                                                onChange={(event) => setEditAmount(event.target.value)}
                                                placeholder="1200"
                                            />
                                        </FormField>
                                        <FormField label="Devise">
                                            <input
                                                required
                                                minLength={3}
                                                maxLength={3}
                                                className={inputClassName}
                                                value={editCurrency}
                                                onChange={(event) => setEditCurrency(event.target.value.toUpperCase())}
                                            />
                                        </FormField>
                                    </div>
                                    <p className="text-xs text-atlas-ink-muted">
                                        Les devis et snapshots déjà créés conservent leurs valeurs historiques.
                                    </p>
                                    <div className="flex flex-col gap-3 sm:flex-row">
                                        <SubmitButton loading={actionLoading} loadingLabel="Enregistrement…">
                                            Enregistrer les modifications
                                        </SubmitButton>
                                        <button
                                            type="button"
                                            onClick={() => setShowEditForm(false)}
                                            className="rounded-xl border border-atlas-border px-4 py-3 text-sm font-medium text-atlas-ink-muted"
                                        >
                                            Annuler
                                        </button>
                                    </div>
                                </fieldset>
                            </form>
                        )}

                        {showLossForm && (
                            <form
                                aria-label="Marquer l’opportunité comme perdue"
                                onSubmit={onLoseOpportunity}
                                className="mt-6 rounded-2xl border border-red-200 bg-red-50 p-6"
                            >
                                <fieldset disabled={actionLoading} className="space-y-4">
                                    <legend className="text-base font-semibold text-red-900">
                                        Confirmer la perte de l’opportunité
                                    </legend>
                                    <p className="text-sm text-red-800">
                                        Cette clôture est terminale. Elle ne modifie aucun devis déjà créé.
                                    </p>
                                    <FormField label="Raison de la perte">
                                        <select
                                            required
                                            className={inputClassName}
                                            value={lossReasonCode}
                                            onChange={(event) => setLossReasonCode(event.target.value as LossReasonCode | '')}
                                        >
                                            <option value="">Sélectionner une raison</option>
                                            {lossReasons.map((reason) => (
                                                <option key={reason.code} value={reason.code}>{reason.label}</option>
                                            ))}
                                        </select>
                                    </FormField>
                                    <FormField
                                        label="Contexte complémentaire (optionnel)"
                                        hint="Cette note reste interne et n’est pas publiée dans les événements."
                                    >
                                        <textarea
                                            maxLength={500}
                                            rows={4}
                                            className={inputClassName}
                                            value={lossNote}
                                            onChange={(event) => setLossNote(event.target.value)}
                                        />
                                    </FormField>
                                    <div className="flex flex-col gap-3 sm:flex-row">
                                        <button
                                            type="submit"
                                            className="rounded-xl bg-red-700 px-4 py-3 text-sm font-semibold text-white hover:bg-red-800 disabled:cursor-not-allowed disabled:opacity-60"
                                        >
                                            {actionLoading ? 'Clôture…' : 'Confirmer la perte'}
                                        </button>
                                        <button
                                            type="button"
                                            onClick={() => setShowLossForm(false)}
                                            className="rounded-xl border border-atlas-border bg-white px-4 py-3 text-sm font-medium text-atlas-ink-muted"
                                        >
                                            Annuler
                                        </button>
                                    </div>
                                </fieldset>
                            </form>
                        )}

                        {opportunity.status === 'Open' && !showEditForm && !showLossForm && (
                            <div className="mt-6 rounded-xl border border-atlas-border bg-atlas-card px-4 py-4">
                                <p className="text-sm text-atlas-ink-muted">
                                    Qualifiez l'opportunité avant d'envoyer un devis au client.
                                </p>
                                <button
                                    type="button"
                                    disabled={actionLoading}
                                    onClick={() => void onQualify()}
                                    className="mt-3 rounded-xl bg-atlas-accent px-4 py-2 text-sm font-semibold text-white hover:opacity-90 disabled:opacity-60"
                                >
                                    Qualifier l'opportunité
                                </button>
                            </div>
                        )}

                        <section className="mt-10">
                            <div className="mb-4 flex items-center justify-between gap-4">
                                <h3 className="text-lg font-semibold text-atlas-ink">Devis</h3>
                                {!showQuoteForm && !showEditForm && !showLossForm && opportunity.status === 'Qualified' && (
                                    <button
                                        type="button"
                                        onClick={() => setShowQuoteForm(true)}
                                        className="rounded-xl bg-atlas-accent px-4 py-2 text-sm font-semibold text-white hover:opacity-90"
                                    >
                                        Nouveau devis
                                    </button>
                                )}
                            </div>

                            {showQuoteForm && (
                                <form
                                    onSubmit={onCreateQuote}
                                    className="mb-6 rounded-2xl border border-atlas-border bg-atlas-card p-6 shadow-sm"
                                >
                                    <FormField label="Description de la ligne">
                                        <input
                                            required
                                            className={inputClassName}
                                            value={lineDescription}
                                            onChange={(e) => setLineDescription(e.target.value)}
                                            placeholder="Prestation conseil"
                                        />
                                    </FormField>
                                    <div className="mt-4">
                                        <FormField label="Montant (€)">
                                            <input
                                                required
                                                inputMode="decimal"
                                                className={inputClassName}
                                                value={lineAmount}
                                                onChange={(e) => setLineAmount(e.target.value)}
                                                placeholder="500"
                                            />
                                        </FormField>
                                    </div>
                                    <div className="mt-4 flex flex-col gap-3 sm:flex-row">
                                        <SubmitButton loading={actionLoading} loadingLabel="Création du devis…">
                                            Créer le devis
                                        </SubmitButton>
                                        <button
                                            type="button"
                                            onClick={() => setShowQuoteForm(false)}
                                            className="rounded-xl border border-atlas-border px-4 py-3 text-sm font-medium text-atlas-ink-muted"
                                        >
                                            Annuler
                                        </button>
                                    </div>
                                </form>
                            )}

                            {quotes.length === 0 && (
                                <div className="rounded-2xl border border-dashed border-atlas-border bg-atlas-card px-6 py-10 text-center">
                                    <p className="text-sm text-atlas-ink-muted">
                                        Aucun devis. Créez un brouillon puis envoyez-le au client.
                                    </p>
                                </div>
                            )}

                            {quotes.length > 0 && (
                                <ul className="divide-y divide-atlas-border overflow-hidden rounded-2xl border border-atlas-border bg-atlas-card shadow-sm">
                                    {quotes.map((quote) => (
                                        <li
                                            key={quote.quote_id}
                                            className="flex flex-wrap items-center justify-between gap-4 px-6 py-4"
                                        >
                                            <div>
                                                <p className="font-medium text-atlas-ink">
                                                    {formatMoney(quote.total_cents, quote.currency)}
                                                </p>
                                                <p className="mt-0.5 font-mono text-xs text-atlas-ink-muted">
                                                    {quote.quote_id.slice(0, 8)}…
                                                </p>
                                            </div>
                                            <div className="flex items-center gap-3">
                                                <StatusBadge status={quote.status} />
                                                <Link
                                                    to={`/app/billing/quotes/${quote.quote_id}`}
                                                    className="inline-flex min-h-9 items-center rounded-lg bg-atlas-accent px-3 py-1.5 text-xs font-semibold text-white hover:opacity-90"
                                                >
                                                    {quote.status === 'Draft' ? 'Vérifier et envoyer' : 'Voir le devis'}
                                                </Link>
                                            </div>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </section>
                    </>
                )}
            </div>
        </RequireAuth>
    );
}
