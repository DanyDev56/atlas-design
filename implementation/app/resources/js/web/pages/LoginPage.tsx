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

export function LoginPage() {
    const { loginWithPassword, session, isAuthenticated } = useAuth();
    const navigate = useNavigate();
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [error, setError] = useState<string | null>(null);
    const [loading, setLoading] = useState(false);

    if (isAuthenticated) {
        return <Navigate to={session?.workspaceId ? '/app' : '/app/onboarding'} replace />;
    }

    async function onSubmit(event: FormEvent) {
        event.preventDefault();
        setError(null);
        setLoading(true);
        try {
            const next = await loginWithPassword(email, password);
            navigate(next.workspaceId ? '/app' : '/app/onboarding');
        } catch (err) {
            setError(err instanceof Error ? err.message : 'Connexion impossible');
        } finally {
            setLoading(false);
        }
    }

    return (
        <AuthLayout title="Connexion" subtitle="Accédez à votre espace Atlas.">
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
                <SubmitButton loading={loading}>Se connecter</SubmitButton>
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
