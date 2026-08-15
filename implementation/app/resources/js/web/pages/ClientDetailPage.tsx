import { FormEvent, useEffect, useMemo, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import {
    addContact,
    archiveClient,
    archiveContact,
    changePrimaryContact,
    createOpportunity,
    getClient,
    listContacts,
    listOpportunities,
    reactivateClient,
    reactivateContact,
    updateContact,
} from '@/api/crm';
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

function formatContactDate(value: string): string {
    return new Intl.DateTimeFormat('fr-FR', { dateStyle: 'long' }).format(new Date(value));
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

    const [showClientArchiveForm, setShowClientArchiveForm] = useState(false);
    const [clientArchiveReason, setClientArchiveReason] = useState('');
    const [archivingClient, setArchivingClient] = useState(false);
    const [showClientReactivateConfirm, setShowClientReactivateConfirm] = useState(false);
    const [reactivatingClient, setReactivatingClient] = useState(false);

    const [showContactForm, setShowContactForm] = useState(false);
    const [contactName, setContactName] = useState('');
    const [contactEmail, setContactEmail] = useState('');
    const [contactPhone, setContactPhone] = useState('');
    const [contactRole, setContactRole] = useState('');
    const [makePrimary, setMakePrimary] = useState(false);
    const [creatingContact, setCreatingContact] = useState(false);
    const [changingPrimaryContactId, setChangingPrimaryContactId] = useState<string | null>(null);
    const [editingContactId, setEditingContactId] = useState<string | null>(null);
    const [editContactName, setEditContactName] = useState('');
    const [editContactEmail, setEditContactEmail] = useState('');
    const [editContactPhone, setEditContactPhone] = useState('');
    const [editContactRole, setEditContactRole] = useState('');
    const [updatingContactId, setUpdatingContactId] = useState<string | null>(null);
    const [archivingContactId, setArchivingContactId] = useState<string | null>(null);
    const [archiveReason, setArchiveReason] = useState('');
    const [archiveBusyContactId, setArchiveBusyContactId] = useState<string | null>(null);
    const [reactivatingContactId, setReactivatingContactId] = useState<string | null>(null);

    const [showOpportunityForm, setShowOpportunityForm] = useState(false);
    const [opportunityContactId, setOpportunityContactId] = useState('');
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
            if (a.status !== b.status) return a.status === 'Active' ? -1 : 1;

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

    const activeOpportunityCount = useMemo(
        () => opportunities.filter((opportunity) => ['Open', 'Qualified'].includes(opportunity.status)).length,
        [opportunities],
    );

    function openClientArchiveForm() {
        setShowContactForm(false);
        setShowOpportunityForm(false);
        setEditingContactId(null);
        setArchivingContactId(null);
        setArchiveReason('');
        setShowClientArchiveForm(true);
        setClientArchiveReason('');
        setError(null);
        setSuccess(null);
    }

    async function onArchiveClient(event: FormEvent) {
        event.preventDefault();
        if (!token || !workspaceId || !clientId || !client) return;

        setArchivingClient(true);
        setError(null);
        setSuccess(null);
        try {
            await archiveClient(token, workspaceId, clientId, clientArchiveReason.trim(), client.version);
            setClient(await getClient(token, workspaceId, clientId));
            setShowClientArchiveForm(false);
            setClientArchiveReason('');
            setSuccess(`Le client « ${client.display_name} » a bien été archivé. Son historique reste consultable.`);
        } catch (err) {
            const message = err instanceof Error ? err.message : 'Archivage du client impossible';
            setError(message === 'Active opportunity exists.'
                ? 'Ce client possède encore une opportunité en cours. Marquez-la comme gagnée ou perdue avant de l’archiver.'
                : message);
        } finally {
            setArchivingClient(false);
        }
    }

    async function onReactivateClient() {
        if (!token || !workspaceId || !clientId || !client) return;

        setReactivatingClient(true);
        setError(null);
        setSuccess(null);
        try {
            await reactivateClient(token, workspaceId, clientId, client.version);
            setClient(await getClient(token, workspaceId, clientId));
            setShowClientReactivateConfirm(false);
            setSuccess(`Le client « ${client.display_name} » est de nouveau actif.`);
        } catch (err) {
            setError(err instanceof Error ? err.message : 'Réactivation du client impossible');
        } finally {
            setReactivatingClient(false);
        }
    }

    function openContactForm() {
        setMakePrimary(client?.primary_contact_id === null);
        setShowContactForm(true);
        setSuccess(null);
    }

    function openOpportunityForm() {
        const primaryContact = contacts.find((contact) => contact.is_primary && contact.status === 'Active');
        setOpportunityContactId(primaryContact?.contact_id ?? '');
        setShowOpportunityForm(true);
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

    async function onChangePrimaryContact(
        newPrimaryContactId: string | null,
        selectedContactId: string,
        contactName: string,
    ) {
        if (!token || !workspaceId || !clientId || !client) return;

        setChangingPrimaryContactId(selectedContactId);
        setError(null);
        setSuccess(null);
        try {
            await changePrimaryContact(
                token,
                workspaceId,
                clientId,
                newPrimaryContactId,
                client.version,
            );
            const [clientData, contactData] = await Promise.all([
                getClient(token, workspaceId, clientId),
                listContacts(token, workspaceId, clientId),
            ]);
            setClient(clientData);
            setContacts(contactData);
            setSuccess(newPrimaryContactId
                ? `« ${contactName} » est maintenant le contact principal.`
                : `« ${contactName} » n’est plus le contact principal.`);
        } catch (err) {
            setError(err instanceof Error ? err.message : 'Changement du contact principal impossible');
        } finally {
            setChangingPrimaryContactId(null);
        }
    }

    function openEditContact(contact: ContactSummary) {
        setArchivingContactId(null);
        setArchiveReason('');
        setEditingContactId(contact.contact_id);
        setEditContactName(contactProfileValue(contact, 'display_name') ?? '');
        setEditContactEmail(contactProfileValue(contact, 'email') ?? '');
        setEditContactPhone(contactProfileValue(contact, 'phone') ?? '');
        setEditContactRole(contactProfileValue(contact, 'role') ?? '');
        setError(null);
        setSuccess(null);
    }

    async function onUpdateContact(event: FormEvent, contact: ContactSummary) {
        event.preventDefault();
        if (!token || !workspaceId || !clientId || !client) return;

        setUpdatingContactId(contact.contact_id);
        setError(null);
        setSuccess(null);
        try {
            const displayName = editContactName.trim();
            await updateContact(token, workspaceId, clientId, contact.contact_id, {
                profile: {
                    display_name: displayName,
                    email: editContactEmail.trim() || null,
                    phone: editContactPhone.trim() || null,
                    role: editContactRole.trim() || null,
                },
                expected_revision: client.version,
            });
            const [clientData, contactData] = await Promise.all([
                getClient(token, workspaceId, clientId),
                listContacts(token, workspaceId, clientId),
            ]);
            setClient(clientData);
            setContacts(contactData);
            setEditingContactId(null);
            setSuccess(`Les informations de « ${displayName} » sont enregistrées.`);
        } catch (err) {
            setError(err instanceof Error ? err.message : 'Modification du contact impossible');
        } finally {
            setUpdatingContactId(null);
        }
    }

    function openArchiveContact(contactId: string) {
        setEditingContactId(null);
        setArchivingContactId(contactId);
        setArchiveReason('');
        setError(null);
        setSuccess(null);
    }

    async function onArchiveContact(event: FormEvent, contact: ContactSummary, contactName: string) {
        event.preventDefault();
        if (!token || !workspaceId || !clientId || !client) return;

        setArchiveBusyContactId(contact.contact_id);
        setError(null);
        setSuccess(null);
        try {
            await archiveContact(
                token,
                workspaceId,
                clientId,
                contact.contact_id,
                archiveReason.trim(),
                client.version,
            );
            const [clientData, contactData] = await Promise.all([
                getClient(token, workspaceId, clientId),
                listContacts(token, workspaceId, clientId),
            ]);
            setClient(clientData);
            setContacts(contactData);
            setArchivingContactId(null);
            setArchiveReason('');
            setSuccess(`« ${contactName} » a bien été archivé.`);
        } catch (err) {
            const message = err instanceof Error ? err.message : 'Archivage du contact impossible';
            setError(message === 'Contact in use.'
                ? 'Ce contact est associé à une opportunité en cours. Réaffectez ou terminez cette opportunité avant de l’archiver.'
                : message);
        } finally {
            setArchiveBusyContactId(null);
        }
    }

    async function onReactivateContact(contact: ContactSummary, contactName: string) {
        if (!token || !workspaceId || !clientId || !client) return;

        setReactivatingContactId(contact.contact_id);
        setError(null);
        setSuccess(null);
        try {
            await reactivateContact(token, workspaceId, clientId, contact.contact_id, client.version);
            const [clientData, contactData] = await Promise.all([
                getClient(token, workspaceId, clientId),
                listContacts(token, workspaceId, clientId),
            ]);
            setClient(clientData);
            setContacts(contactData);
            setSuccess(`« ${contactName} » est de nouveau actif. Vous pouvez le définir comme principal si nécessaire.`);
        } catch (err) {
            setError(err instanceof Error ? err.message : 'Réactivation du contact impossible');
        } finally {
            setReactivatingContactId(null);
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
                ...(opportunityContactId ? { contact_id: opportunityContactId } : {}),
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
                            <div className="flex flex-col items-end gap-3">
                                <StatusBadge status={client.status} />
                                {client.status === 'Active' && !showClientArchiveForm && (
                                    <button
                                        type="button"
                                        onClick={openClientArchiveForm}
                                        className="text-sm font-semibold text-red-700 hover:underline"
                                    >
                                        Archiver le client
                                    </button>
                                )}
                            </div>
                        </div>

                        {client.status === 'Archived' && (
                            <div className="mt-6 rounded-2xl border border-atlas-border bg-atlas-surface p-5">
                                <div className="flex flex-wrap items-start justify-between gap-4">
                                    <div>
                                        <p className="font-semibold text-atlas-ink">Client conservé dans l’historique</p>
                                        <p className="mt-1 text-sm text-atlas-ink-muted">
                                            Les contacts, opportunités et documents restent consultables, mais aucune nouvelle action courante n’est disponible.
                                            {client.archived_at ? ` Archivé le ${formatContactDate(client.archived_at)}.` : ''}
                                        </p>
                                    </div>
                                    {!showClientReactivateConfirm && (
                                        <button
                                            type="button"
                                            onClick={() => {
                                                setShowClientReactivateConfirm(true);
                                                setError(null);
                                                setSuccess(null);
                                            }}
                                            className="rounded-xl bg-atlas-accent px-4 py-2.5 text-sm font-semibold text-white hover:opacity-90"
                                        >
                                            Réactiver le client
                                        </button>
                                    )}
                                </div>
                                {showClientReactivateConfirm && (
                                    <div role="group" aria-label="Confirmer la réactivation du client" className="mt-5 border-t border-atlas-border pt-5">
                                        <p className="text-sm text-atlas-ink">
                                            Le client redeviendra disponible pour les actions courantes. Ses contacts archivés resteront archivés.
                                        </p>
                                        <div className="mt-4 flex flex-col gap-2 sm:flex-row">
                                            <button
                                                type="button"
                                                disabled={reactivatingClient}
                                                aria-busy={reactivatingClient}
                                                onClick={() => void onReactivateClient()}
                                                className="rounded-xl bg-atlas-accent px-4 py-2.5 text-sm font-semibold text-white hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-60"
                                            >
                                                {reactivatingClient ? 'Réactivation…' : 'Confirmer la réactivation'}
                                            </button>
                                            <button
                                                type="button"
                                                disabled={reactivatingClient}
                                                onClick={() => setShowClientReactivateConfirm(false)}
                                                className="rounded-xl border border-atlas-border bg-white px-4 py-2.5 text-sm font-medium text-atlas-ink-muted"
                                            >
                                                Annuler
                                            </button>
                                        </div>
                                    </div>
                                )}
                            </div>
                        )}

                        {showClientArchiveForm && client.status === 'Active' && (
                            <form
                                aria-label="Archiver le client"
                                onSubmit={onArchiveClient}
                                className="mt-6 space-y-4 rounded-2xl border border-red-200 bg-red-50 p-5"
                            >
                                <fieldset disabled={archivingClient} className="space-y-4">
                                    <div>
                                        <p className="font-semibold text-red-900">Archiver ce client ?</p>
                                        <p className="mt-1 text-sm text-red-800">
                                            Son historique et ses contacts seront conservés. L’archivage exige que toutes ses opportunités soient gagnées ou perdues.
                                        </p>
                                        {activeOpportunityCount > 0 && (
                                            <p className="mt-2 text-sm font-semibold text-red-900">
                                                {activeOpportunityCount} opportunité{activeOpportunityCount > 1 ? 's' : ''} encore en cours.
                                            </p>
                                        )}
                                    </div>
                                    <FormField label="Motif d’archivage">
                                        <textarea
                                            required
                                            minLength={2}
                                            maxLength={160}
                                            rows={3}
                                            className={inputClassName}
                                            value={clientArchiveReason}
                                            onChange={(event) => setClientArchiveReason(event.target.value)}
                                        />
                                    </FormField>
                                    <div className="flex flex-col gap-2 sm:flex-row">
                                        <button
                                            type="submit"
                                            disabled={archivingClient || activeOpportunityCount > 0}
                                            className="rounded-xl bg-red-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-red-800 disabled:cursor-not-allowed disabled:opacity-60"
                                        >
                                            {archivingClient ? 'Archivage…' : 'Confirmer l’archivage du client'}
                                        </button>
                                        <button
                                            type="button"
                                            onClick={() => {
                                                setShowClientArchiveForm(false);
                                                setClientArchiveReason('');
                                            }}
                                            className="rounded-xl border border-atlas-border bg-white px-4 py-2.5 text-sm font-medium text-atlas-ink-muted"
                                        >
                                            Annuler
                                        </button>
                                    </div>
                                </fieldset>
                            </form>
                        )}

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
                                {client.status === 'Active' && !showContactForm && (
                                    <button
                                        type="button"
                                        onClick={openContactForm}
                                        className="rounded-xl border border-atlas-accent px-4 py-2 text-sm font-semibold text-atlas-accent hover:bg-atlas-accent/5"
                                    >
                                        Ajouter un contact
                                    </button>
                                )}
                            </div>

                            {client.status === 'Active' && showContactForm && (
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
                                                    {contact.status === 'Archived' && (
                                                        <span className="rounded-full bg-atlas-surface px-2.5 py-1 text-xs font-semibold text-atlas-ink-muted">
                                                            Archivé
                                                        </span>
                                                    )}
                                                </div>
                                                {(email || phone) && (
                                                    <div className="mt-4 space-y-1.5 text-sm">
                                                        {email && client.status === 'Active' && contact.status === 'Active' && (
                                                            <a className="block text-atlas-accent hover:underline" href={`mailto:${email}`}>
                                                                {email}
                                                            </a>
                                                        )}
                                                        {email && (client.status !== 'Active' || contact.status !== 'Active') && (
                                                            <p className="text-atlas-ink-muted">{email}</p>
                                                        )}
                                                        {phone && client.status === 'Active' && contact.status === 'Active' && (
                                                            <a className="block text-atlas-accent hover:underline" href={`tel:${phone}`}>
                                                                {phone}
                                                            </a>
                                                        )}
                                                        {phone && (client.status !== 'Active' || contact.status !== 'Active') && (
                                                            <p className="text-atlas-ink-muted">{phone}</p>
                                                        )}
                                                    </div>
                                                )}
                                                {client.status === 'Active' && contact.status === 'Active' && (
                                                    <div className="mt-4 flex flex-wrap gap-x-4 gap-y-2">
                                                        <button
                                                            type="button"
                                                            disabled={changingPrimaryContactId !== null || updatingContactId !== null || archiveBusyContactId !== null}
                                                            aria-busy={changingPrimaryContactId === contact.contact_id}
                                                            aria-label={contact.is_primary
                                                                ? `Retirer ${name} comme contact principal`
                                                                : `Définir ${name} comme contact principal`}
                                                            onClick={() => void onChangePrimaryContact(
                                                                contact.is_primary ? null : contact.contact_id,
                                                                contact.contact_id,
                                                                name,
                                                            )}
                                                            className="text-xs font-semibold text-atlas-accent hover:underline disabled:cursor-not-allowed disabled:opacity-50"
                                                        >
                                                            {changingPrimaryContactId === contact.contact_id
                                                                ? 'Mise à jour…'
                                                                : contact.is_primary
                                                                    ? 'Retirer comme principal'
                                                                    : 'Définir comme principal'}
                                                        </button>
                                                        <button
                                                            type="button"
                                                            disabled={updatingContactId !== null || changingPrimaryContactId !== null || archiveBusyContactId !== null}
                                                            aria-label={`Modifier ${name}`}
                                                            onClick={() => openEditContact(contact)}
                                                            className="text-xs font-semibold text-atlas-ink-muted hover:text-atlas-accent hover:underline disabled:cursor-not-allowed disabled:opacity-50"
                                                        >
                                                            Modifier
                                                        </button>
                                                        <button
                                                            type="button"
                                                            disabled={updatingContactId !== null || changingPrimaryContactId !== null || archiveBusyContactId !== null}
                                                            aria-label={`Archiver ${name}`}
                                                            onClick={() => openArchiveContact(contact.contact_id)}
                                                            className="text-xs font-semibold text-red-700 hover:underline disabled:cursor-not-allowed disabled:opacity-50"
                                                        >
                                                            Archiver
                                                        </button>
                                                    </div>
                                                )}
                                                {client.status === 'Active' && contact.status === 'Archived' && (
                                                    <div className="mt-4 border-t border-atlas-border pt-4">
                                                        {contact.archived_at && (
                                                            <p className="mb-2 text-xs text-atlas-ink-muted">
                                                                Archivé le {formatContactDate(contact.archived_at)}
                                                            </p>
                                                        )}
                                                        <button
                                                            type="button"
                                                            disabled={reactivatingContactId !== null}
                                                            aria-busy={reactivatingContactId === contact.contact_id}
                                                            aria-label={`Réactiver ${name}`}
                                                            onClick={() => void onReactivateContact(contact, name)}
                                                            className="text-xs font-semibold text-atlas-accent hover:underline disabled:cursor-not-allowed disabled:opacity-50"
                                                        >
                                                            {reactivatingContactId === contact.contact_id
                                                                ? 'Réactivation…'
                                                                : 'Réactiver le contact'}
                                                        </button>
                                                        <p className="mt-1 text-xs text-atlas-ink-muted">
                                                            Il restera secondaire après sa réactivation.
                                                        </p>
                                                    </div>
                                                )}
                                                {editingContactId === contact.contact_id && (
                                                    <form
                                                        aria-label={`Modifier ${name}`}
                                                        onSubmit={(event) => void onUpdateContact(event, contact)}
                                                        className="mt-5 space-y-3 border-t border-atlas-border pt-5"
                                                    >
                                                        <fieldset disabled={updatingContactId === contact.contact_id} className="space-y-3">
                                                            <FormField label="Nom complet">
                                                                <input
                                                                    required
                                                                    autoComplete="name"
                                                                    className={inputClassName}
                                                                    value={editContactName}
                                                                    onChange={(event) => setEditContactName(event.target.value)}
                                                                />
                                                            </FormField>
                                                            <FormField label="Rôle (optionnel)">
                                                                <input
                                                                    autoComplete="organization-title"
                                                                    className={inputClassName}
                                                                    value={editContactRole}
                                                                    onChange={(event) => setEditContactRole(event.target.value)}
                                                                />
                                                            </FormField>
                                                            <FormField label="Email (optionnel)">
                                                                <input
                                                                    type="email"
                                                                    autoComplete="email"
                                                                    className={inputClassName}
                                                                    value={editContactEmail}
                                                                    onChange={(event) => setEditContactEmail(event.target.value)}
                                                                />
                                                            </FormField>
                                                            <FormField label="Téléphone (optionnel)">
                                                                <input
                                                                    type="tel"
                                                                    autoComplete="tel"
                                                                    className={inputClassName}
                                                                    value={editContactPhone}
                                                                    onChange={(event) => setEditContactPhone(event.target.value)}
                                                                />
                                                            </FormField>
                                                            <div className="flex flex-col gap-2">
                                                                <SubmitButton
                                                                    loading={updatingContactId === contact.contact_id}
                                                                    loadingLabel="Enregistrement…"
                                                                >
                                                                    Enregistrer
                                                                </SubmitButton>
                                                                <button
                                                                    type="button"
                                                                    onClick={() => setEditingContactId(null)}
                                                                    className="rounded-xl border border-atlas-border px-4 py-2.5 text-sm font-medium text-atlas-ink-muted"
                                                                >
                                                                    Annuler
                                                                </button>
                                                            </div>
                                                        </fieldset>
                                                    </form>
                                                )}
                                                {archivingContactId === contact.contact_id && (
                                                    <form
                                                        aria-label={`Archiver ${name}`}
                                                        onSubmit={(event) => void onArchiveContact(event, contact, name)}
                                                        className="mt-5 space-y-3 rounded-xl border border-red-200 bg-red-50 p-4"
                                                    >
                                                        <fieldset disabled={archiveBusyContactId === contact.contact_id} className="space-y-3">
                                                            <div>
                                                                <p className="text-sm font-semibold text-red-900">
                                                                    Archiver ce contact ?
                                                                </p>
                                                                <p className="mt-1 text-xs text-red-800">
                                                                    Il ne pourra plus être utilisé pour les actions courantes. L’archivage est refusé si une opportunité en cours le référence.
                                                                </p>
                                                            </div>
                                                            <FormField label="Motif d’archivage">
                                                                <textarea
                                                                    required
                                                                    minLength={2}
                                                                    maxLength={160}
                                                                    rows={3}
                                                                    className={inputClassName}
                                                                    value={archiveReason}
                                                                    onChange={(event) => setArchiveReason(event.target.value)}
                                                                />
                                                            </FormField>
                                                            <div className="flex flex-col gap-2">
                                                                <button
                                                                    type="submit"
                                                                    className="rounded-xl bg-red-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-red-800 disabled:cursor-not-allowed disabled:opacity-60"
                                                                >
                                                                    {archiveBusyContactId === contact.contact_id
                                                                        ? 'Archivage…'
                                                                        : 'Confirmer l’archivage'}
                                                                </button>
                                                                <button
                                                                    type="button"
                                                                    onClick={() => {
                                                                        setArchivingContactId(null);
                                                                        setArchiveReason('');
                                                                    }}
                                                                    className="rounded-xl border border-atlas-border bg-white px-4 py-2.5 text-sm font-medium text-atlas-ink-muted"
                                                                >
                                                                    Annuler
                                                                </button>
                                                            </div>
                                                        </fieldset>
                                                    </form>
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
                                {client.status === 'Active' && !showOpportunityForm && (
                                    <button
                                        type="button"
                                        onClick={openOpportunityForm}
                                        className="rounded-xl bg-atlas-accent px-4 py-2 text-sm font-semibold text-white hover:opacity-90"
                                    >
                                        Nouvelle opportunité
                                    </button>
                                )}
                            </div>

                            {client.status === 'Active' && showOpportunityForm && (
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
                                        <FormField
                                            label="Contact associé (optionnel)"
                                            hint={contacts.length > 0
                                                ? 'Le contact principal est présélectionné.'
                                                : 'Ajoutez d’abord un contact si vous souhaitez l’associer.'}
                                        >
                                            <select
                                                className={inputClassName}
                                                value={opportunityContactId}
                                                onChange={(event) => setOpportunityContactId(event.target.value)}
                                            >
                                                <option value="">Aucun contact associé</option>
                                                {sortedContacts
                                                    .filter((contact) => contact.status === 'Active')
                                                    .map((contact) => (
                                                        <option key={contact.contact_id} value={contact.contact_id}>
                                                            {contactProfileValue(contact, 'display_name') ?? 'Contact sans nom'}
                                                            {contact.is_primary ? ' — principal' : ''}
                                                        </option>
                                                    ))}
                                            </select>
                                        </FormField>
                                    </div>
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
