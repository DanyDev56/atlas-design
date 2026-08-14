import { FormEvent, useState } from 'react';
import { Navigate, useNavigate } from 'react-router-dom';
import {
    AuthLayout,
    ErrorBanner,
    FormField,
    SubmitButton,
    inputClassName,
} from '@/components/auth/AuthLayout';
import { useAuth } from '@/hooks/useAuth';

export function OnboardingPage() {
    const { isAuthenticated, session, createWorkspace, isResolvingWorkspace } = useAuth();
    const navigate = useNavigate();
    const [workspaceName, setWorkspaceName] = useState('');
    const [error, setError] = useState<string | null>(null);
    const [loading, setLoading] = useState(false);

    if (!isAuthenticated) {
        return <Navigate to="/app/login" replace />;
    }

    if (session?.workspaceId) {
        return <Navigate to="/app" replace />;
    }

    if (isResolvingWorkspace) {
        return (
            <AuthLayout title="Chargement" subtitle="Récupération de votre workspace…">
                <div className="h-24 animate-pulse rounded-xl bg-atlas-surface" />
            </AuthLayout>
        );
    }

    async function onSubmit(event: FormEvent) {
        event.preventDefault();
        setError(null);
        setLoading(true);
        try {
            await createWorkspace(workspaceName);
            navigate('/app');
        } catch (err) {
            setError(err instanceof Error ? err.message : 'Création impossible');
        } finally {
            setLoading(false);
        }
    }

    return (
        <AuthLayout
            title="Configurez votre activité"
            subtitle="Une dernière étape avant d’accéder à votre tableau de bord."
        >
            <div className="mb-6 flex items-center gap-3" aria-label="Étape 2 sur 2">
                <span className="h-1.5 flex-1 rounded-full bg-atlas-accent" />
                <span className="h-1.5 flex-1 rounded-full bg-atlas-accent" />
                <span className="text-xs font-semibold text-atlas-accent">2 sur 2</span>
            </div>
            <form onSubmit={onSubmit} className="space-y-4">
                <ErrorBanner message={error} />
                <FormField
                    label="Nom de votre activité"
                    hint="Utilisez le nom que vous employez avec vos clients. Vous pourrez le modifier plus tard."
                >
                    <input
                        type="text"
                        required
                        minLength={2}
                        autoFocus
                        autoComplete="organization"
                        className={inputClassName}
                        value={workspaceName}
                        onChange={(e) => setWorkspaceName(e.target.value)}
                        placeholder="Studio Dupont"
                    />
                </FormField>
                <p className="rounded-xl bg-atlas-accent-soft px-4 py-3 text-sm leading-relaxed text-atlas-accent">
                    Votre tableau de bord sera prêt immédiatement. Vous pourrez ensuite ajouter votre premier client.
                </p>
                <SubmitButton loading={loading} loadingLabel="Création de votre espace…">
                    Accéder à mon tableau de bord
                </SubmitButton>
            </form>
        </AuthLayout>
    );
}
