import { FormEvent, useEffect, useRef, useState } from 'react';
import { elevateSession, fetchSessionContext } from '@/api/auth';
import { ApiClientError } from '@/api/client';
import { ErrorBanner, FormField, inputClassName } from '@/components/auth/AuthLayout';

export function isStepUpRequired(error: unknown): boolean {
    return error instanceof ApiClientError && error.body.error === 'StepUpRequired';
}

export function useImportStepUp(token: string) {
    const [promptOpen, setPromptOpen] = useState(false);
    const [password, setPassword] = useState('');
    const [promptError, setPromptError] = useState<string | null>(null);
    const [submitting, setSubmitting] = useState(false);
    const [elevatedUntil, setElevatedUntil] = useState<string | null>(null);
    const pendingAction = useRef<(() => Promise<void>) | null>(null);

    useEffect(() => {
        let active = true;
        void fetchSessionContext(token).then((context) => {
            if (active) {
                setElevatedUntil(context.elevation_expires_at);
            }
        }).catch(() => {
            if (active) {
                setElevatedUntil(null);
            }
        });

        return () => {
            active = false;
        };
    }, [token]);

    function hasElevation(): boolean {
        return elevatedUntil !== null && Date.parse(elevatedUntil) > Date.now();
    }

    async function runWithStepUp(action: () => Promise<void>): Promise<void> {
        if (hasElevation()) {
            await action();
            return;
        }

        try {
            await action();
        } catch (error) {
            if (isStepUpRequired(error)) {
                pendingAction.current = action;
                setPassword('');
                setPromptError(null);
                setPromptOpen(true);
                return;
            }

            throw error;
        }
    }

    async function submitPassword(event: FormEvent): Promise<void> {
        event.preventDefault();
        setSubmitting(true);
        setPromptError(null);
        try {
            const result = await elevateSession(token, password);
            setElevatedUntil(result.elevation_expires_at);
            setPromptOpen(false);
            setPassword('');
            const action = pendingAction.current;
            pendingAction.current = null;
            if (action) {
                await action();
            }
        } catch (error) {
            setPromptError(error instanceof Error ? error.message : 'Authentification renforcée impossible.');
        } finally {
            setSubmitting(false);
        }
    }

    function closePrompt(): void {
        pendingAction.current = null;
        setPromptOpen(false);
        setPassword('');
        setPromptError(null);
    }

    return {
        promptOpen,
        password,
        setPassword,
        promptError,
        submitting,
        runWithStepUp,
        submitPassword,
        closePrompt,
    };
}

export function StepUpPasswordDialog({
    open,
    password,
    error,
    submitting,
    onPasswordChange,
    onSubmit,
    onCancel,
}: {
    open: boolean;
    password: string;
    error: string | null;
    submitting: boolean;
    onPasswordChange: (value: string) => void;
    onSubmit: (event: FormEvent) => void;
    onCancel: () => void;
}) {
    if (!open) {
        return null;
    }

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 px-4">
            <form
                role="dialog"
                aria-modal="true"
                aria-labelledby="step-up-title"
                onSubmit={onSubmit}
                className="w-full max-w-md rounded-2xl border border-atlas-border bg-atlas-card p-6 shadow-xl"
            >
                <h3 id="step-up-title" className="text-lg font-semibold text-atlas-ink">
                    Confirmer votre identité
                </h3>
                <p className="mt-2 text-sm leading-6 text-atlas-ink-muted">
                    L’import historique est une action critique. Saisissez à nouveau votre mot de passe
                    pour continuer. Aucune nouvelle session n’est créée.
                </p>
                <ErrorBanner message={error} />
                <div className="mt-5">
                    <FormField label="Mot de passe">
                        <input
                            required
                            autoFocus
                            type="password"
                            autoComplete="current-password"
                            className={inputClassName}
                            value={password}
                            onChange={(event) => onPasswordChange(event.target.value)}
                        />
                    </FormField>
                </div>
                <div className="mt-6 flex flex-col gap-3 sm:flex-row">
                    <button
                        type="submit"
                        disabled={submitting}
                        className="rounded-xl bg-atlas-accent px-6 py-3 text-sm font-semibold text-white hover:opacity-90 disabled:opacity-50"
                    >
                        {submitting ? 'Vérification…' : 'Continuer l’import'}
                    </button>
                    <button
                        type="button"
                        onClick={onCancel}
                        disabled={submitting}
                        className="rounded-xl border border-atlas-border px-6 py-3 text-sm font-semibold text-atlas-ink hover:bg-atlas-surface disabled:opacity-50"
                    >
                        Annuler
                    </button>
                </div>
            </form>
        </div>
    );
}
