import { FormEvent, useState } from 'react';
import { Link, Navigate, useLocation, useNavigate, useParams, useSearchParams } from 'react-router-dom';
import { acceptWorkspaceInvitation } from '@/api/workspace';
import { AuthLayout, ErrorBanner, SubmitButton } from '@/components/auth/AuthLayout';
import { useAuth } from '@/hooks/useAuth';

export function AcceptInvitationPage() {
    const { invitationId } = useParams<{ invitationId: string }>();
    const [params] = useSearchParams();
    const location = useLocation();
    const navigate = useNavigate();
    const { isAuthenticated, session, setWorkspaceId } = useAuth();
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const token = params.get('token')?.trim() ?? '';

    if (!isAuthenticated) {
        return (
            <Navigate
                to="/app/login"
                replace
                state={{ from: `${location.pathname}${location.search}` }}
            />
        );
    }

    async function onAccept(event: FormEvent) {
        event.preventDefault();
        if (!session || !invitationId || token === '') {
            setError('Ce lien d’invitation est incomplet.');
            return;
        }

        setLoading(true);
        setError(null);
        try {
            const result = await acceptWorkspaceInvitation(session.token, invitationId, token);
            setWorkspaceId(result.workspace_id);
            navigate('/app', { replace: true });
        } catch (err) {
            setError(err instanceof Error ? err.message : 'Cette invitation ne peut pas être acceptée.');
        } finally {
            setLoading(false);
        }
    }

    return (
        <AuthLayout
            title="Rejoindre l’espace"
            subtitle="L’invitation sera liée à l’adresse vérifiée de votre compte actuel."
        >
            <form onSubmit={(event) => void onAccept(event)} className="space-y-4">
                <ErrorBanner message={error} />
                <SubmitButton
                    loading={loading}
                    loadingLabel="Acceptation…"
                >
                    Accepter l’invitation
                </SubmitButton>
                <p className="text-center text-sm text-atlas-ink-muted">
                    Mauvais compte ?{' '}
                    <Link to="/app/login" className="font-medium text-atlas-accent hover:underline">
                        Revenir à la connexion
                    </Link>
                </p>
            </form>
        </AuthLayout>
    );
}
