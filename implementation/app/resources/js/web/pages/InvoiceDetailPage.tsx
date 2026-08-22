import { FormEvent, useEffect, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import {
    applyCreditNote,
    createCreditNote,
    downloadBillingDocument,
    getInvoice,
    issueCreditNote,
    issueInvoice,
    recordPayment,
    requestInvoiceReminder,
    sendInvoice,
} from '@/api/billing';
import { getClient } from '@/api/crm';
import { ErrorBanner, FormField, SubmitButton, SuccessBanner, inputClassName } from '@/components/auth/AuthLayout';
import { StatusBadge } from '@/components/crm/StatusBadge';
import { RequireAuth } from '@/components/layout/RequireAuth';
import { PageSkeleton } from '@/components/ui/PageSkeleton';
import { useAuth } from '@/hooks/useAuth';
import type { ClientDetail, InvoiceDetail } from '@/types/api';
import { formatMoney, invoiceDisplayStatus } from '@/utils/format';

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
    const [creditAmount, setCreditAmount] = useState('');
    const [creditReason, setCreditReason] = useState('');
    const [reminderMessage, setReminderMessage] = useState('');

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

    useEffect(() => {
        if (!invoiceId || !['Pending', 'Retrying'].includes(invoice?.email_delivery_status ?? '')) return;

        const timer = window.setInterval(() => {
            void getInvoice(token, workspaceId, invoiceId).then(setInvoice).catch(() => undefined);
        }, 1500);

        return () => window.clearInterval(timer);
    }, [invoiceId, invoice?.email_delivery_status, token, workspaceId]);

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

    async function onSend() {
        if (!invoice || invoice.status !== 'Issued' || invoice.is_historical_import) return;

        if (!client?.billing_profile?.billing_email) {
            setError('Renseignez l’adresse email de facturation du client avant d’envoyer cette facture.');
            return;
        }

        setActionLoading(true);
        setError(null);
        setSuccess(null);
        try {
            const result = await sendInvoice(token, workspaceId, invoice.invoice_id, invoice.version);
            setInvoice({
                ...invoice,
                version: result.version,
                sent_at: result.sent_at,
                email_delivery_status: result.delivery_status,
                email_delivery_updated_at: new Date().toISOString(),
            });
            setSuccess(result.resent
                ? 'Le renvoi est en cours. Atlas attend la confirmation du serveur email.'
                : 'La facture est en cours d’envoi par email.');
        } catch (err) {
            setError(err instanceof Error ? err.message : 'La facture n’a pas pu être envoyée.');
        } finally {
            setActionLoading(false);
        }
    }

    async function onDownloadPdf(documentType: 'invoice' | 'credit_note', documentId: string) {
        setError(null);
        try {
            await downloadBillingDocument(token, workspaceId, documentType, documentId);
        } catch (err) {
            setError(err instanceof Error ? err.message : 'Téléchargement du document impossible.');
        }
    }

    async function onRemind(event: FormEvent) {
        event.preventDefault();
        if (!invoice || invoice.status !== 'Issued' || invoice.balance_cents <= 0 || invoice.is_historical_import) {
            return;
        }

        setActionLoading(true);
        setError(null);
        setSuccess(null);
        try {
            await requestInvoiceReminder(
                token,
                workspaceId,
                invoice.invoice_id,
                invoice.version,
                reminderMessage.trim() || undefined,
            );
            await loadInvoice(false);
            setReminderMessage('');
            setSuccess('La relance est en cours d’envoi par email.');
        } catch (err) {
            setError(err instanceof Error ? err.message : 'La relance n’a pas pu être enregistrée.');
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

    async function onCreateCreditNote(event: FormEvent) {
        event.preventDefault();
        if (!invoice || invoice.status !== 'Issued' || invoice.is_historical_import) return;

        const amountCents = parseAmount(creditAmount);
        if (amountCents === null || amountCents > invoice.total_cents) {
            setError(`Le montant de l’avoir doit être compris entre 0 et ${formatMoney(invoice.total_cents, invoice.currency)}.`);
            return;
        }

        setActionLoading(true);
        setError(null);
        setSuccess(null);
        try {
            await createCreditNote(token, workspaceId, invoice.invoice_id, [{
                description: creditReason.trim() || 'Correction de facture',
                quantity: 1,
                unit_price_cents: amountCents,
            }], creditReason.trim());
            await loadInvoice(false);
            setCreditAmount('');
            setCreditReason('');
            setSuccess('Le brouillon d’avoir est créé. Vérifiez-le avant de l’émettre.');
        } catch (err) {
            setError(err instanceof Error ? err.message : 'Création de l’avoir impossible.');
        } finally {
            setActionLoading(false);
        }
    }

    async function onIssueCreditNote(creditNoteId: string, revision: number) {
        setActionLoading(true);
        setError(null);
        setSuccess(null);
        try {
            await issueCreditNote(token, workspaceId, creditNoteId, revision);
            await loadInvoice(false);
            setSuccess('L’avoir est émis et son numéro est définitif.');
        } catch (err) {
            setError(err instanceof Error ? err.message : 'Émission de l’avoir impossible.');
        } finally {
            setActionLoading(false);
        }
    }

    async function onApplyCreditNote(creditNoteId: string, revision: number, totalCents: number) {
        if (!invoice) return;
        const amount = Math.min(totalCents, invoice.balance_cents);
        const disposition = amount < totalCents ? 'ClientCredit' : undefined;

        setActionLoading(true);
        setError(null);
        setSuccess(null);
        try {
            await applyCreditNote(
                token,
                workspaceId,
                creditNoteId,
                amount,
                revision,
                invoice.version,
                disposition,
            );
            await loadInvoice(false);
            setSuccess(
                amount < totalCents
                    ? 'L’avoir est appliqué au solde ; le reliquat est conservé en crédit client.'
                    : 'L’avoir est appliqué au solde de la facture.',
            );
        } catch (err) {
            setError(err instanceof Error ? err.message : 'Application de l’avoir impossible.');
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

    const displayedStatus = invoiceDisplayStatus(invoice ?? { status: 'Draft' });

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
                                    {invoice.kind === 'Deposit' ? 'Acompte' : 'Facture'}{' '}
                                    {invoice.invoice_number ?? invoice.invoice_id.slice(0, 8).toUpperCase()}
                                </p>
                                <h2 className="mt-2 text-3xl font-semibold tracking-tight text-atlas-ink">
                                    {client?.display_name ?? 'Facture client'}
                                </h2>
                                <div className="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-sm text-atlas-ink-muted">
                                    {invoice.issued_at && <span>Émise le {formatDate(invoice.issued_at)}</span>}
                                    {invoice.due_date && <span>Échéance le {formatDate(invoice.due_date)}</span>}
                                    {invoice.sent_at && <span>Envoi email demandé</span>}
                                </div>
                            </div>
                            <div className="flex flex-col items-end gap-3">
                                <StatusBadge status={displayedStatus} />
                                {invoice.status === 'Issued' && !invoice.is_historical_import && (
                                    <button
                                        type="button"
                                        onClick={() => void onDownloadPdf('invoice', invoice.invoice_id)}
                                        className="min-h-10 rounded-lg border border-atlas-border bg-white px-3 text-sm font-semibold text-atlas-ink hover:bg-slate-50"
                                    >
                                        Télécharger le PDF
                                    </button>
                                )}
                            </div>
                        </div>

                        {error && <div className="mt-6"><ErrorBanner message={error} /></div>}
                        {success && <div className="mt-6"><SuccessBanner message={success} /></div>}

                        {invoice.email_delivery_status === 'Accepted' && (
                            <div role="status" className="mt-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                                Email accepté par le serveur de messagerie du client.
                            </div>
                        )}
                        {['Pending', 'Retrying'].includes(invoice.email_delivery_status ?? '') && (
                            <div role="status" className="mt-6 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950">
                                {invoice.email_delivery_status === 'Retrying'
                                    ? 'La première tentative a échoué. Atlas va réessayer automatiquement.'
                                    : 'La facture est en cours de remise au serveur de messagerie.'}
                            </div>
                        )}
                        {['Cancelled', 'Failed'].includes(invoice.email_delivery_status ?? '') && (
                            <div role="alert" className="mt-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900">
                                L’email n’a pas été envoyé. Vérifiez{' '}
                                <Link to={`/app/crm/clients/${invoice.client_id}`} className="font-semibold underline">
                                    l’adresse de facturation du client
                                </Link>
                                , puis utilisez « Renvoyer l’email ».
                            </div>
                        )}

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

                        {invoice.status === 'Issued' && !invoice.is_historical_import && (
                            <section className="mt-6 rounded-2xl border border-atlas-border bg-atlas-card p-5 shadow-sm">
                                <h3 className="font-semibold text-atlas-ink">
                                    {invoice.sent_at ? 'Renvoyer la facture' : 'Envoyer la facture'}
                                </h3>
                                <p className="mt-2 text-sm leading-relaxed text-atlas-ink-muted">
                                    Atlas envoie le PDF à l’adresse email de facturation du client.
                                </p>
                                <button
                                    type="button"
                                    disabled={actionLoading || invoice.email_delivery_status === 'Pending' || invoice.email_delivery_status === 'Retrying'}
                                    onClick={() => void onSend()}
                                    className="mt-4 min-h-11 rounded-xl bg-atlas-ink px-4 py-2.5 text-sm font-semibold text-white hover:bg-atlas-sidebar disabled:cursor-not-allowed disabled:opacity-50"
                                >
                                    {actionLoading
                                        ? 'Envoi…'
                                        : invoice.sent_at
                                          ? 'Renvoyer l’email'
                                          : 'Envoyer par email'}
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

                        {invoice.status === 'Issued' && invoice.sent_at && invoice.balance_cents > 0 && !invoice.is_historical_import && (
                            <form onSubmit={onRemind} className="mt-6 rounded-2xl border border-atlas-border bg-atlas-card p-5 shadow-sm">
                                <h3 className="font-semibold text-atlas-ink">Relancer le client</h3>
                                <p className="mt-2 text-sm leading-relaxed text-atlas-ink-muted">
                                    Envoyez une relance par email. Le PDF de la facture reste inchangé.
                                    {invoice.last_reminded_at
                                        ? ` Dernière relance le ${formatDate(invoice.last_reminded_at)}.`
                                        : ''}
                                </p>
                                <div className="mt-5">
                                    <FormField label="Message" hint="Facultatif — texte ajouté à l’email de relance.">
                                        <textarea
                                            maxLength={1000}
                                            rows={3}
                                            className={inputClassName}
                                            value={reminderMessage}
                                            onChange={(event) => setReminderMessage(event.target.value)}
                                            placeholder="Bonjour, le solde de cette facture reste dû."
                                        />
                                    </FormField>
                                </div>
                                <div className="mt-5 max-w-xs">
                                    <SubmitButton loading={actionLoading} loadingLabel="Enregistrement…">
                                        Envoyer la relance
                                    </SubmitButton>
                                </div>
                            </form>
                        )}

                        {invoice.is_historical_import && (invoice.credit_notes ?? []).length > 0 && (
                            <section aria-labelledby="historical-credit-notes-title" className="mt-8 rounded-2xl border border-atlas-border bg-atlas-card p-5 shadow-sm">
                                <h3 id="historical-credit-notes-title" className="text-lg font-semibold text-atlas-ink">Avoirs historiques</h3>
                                <p className="mt-2 text-sm text-atlas-ink-muted">
                                    Ces avoirs proviennent de l’import historique. Ils sont en lecture seule.
                                </p>
                                <ul className="mt-5 divide-y divide-atlas-border rounded-xl border border-atlas-border">
                                    {(invoice.credit_notes ?? []).map((creditNote) => (
                                        <li key={creditNote.credit_note_id} className="p-4">
                                            <p className="font-medium text-atlas-ink">
                                                {creditNote.original_number ?? creditNote.credit_note_number ?? 'Avoir'}
                                            </p>
                                            <p className="mt-1 text-sm text-atlas-ink-muted">
                                                {formatMoney(creditNote.total_cents, creditNote.currency)}
                                                {' · '}{creditNote.status}
                                                {creditNote.amount_applied_cents > 0
                                                    ? ` · ${formatMoney(creditNote.amount_applied_cents, creditNote.currency)} appliqués`
                                                    : ''}
                                            </p>
                                        </li>
                                    ))}
                                </ul>
                            </section>
                        )}

                        {invoice.status === 'Issued' && !invoice.is_historical_import && (
                            <section aria-labelledby="credit-notes-title" className="mt-8 rounded-2xl border border-atlas-border bg-atlas-card p-5 shadow-sm">
                                <h3 id="credit-notes-title" className="text-lg font-semibold text-atlas-ink">Avoirs</h3>
                                <p className="mt-2 text-sm text-atlas-ink-muted">
                                    Créez un brouillon, émettez-le après vérification, puis appliquez-le au solde de la facture.
                                </p>

                                {(invoice.credit_notes ?? []).length > 0 && (
                                    <ul className="mt-5 divide-y divide-atlas-border rounded-xl border border-atlas-border">
                                        {(invoice.credit_notes ?? []).map((creditNote) => (
                                            <li key={creditNote.credit_note_id} className="flex flex-wrap items-center justify-between gap-3 p-4">
                                                <div>
                                                    <p className="font-medium text-atlas-ink">
                                                        {creditNote.credit_note_number ?? 'Brouillon d’avoir'}
                                                    </p>
                                                    <p className="mt-1 text-sm text-atlas-ink-muted">
                                                        {formatMoney(creditNote.total_cents, creditNote.currency)}
                                                        {' · '}{creditNote.status}
                                                        {creditNote.amount_applied_cents > 0
                                                            ? ` · ${formatMoney(creditNote.amount_applied_cents, creditNote.currency)} appliqués`
                                                            : ''}
                                                    </p>
                                                </div>
                                                <div className="flex gap-2">
                                                    {creditNote.status !== 'Draft' && creditNote.status !== 'Discarded' && (
                                                        <button
                                                            type="button"
                                                            onClick={() => void onDownloadPdf('credit_note', creditNote.credit_note_id)}
                                                            className="min-h-10 rounded-lg border border-atlas-border px-3 text-sm font-semibold text-atlas-ink hover:bg-slate-50"
                                                        >
                                                            PDF
                                                        </button>
                                                    )}
                                                    {creditNote.status === 'Draft' && (
                                                        <button
                                                            type="button"
                                                            disabled={actionLoading}
                                                            onClick={() => void onIssueCreditNote(creditNote.credit_note_id, creditNote.version)}
                                                            className="min-h-10 rounded-lg border border-atlas-border px-3 text-sm font-semibold text-atlas-ink hover:bg-slate-50 disabled:opacity-50"
                                                        >
                                                            Émettre
                                                        </button>
                                                    )}
                                                    {creditNote.status === 'Issued' && invoice.balance_cents > 0 && (
                                                        <button
                                                            type="button"
                                                            disabled={actionLoading}
                                                            onClick={() => void onApplyCreditNote(
                                                                creditNote.credit_note_id,
                                                                creditNote.version,
                                                                creditNote.total_cents,
                                                            )}
                                                            className="min-h-10 rounded-lg bg-atlas-ink px-3 text-sm font-semibold text-white hover:bg-atlas-sidebar disabled:opacity-50"
                                                        >
                                                            Appliquer au solde
                                                        </button>
                                                    )}
                                                </div>
                                            </li>
                                        ))}
                                    </ul>
                                )}

                                <form onSubmit={onCreateCreditNote} className="mt-5 grid gap-4 sm:grid-cols-2">
                                    <FormField label={`Montant de l’avoir (${invoice.currency})`}>
                                        <input
                                            required
                                            inputMode="decimal"
                                            className={inputClassName}
                                            value={creditAmount}
                                            onChange={(event) => setCreditAmount(event.target.value)}
                                        />
                                    </FormField>
                                    <FormField label="Motif" hint="Ce motif décrit la correction apportée.">
                                        <input
                                            maxLength={1000}
                                            className={inputClassName}
                                            value={creditReason}
                                            onChange={(event) => setCreditReason(event.target.value)}
                                            placeholder="Prestation annulée"
                                        />
                                    </FormField>
                                    <div className="sm:col-span-2 sm:max-w-xs">
                                        <SubmitButton loading={actionLoading} loadingLabel="Création…">
                                            Créer le brouillon d’avoir
                                        </SubmitButton>
                                    </div>
                                </form>
                            </section>
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
