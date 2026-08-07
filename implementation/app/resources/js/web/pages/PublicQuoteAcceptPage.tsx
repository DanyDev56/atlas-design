import { FormEvent, useMemo, useState } from 'react';
import { useParams, useSearchParams } from 'react-router-dom';
import { acceptQuotePublic } from '@/api/billing';
import { AuthLayout, ErrorBanner, SubmitButton } from '@/components/auth/AuthLayout';

export function PublicQuoteAcceptPage() {
    const { workspaceId, quoteId } = useParams<{ workspaceId: string; quoteId: string }>();
    const [searchParams] = useSearchParams();
    const token = searchParams.get('token') ?? '';
    const revisionParam = searchParams.get('revision');
    const expectedRevision = revisionParam ? parseInt(revisionParam, 10) : null;

    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [accepted, setAccepted] = useState(false);

    const missingParams = useMemo(() => {
        if (!workspaceId || !quoteId) return 'Lien incomplet.';
        if (!token) return 'Jeton d\'acceptation manquant.';
        if (!expectedRevision || expectedRevision < 1) return 'Révision invalide.';
        return null;
    }, [workspaceId, quoteId, token, expectedRevision]);

    async function onAccept(event: FormEvent) {
        event.preventDefault();
        if (missingParams || !workspaceId || !quoteId || !expectedRevision) return;

        setLoading(true);
        setError(null);
        try {
            await acceptQuotePublic(workspaceId, quoteId, token, expectedRevision);
            setAccepted(true);
        } catch (err) {
            setError(err instanceof Error ? err.message : 'Acceptation impossible');
        } finally {
            setLoading(false);
        }
    }

    return (
        <AuthLayout
            title="Accepter le devis"
            subtitle="Confirmation client — cette page ne nécessite pas de compte Atlas."
        >
            {missingParams && (
                <div className="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                    {missingParams}
                </div>
            )}

            {accepted && (
                <div className="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-4 text-sm text-emerald-900">
                    <p className="font-semibold">Devis accepté</p>
                    <p className="mt-2">Merci — votre acceptation a été enregistrée.</p>
                </div>
            )}

            {!missingParams && !accepted && (
                <form onSubmit={onAccept} className="space-y-4">
                    <ErrorBanner message={error} />
                    <p className="text-sm leading-relaxed text-atlas-ink-muted">
                        En confirmant, vous acceptez les conditions du devis référencé. Cette action est
                        définitive côté plateforme.
                    </p>
                    <SubmitButton loading={loading}>Confirmer l'acceptation</SubmitButton>
                </form>
            )}
        </AuthLayout>
    );
}
