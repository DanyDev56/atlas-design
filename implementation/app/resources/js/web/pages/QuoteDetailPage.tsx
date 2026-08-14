import { FormEvent, useEffect, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { getQuote, sendQuote, updateQuote } from '@/api/billing';
import { getClient } from '@/api/crm';
import { ErrorBanner, FormField, SubmitButton, SuccessBanner, inputClassName } from '@/components/auth/AuthLayout';
import { StatusBadge } from '@/components/crm/StatusBadge';
import { RequireAuth } from '@/components/layout/RequireAuth';
import { PageSkeleton } from '@/components/ui/PageSkeleton';
import { useAuth } from '@/hooks/useAuth';
import type { ClientDetail, QuoteDetail, QuoteLine, SendQuoteResponse } from '@/types/api';
import { formatMoney } from '@/utils/format';

interface EditableQuoteLine {
    key: string;
    description: string;
    quantity: string;
    unitPrice: string;
}

function editableLine(line?: QuoteLine): EditableQuoteLine {
    return {
        key: crypto.randomUUID(),
        description: line?.description ?? '',
        quantity: line ? String(line.quantity) : '1',
        unitPrice: line ? (line.unit_price_cents / 100).toFixed(2).replace('.', ',') : '',
    };
}

function parseUnitPrice(value: string): number | null {
    const normalized = value.trim().replace(/\s/g, '').replace(',', '.');

    if (!/^\d+(\.\d{1,2})?$/.test(normalized)) return null;

    const cents = Math.round(Number(normalized) * 100);
    return Number.isSafeInteger(cents) && cents >= 0 ? cents : null;
}

export function QuoteDetailPage() {
    const { quoteId } = useParams<{ quoteId: string }>();
    const { session } = useAuth();
    const token = session!.token;
    const workspaceId = session!.workspaceId!;
    const [quote, setQuote] = useState<QuoteDetail | null>(null);
    const [client, setClient] = useState<ClientDetail | null>(null);
    const [lines, setLines] = useState<EditableQuoteLine[]>([]);
    const [loading, setLoading] = useState(true);
    const [actionLoading, setActionLoading] = useState(false);
    const [dirty, setDirty] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [success, setSuccess] = useState<string | null>(null);
    const [sentQuote, setSentQuote] = useState<SendQuoteResponse | null>(null);
    const [linkCopied, setLinkCopied] = useState(false);

    async function loadQuote() {
        if (!quoteId) return;

        setLoading(true);
        setError(null);
        try {
            const quoteData = await getQuote(token, workspaceId, quoteId);
            setQuote(quoteData);
            setLines(quoteData.lines.map((line) => editableLine(line)));
            setDirty(false);

            try {
                setClient(await getClient(token, workspaceId, quoteData.client_id));
            } catch {
                setClient(null);
            }
        } catch {
            setError('Ce devis est introuvable ou vous n’avez plus accès à son espace de travail.');
        } finally {
            setLoading(false);
        }
    }

    useEffect(() => {
        void loadQuote();
    }, [quoteId, token, workspaceId]);

    function changeLine(key: string, field: 'description' | 'quantity' | 'unitPrice', value: string) {
        setLines((current) => current.map((line) => (line.key === key ? { ...line, [field]: value } : line)));
        setDirty(true);
        setError(null);
        setSuccess(null);
    }

    function addLine() {
        setLines((current) => [...current, editableLine()]);
        setDirty(true);
        setError(null);
        setSuccess(null);
    }

    function removeLine(key: string) {
        if (lines.length === 1) return;
        setLines((current) => current.filter((line) => line.key !== key));
        setDirty(true);
        setError(null);
        setSuccess(null);
    }

    function validatedLines(): QuoteLine[] | null {
        const result: QuoteLine[] = [];

        for (const line of lines) {
            const description = line.description.trim();
            const quantity = Number(line.quantity);
            const unitPriceCents = parseUnitPrice(line.unitPrice);

            if (!description) {
                setError('Décrivez chaque prestation avant d’enregistrer le devis.');
                return null;
            }

            if (!Number.isInteger(quantity) || quantity < 1) {
                setError('Chaque quantité doit être un nombre entier supérieur ou égal à 1.');
                return null;
            }

            if (unitPriceCents === null) {
                setError('Chaque prix doit être positif ou nul et comporter au maximum deux décimales.');
                return null;
            }

            result.push({ description, quantity, unit_price_cents: unitPriceCents });
        }

        return result;
    }

    async function onSave(event: FormEvent) {
        event.preventDefault();
        if (!quote || quote.status !== 'Draft') return;

        const payload = validatedLines();
        if (!payload) return;

        setActionLoading(true);
        setError(null);
        setSuccess(null);
        try {
            const result = await updateQuote(token, workspaceId, quote.quote_id, payload, quote.version);
            setQuote({
                ...quote,
                lines: payload,
                total_cents: result.total_cents,
                status: result.status,
                version: result.version,
            });
            setLines(payload.map((line) => editableLine(line)));
            setDirty(false);
            setSuccess('Les modifications sont enregistrées. Le total a été recalculé par Atlas.');
        } catch (err) {
            setError(err instanceof Error ? err.message : 'Enregistrement du devis impossible');
        } finally {
            setActionLoading(false);
        }
    }

    async function onSend() {
        if (!quote || quote.status !== 'Draft' || dirty) return;

        setActionLoading(true);
        setError(null);
        setSuccess(null);
        setSentQuote(null);
        setLinkCopied(false);
        try {
            const result = await sendQuote(token, workspaceId, quote.quote_id, quote.version);
            setSentQuote(result);
            setQuote({ ...quote, status: result.status, version: result.version });
            setSuccess('Le devis est envoyé. Partagez maintenant le lien d’acceptation avec votre client.');
        } catch (err) {
            setError(err instanceof Error ? err.message : 'Envoi du devis impossible');
        } finally {
            setActionLoading(false);
        }
    }

    const acceptUrl =
        sentQuote &&
        `${window.location.origin}/app/quotes/accept/${workspaceId}/${sentQuote.quote_id}?token=${encodeURIComponent(sentQuote.public_accept_token)}`;

    async function copyAcceptUrl() {
        if (!acceptUrl) return;

        try {
            await navigator.clipboard.writeText(acceptUrl);
            setLinkCopied(true);
        } catch {
            setError('Le lien n’a pas pu être copié. Sélectionnez-le manuellement.');
        }
    }

    if (!quoteId) {
        return (
            <RequireAuth>
                <p className="text-sm text-red-800">Devis introuvable.</p>
            </RequireAuth>
        );
    }

    const backUrl = quote?.opportunity_id
        ? `/app/crm/opportunities/${quote.opportunity_id}`
        : '/app/billing';
    const backLabel = quote?.opportunity_id ? 'Retour à l’opportunité' : 'Retour aux devis';

    return (
        <RequireAuth>
            <div className="mx-auto max-w-4xl">
                <Link to={backUrl} className="text-sm font-medium text-atlas-accent hover:underline">
                    ← {backLabel}
                </Link>

                {loading && <div className="mt-8"><PageSkeleton rows={4} /></div>}

                {!loading && error && !quote && (
                    <div className="mt-6">
                        <ErrorBanner message={error} />
                        <button
                            type="button"
                            onClick={() => void loadQuote()}
                            className="text-sm font-semibold text-atlas-accent hover:underline"
                        >
                            Réessayer
                        </button>
                    </div>
                )}

                {quote && !loading && (
                    <>
                        <div className="mt-6 flex flex-wrap items-start justify-between gap-4">
                            <div>
                                <p className="text-xs font-semibold uppercase tracking-[0.18em] text-atlas-accent">
                                    Devis {quote.quote_id.slice(0, 8).toUpperCase()}
                                </p>
                                <h2 className="mt-2 text-3xl font-semibold tracking-tight text-atlas-ink">
                                    {client?.display_name ?? 'Devis client'}
                                </h2>
                                <p className="mt-2 text-sm text-atlas-ink-muted">
                                    {quote.status === 'Draft'
                                        ? 'Vérifiez chaque prestation avant l’envoi.'
                                        : 'Ce devis est verrouillé depuis son envoi.'}
                                </p>
                            </div>
                            <StatusBadge status={quote.status} />
                        </div>

                        {error && <div className="mt-6"><ErrorBanner message={error} /></div>}
                        {success && <div className="mt-6"><SuccessBanner message={success} /></div>}

                        {sentQuote && acceptUrl && (
                            <div
                                role="status"
                                className="mt-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-4 text-sm text-emerald-900"
                            >
                                <p className="font-medium">Lien d’acceptation à transmettre au client</p>
                                <input
                                    readOnly
                                    aria-label="Lien d’acceptation du devis"
                                    value={acceptUrl}
                                    onFocus={(event) => event.currentTarget.select()}
                                    className="mt-3 w-full rounded-lg border border-emerald-200 bg-white px-3 py-2 font-mono text-xs text-emerald-900"
                                />
                                <div className="mt-3 flex flex-col gap-2 sm:flex-row">
                                    <button
                                        type="button"
                                        onClick={() => void copyAcceptUrl()}
                                        className="min-h-11 rounded-lg bg-emerald-800 px-3 py-2 text-xs font-semibold text-white hover:bg-emerald-900"
                                    >
                                        {linkCopied ? 'Lien copié ✓' : 'Copier le lien'}
                                    </button>
                                    <a
                                        href={acceptUrl}
                                        target="_blank"
                                        rel="noreferrer"
                                        className="inline-flex min-h-11 items-center justify-center rounded-lg border border-emerald-300 px-3 py-2 text-xs font-semibold text-emerald-900 hover:bg-emerald-100"
                                    >
                                        Prévisualiser la page client
                                    </a>
                                </div>
                            </div>
                        )}

                        {quote.status === 'Draft' ? (
                            <form onSubmit={onSave} className="mt-8 space-y-5">
                                <div className="space-y-4">
                                    {lines.map((line, index) => (
                                        <div
                                            key={line.key}
                                            className="rounded-2xl border border-atlas-border bg-atlas-card p-5 shadow-sm"
                                        >
                                            <div className="mb-4 flex items-center justify-between gap-3">
                                                <p className="text-sm font-semibold text-atlas-ink">
                                                    Prestation {index + 1}
                                                </p>
                                                <button
                                                    type="button"
                                                    disabled={lines.length === 1}
                                                    onClick={() => removeLine(line.key)}
                                                    aria-label={`Supprimer la prestation ${index + 1}`}
                                                    className="text-xs font-medium text-red-700 hover:underline disabled:cursor-not-allowed disabled:text-atlas-ink-muted disabled:no-underline"
                                                >
                                                    Supprimer
                                                </button>
                                            </div>
                                            <FormField label="Description">
                                                <input
                                                    required
                                                    maxLength={500}
                                                    className={inputClassName}
                                                    value={line.description}
                                                    onChange={(event) => changeLine(line.key, 'description', event.target.value)}
                                                    placeholder="Ex. Direction artistique"
                                                />
                                            </FormField>
                                            <div className="mt-4 grid gap-4 sm:grid-cols-2">
                                                <FormField label="Quantité">
                                                    <input
                                                        required
                                                        type="number"
                                                        min="1"
                                                        step="1"
                                                        inputMode="numeric"
                                                        className={inputClassName}
                                                        value={line.quantity}
                                                        onChange={(event) => changeLine(line.key, 'quantity', event.target.value)}
                                                    />
                                                </FormField>
                                                <FormField label={`Prix unitaire (${quote.currency})`}>
                                                    <input
                                                        required
                                                        inputMode="decimal"
                                                        className={inputClassName}
                                                        value={line.unitPrice}
                                                        onChange={(event) => changeLine(line.key, 'unitPrice', event.target.value)}
                                                        placeholder="750,00"
                                                    />
                                                </FormField>
                                            </div>
                                        </div>
                                    ))}
                                </div>

                                <button
                                    type="button"
                                    onClick={addLine}
                                    className="min-h-11 rounded-xl border border-atlas-border bg-white px-4 py-2 text-sm font-semibold text-atlas-ink hover:bg-slate-50"
                                >
                                    + Ajouter une prestation
                                </button>

                                <div className="rounded-2xl border border-atlas-border bg-slate-50 p-5">
                                    <div className="flex flex-wrap items-end justify-between gap-4">
                                        <div>
                                            <p className="text-xs font-medium uppercase tracking-wide text-atlas-ink-muted">
                                                Total enregistré
                                            </p>
                                            <p className="mt-1 text-2xl font-semibold text-atlas-ink">
                                                {formatMoney(quote.total_cents, quote.currency)}
                                            </p>
                                            {dirty && (
                                                <p className="mt-1 text-xs text-amber-800">
                                                    Enregistrez les modifications pour actualiser ce total.
                                                </p>
                                            )}
                                        </div>
                                        <div className="flex w-full flex-col gap-3 sm:w-auto sm:min-w-64">
                                            <SubmitButton
                                                loading={actionLoading}
                                                disabled={!dirty}
                                                loadingLabel="Enregistrement…"
                                            >
                                                Enregistrer les modifications
                                            </SubmitButton>
                                            <button
                                                type="button"
                                                disabled={actionLoading || dirty}
                                                onClick={() => void onSend()}
                                                className="min-h-11 rounded-xl bg-atlas-ink px-4 py-3 text-sm font-semibold text-white hover:bg-atlas-sidebar disabled:cursor-not-allowed disabled:opacity-50"
                                            >
                                                Envoyer au client
                                            </button>
                                        </div>
                                    </div>
                                    <p className="mt-4 text-xs leading-relaxed text-atlas-ink-muted">
                                        Après l’envoi, les lignes et le montant de ce devis ne pourront plus être modifiés.
                                    </p>
                                </div>
                            </form>
                        ) : (
                            <section aria-labelledby="quote-lines-title" className="mt-8">
                                <h3 id="quote-lines-title" className="text-lg font-semibold text-atlas-ink">
                                    Prestations
                                </h3>
                                <div className="mt-4 overflow-hidden rounded-2xl border border-atlas-border bg-atlas-card shadow-sm">
                                    <ul className="divide-y divide-atlas-border">
                                        {quote.lines.map((line, index) => (
                                            <li key={`${line.description}-${index}`} className="flex flex-wrap items-start justify-between gap-4 px-5 py-4">
                                                <div>
                                                    <p className="text-sm font-medium text-atlas-ink">{line.description}</p>
                                                    <p className="mt-1 text-xs text-atlas-ink-muted">Quantité : {line.quantity}</p>
                                                </div>
                                                <p className="text-sm font-semibold tabular-nums text-atlas-ink">
                                                    {formatMoney(line.unit_price_cents, quote.currency)} / unité
                                                </p>
                                            </li>
                                        ))}
                                    </ul>
                                    <div className="flex items-center justify-between gap-4 bg-slate-50 px-5 py-4">
                                        <p className="text-sm font-semibold text-atlas-ink">Total</p>
                                        <p className="text-xl font-semibold tabular-nums text-atlas-ink">
                                            {formatMoney(quote.total_cents, quote.currency)}
                                        </p>
                                    </div>
                                </div>
                            </section>
                        )}
                    </>
                )}
            </div>
        </RequireAuth>
    );
}
