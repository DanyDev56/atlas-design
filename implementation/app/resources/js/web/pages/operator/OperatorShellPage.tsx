import { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { Brand } from '@/components/ui/Brand';
import { Icon, type IconName } from '@/components/ui/Icon';
import { useOperatorAuth } from '@/hooks/useOperatorAuth';

const modules: Array<{
    title: string;
    description: string;
    icon: IconName;
    permission: string;
}> = [
    {
        title: 'Cohorte beta',
        description: 'Activation, jalons, blocages et cellules pricing pseudonymisées.',
        icon: 'crm',
        permission: 'operations.beta.read',
    },
    {
        title: 'Métriques produit',
        description: 'Entonnoir, première valeur, réutilisation et qualité des données.',
        icon: 'activity',
        permission: 'operations.metrics.read-product',
    },
    {
        title: 'Exploitation',
        description: 'API, worker, scheduler, outbox, emails et sauvegardes.',
        icon: 'dashboard',
        permission: 'operations.outbox.read',
    },
    {
        title: 'Support',
        description: 'Dossiers, objectifs de réponse, exports et fermetures.',
        icon: 'advisor',
        permission: 'operations.support.read',
    },
    {
        title: 'Abonnements',
        description: 'Essais, statuts normalisés et séparation sandbox/live.',
        icon: 'billing',
        permission: 'operations.subscriptions.read',
    },
    {
        title: 'Conformité et audit',
        description: 'Consentements, demandes de données et accès privilégiés.',
        icon: 'settings',
        permission: 'operations.compliance.read',
    },
];

export function OperatorShellPage() {
    const { session, logout } = useOperatorAuth();
    const navigate = useNavigate();
    const [loggingOut, setLoggingOut] = useState(false);

    useEffect(() => {
        const previousTitle = document.title;
        document.title = 'Back-office — Atlas';
        return () => {
            document.title = previousTitle;
        };
    }, []);

    async function onLogout() {
        setLoggingOut(true);
        try {
            await logout();
        } finally {
            navigate('/backoffice/login', { replace: true });
        }
    }

    return (
        <div className="min-h-screen bg-atlas-surface text-atlas-ink">
            <header className="border-b border-atlas-border bg-atlas-sidebar text-white">
                <div className="mx-auto flex min-h-20 max-w-[90rem] items-center justify-between gap-5 px-5 sm:px-8 lg:px-10">
                    <div className="flex items-center gap-5">
                        <Brand inverse />
                        <span className="hidden h-6 w-px bg-white/15 sm:block" />
                        <span className="hidden text-xs font-semibold uppercase tracking-[.18em] text-white/50 sm:block">Back-office</span>
                    </div>
                    <div className="flex items-center gap-3">
                        <span className="hidden text-sm text-white/60 sm:inline">{session?.displayName}</span>
                        <button
                            type="button"
                            disabled={loggingOut}
                            onClick={() => void onLogout()}
                            className="inline-flex min-h-10 items-center gap-2 rounded-xl border border-white/15 px-3.5 py-2 text-sm font-semibold text-white/80 hover:bg-white/10 disabled:opacity-50"
                        >
                            <Icon name="logout" className="size-4" />
                            Déconnexion
                        </button>
                    </div>
                </div>
            </header>

            <main className="mx-auto max-w-[90rem] px-5 py-10 sm:px-8 lg:px-10 lg:py-14">
                <div className="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <p className="atlas-kicker">Plan de contrôle</p>
                        <h1 className="mt-3 text-4xl font-semibold tracking-[-0.045em] sm:text-5xl">Socle opérateur</h1>
                        <p className="mt-4 max-w-2xl text-base leading-7 text-atlas-ink-muted">
                            L’audience, les grants, les sessions et l’audit sont séparés des Workspaces. Les données métier ne sont pas encore raccordées.
                        </p>
                    </div>
                    <div className="rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm text-emerald-900">
                        <div className="flex items-center gap-2 font-semibold">
                            <span className="size-2 rounded-full bg-emerald-500" />
                            Mode lecture seule
                        </div>
                        <p className="mt-1 text-xs text-emerald-800/70">Aucune action métier ou destructive disponible.</p>
                    </div>
                </div>

                <section className="mt-10 rounded-[1.5rem] bg-atlas-sidebar p-6 text-white shadow-[0_18px_45px_rgb(16_28_26/0.16)] sm:p-8">
                    <div className="grid gap-7 md:grid-cols-3">
                        <div>
                            <p className="text-xs uppercase tracking-[.16em] text-white/45">Audience</p>
                            <p className="mt-2 text-xl font-semibold">Operator</p>
                        </div>
                        <div>
                            <p className="text-xs uppercase tracking-[.16em] text-white/45">Permissions actives</p>
                            <p className="mt-2 text-xl font-semibold">{session?.permissions.length ?? 0}</p>
                        </div>
                        <div>
                            <p className="text-xs uppercase tracking-[.16em] text-white/45">Expiration</p>
                            <p className="mt-2 text-xl font-semibold">
                                {session ? new Intl.DateTimeFormat('fr-FR', { hour: '2-digit', minute: '2-digit' }).format(new Date(session.expiresAt)) : '—'}
                            </p>
                        </div>
                    </div>
                </section>

                <section className="mt-10">
                    <div>
                        <p className="text-lg font-semibold">Surfaces préparées</p>
                        <p className="mt-1 text-sm text-atlas-ink-muted">Elles seront activées incrément par incrément après leurs preuves d’isolation.</p>
                    </div>
                    <div className="mt-5 grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                        {modules.map((module) => {
                            const granted = session?.permissions.includes(module.permission) ?? false;
                            return (
                                <article key={module.title} className="rounded-2xl border border-atlas-border bg-white p-6 shadow-sm">
                                    <div className="flex items-start justify-between gap-4">
                                        <span className="grid size-11 place-items-center rounded-xl bg-atlas-accent-soft text-atlas-accent">
                                            <Icon name={module.icon} className="size-5" />
                                        </span>
                                        <span className={`rounded-full px-2.5 py-1 text-[11px] font-semibold ${granted ? 'bg-emerald-50 text-emerald-700' : 'bg-atlas-surface text-atlas-ink-muted'}`}>
                                            {granted ? 'Grant prêt' : 'Non accordé'}
                                        </span>
                                    </div>
                                    <h2 className="mt-5 text-lg font-semibold">{module.title}</h2>
                                    <p className="mt-2 text-sm leading-6 text-atlas-ink-muted">{module.description}</p>
                                    <p className="mt-5 break-all text-[11px] font-medium text-atlas-ink-muted/70">{module.permission}</p>
                                </article>
                            );
                        })}
                    </div>
                </section>
            </main>
        </div>
    );
}

