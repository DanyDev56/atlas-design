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
    const { isAuthenticated, session, createWorkspace } = useAuth();
    const navigate = useNavigate();
    const [workspaceName, setWorkspaceName] = useState('Mon activité');
    const [error, setError] = useState<string | null>(null);
    const [loading, setLoading] = useState(false);

    if (!isAuthenticated) {
        return <Navigate to="/app/login" replace />;
    }

    if (session?.workspaceId) {
        return <Navigate to="/app" replace />;
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
            title="Votre workspace"
            subtitle="Nommez votre activité pour activer CRM, facturation et pilotage."
        >
            <form onSubmit={onSubmit} className="space-y-4">
                <ErrorBanner message={error} />
                <FormField label="Nom du workspace">
                    <input
                        type="text"
                        required
                        minLength={2}
                        className={inputClassName}
                        value={workspaceName}
                        onChange={(e) => setWorkspaceName(e.target.value)}
                    />
                </FormField>
                <SubmitButton loading={loading}>Activer Atlas</SubmitButton>
            </form>
        </AuthLayout>
    );
}
