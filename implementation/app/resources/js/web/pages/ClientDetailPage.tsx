import { FormEvent, useEffect, useMemo, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { addContact, createOpportunity, getClient, listContacts, listOpportunities } from '@/api/crm';
import { ErrorBanner, FormField, SubmitButton, SuccessBanner, inputClassName } from '@/components/auth/AuthLayout';
import { StatusBadge } from '@/components/crm/StatusBadge';
import { RequireAuth } from '@/components/layout/RequireAuth';
import { EmptyState } from '@/components/ui/EmptyState';
import { PageSkeleton } from '@/components/ui/PageSkeleton';
import { useAuth } from '@/hooks/useAuth';
import type { ClientDetail, ContactSummary, OpportunitySummary } from '@/types/api';
import { formatMoney } from '@/utils/format';

function contactProfileValue(contact: ContactSummary, key: string): string | null {
    const value = contact.profile[key];

    return typeof value === 'string' && value.trim() !== '' ? value : null;
}

export function ClientDetailPage() {
    const { clientId } = useParams<{ clientId: string }>();
    const { session } = useAuth();
    const token = session?.token ?? null;
    const workspaceId = session?.workspaceId ?? null;

    const [client, setClient] = useState<ClientDetail | null>(null);
    const [contacts, setContacts] = useState<ContactSummary[]>([]);
    const [opportunities, setOpportunities] = useState<OpportunitySummary[]>([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [success, setSuccess] = useState<string | null>(null);

    const [showContactForm, setShowContactForm] = useState(false);
    const [contactName, setContactName] = useState('');
    const [contactEmail, setContactEmail] = useState('');
    const [contactPhone, setContactPhone] = useState('');
    const [contactRole, setContactRole] = useState('');
    const [makePrimary, setMakePrimary] = useState(false);
    const [creatingContact, setCreatingContact] = useState(false);

    const [showOpportunityForm, setShowOpportunityForm] = useState(false);
    const [title, setTitle] = useState('');
    const [amount, setAmount] = useState('');
    const [creatingOpportunity, setCreatingOpportunity] = useState(false);

    useEffect(() => {
        if (!token || !workspaceId || !clientId) {
            setLoading(false);
            return;
        }

        let cancelled = false;
        const activeToken = token;
        const activeWorkspaceId = workspaceId;
        const selectedClientId = clientId;

        async function load() {
            setLoading(true);
            setError(null);
            try {
                const [clientData, contactData, allOpportunities] = await Promise.all([
                    getClient(activeToken, activeWorkspaceId, selectedClientId),
                    listContacts(activeToken, activeWorkspaceId, selectedClientId),
                    listOpportunities(activeToken, activeWorkspaceId),
                ]);
                if (!cancelled) {
                    setClient(clientData);
                    setContacts(contactData);
                    setOpportunities(allOpportunities.filter((opportunity) => opportunity.client_id === selectedClientId));
                }
            } catch (err) {
                if (!cancelled) {
                    setError(err instanceof Error ? err.message : 'Chargement impossible');
                }
            } finally {
                if (!cancelled) setLoading(false);
            }
        }

        void load();

        return () => {
            cancelled = true;
        };
    }, [token, workspaceId, clientId]);

    const sortedContacts = useMemo(
        () => [...contacts].sort((a, b) => {
            if (a.is_primary !== b.is_primary) return a.is_primary ? -1 : 1;

            return (contactProfileValue(a, 'display_name') ?? '').localeCompare(
                contactProfileValue(b, 'display_name') ?? '',
                'fr',
            );
        }),
        [contacts],
    );

    const sortedOpportunities = useMemo(
        () => [...opportunities].sort((a, b) => a.title.localeCompare(b.title, 'fr')),
        [opportunities],
    );

    function openContactForm() {
        setMakePrimary(client?.primary_contact_id === null);
        setShowContactForm(true);
        setSuccess(null);
    }

    async function onAddContact(event: FormEvent) {
        event.preventDefault();
        if (!token || !workspaceId || !clientId || !client) return;

        setCreatingContact(true);
        setError(null);
        setSuccess(null);
        try {
            const profile = {
                display_name: contactName.trim(),
                ...(contactEmail.trim() ? { email: contactEmail.trim() } : {}),
                ...(contactPhone.trim() ? { phone: contactPhone.trim() } : {}),
                ...(contactRole.trim() ? { role: contactRole.trim() } : {}),
            };

            await addContact(token, workspaceId, clientId, {
                profile,
                make_primary: makePrimary,
                expected_revision: client.version,
            });

            const [clientData, contactData] = await Promise.all([
                getClient(token, workspaceId, clientId),
                listContacts(token, workspaceId, clientId),
            ]);
            setClient(clientData);
            setContacts(contactData);
            setSuccess(`Le contact « ${profile.display_name} » a bien été ajouté.`);
            setContactName('');
            setContactEmail('');
            setContactPhone('');
            setContactRole('');
            setMakePrimary(false);
            setShowContactForm(false);
        } catch (err) {
            setError(err instanceof Error ? err.message : 'Ajout du contact impossible');
        } finally {
            setCreatingContact(false);
        }
    }

    async function onCreateOpportunity(event: FormEvent) {
        event.preventDefault();
        if (!token || !workspaceId || !clientId) return;

        setCreatingOpportunity(true);
        setError(null);
        setSuccess(null);
        try {
            const cents = amount ? Math.round(parseFloat(amount.replace(',', '.')) * 100) : undefined;
            await createOpportunity(token, workspaceId, {
                client_id: clientId,
                title,
                estimated_amount_cents: cents,
                currency: 'EUR',
            });
            setSuccess(`L’opportunité « ${title} » a bien été créée.`);
            setTitle('');
            setAmount('');
            setShowOpportunityForm(false);
            const all = await listOpportunities(token, workspaceId);
            setOpportunities(all.filter((opportunity) => opportunity.client_id === clientId));
        } catch (err) {
            setError(err instanceof Error ? err.message : 'Création impossible');
        } finally {
            setCreatingOpportunity(false);
        }
    }

    if (!clientId) {
        return (
            <RequireAuth>
                <p className="text-sm text-red-800">Client introuvable.</p>
            </RequireAuth>
        );
    }

    return (
        <RequireAuth>
            <div className="mx-auto max-w-4xl">
                <Link to="/app/crm" className="text-sm font-medium text-atlas-accent hover:underline">
                    ← Retour aux clients
                </Link>

                {loading && (
                    <div className="mt-8">
                        <PageSkeleton rows={2} />
                    </div>
                )}

                {error && (
                    <div className="mt-6">
                        <ErrorBanner message={error} />
                    </div>
                )}

                {success && (
                    <div className="mt-6">
                        <SuccessBanner message={success} />
                    </div>
                )}

                {client && !loading && (
                    <>
                        <div className="mt-6 flex flex-wrap items-start justify-between gap-4">
                            <div>
                                <h2 className="text-3xl font-semibold tracking-tight text-atlas-ink">
                                    {client.display_name}
                                </h2>
                                <p className="mt-2 text-sm text-atlas-ink-muted">
                                    {client.kind === 'Organization' ? 'Organisation' : 'Particulier'}
                                </p>
                            </div>
                            <StatusBadge status={client.status} />
                        </div>

                        <section className="mt-10" aria-labelledby="contacts-heading">
                            <div className="mb-4 flex flex-wrap items-center justify-between gap-4">
                                <div>
                                    <h3 id="contacts-heading" className="text-lg font-semibold text-atlas-ink">
                                        Contacts
                                    </h3>
                                    <p className="mt-1 text-sm text-atlas-ink-muted">
                                        Les personnes à joindre pour ce client.
                                    </p>
                                </div>
                                {!showContactForm && (
                                    <button
                                        type="button"
                                        onClick={openContactForm}
                                        className="rounded-xl border border-atlas-accent px-4 py-2 text-sm font-semibold text-atlas-accent hover:bg-atlas-accent/5"
                                    >
                                        Ajouter un contact
                                    </button>
                                )}
                            </div>

                            {showContactForm && (
                                <form
                                    onSubmit={onAddContact}
                                    className="mb-6 rounded-2xl border border-atlas-border bg-atlas-card p-6 shadow-sm"
                                >
                                    <fieldset disabled={creatingContact} className="space-y-4">
                                        <legend className="mb-4 text-base font-semibold text-atlas-ink">
                                            Nouveau contact
                                        </legend>
                                        <div className="grid gap-4 sm:grid-cols-2">
                                            <FormField label="Nom complet">
                                                <input
                                                    required
                                                    autoComplete="name"
                                                    className={inputClassName}
                                                    value={contactName}
                                                    onChange={(event) => setContactName(event.target.value)}
                                                    placeholder="Camille Martin"
                                                />
                                            </FormField>
                                            <FormField label="Rôle (optionnel)">
                                                <input
                                                    autoComplete="organization-title"
                                                    className={inputClassName}
                                                    value={contactRole}
                                                    onChange={(event) => setContactRole(event.target.value)}
                                                    placeholder="Direction"
                                                />
                                            </FormField>
                                            <FormField label="Email (optionnel)">
                                                <input
                                                    type="email"
                                                    autoComplete="email"
                                                    className={inputClassName}
                                                    value={contactEmail}
                                                    onChange={(event) => setContactEmail(event.target.value)}
                                                    placeholder="camille@entreprise.fr"
                                                />
                                            </FormField>
                                            <FormField label="Téléphone (optionnel)">
                                                <input
                                                    type="tel"
                                                    autoComplete="tel"
                                                    className={inputClassName}
                                                    value={contactPhone}
                                                    onChange={(event) => setContactPhone(event.target.value)}
                                                    placeholder="06 12 34 56 78"
                                                />
                                            </FormField>
                                        </div>
                                        <label className="flex items-start gap-3 rounded-xl bg-atlas-surface px-4 py-3 text-sm text-atlas-ink">
                                            <input
                                                type="checkbox"
                                                checked={makePrimary}
                                                onChange={(event) => setMakePrimary(event.target.checked)}
                                                className="mt-0.5 h-4 w-4 rounded border-atlas-border text-atlas-accent focus:ring-atlas-accent"
                                            />
                                            <span>
                                                <span className="block font-medium">Définir comme contact principal</span>
                                                <span className="mt-0.5 block text-xs text-atlas-ink-muted">
                                                    Ce contact sera identifié en priorité sur la fiche client.
                                                </span>
                                            </span>
                                        </label>
                                        <div className="flex flex-col gap-3 sm:flex-row">
                                            <SubmitButton loading={creatingContact} loadingLabel="Ajout du contact…">
                                                Ajouter le contact
                                            </SubmitButton>
                                            <button
                                                type="button"
                                                onClick={() => setShowContactForm(false)}
                                                className="rounded-xl border border-atlas-border px-4 py-3 text-sm font-medium text-atlas-ink-muted"
                                            >
                                                Annuler
                                            </button>
                                        </div>
                                    </fieldset>
                                </form>
                            )}

                            {sortedContacts.length === 0 && !showContactForm && (
                                <EmptyState
                                    title="Aucun contact"
                                    description="Ajoutez une personne à joindre pour faciliter le suivi commercial."
                                    action={(
                                        <button
                                            type="button"
                                            onClick={openContactForm}
                                            className="rounded-xl bg-atlas-accent px-4 py-2 text-sm font-semibold text-white hover:opacity-90"
                                        >
                                            Ajouter un contact
                                        </button>
                                    )}
                                />
                            )}

                            {sortedContacts.length > 0 && (
                                <ul className="grid gap-3 sm:grid-cols-2">
                                    {sortedContacts.map((contact) => {
                                        const name = contactProfileValue(contact, 'display_name') ?? 'Contact sans nom';
                                        const role = contactProfileValue(contact, 'role');
                                        const email = contactProfileValue(contact, 'email');
                                        const phone = contactProfileValue(contact, 'phone');

                                        return (
                                            <li
                                                key={contact.contact_id}
                                                className="rounded-2xl border border-atlas-border bg-atlas-card p-5 shadow-sm"
                                            >
                                                <div className="flex flex-wrap items-start justify-between gap-3">
                                                    <div>
                                                        <p className="font-semibold text-atlas-ink">{name}</p>
                                                        {role && <p className="mt-1 text-sm text-atlas-ink-muted">{role}</p>}
                                                    </div>
                                                    {contact.is_primary && (
                                                        <span className="rounded-full bg-atlas-accent/10 px-2.5 py-1 text-xs font-semibold text-atlas-accent">
                                                            Principal
                                                        </span>
                                                    )}
                                                </div>
                                                {(email || phone) && (
                                                    <div className="mt-4 space-y-1.5 text-sm">
                                                        {email && (
                                                            <a className="block text-atlas-accent hover:underline" href={`mailto:${email}`}>
                                                                {email}
                                                            </a>
                                                        )}
                                                        {phone && (
                                                            <a className="block text-atlas-accent hover:underline" href={`tel:${phone}`}>
                                                                {phone}
                                                            </a>
                                                        )}
                                                    </div>
                                                )}
                                            </li>
                                        );
                                    })}
                                </ul>
                            )}
                        </section>

                        <section className="mt-10" aria-labelledby="opportunities-heading">
                            <div className="mb-4 flex items-center justify-between gap-4">
                                <h3 id="opportunities-heading" className="text-lg font-semibold text-atlas-ink">
                                    Opportunités
                                </h3>
                                {!showOpportunityForm && (
                                    <button
                                        type="button"
                                        onClick={() => setShowOpportunityForm(true)}
                                        className="rounded-xl bg-atlas-accent px-4 py-2 text-sm font-semibold text-white hover:opacity-90"
                                    >
                                        Nouvelle opportunité
                                    </button>
                                )}
                            </div>

                            {showOpportunityForm && (
                                <form
                                    onSubmit={onCreateOpportunity}
                                    className="mb-6 rounded-2xl border border-atlas-border bg-atlas-card p-6 shadow-sm"
                                >
                                    <FormField label="Titre">
                                        <input
                                            required
                                            className={inputClassName}
                                            value={title}
                                            onChange={(event) => setTitle(event.target.value)}
                                            placeholder="Refonte site web"
                                        />
                                    </FormField>
                                    <div className="mt-4">
                                        <FormField label="Montant estimé (€, optionnel)">
                                            <input
                                                type="text"
                                                inputMode="decimal"
                                                className={inputClassName}
                                                value={amount}
                                                onChange={(event) => setAmount(event.target.value)}
                                                placeholder="1200"
                                            />
                                        </FormField>
                                    </div>
                                    <div className="mt-4 flex flex-col gap-3 sm:flex-row">
                                        <SubmitButton
                                            loading={creatingOpportunity}
                                            loadingLabel="Création de l’opportunité…"
                                        >
                                            Créer l’opportunité
                                        </SubmitButton>
                                        <button
                                            type="button"
                                            onClick={() => setShowOpportunityForm(false)}
                                            className="rounded-xl border border-atlas-border px-4 py-3 text-sm font-medium text-atlas-ink-muted"
                                        >
                                            Annuler
                                        </button>
                                    </div>
                                </form>
                            )}

                            {sortedOpportunities.length === 0 && (
                                <EmptyState
                                    title="Aucune opportunité"
                                    description="Créez une opportunité pour préparer un devis."
                                />
                            )}

                            {sortedOpportunities.length > 0 && (
                                <ul className="divide-y divide-atlas-border overflow-hidden rounded-2xl border border-atlas-border bg-atlas-card shadow-sm">
                                    {sortedOpportunities.map((opportunity) => (
                                        <li key={opportunity.opportunity_id}>
                                            <Link
                                                to={`/app/crm/opportunities/${opportunity.opportunity_id}`}
                                                className="flex items-center justify-between gap-4 px-6 py-4 hover:bg-atlas-surface"
                                            >
                                                <div>
                                                    <p className="font-medium text-atlas-ink">{opportunity.title}</p>
                                                    {opportunity.estimated_amount_cents != null && (
                                                        <p className="mt-0.5 text-xs text-atlas-ink-muted">
                                                            {formatMoney(
                                                                opportunity.estimated_amount_cents,
                                                                opportunity.currency,
                                                            )}
                                                        </p>
                                                    )}
                                                </div>
                                                <StatusBadge status={opportunity.status} />
                                            </Link>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </section>
                    </>
                )}
            </div>
        </RequireAuth>
    );
}
