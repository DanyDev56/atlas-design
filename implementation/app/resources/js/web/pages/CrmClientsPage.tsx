import { FormEvent, useEffect, useMemo, useState } from 'react';
import { Link } from 'react-router-dom';
import { createClient, listClients } from '@/api/crm';
import { StatusBadge } from '@/components/crm/StatusBadge';
import { RequireAuth } from '@/components/layout/RequireAuth';
import { EmptyState } from '@/components/ui/EmptyState';
import { PageSkeleton } from '@/components/ui/PageSkeleton';
import { PageHeader } from '@/components/ui/PageHeader';
import { Icon } from '@/components/ui/Icon';
import { ErrorBanner, FormField, SubmitButton, SuccessBanner, inputClassName } from '@/components/auth/AuthLayout';
import { useAuth } from '@/hooks/useAuth';
import type { ClientSummary } from '@/types/api';

export function CrmClientsPage() {
    const { session } = useAuth();
    const token = session!.token;
    const workspaceId = session!.workspaceId!;

    const [clients, setClients] = useState<ClientSummary[]>([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [showForm, setShowForm] = useState(false);
    const [displayName, setDisplayName] = useState('');
    const [kind, setKind] = useState<'Organization' | 'Individual'>('Organization');
    const [creating, setCreating] = useState(false);
    const [success, setSuccess] = useState<string | null>(null);
    const [search, setSearch] = useState('');

    const sortedClients = useMemo(
        () => [...clients].sort((a, b) => {
            if (a.status !== b.status) return a.status === 'Active' ? -1 : 1;

            return a.display_name.localeCompare(b.display_name, 'fr');
        }),
        [clients],
    );
    const visibleClients = useMemo(() => {
        const normalizedSearch = search.trim().toLocaleLowerCase('fr-FR');
        if (!normalizedSearch) return sortedClients;

        return sortedClients.filter((client) => client.display_name.toLocaleLowerCase('fr-FR').includes(normalizedSearch));
    }, [search, sortedClients]);

    async function reload() {
        setLoading(true);
        setError(null);
        try {
            setClients(await listClients(token, workspaceId));
        } catch (err) {
            setError(err instanceof Error ? err.message : 'Chargement impossible');
        } finally {
            setLoading(false);
        }
    }

    useEffect(() => {
        void reload();
    }, [token, workspaceId]);

    async function onCreate(event: FormEvent) {
        event.preventDefault();
        setCreating(true);
        setError(null);
        setSuccess(null);
        try {
            await createClient(token, workspaceId, { kind, display_name: displayName });
            setSuccess(`Le client « ${displayName} » a bien été créé.`);
            setDisplayName('');
            setShowForm(false);
            await reload();
        } catch (err) {
            setError(err instanceof Error ? err.message : 'Création impossible');
        } finally {
            setCreating(false);
        }
    }

    return (
        <RequireAuth>
            <div className="atlas-page max-w-5xl">
                <PageHeader
                    eyebrow="Relations commerciales"
                    title="Clients"
                    description="Centralisez vos clients et faites avancer chaque opportunité jusqu’au devis."
                    actions={!showForm ? (
                        <div className="flex flex-wrap gap-3">
                            <Link
                                to="/app/crm/import"
                                className="inline-flex min-h-11 items-center gap-2 rounded-xl border border-atlas-border bg-white px-4 py-2.5 text-sm font-semibold text-atlas-ink shadow-sm hover:border-atlas-accent/30 hover:bg-atlas-surface"
                            >
                                <Icon name="upload" className="size-4 text-atlas-ink-muted" />
                                Importer un historique
                            </Link>
                            <button
                                type="button"
                                onClick={() => setShowForm(true)}
                                className="inline-flex min-h-11 items-center gap-2 rounded-xl bg-atlas-accent px-4 py-2.5 text-sm font-semibold text-white hover:bg-[#066557]"
                            >
                                <Icon name="plus" className="size-4" />
                                Nouveau client
                            </button>
                        </div>
                    ) : undefined}
                />

                <ErrorBanner message={error} />
                <SuccessBanner message={success} />

                {showForm && (
                    <form
                        onSubmit={onCreate}
                        className="mb-8 rounded-2xl border border-atlas-border bg-atlas-card p-6 shadow-sm"
                    >
                        <h3 className="text-base font-semibold text-atlas-ink">Créer un client</h3>
                        <div className="mt-4 grid gap-4 sm:grid-cols-2">
                            <FormField label="Type">
                                <select
                                    className={inputClassName}
                                    value={kind}
                                    onChange={(e) => setKind(e.target.value as 'Organization' | 'Individual')}
                                >
                                    <option value="Organization">Organisation</option>
                                    <option value="Individual">Particulier</option>
                                </select>
                            </FormField>
                            <FormField label="Nom affiché">
                                <input
                                    required
                                    minLength={2}
                                    className={inputClassName}
                                    value={displayName}
                                    onChange={(e) => setDisplayName(e.target.value)}
                                    placeholder="Acme Corp"
                                />
                            </FormField>
                        </div>
                        <div className="mt-4 flex flex-col gap-3 sm:flex-row">
                            <SubmitButton loading={creating} loadingLabel="Création du client…">Créer le client</SubmitButton>
                            <button
                                type="button"
                                onClick={() => setShowForm(false)}
                                className="rounded-xl border border-atlas-border px-4 py-3 text-sm font-medium text-atlas-ink-muted hover:bg-atlas-surface"
                            >
                                Annuler
                            </button>
                        </div>
                    </form>
                )}

                {loading && <PageSkeleton rows={3} />}

                {!loading && clients.length > 0 && (
                    <div className="mb-4 flex flex-col gap-3 rounded-2xl border border-atlas-border bg-white/75 p-3 shadow-sm sm:flex-row sm:items-center sm:justify-between">
                        <label className="relative block min-w-0 flex-1 sm:max-w-sm">
                            <span className="sr-only">Rechercher un client</span>
                            <Icon name="search" className="pointer-events-none absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-atlas-ink-muted" />
                            <input
                                type="search"
                                value={search}
                                onChange={(event) => setSearch(event.target.value)}
                                placeholder="Rechercher un client…"
                                className="min-h-10 w-full rounded-xl border border-atlas-border bg-white py-2 pl-10 pr-4 text-sm outline-none focus:border-atlas-accent focus:ring-4 focus:ring-atlas-accent/10"
                            />
                        </label>
                        <p className="px-2 text-xs font-medium text-atlas-ink-muted">
                            {visibleClients.length} sur {clients.length} client{clients.length > 1 ? 's' : ''}
                        </p>
                    </div>
                )}

                {!loading && clients.length === 0 && (
                    <EmptyState
                        title="Aucun client"
                        description="Créez votre premier client pour démarrer une opportunité commerciale."
                        action={
                            !showForm ? (
                                <button
                                    type="button"
                                    onClick={() => setShowForm(true)}
                                    className="rounded-xl bg-atlas-accent px-4 py-2.5 text-sm font-semibold text-white hover:opacity-90"
                                >
                                    Nouveau client
                                </button>
                            ) : undefined
                        }
                    />
                )}

                {!loading && clients.length > 0 && visibleClients.length === 0 && (
                    <EmptyState
                        title="Aucun client trouvé"
                        description={`Aucun résultat ne correspond à « ${search.trim()} ».`}
                        action={(
                            <button type="button" onClick={() => setSearch('')} className="text-sm font-semibold text-atlas-accent hover:underline">
                                Effacer la recherche
                            </button>
                        )}
                    />
                )}

                {!loading && visibleClients.length > 0 && (
                    <ul className="divide-y divide-atlas-border overflow-hidden rounded-2xl border border-atlas-border bg-atlas-card shadow-sm">
                        {visibleClients.map((client) => (
                            <li key={client.client_id}>
                                <Link
                                    to={`/app/crm/clients/${client.client_id}`}
                                    className="flex items-center justify-between gap-4 px-6 py-4 transition-colors hover:bg-atlas-surface"
                                >
                                    <div className="flex min-w-0 items-center gap-3.5">
                                        <span className="grid size-10 shrink-0 place-items-center rounded-xl bg-atlas-accent-soft text-sm font-bold text-atlas-accent">
                                            {client.display_name.charAt(0).toLocaleUpperCase('fr-FR')}
                                        </span>
                                        <div className="min-w-0">
                                            <p className="truncate font-semibold text-atlas-ink">{client.display_name}</p>
                                            <p className="mt-0.5 text-xs text-atlas-ink-muted">
                                                {client.kind === 'Organization' ? 'Organisation' : 'Particulier'}
                                                {client.status === 'Archived' && client.archived_at
                                                    ? ` · Archivé le ${new Intl.DateTimeFormat('fr-FR', { dateStyle: 'medium' }).format(new Date(client.archived_at))}`
                                                    : ''}
                                            </p>
                                        </div>
                                    </div>
                                    <StatusBadge status={client.status} />
                                </Link>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </RequireAuth>
    );
}
