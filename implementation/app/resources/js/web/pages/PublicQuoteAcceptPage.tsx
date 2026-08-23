import { FormEvent, useEffect, useMemo, useState } from 'react';
import { useParams, useSearchParams } from 'react-router-dom';
import { acceptQuotePublic, getPublicQuote } from '@/api/billing';
import { AuthLayout, ErrorBanner, SubmitButton } from '@/components/auth/AuthLayout';
import { StatusBadge } from '@/components/crm/StatusBadge';
import type { PublicQuoteDetail } from '@/types/api';
import { formatMoney } from '@/utils/format';

export function PublicQuoteAcceptPage() {
    const { workspaceId, quoteId } = useParams<{ workspaceId: string; quoteId: string }>();
    const [searchParams] = useSearchParams();
    const token = searchParams.get('token') ?? '';

    const [quote, setQuote] = useState<PublicQuoteDetail | null>(null);
    const [loading, setLoading] = useState(true);
    const [accepting, setAccepting] = useState(false);
    const [confirmed, setConfirmed] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [accepted, setAccepted] = useState(false);

    const missingParams = useMemo(() => {
        if (!workspaceId || !quoteId || !token) return 'Ce lien est incomplet ou invalide.';
        return null;
    }, [workspaceId, quoteId, token]);

    useEffect(() => {
        if (missingParams || !workspaceId || !quoteId) {
            setLoading(false);
            return;
        }

        let active = true;
        setLoading(true);
        setError(null);

        getPublicQuote(workspaceId, quoteId, token)
            .then((result) => {
                if (active) setQuote(result);
            })
            .catch(() => {
                if (active) setError('Ce devis n’est plus accessible. Demandez un nouveau lien à son expéditeur.');
            })
            .finally(() => {
                if (active) setLoading(false);
            });

        return () => {
            active = false;
        };
    }, [missingParams, quoteId, token, workspaceId]);

    async function onAccept(event: FormEvent) {
        event.preventDefault();
        if (missingParams || !workspaceId || !quoteId || !quote || !confirmed) return;

        setAccepting(true);
        setError(null);
        try {
            await acceptQuotePublic(workspaceId, quoteId, token, quote.version);
            setAccepted(true);
        } catch {
            setError('L’acceptation n’a pas pu être enregistrée. Le lien a peut-être expiré ou le devis a changé.');
        } finally {
            setAccepting(false);
        }
    }

    const reference = quote?.quote_id.slice(0, 8).toUpperCase();
    const validityDate = quote?.valid_until
        ? new Intl.DateTimeFormat('fr-FR', { dateStyle: 'long' }).format(new Date(quote.valid_until))
        : null;

    return (
        <AuthLayout
            title={accepted ? 'Devis accepté' : 'Votre devis'}
            subtitle="Consultez son contenu avant de confirmer votre décision. Aucun compte Atlas n’est nécessaire."
            wide
        >
            {missingParams && (
                <div
                    role="alert"
                    className="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900"
                >
                    {missingParams}
                </div>
            )}

            {loading && !missingParams && (
                <div role="status" className="space-y-3" aria-live="polite">
                    <span className="sr-only">Chargement du devis…</span>
                    <div aria-hidden="true" className="h-24 animate-pulse rounded-xl bg-slate-100" />
                    <div aria-hidden="true" className="h-36 animate-pulse rounded-xl bg-slate-100" />
                </div>
            )}

            {!loading && <ErrorBanner message={error} />}

            {accepted && quote && (
                <div
                    role="status"
                    className="rounded-xl border border-emerald-200 bg-emerald-50 px-5 py-5 text-emerald-900"
                >
                    <p className="font-semibold">Votre acceptation est enregistrée.</p>
                    <p className="mt-2 text-sm leading-relaxed">
                        Le devis {reference} d’un montant de {formatMoney(quote.total_cents, quote.currency)} a bien
                        été accepté.
                    </p>
                    <p className="mt-3 text-xs text-emerald-800">Vous pouvez maintenant fermer cette page.</p>
                </div>
            )}

            {!loading && !missingParams && quote && !accepted && (
                <form onSubmit={onAccept} className="space-y-6">
                    <section
                        aria-labelledby="quote-summary-title"
                        className="overflow-hidden rounded-2xl border border-atlas-border bg-white shadow-sm"
                    >
                        <div className="flex flex-wrap items-start justify-between gap-3 border-b border-atlas-border px-5 py-4">
                            <div>
                                <h3 id="quote-summary-title" className="font-semibold text-atlas-ink">
                                    Devis {reference}
                                </h3>
                                {quote.client_display_name && (
                                    <p className="mt-1 text-sm text-atlas-ink-muted">Pour {quote.client_display_name}</p>
                                )}
                            </div>
                            <StatusBadge status={quote.status} />
                        </div>

                        <ul className="divide-y divide-atlas-border">
                            {quote.lines.map((line, index) => (
                                <li key={`${line.description}-${index}`} className="px-5 py-4">
                                    <p className="text-sm font-medium text-atlas-ink">{line.description}</p>
                                    <p className="mt-1 text-xs text-atlas-ink-muted">
                                        {line.quantity} × {formatMoney(line.unit_price_cents, quote.currency)}
                                    </p>
                                </li>
                            ))}
                        </ul>

                        <div className="flex items-end justify-between gap-4 bg-slate-50 px-5 py-4">
                            <div>
                                <p className="text-xs font-medium uppercase tracking-wide text-atlas-ink-muted">Total</p>
                                {validityDate && (
                                    <p className="mt-1 text-xs text-atlas-ink-muted">Valable jusqu’au {validityDate}</p>
                                )}
                            </div>
                            <p className="text-xl font-semibold text-atlas-ink">
                                {formatMoney(quote.total_cents, quote.currency)}
                            </p>
                        </div>
                    </section>

                    <label className="flex cursor-pointer items-start gap-3 rounded-xl border border-atlas-border px-4 py-3 text-sm leading-relaxed text-atlas-ink">
                        <input
                            type="checkbox"
                            checked={confirmed}
                            onChange={(event) => setConfirmed(event.target.checked)}
                            className="mt-0.5 size-4 shrink-0 accent-atlas-accent"
                        />
                        <span>J’ai lu ce devis et je confirme vouloir l’accepter.</span>
                    </label>

                    <SubmitButton loading={accepting} disabled={!confirmed} loadingLabel="Enregistrement…">
                        Confirmer l’acceptation
                    </SubmitButton>
                    {!confirmed && (
                        <p className="-mt-3 text-center text-xs text-atlas-ink-muted">
                            Cochez la confirmation pour activer l’acceptation.
                        </p>
                    )}
                </form>
            )}
        </AuthLayout>
    );
}
