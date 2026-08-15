import { FormEvent, useEffect, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { getInvoice, issueInvoice, recordPayment, sendInvoice } from '@/api/billing';
import { getClient } from '@/api/crm';
import { ErrorBanner, FormField, SubmitButton, SuccessBanner, inputClassName } from '@/components/auth/AuthLayout';
import { StatusBadge } from '@/components/crm/StatusBadge';
import { RequireAuth } from '@/components/layout/RequireAuth';
import { PageSkeleton } from '@/components/ui/PageSkeleton';
import { useAuth } from '@/hooks/useAuth';
import type { ClientDetail, InvoiceDetail } from '@/types/api';
import { formatMoney } from '@/utils/format';

function parseAmount(value: string): number | null {
    const normalized = value.trim().replace(/\s/g, '').replace(',', '.');

    if (!/^\d+(\.\d{1,2})?$/.test(normalized)) return null;

    const cents = Math.round(Number(normalized) * 100);
    return Number.isSafeInteger(cents) && cents > 0 ? cents : null;
}

function formatDate(value: string | null): string | null {
    if (!value) return null;
    return new Intl.DateTimeFormat('fr-FR', { dateStyle: 'long' }).format(new Date(value));
}

export function InvoiceDetailPage() {
    const { invoiceId } = useParams<{ invoiceId: string }>();
    const { session } = useAuth();
    const token = session!.token;
    const workspaceId = session!.workspaceId!;
    const [invoice, setInvoice] = useState<InvoiceDetail | null>(null);
    const [client, setClient] = useState<ClientDetail | null>(null);
    const [loading, setLoading] = useState(true);
    const [actionLoading, setActionLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [success, setSuccess] = useState<string | null>(null);
    const [paymentAmount, setPaymentAmount] = useState('');
    const [paymentReference, setPaymentReference] = useState('');

    async function loadInvoice(showSkeleton = true) {
        if (!invoiceId) return;

        if (showSkeleton) setLoading(true);
        setError(null);
        try {
            const invoiceData = await getInvoice(token, workspaceId, invoiceId);
            setInvoice(invoiceData);
            setPaymentAmount((invoiceData.balance_cents / 100).toFixed(2).replace('.', ','));

            try {
                setClient(await getClient(token, workspaceId, invoiceData.client_id));
            } catch {
                setClient(null);
            }
        } catch {
            setError('Cette facture est introuvable ou vous n’avez plus accès à son espace de travail.');
        } finally {
            if (showSkeleton) setLoading(false);
        }
    }

    useEffect(() => {
        void loadInvoice();
    }, [invoiceId, token, workspaceId]);

    async function onIssue() {
        if (!invoice || invoice.status !== 'Draft') return;

        setActionLoading(true);
        setError(null);
        setSuccess(null);
        try {
            await issueInvoice(token, workspaceId, invoice.invoice_id, invoice.version);
            await loadInvoice(false);
            setSuccess('La facture est émise. Son numéro et sa date d’échéance sont maintenant définitifs.');
        } catch {
            setError('La facture n’a pas pu être émise. Rechargez-la avant de réessayer.');
        } finally {
            setActionLoading(false);
        }
    }

    async function onConfirmSent() {
        if (!invoice || invoice.status !== 'Issued' || invoice.sent_at) return;

        setActionLoading(true);
        setError(null);
        setSuccess(null);
        try {
            await sendInvoice(token, workspaceId, invoice.invoice_id, invoice.version);
            await loadInvoice(false);
            setSuccess('L’envoi de la facture est enregistré dans Atlas.');
        } catch {
            setError('L’envoi n’a pas pu être confirmé. Rechargez la facture avant de réessayer.');
        } finally {
            setActionLoading(false);
        }
    }

    async function onRecordPayment(event: FormEvent) {
        event.preventDefault();
        if (!invoice || invoice.status !== 'Issued' || invoice.balance_cents === 0) return;

        const amountCents = parseAmount(paymentAmount);
        if (amountCents === null) {
            setError('Saisissez un montant supérieur à zéro, avec au maximum deux décimales.');
            return;
        }

        if (amountCents > invoice.balance_cents) {
            setError(`Le montant ne peut pas dépasser le solde de ${formatMoney(invoice.balance_cents, invoice.currency)}.`);
            return;
        }

        setActionLoading(true);
        setError(null);
        setSuccess(null);
        try {
            const payment = await recordPayment(
                token,
                workspaceId,
                invoice.invoice_id,
                amountCents,
                paymentReference.trim(),
            );
            setInvoice({
                ...invoice,
                balance_cents: payment.balance_cents,
                settlement_status: payment.settlement_status,
                version: payment.version,
            });
            setPaymentReference('');
            setPaymentAmount(
                payment.balance_cents > 0 ? (payment.balance_cents / 100).toFixed(2).replace('.', ',') : '',
            );
            setSuccess(
                payment.balance_cents === 0
                    ? 'Le paiement est enregistré. Cette facture est entièrement réglée.'
                    : `Le paiement est enregistré. Il reste ${formatMoney(payment.balance_cents, invoice.currency)} à encaisser.`,
            );
        } catch {
            setError('Le paiement n’a pas pu être enregistré. Vérifiez le solde puis réessayez.');
        } finally {
            setActionLoading(false);
        }
    }

    if (!invoiceId) {
        return (
            <RequireAuth>
                <p className="text-sm text-red-800">Facture introuvable.</p>
            </RequireAuth>
        );
    }

    const displayedStatus =
        invoice?.settlement_status && invoice.settlement_status !== 'Unpaid'
            ? invoice.settlement_status
            : invoice?.status ?? 'Draft';

    return (
        <RequireAuth>
            <div className="mx-auto max-w-4xl">
                <Link to="/app/billing" className="text-sm font-medium text-atlas-accent hover:underline">
                    ← Retour à la facturation
                </Link>

                {loading && <div className="mt-8"><PageSkeleton rows={4} /></div>}

                {!loading && error && !invoice && (
                    <div className="mt-6">
                        <ErrorBanner message={error} />
                        <button
                            type="button"
                            onClick={() => void loadInvoice()}
                            className="text-sm font-semibold text-atlas-accent hover:underline"
                        >
                            Réessayer
                        </button>
                    </div>
                )}

                {invoice && !loading && (
                    <>
                        <div className="mt-6 flex flex-wrap items-start justify-between gap-4">
                            <div>
                                <p className="text-xs font-semibold uppercase tracking-[0.18em] text-atlas-accent">
                                    {invoice.invoice_number ?? `Facture ${invoice.invoice_id.slice(0, 8).toUpperCase()}`}
                                </p>
                                <h2 className="mt-2 text-3xl font-semibold tracking-tight text-atlas-ink">
                                    {client?.display_name ?? 'Facture client'}
                                </h2>
                                <div className="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-sm text-atlas-ink-muted">
                                    {invoice.issued_at && <span>Émise le {formatDate(invoice.issued_at)}</span>}
                                    {invoice.due_date && <span>Échéance le {formatDate(invoice.due_date)}</span>}
                                    {invoice.sent_at && <span>Envoi confirmé</span>}
                                </div>
                            </div>
                            <StatusBadge status={displayedStatus} />
                        </div>

                        {error && <div className="mt-6"><ErrorBanner message={error} /></div>}
                        {success && <div className="mt-6"><SuccessBanner message={success} /></div>}

                        <section aria-labelledby="invoice-lines-title" className="mt-8">
                            <div className="flex items-center justify-between gap-4">
                                <h3 id="invoice-lines-title" className="text-lg font-semibold text-atlas-ink">
                                    Prestations facturées
                                </h3>
                                {invoice.quote_id && (
                                    <Link
                                        to={`/app/billing/quotes/${invoice.quote_id}`}
                                        state={{ from: 'billing' }}
                                        className="text-sm font-medium text-atlas-accent hover:underline"
                                    >
                                        Voir le devis source
                                    </Link>
                                )}
                            </div>
                            <div className="mt-4 overflow-hidden rounded-2xl border border-atlas-border bg-atlas-card shadow-sm">
                                <ul className="divide-y divide-atlas-border">
                                    {invoice.lines.map((line, index) => (
                                        <li key={`${line.description}-${index}`} className="flex flex-wrap items-start justify-between gap-4 px-5 py-4">
                                            <div>
                                                <p className="text-sm font-medium text-atlas-ink">{line.description}</p>
                                                <p className="mt-1 text-xs text-atlas-ink-muted">Quantité : {line.quantity}</p>
                                            </div>
                                            <p className="text-sm font-semibold tabular-nums text-atlas-ink">
                                                {formatMoney(line.unit_price_cents, invoice.currency)} / unité
                                            </p>
                                        </li>
                                    ))}
                                </ul>
                                <div className="grid gap-px bg-atlas-border sm:grid-cols-2">
                                    <div className="bg-slate-50 px-5 py-4">
                                        <p className="text-xs font-medium uppercase tracking-wide text-atlas-ink-muted">Total</p>
                                        <p className="mt-1 text-xl font-semibold text-atlas-ink">
                                            {formatMoney(invoice.total_cents, invoice.currency)}
                                        </p>
                                    </div>
                                    <div className="bg-slate-50 px-5 py-4 sm:text-right">
                                        <p className="text-xs font-medium uppercase tracking-wide text-atlas-ink-muted">Reste à encaisser</p>
                                        <p className="mt-1 text-xl font-semibold text-atlas-ink">
                                            {formatMoney(invoice.balance_cents, invoice.currency)}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </section>

                        {invoice.status === 'Draft' && (
                            <section className="mt-6 rounded-2xl border border-amber-200 bg-amber-50 p-5">
                                <h3 className="font-semibold text-amber-950">Émettre cette facture</h3>
                                <p className="mt-2 text-sm leading-relaxed text-amber-900">
                                    Atlas attribuera un numéro définitif et fixera l’échéance à 30 jours. Cette étape ne pourra pas être annulée.
                                </p>
                                <button
                                    type="button"
                                    disabled={actionLoading}
                                    onClick={() => void onIssue()}
                                    className="mt-4 min-h-11 rounded-xl bg-atlas-ink px-4 py-2.5 text-sm font-semibold text-white hover:bg-atlas-sidebar disabled:opacity-50"
                                >
                                    {actionLoading ? 'Émission…' : 'Émettre la facture'}
                                </button>
                            </section>
                        )}

                        {invoice.status === 'Issued' && !invoice.sent_at && (
                            <section className="mt-6 rounded-2xl border border-atlas-border bg-atlas-card p-5 shadow-sm">
                                <h3 className="font-semibold text-atlas-ink">Confirmer l’envoi</h3>
                                <p className="mt-2 text-sm leading-relaxed text-atlas-ink-muted">
                                    Envoyez la facture par votre canal habituel, puis confirmez ici que le client l’a reçue.
                                </p>
                                <button
                                    type="button"
                                    disabled={actionLoading}
                                    onClick={() => void onConfirmSent()}
                                    className="mt-4 min-h-11 rounded-xl border border-atlas-border bg-white px-4 py-2.5 text-sm font-semibold text-atlas-ink hover:bg-slate-50 disabled:opacity-50"
                                >
                                    {actionLoading ? 'Enregistrement…' : 'Confirmer l’envoi'}
                                </button>
                            </section>
                        )}

                        {invoice.status === 'Issued' && invoice.sent_at && invoice.balance_cents > 0 && (
                            <form onSubmit={onRecordPayment} className="mt-6 rounded-2xl border border-atlas-border bg-atlas-card p-5 shadow-sm">
                                <h3 className="font-semibold text-atlas-ink">Enregistrer un paiement</h3>
                                <p className="mt-2 text-sm leading-relaxed text-atlas-ink-muted">
                                    Le montant saisi sera déduit du solde par Atlas. Plusieurs paiements partiels sont possibles.
                                </p>
                                <div className="mt-5 grid gap-4 sm:grid-cols-2">
                                    <FormField label={`Montant reçu (${invoice.currency})`}>
                                        <input
                                            required
                                            inputMode="decimal"
                                            className={inputClassName}
                                            value={paymentAmount}
                                            onChange={(event) => {
                                                setPaymentAmount(event.target.value);
                                                setError(null);
                                            }}
                                        />
                                    </FormField>
                                    <FormField label="Référence" hint="Facultative — par exemple, la référence du virement.">
                                        <input
                                            maxLength={200}
                                            className={inputClassName}
                                            value={paymentReference}
                                            onChange={(event) => setPaymentReference(event.target.value)}
                                            placeholder="VIR-2026-001"
                                        />
                                    </FormField>
                                </div>
                                <div className="mt-5 max-w-xs">
                                    <SubmitButton loading={actionLoading} loadingLabel="Enregistrement…">
                                        Enregistrer le paiement
                                    </SubmitButton>
                                </div>
                            </form>
                        )}

                        {invoice.settlement_status === 'Paid' && (
                            <div role="status" className="mt-6 rounded-2xl border border-emerald-200 bg-emerald-50 p-5 text-emerald-900">
                                <p className="font-semibold">Facture entièrement réglée</p>
                                <p className="mt-2 text-sm">Aucun solde ne reste à encaisser.</p>
                            </div>
                        )}
                    </>
                )}
            </div>
        </RequireAuth>
    );
}
