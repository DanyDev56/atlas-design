import { FormEvent, useEffect, useState } from 'react';
import { Navigate, useLocation, useNavigate } from 'react-router-dom';
import { ApiClientError } from '@/api/client';
import { ErrorBanner, FormField, SubmitButton, inputClassName } from '@/components/auth/AuthLayout';
import { Brand } from '@/components/ui/Brand';
import { Icon } from '@/components/ui/Icon';
import { useOperatorAuth } from '@/hooks/useOperatorAuth';

export function OperatorLoginPage() {
    const { session, resolving, login } = useOperatorAuth();
    const navigate = useNavigate();
    const location = useLocation();
    const target = (location.state as { from?: string } | null)?.from ?? '/backoffice';
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [mfaCode, setMfaCode] = useState('');
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        const previousTitle = document.title;
        document.title = 'Connexion opérateur — Atlas';
        return () => {
            document.title = previousTitle;
        };
    }, []);

    if (!resolving && session) {
        return <Navigate to="/backoffice" replace />;
    }

    async function onSubmit(event: FormEvent) {
        event.preventDefault();
        setError(null);
        setLoading(true);
        try {
            await login(email, password, mfaCode);
            navigate(target, { replace: true });
        } catch (err) {
            if (err instanceof ApiClientError && err.status === 404) {
                setError('Le back-office est désactivé sur cet environnement.');
            } else if (err instanceof ApiClientError && err.status === 503) {
                setError('L’authentification renforcée doit être configurée avant d’ouvrir cet environnement.');
            } else {
                setError(err instanceof Error ? err.message : 'Connexion opérateur impossible.');
            }
        } finally {
            setLoading(false);
        }
    }

    return (
        <div className="relative flex min-h-screen overflow-hidden bg-atlas-sidebar text-white">
            <div className="pointer-events-none absolute -right-24 -top-32 size-[34rem] rounded-full border border-white/[0.06]" />
            <div className="pointer-events-none absolute bottom-[-16rem] left-[-12rem] size-[36rem] rounded-full bg-atlas-accent/20 blur-3xl" />

            <main className="relative mx-auto grid w-full max-w-6xl items-center gap-12 px-5 py-10 lg:grid-cols-[minmax(0,1fr)_27rem] lg:px-10">
                <section className="hidden lg:block">
                    <Brand inverse />
                    <p className="mt-20 text-xs font-semibold uppercase tracking-[.2em] text-[#58c8ac]">Plan de contrôle</p>
                    <h1 className="mt-4 max-w-xl text-5xl font-semibold leading-[1.05] tracking-[-0.05em]">
                        Exploiter Atlas sans contourner ses frontières.
                    </h1>
                    <p className="mt-6 max-w-lg text-base leading-7 text-white/55">
                        Cet espace est réservé aux opérateurs explicitement habilités. Chaque accès sensible et chaque action sont attribuables.
                    </p>
                    <div className="mt-10 grid max-w-lg gap-4 sm:grid-cols-2">
                        {[
                            ['Audience séparée', 'Une session client ne donne aucun accès opérateur.'],
                            ['Lecture seule', 'Les actions restent désactivées pendant ce premier incrément.'],
                        ].map(([title, description]) => (
                            <div key={title} className="rounded-2xl border border-white/[0.08] bg-white/[0.04] p-5">
                                <Icon name="check" className="size-5 text-[#58c8ac]" />
                                <p className="mt-4 text-sm font-semibold">{title}</p>
                                <p className="mt-2 text-xs leading-5 text-white/45">{description}</p>
                            </div>
                        ))}
                    </div>
                </section>

                <section className="rounded-[1.75rem] border border-white/80 bg-white p-6 text-atlas-ink shadow-[0_30px_90px_rgb(0_0_0/0.28)] sm:p-9">
                    <div className="mb-9 lg:hidden"><Brand /></div>
                    <p className="atlas-kicker">Accès opérateur</p>
                    <h2 className="mt-3 text-3xl font-semibold tracking-[-0.04em]">Connexion sécurisée</h2>
                    <p className="mt-3 text-sm leading-6 text-atlas-ink-muted">
                        Utilisez un compte Atlas vérifié auquel un grant opérateur actif a été attribué hors inscription publique.
                    </p>

                    <form onSubmit={onSubmit} className="mt-8 space-y-4">
                        <ErrorBanner message={error} />
                        <FormField label="Email opérateur">
                            <input
                                type="email"
                                required
                                autoComplete="username"
                                className={inputClassName}
                                value={email}
                                onChange={(event) => setEmail(event.target.value)}
                            />
                        </FormField>
                        <FormField label="Mot de passe">
                            <input
                                type="password"
                                required
                                autoComplete="current-password"
                                className={inputClassName}
                                value={password}
                                onChange={(event) => setPassword(event.target.value)}
                            />
                        </FormField>
                        <FormField label="Code d’authentification ou de récupération">
                            <input
                                type="text"
                                inputMode="numeric"
                                autoComplete="one-time-code"
                                className={inputClassName}
                                value={mfaCode}
                                onChange={(event) => setMfaCode(event.target.value)}
                                placeholder="123456"
                            />
                        </FormField>
                        <SubmitButton loading={loading} loadingLabel="Vérification…">
                            Ouvrir le back-office
                        </SubmitButton>
                    </form>

                    <p className="mt-6 border-t border-atlas-border pt-5 text-xs leading-5 text-atlas-ink-muted">
                        Le code est obligatoire dès qu’une MFA est enrôlée. Le mode mot de passe seul reste réservé aux environnements local et test.
                    </p>
                </section>
            </main>
        </div>
    );
}
