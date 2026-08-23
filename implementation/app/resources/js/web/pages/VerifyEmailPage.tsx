import { useEffect, useRef, useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { verifyEmail } from '@/api/auth';
import { AuthLayout, ErrorBanner, SuccessBanner } from '@/components/auth/AuthLayout';
import { readInvitationContinuation } from '@/utils/invitationContinuation';

export function VerifyEmailPage() {
    const [params] = useSearchParams();
    const userId = params.get('user_id')?.trim() ?? '';
    const token = params.get('token')?.trim() ?? '';
    const started = useRef(false);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [verified, setVerified] = useState(false);
    const invitationUrl = readInvitationContinuation();

    useEffect(() => {
        if (started.current) return;
        started.current = true;

        if (userId === '' || token === '') {
            setError('Ce lien de vérification est incomplet.');
            setLoading(false);
            return;
        }

        void verifyEmail(userId, token)
            .then(() => setVerified(true))
            .catch((err: unknown) => {
                setError(err instanceof Error ? err.message : 'Ce lien ne peut pas être vérifié.');
            })
            .finally(() => setLoading(false));
    }, [token, userId]);

    return (
        <AuthLayout
            title="Vérification de votre adresse"
            subtitle={loading ? 'Atlas vérifie votre lien…' : 'La vérification protège l’accès à votre espace.'}
        >
            {loading && <p role="status" className="text-sm text-atlas-ink-muted">Vérification en cours…</p>}
            <ErrorBanner message={error} />
            <SuccessBanner message={verified ? 'Votre adresse est vérifiée. Vous pouvez maintenant vous connecter.' : null} />
            {!loading && (
                <Link
                    to="/app/login"
                    state={invitationUrl ? { from: invitationUrl } : undefined}
                    className="mt-4 block w-full rounded-xl bg-atlas-accent px-4 py-3 text-center text-sm font-semibold text-white hover:opacity-90"
                >
                    {invitationUrl ? 'Continuer vers l’invitation' : 'Aller à la connexion'}
                </Link>
            )}
        </AuthLayout>
    );
}
