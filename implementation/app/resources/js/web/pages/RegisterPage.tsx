import { FormEvent, useState } from 'react';
import { Link, Navigate, useNavigate } from 'react-router-dom';
import {
    AuthLayout,
    ErrorBanner,
    FormField,
    SubmitButton,
    inputClassName,
} from '@/components/auth/AuthLayout';
import { useAuth } from '@/hooks/useAuth';

export function RegisterPage() {
    const { registerAccount, isAuthenticated, session } = useAuth();
    const navigate = useNavigate();
    const [email, setEmail] = useState('');
    const [displayName, setDisplayName] = useState('');
    const [password, setPassword] = useState('');
    const [error, setError] = useState<string | null>(null);
    const [loading, setLoading] = useState(false);
    const [verificationRequiredFor, setVerificationRequiredFor] = useState<string | null>(null);

    if (isAuthenticated) {
        return <Navigate to={session?.workspaceId ? '/app' : '/app/onboarding'} replace />;
    }

    async function onSubmit(event: FormEvent) {
        event.preventDefault();
        setError(null);
        setLoading(true);
        try {
            const authenticated = await registerAccount(email, displayName, password);
            if (authenticated) {
                navigate('/app/onboarding');
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
            <AuthLayout title="Compte créé" subtitle="Votre adresse doit encore être vérifiée.">
                <div className="rounded-xl border border-amber-200 bg-amber-50 px-4 py-4 text-sm text-amber-900">
                    <p className="font-semibold">Vérification requise</p>
                    <p className="mt-2">
                        L’accès de {verificationRequiredFor} doit être activé avant la première connexion.
                        Contactez l’équipe Atlas si aucun parcours de vérification ne vous a été transmis.
                    </p>
                </div>
                <Link
                    to="/app/login"
                    className="mt-5 block text-center text-sm font-medium text-atlas-accent hover:underline"
                >
                    Retour à la connexion
                </Link>
            </AuthLayout>
        );
    }

    return (
        <AuthLayout title="Créer un compte" subtitle="Démarrez avec Atlas en quelques secondes.">
            <form onSubmit={onSubmit} className="space-y-4">
                <ErrorBanner message={error} />
                <FormField label="Email">
                    <input
                        type="email"
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
                        required
                        minLength={2}
                        className={inputClassName}
                        value={displayName}
                        onChange={(e) => setDisplayName(e.target.value)}
                    />
                </FormField>
                <FormField label="Mot de passe">
                    <input
                        type="password"
                        required
                        minLength={8}
                        autoComplete="new-password"
                        className={inputClassName}
                        value={password}
                        onChange={(e) => setPassword(e.target.value)}
                    />
                </FormField>
                <SubmitButton loading={loading}>Créer mon compte</SubmitButton>
            </form>
            <p className="mt-4 text-center text-sm text-atlas-ink-muted">
                Déjà inscrit ?{' '}
                <Link to="/app/login" className="font-medium text-atlas-accent hover:underline">
                    Se connecter
                </Link>
            </p>
        </AuthLayout>
    );
}
