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

    if (isAuthenticated) {
        return <Navigate to={session?.workspaceId ? '/app' : '/app/onboarding'} replace />;
    }

    async function onSubmit(event: FormEvent) {
        event.preventDefault();
        setError(null);
        setLoading(true);
        try {
            await registerAccount(email, displayName, password);
            navigate('/app/onboarding');
        } catch (err) {
            setError(err instanceof Error ? err.message : 'Inscription impossible');
        } finally {
            setLoading(false);
        }
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
