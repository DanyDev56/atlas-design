import { FormEvent, useMemo, useState } from 'react';
import { Link, useNavigate, useSearchParams } from 'react-router-dom';
import { completeAccountRecovery } from '@/api/auth';
import {
    AuthLayout,
    ErrorBanner,
    FormField,
    SubmitButton,
    inputClassName,
} from '@/components/auth/AuthLayout';

export function ResetPasswordPage() {
    const navigate = useNavigate();
    const [params] = useSearchParams();
    const token = useMemo(() => params.get('token')?.trim() ?? '', [params]);
    const [password, setPassword] = useState('');
    const [confirmation, setConfirmation] = useState('');
    const [error, setError] = useState<string | null>(null);
    const [loading, setLoading] = useState(false);

    async function onSubmit(event: FormEvent) {
        event.preventDefault();
        setError(null);

        if (token === '') {
            setError('Ce lien de réinitialisation est incomplet.');
            return;
        }
        if (password !== confirmation) {
            setError('Les deux mots de passe doivent être identiques.');
            return;
        }

        setLoading(true);
        try {
            await completeAccountRecovery(token, password);
            navigate('/app/login', { state: { recovered: true } });
        } catch (err) {
            setError(err instanceof Error ? err.message : 'La réinitialisation a échoué. Demandez un nouveau lien.');
        } finally {
            setLoading(false);
        }
    }

    return (
        <AuthLayout title="Nouveau mot de passe" subtitle="Choisissez un mot de passe d’au moins 8 caractères.">
            <form onSubmit={onSubmit} className="space-y-4">
                <ErrorBanner message={error} />
                <FormField label="Nouveau mot de passe">
                    <input
                        type="password"
                        required
                        minLength={8}
                        autoComplete="new-password"
                        className={inputClassName}
                        value={password}
                        onChange={(event) => setPassword(event.target.value)}
                    />
                </FormField>
                <FormField label="Confirmer le mot de passe">
                    <input
                        type="password"
                        required
                        minLength={8}
                        autoComplete="new-password"
                        className={inputClassName}
                        value={confirmation}
                        onChange={(event) => setConfirmation(event.target.value)}
                    />
                </FormField>
                <SubmitButton loading={loading} loadingLabel="Enregistrement…">
                    Enregistrer le mot de passe
                </SubmitButton>
            </form>
            <p className="mt-4 text-center text-sm text-atlas-ink-muted">
                <Link to="/app/login" className="font-medium text-atlas-accent hover:underline">
                    Retour à la connexion
                </Link>
            </p>
        </AuthLayout>
    );
}
