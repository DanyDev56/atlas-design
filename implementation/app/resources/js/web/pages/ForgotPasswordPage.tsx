import { FormEvent, useState } from 'react';
import { Link } from 'react-router-dom';
import { requestAccountRecovery } from '@/api/auth';
import {
    AuthLayout,
    ErrorBanner,
    FormField,
    SubmitButton,
    SuccessBanner,
    inputClassName,
} from '@/components/auth/AuthLayout';

export function ForgotPasswordPage() {
    const [email, setEmail] = useState('');
    const [error, setError] = useState<string | null>(null);
    const [submitted, setSubmitted] = useState(false);
    const [loading, setLoading] = useState(false);
    const [debugToken, setDebugToken] = useState<string | null>(null);

    async function onSubmit(event: FormEvent) {
        event.preventDefault();
        setError(null);
        setLoading(true);
        try {
            const result = await requestAccountRecovery(email);
            setSubmitted(true);
            setDebugToken(result.recovery_token ?? null);
        } catch (err) {
            setError(err instanceof Error ? err.message : 'La demande n’a pas pu être enregistrée.');
        } finally {
            setLoading(false);
        }
    }

    return (
        <AuthLayout
            title="Mot de passe oublié"
            subtitle="Saisissez l’adresse du compte. Atlas ne confirme jamais si elle existe."
        >
            {submitted ? (
                <div className="space-y-4">
                    <SuccessBanner message="Si un compte actif correspond à cette adresse, un lien de réinitialisation a été préparé. Consultez votre messagerie, puis revenez vous connecter." />
                    {debugToken && (
                        <p className="text-sm text-atlas-ink-muted">
                            Jeton de développement :{' '}
                            <Link
                                to={`/app/reset-password?token=${encodeURIComponent(debugToken)}`}
                                className="font-medium text-atlas-accent hover:underline"
                            >
                                ouvrir la réinitialisation
                            </Link>
                        </p>
                    )}
                    <p className="text-center text-sm text-atlas-ink-muted">
                        <Link to="/app/login" className="font-medium text-atlas-accent hover:underline">
                            Retour à la connexion
                        </Link>
                    </p>
                </div>
            ) : (
                <form onSubmit={onSubmit} className="space-y-4">
                    <ErrorBanner message={error} />
                    <FormField label="Email">
                        <input
                            type="email"
                            required
                            autoComplete="email"
                            className={inputClassName}
                            value={email}
                            onChange={(event) => setEmail(event.target.value)}
                        />
                    </FormField>
                    <SubmitButton loading={loading} loadingLabel="Envoi…">
                        Demander la réinitialisation
                    </SubmitButton>
                    <p className="text-center text-sm text-atlas-ink-muted">
                        <Link to="/app/login" className="font-medium text-atlas-accent hover:underline">
                            Retour à la connexion
                        </Link>
                    </p>
                </form>
            )}
        </AuthLayout>
    );
}
