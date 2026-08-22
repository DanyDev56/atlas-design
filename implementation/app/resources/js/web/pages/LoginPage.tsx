import { FormEvent, useState } from 'react';
import { Link, Navigate, useLocation, useNavigate } from 'react-router-dom';
import {
    AuthLayout,
    ErrorBanner,
    FormField,
    SubmitButton,
    SuccessBanner,
    inputClassName,
} from '@/components/auth/AuthLayout';
import { useAuth } from '@/hooks/useAuth';

const DEMO_EMAIL = import.meta.env.VITE_DEMO_EMAIL;
const DEMO_PASSWORD = import.meta.env.VITE_DEMO_PASSWORD;
const demoCredentialsAvailable = Boolean(DEMO_EMAIL && DEMO_PASSWORD);

export function LoginPage() {
    const { loginWithPassword, session, isAuthenticated } = useAuth();
    const navigate = useNavigate();
    const location = useLocation();
    const locationState = location.state as { recovered?: boolean; from?: string } | null;
    const recovered = Boolean(locationState?.recovered);
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [error, setError] = useState<string | null>(null);
    const [loading, setLoading] = useState(false);

    if (isAuthenticated) {
        return <Navigate to={session?.workspaceId ? '/app' : '/app/onboarding'} replace />;
    }

    function fillDemoCredentials() {
        if (!DEMO_EMAIL || !DEMO_PASSWORD) return;
        setEmail(DEMO_EMAIL);
        setPassword(DEMO_PASSWORD);
    }

    async function onSubmit(event: FormEvent) {
        event.preventDefault();
        setError(null);
        setLoading(true);
        try {
            const next = await loginWithPassword(email, password);
            navigate(locationState?.from ?? (next.workspaceId ? '/app' : '/app/onboarding'));
        } catch (err) {
            setError(err instanceof Error ? err.message : 'Connexion impossible');
        } finally {
            setLoading(false);
        }
    }

    return (
        <AuthLayout title="Connexion" subtitle="Accédez à votre espace Atlas.">
            {demoCredentialsAvailable && (
                <div className="mb-6 rounded-xl border border-atlas-border bg-atlas-surface px-4 py-3">
                    <p className="text-sm font-medium text-atlas-ink">Compte démo présentation</p>
                    <p className="mt-1 font-mono text-xs text-atlas-ink-muted">
                        {DEMO_EMAIL} / {DEMO_PASSWORD}
                    </p>
                    <button
                        type="button"
                        onClick={fillDemoCredentials}
                        className="mt-3 text-sm font-medium text-atlas-accent hover:underline"
                    >
                        Pré-remplir le formulaire
                    </button>
                </div>
            )}
            <form onSubmit={onSubmit} className="space-y-4">
                {recovered && (
                    <SuccessBanner message="Votre mot de passe a été mis à jour. Connectez-vous avec le nouveau mot de passe." />
                )}
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
                <FormField label="Mot de passe">
                    <input
                        type="password"
                        required
                        autoComplete="current-password"
                        className={inputClassName}
                        value={password}
                        onChange={(e) => setPassword(e.target.value)}
                    />
                </FormField>
                <p className="text-right text-sm">
                    <Link to="/app/forgot-password" className="font-medium text-atlas-accent hover:underline">
                        Mot de passe oublié ?
                    </Link>
                </p>
                <SubmitButton loading={loading} loadingLabel="Connexion…">Se connecter</SubmitButton>
            </form>
            <p className="mt-4 text-center text-sm text-atlas-ink-muted">
                Pas de compte ?{' '}
                <Link to="/app/register" className="font-medium text-atlas-accent hover:underline">
                    S'inscrire
                </Link>
            </p>
        </AuthLayout>
    );
}
