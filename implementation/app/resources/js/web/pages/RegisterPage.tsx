import { FormEvent, useEffect, useState } from 'react';
import { Link, Navigate, useLocation, useNavigate } from 'react-router-dom';
import {
    AuthLayout,
    ErrorBanner,
    FormField,
    SubmitButton,
    inputClassName,
} from '@/components/auth/AuthLayout';
import { useAuth } from '@/hooks/useAuth';
import { normalizeInvitationContinuation, rememberInvitationContinuation } from '@/utils/invitationContinuation';

export function RegisterPage() {
    const { registerAccount, isAuthenticated, session } = useAuth();
    const navigate = useNavigate();
    const location = useLocation();
    const locationState = location.state as { from?: string } | null;
    const invitationUrl = normalizeInvitationContinuation(locationState?.from);
    const [email, setEmail] = useState('');
    const [displayName, setDisplayName] = useState('');
    const [password, setPassword] = useState('');
    const [error, setError] = useState<string | null>(null);
    const [loading, setLoading] = useState(false);
    const [verificationRequiredFor, setVerificationRequiredFor] = useState<string | null>(null);

    useEffect(() => {
        if (invitationUrl) rememberInvitationContinuation(invitationUrl);
    }, [invitationUrl]);

    if (isAuthenticated) {
        return <Navigate to={invitationUrl ?? (session?.workspaceId ? '/app' : '/app/onboarding')} replace />;
    }

    async function onSubmit(event: FormEvent) {
        event.preventDefault();
        setError(null);

        if (displayName.trim() === password) {
            setError('Le nom affiché doit être différent du mot de passe.');
            return;
        }

        setLoading(true);
        try {
            const authenticated = await registerAccount(email, displayName, password);
            if (authenticated) {
                navigate(invitationUrl ?? '/app/onboarding');
            } else {
                setVerificationRequiredFor(email);
            }
        } catch (err) {
            setError(err instanceof Error ? err.message : 'Inscription impossible');
        } finally {
            setLoading(false);
        }
    }

    if (verificationRequiredFor) {
        return (
            <AuthLayout
                title="Compte créé"
                subtitle={invitationUrl
                    ? 'Vérifiez votre adresse pour rejoindre l’espace.'
                    : 'Votre adresse doit encore être vérifiée.'}
            >
                <div className="rounded-xl border border-amber-200 bg-amber-50 px-4 py-4 text-sm text-amber-900">
                    <p className="font-semibold">Vérification requise</p>
                    <p className="mt-2">
                        Un lien vient d’être envoyé à {verificationRequiredFor}. Ouvrez-le avant votre première
                        connexion. {invitationUrl && 'Vous reprendrez ensuite automatiquement cette invitation. '}
                        En développement, l’email est visible dans Mailpit sur le port 8025.
                    </p>
                </div>
                <Link
                    to="/app/login"
                    state={invitationUrl ? { from: invitationUrl } : undefined}
                    className="mt-5 block text-center text-sm font-medium text-atlas-accent hover:underline"
                >
                    Retour à la connexion
                </Link>
            </AuthLayout>
        );
    }

    return (
        <AuthLayout
            title={invitationUrl ? 'Créer votre compte' : 'Créer un compte'}
            subtitle={invitationUrl
                ? 'Utilisez l’adresse qui a reçu l’invitation.'
                : 'Démarrez avec Atlas en quelques secondes.'}
        >
            <form onSubmit={onSubmit} className="space-y-4">
                <ErrorBanner message={error} />
                <FormField label="Email">
                    <input
                        type="email"
                        name="email"
                        required
                        autoComplete="email"
                        className={inputClassName}
                        value={email}
                        onChange={(e) => setEmail(e.target.value)}
                    />
                </FormField>
                <FormField label="Nom affiché">
                    <input
                        type="text"
                        name="display_name"
                        required
                        minLength={2}
                        autoComplete="name"
                        className={inputClassName}
                        value={displayName}
                        onChange={(e) => setDisplayName(e.target.value)}
                    />
                </FormField>
                <FormField label="Mot de passe">
                    <input
                        type="password"
                        name="password"
                        required
                        minLength={8}
                        autoComplete="new-password"
                        className={inputClassName}
                        value={password}
                        onChange={(e) => setPassword(e.target.value)}
                    />
                </FormField>
                <SubmitButton loading={loading} loadingLabel="Création du compte…">Créer mon compte</SubmitButton>
            </form>
            <p className="mt-4 text-center text-sm text-atlas-ink-muted">
                Déjà inscrit ?{' '}
                <Link
                    to="/app/login"
                    state={invitationUrl ? { from: invitationUrl } : undefined}
                    className="font-medium text-atlas-accent hover:underline"
                >
                    Se connecter
                </Link>
            </p>
        </AuthLayout>
    );
}
