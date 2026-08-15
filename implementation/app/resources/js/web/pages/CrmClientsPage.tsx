import { FormEvent, useEffect, useMemo, useState } from 'react';
import { Link } from 'react-router-dom';
import { createClient, listClients } from '@/api/crm';
import { StatusBadge } from '@/components/crm/StatusBadge';
import { RequireAuth } from '@/components/layout/RequireAuth';
import { EmptyState } from '@/components/ui/EmptyState';
import { PageSkeleton } from '@/components/ui/PageSkeleton';
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

    const sortedClients = useMemo(
        () => [...clients].sort((a, b) => {
            if (a.status !== b.status) return a.status === 'Active' ? -1 : 1;

            return a.display_name.localeCompare(b.display_name, 'fr');
        }),
        [clients],
    );

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
            <div className="mx-auto max-w-4xl">
                <div className="mb-8 flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <p className="text-sm font-medium text-atlas-accent">CRM</p>
                        <h2 className="mt-1 text-3xl font-semibold tracking-tight text-atlas-ink">Clients</h2>
                        <p className="mt-2 text-sm text-atlas-ink-muted">
                            Centralisez vos clients et faites avancer chaque opportunité jusqu’au devis.
                        </p>
                    </div>
                    {!showForm && (
                        <button
                            type="button"
                            onClick={() => setShowForm(true)}
                            className="rounded-xl bg-atlas-accent px-4 py-2.5 text-sm font-semibold text-white hover:opacity-90"
                        >
                            Nouveau client
                        </button>
                    )}
                </div>

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

                {!loading && clients.length > 0 && (
                    <ul className="divide-y divide-atlas-border overflow-hidden rounded-2xl border border-atlas-border bg-atlas-card shadow-sm">
                        {sortedClients.map((client) => (
                            <li key={client.client_id}>
                                <Link
                                    to={`/app/crm/clients/${client.client_id}`}
                                    className="flex items-center justify-between gap-4 px-6 py-4 transition-colors hover:bg-atlas-surface"
                                >
                                    <div>
                                        <p className="font-medium text-atlas-ink">{client.display_name}</p>
                                        <p className="mt-0.5 text-xs text-atlas-ink-muted">
                                            {client.kind === 'Organization' ? 'Organisation' : 'Particulier'}
                                            {client.archived_at ? ` · Archivé le ${new Intl.DateTimeFormat('fr-FR', { dateStyle: 'medium' }).format(new Date(client.archived_at))}` : ''}
                                        </p>
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
