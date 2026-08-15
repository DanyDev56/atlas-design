import { FormEvent, useEffect, useMemo, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import {
    addContact,
    archiveClient,
    archiveContact,
    changePrimaryContact,
    correctClientActivity,
    createOpportunity,
    getClient,
    listClientActivities,
    listContacts,
    listOpportunities,
    reactivateClient,
    reactivateContact,
    recordClientActivity,
    removeClientActivity,
    updateClientBillingProfile,
    updateClientProfile,
    updateContact,
} from '@/api/crm';
import { ErrorBanner, FormField, SubmitButton, SuccessBanner, inputClassName } from '@/components/auth/AuthLayout';
import { StatusBadge } from '@/components/crm/StatusBadge';
import { RequireAuth } from '@/components/layout/RequireAuth';
import { EmptyState } from '@/components/ui/EmptyState';
import { PageSkeleton } from '@/components/ui/PageSkeleton';
import { useAuth } from '@/hooks/useAuth';
import type {
    ActivityKind,
    ClientActivity,
    ClientBillingIdentifier,
    ClientDetail,
    ContactSummary,
    OpportunitySummary,
} from '@/types/api';
import { formatMoney } from '@/utils/format';

function contactProfileValue(contact: ContactSummary, key: string): string | null {
    const value = contact.profile[key];

    return typeof value === 'string' && value.trim() !== '' ? value : null;
}

function clientProfileValue(client: ClientDetail, key: string): string | null {
    const value = client.profile[key];

    return typeof value === 'string' && value.trim() !== '' ? value : null;
}

function billingProfileValue(client: ClientDetail, key: 'billing_name' | 'billing_email'): string | null {
    const value = client.billing_profile?.[key];

    return typeof value === 'string' && value.trim() !== '' ? value : null;
}

function billingAddressValue(client: ClientDetail, key: 'line1' | 'line2' | 'postal_code' | 'city' | 'country_code'): string {
    const value = client.billing_profile?.billing_address?.[key];

    return typeof value === 'string' ? value : '';
}

function formatContactDate(value: string): string {
    return new Intl.DateTimeFormat('fr-FR', { dateStyle: 'long' }).format(new Date(value));
}

function formatActivityDate(value: string): string {
    return new Intl.DateTimeFormat('fr-FR', { dateStyle: 'long', timeStyle: 'short' }).format(new Date(value));
}

function localDateTimeValue(date = new Date()): string {
    return new Date(date.getTime() - date.getTimezoneOffset() * 60_000).toISOString().slice(0, 16);
}

const activityKindLabels: Record<ActivityKind, string> = {
    Note: 'Note',
    Call: 'Appel',
    Meeting: 'Réunion',
    Email: 'E-mail',
};

export function ClientDetailPage() {
    const { clientId } = useParams<{ clientId: string }>();
    const { session } = useAuth();
    const token = session?.token ?? null;
    const workspaceId = session?.workspaceId ?? null;

    const [client, setClient] = useState<ClientDetail | null>(null);
    const [contacts, setContacts] = useState<ContactSummary[]>([]);
    const [opportunities, setOpportunities] = useState<OpportunitySummary[]>([]);
    const [activities, setActivities] = useState<ClientActivity[]>([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [success, setSuccess] = useState<string | null>(null);

    const [showClientArchiveForm, setShowClientArchiveForm] = useState(false);
    const [clientArchiveReason, setClientArchiveReason] = useState('');
    const [archivingClient, setArchivingClient] = useState(false);
    const [showClientReactivateConfirm, setShowClientReactivateConfirm] = useState(false);
    const [reactivatingClient, setReactivatingClient] = useState(false);
    const [editingClientProfile, setEditingClientProfile] = useState(false);
    const [clientDisplayName, setClientDisplayName] = useState('');
    const [clientLegalName, setClientLegalName] = useState('');
    const [clientDescription, setClientDescription] = useState('');
    const [clientEmail, setClientEmail] = useState('');
    const [clientPhone, setClientPhone] = useState('');
    const [clientWebsite, setClientWebsite] = useState('');
    const [updatingClientProfile, setUpdatingClientProfile] = useState(false);
    const [editingBillingProfile, setEditingBillingProfile] = useState(false);
    const [billingName, setBillingName] = useState('');
    const [billingEmail, setBillingEmail] = useState('');
    const [billingAddressLine1, setBillingAddressLine1] = useState('');
    const [billingAddressLine2, setBillingAddressLine2] = useState('');
    const [billingPostalCode, setBillingPostalCode] = useState('');
    const [billingCity, setBillingCity] = useState('');
    const [billingCountryCode, setBillingCountryCode] = useState('');
    const [registrationIdentifiers, setRegistrationIdentifiers] = useState<ClientBillingIdentifier[]>([]);
    const [taxIdentifiers, setTaxIdentifiers] = useState<ClientBillingIdentifier[]>([]);
    const [updatingBillingProfile, setUpdatingBillingProfile] = useState(false);

    const [showActivityForm, setShowActivityForm] = useState(false);
    const [activityKind, setActivityKind] = useState<ActivityKind>('Note');
    const [activitySummary, setActivitySummary] = useState('');
    const [activityOccurredAt, setActivityOccurredAt] = useState(localDateTimeValue());
    const [activityContactId, setActivityContactId] = useState('');
    const [activityOpportunityId, setActivityOpportunityId] = useState('');
    const [recordingActivity, setRecordingActivity] = useState(false);
    const [editingActivityId, setEditingActivityId] = useState<string | null>(null);
    const [editActivityKind, setEditActivityKind] = useState<ActivityKind>('Note');
    const [editActivitySummary, setEditActivitySummary] = useState('');
    const [editActivityOccurredAt, setEditActivityOccurredAt] = useState('');
    const [activityCorrectionReason, setActivityCorrectionReason] = useState('');
    const [correctingActivity, setCorrectingActivity] = useState(false);
    const [removingActivityId, setRemovingActivityId] = useState<string | null>(null);
    const [activityRemovalReason, setActivityRemovalReason] = useState('');
    const [removingActivity, setRemovingActivity] = useState(false);

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
                const [clientData, contactData, allOpportunities, activityData] = await Promise.all([
                    getClient(activeToken, activeWorkspaceId, selectedClientId),
                    listContacts(activeToken, activeWorkspaceId, selectedClientId),
                    listOpportunities(activeToken, activeWorkspaceId),
                    listClientActivities(activeToken, activeWorkspaceId, selectedClientId),
                ]);
                if (!cancelled) {
                    setClient(clientData);
                    setContacts(contactData);
                    setOpportunities(allOpportunities.filter((opportunity) => opportunity.client_id === selectedClientId));
                    setActivities(activityData);
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
        setEditingClientProfile(false);
        setEditingBillingProfile(false);
        setShowActivityForm(false);
        setEditingActivityId(null);
        setRemovingActivityId(null);
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

    function openClientProfileForm() {
        if (!client) return;

        setShowClientArchiveForm(false);
        setClientArchiveReason('');
        setEditingBillingProfile(false);
        setShowActivityForm(false);
        setClientDisplayName(client.display_name);
        setClientLegalName(clientProfileValue(client, 'legal_name') ?? '');
        setClientDescription(clientProfileValue(client, 'description') ?? '');
        setClientEmail(clientProfileValue(client, 'email') ?? '');
        setClientPhone(clientProfileValue(client, 'phone') ?? '');
        setClientWebsite(clientProfileValue(client, 'website') ?? '');
        setEditingClientProfile(true);
        setError(null);
        setSuccess(null);
    }

    async function onUpdateClientProfile(event: FormEvent) {
        event.preventDefault();
        if (!token || !workspaceId || !clientId || !client) return;

        setUpdatingClientProfile(true);
        setError(null);
        setSuccess(null);
        try {
            const displayName = clientDisplayName.trim();
            await updateClientProfile(token, workspaceId, clientId, {
                display_name: displayName,
                legal_name: clientLegalName.trim() || null,
                description: clientDescription.trim() || null,
                email: clientEmail.trim() || null,
                phone: clientPhone.trim() || null,
                website: clientWebsite.trim() || null,
            }, client.version);
            setClient(await getClient(token, workspaceId, clientId));
            setEditingClientProfile(false);
            setSuccess(`Les informations de « ${displayName} » sont enregistrées.`);
        } catch (err) {
            const message = err instanceof Error ? err.message : 'Modification du client impossible';
            setError(message === 'Client profile unchanged.' ? 'Aucune information n’a été modifiée.' : message);
        } finally {
            setUpdatingClientProfile(false);
        }
    }

    function openBillingProfileForm() {
        if (!client) return;

        setShowClientArchiveForm(false);
        setClientArchiveReason('');
        setEditingClientProfile(false);
        setShowActivityForm(false);
        setBillingName(billingProfileValue(client, 'billing_name') ?? '');
        setBillingEmail(billingProfileValue(client, 'billing_email') ?? '');
        setBillingAddressLine1(billingAddressValue(client, 'line1'));
        setBillingAddressLine2(billingAddressValue(client, 'line2'));
        setBillingPostalCode(billingAddressValue(client, 'postal_code'));
        setBillingCity(billingAddressValue(client, 'city'));
        setBillingCountryCode(billingAddressValue(client, 'country_code'));
        setRegistrationIdentifiers(client.billing_profile?.registration_identifiers?.map((identifier) => ({ ...identifier })) ?? []);
        setTaxIdentifiers(client.billing_profile?.tax_identifiers?.map((identifier) => ({ ...identifier })) ?? []);
        setEditingBillingProfile(true);
        setError(null);
        setSuccess(null);
    }

    function updateIdentifier(
        collection: ClientBillingIdentifier[],
        setter: (identifiers: ClientBillingIdentifier[]) => void,
        index: number,
        field: keyof ClientBillingIdentifier,
        value: string,
    ) {
        setter(collection.map((identifier, currentIndex) => (
            currentIndex === index ? { ...identifier, [field]: value } : identifier
        )));
    }

    async function onUpdateBillingProfile(event: FormEvent) {
        event.preventDefault();
        if (!token || !workspaceId || !clientId || !client) return;

        setUpdatingBillingProfile(true);
        setError(null);
        setSuccess(null);
        try {
            const address = {
                ...(billingAddressLine1.trim() ? { line1: billingAddressLine1.trim() } : {}),
                ...(billingAddressLine2.trim() ? { line2: billingAddressLine2.trim() } : {}),
                ...(billingPostalCode.trim() ? { postal_code: billingPostalCode.trim() } : {}),
                ...(billingCity.trim() ? { city: billingCity.trim() } : {}),
                ...(billingCountryCode.trim() ? { country_code: billingCountryCode.trim().toUpperCase() } : {}),
            };
            await updateClientBillingProfile(token, workspaceId, clientId, {
                ...(billingName.trim() ? { billing_name: billingName.trim() } : {}),
                ...(billingEmail.trim() ? { billing_email: billingEmail.trim() } : {}),
                ...(Object.keys(address).length > 0 ? { billing_address: address } : {}),
                ...(registrationIdentifiers.length > 0
                    ? { registration_identifiers: registrationIdentifiers.map((identifier) => ({
                        type: identifier.type.trim().toUpperCase(),
                        value: identifier.value.trim(),
                    })) }
                    : {}),
                ...(taxIdentifiers.length > 0
                    ? { tax_identifiers: taxIdentifiers.map((identifier) => ({
                        type: identifier.type.trim().toUpperCase(),
                        value: identifier.value.trim(),
                    })) }
                    : {}),
            }, client.version);
            setClient(await getClient(token, workspaceId, clientId));
            setEditingBillingProfile(false);
            setSuccess('Les informations de facturation sont enregistrées pour les prochains documents.');
        } catch (err) {
            const message = err instanceof Error ? err.message : 'Modification des informations de facturation impossible';
            setError(message === 'Client billing profile unchanged.'
                ? 'Aucune information de facturation n’a été modifiée.'
                : message);
        } finally {
            setUpdatingBillingProfile(false);
        }
    }

    function openContactForm() {
        setShowActivityForm(false);
        setMakePrimary(client?.primary_contact_id === null);
        setShowContactForm(true);
        setSuccess(null);
    }

    function openActivityForm() {
        setShowClientArchiveForm(false);
        setEditingClientProfile(false);
        setEditingBillingProfile(false);
        setShowContactForm(false);
        setShowOpportunityForm(false);
        setActivityKind('Note');
        setActivitySummary('');
        setActivityOccurredAt(localDateTimeValue());
        setActivityContactId('');
        setActivityOpportunityId('');
        setShowActivityForm(true);
        setEditingActivityId(null);
        setRemovingActivityId(null);
        setError(null);
        setSuccess(null);
    }

    async function onRecordActivity(event: FormEvent) {
        event.preventDefault();
        if (!token || !workspaceId || !clientId) return;

        setRecordingActivity(true);
        setError(null);
        setSuccess(null);
        try {
            await recordClientActivity(token, workspaceId, clientId, {
                kind: activityKind,
                summary: activitySummary.trim(),
                occurred_at: new Date(activityOccurredAt).toISOString(),
                ...(activityContactId ? { contact_id: activityContactId } : {}),
                ...(activityOpportunityId ? { opportunity_id: activityOpportunityId } : {}),
            });
            setActivities(await listClientActivities(token, workspaceId, clientId));
            setShowActivityForm(false);
            setActivitySummary('');
            setSuccess(`${activityKindLabels[activityKind]} ajoutée à la chronologie.`);
        } catch (err) {
            const message = err instanceof Error ? err.message : 'Enregistrement de l’activité impossible';
            setError(message === 'Activity occurred at is in the future.'
                ? 'La date de l’activité ne peut pas être dans le futur.'
                : message);
        } finally {
            setRecordingActivity(false);
        }
    }

    function openActivityCorrection(activity: ClientActivity) {
        setShowActivityForm(false);
        setEditActivityKind(activity.kind);
        setEditActivitySummary(activity.summary);
        setEditActivityOccurredAt(localDateTimeValue(new Date(activity.occurred_at)));
        setActivityCorrectionReason('');
        setEditingActivityId(activity.activity_id);
        setRemovingActivityId(null);
        setError(null);
        setSuccess(null);
    }

    async function onCorrectActivity(event: FormEvent, activity: ClientActivity) {
        event.preventDefault();
        if (!token || !workspaceId || !clientId) return;

        setCorrectingActivity(true);
        setError(null);
        setSuccess(null);
        try {
            await correctClientActivity(token, workspaceId, activity.activity_id, {
                content: {
                    kind: editActivityKind,
                    summary: editActivitySummary.trim(),
                    occurred_at: new Date(editActivityOccurredAt).toISOString(),
                },
                correction_reason: activityCorrectionReason.trim(),
                expected_revision: activity.version,
            });
            setActivities(await listClientActivities(token, workspaceId, clientId));
            setEditingActivityId(null);
            setActivityCorrectionReason('');
            setSuccess('L’activité a été corrigée et sa version précédente reste auditée.');
        } catch (err) {
            const message = err instanceof Error ? err.message : 'Correction de l’activité impossible';
            setError(message === 'Activity unchanged.'
                ? 'Modifiez au moins le type, le résumé ou la date de l’activité.'
                : message === 'Activity occurred at is in the future.'
                    ? 'La date de l’activité ne peut pas être dans le futur.'
                    : message);
        } finally {
            setCorrectingActivity(false);
        }
    }

    function openActivityRemoval(activity: ClientActivity) {
        setShowActivityForm(false);
        setEditingActivityId(null);
        setActivityRemovalReason('');
        setRemovingActivityId(activity.activity_id);
        setError(null);
        setSuccess(null);
    }

    async function onRemoveActivity(event: FormEvent, activity: ClientActivity) {
        event.preventDefault();
        if (!token || !workspaceId || !clientId) return;

        setRemovingActivity(true);
        setError(null);
        setSuccess(null);
        try {
            await removeClientActivity(
                token,
                workspaceId,
                activity.activity_id,
                activityRemovalReason.trim(),
                activity.version,
            );
            setActivities(await listClientActivities(token, workspaceId, clientId));
            setRemovingActivityId(null);
            setActivityRemovalReason('');
            setSuccess('L’activité a été retirée de la chronologie et reste conservée dans l’audit.');
        } catch (err) {
            const message = err instanceof Error ? err.message : 'Retrait de l’activité impossible';
            setError(message === 'Activity is not recorded.'
                ? 'Cette activité a déjà été retirée.'
                : message);
        } finally {
            setRemovingActivity(false);
        }
    }

    function openOpportunityForm() {
        setShowActivityForm(false);
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
                                {client.status === 'Active' && !showClientArchiveForm && !editingClientProfile && !editingBillingProfile && (
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

                        <section className="mt-10" aria-labelledby="client-profile-heading">
                            <div className="mb-4 flex flex-wrap items-center justify-between gap-4">
                                <div>
                                    <h3 id="client-profile-heading" className="text-lg font-semibold text-atlas-ink">
                                        Informations client
                                    </h3>
                                    <p className="mt-1 text-sm text-atlas-ink-muted">
                                        Les coordonnées commerciales utilisées pour les prochains échanges.
                                    </p>
                                </div>
                                {client.status === 'Active' && !editingClientProfile && !editingBillingProfile && (
                                    <button
                                        type="button"
                                        onClick={openClientProfileForm}
                                        className="rounded-xl border border-atlas-accent px-4 py-2 text-sm font-semibold text-atlas-accent hover:bg-atlas-accent/5"
                                    >
                                        Modifier les informations
                                    </button>
                                )}
                            </div>

                            {!editingClientProfile && (
                                <div className="rounded-2xl border border-atlas-border bg-atlas-card p-6 shadow-sm">
                                    <dl className="grid gap-5 sm:grid-cols-2">
                                        <div>
                                            <dt className="text-xs font-semibold uppercase tracking-wide text-atlas-ink-muted">Nom affiché</dt>
                                            <dd className="mt-1 text-sm text-atlas-ink">{client.display_name}</dd>
                                        </div>
                                        {clientProfileValue(client, 'legal_name') && (
                                            <div>
                                                <dt className="text-xs font-semibold uppercase tracking-wide text-atlas-ink-muted">Raison sociale</dt>
                                                <dd className="mt-1 text-sm text-atlas-ink">{clientProfileValue(client, 'legal_name')}</dd>
                                            </div>
                                        )}
                                        {clientProfileValue(client, 'email') && (
                                            <div>
                                                <dt className="text-xs font-semibold uppercase tracking-wide text-atlas-ink-muted">Email</dt>
                                                <dd className="mt-1 text-sm">
                                                    {client.status === 'Active' ? (
                                                        <a className="text-atlas-accent hover:underline" href={`mailto:${clientProfileValue(client, 'email')}`}>
                                                            {clientProfileValue(client, 'email')}
                                                        </a>
                                                    ) : (
                                                        <span className="text-atlas-ink">{clientProfileValue(client, 'email')}</span>
                                                    )}
                                                </dd>
                                            </div>
                                        )}
                                        {clientProfileValue(client, 'phone') && (
                                            <div>
                                                <dt className="text-xs font-semibold uppercase tracking-wide text-atlas-ink-muted">Téléphone</dt>
                                                <dd className="mt-1 text-sm">
                                                    {client.status === 'Active' ? (
                                                        <a className="text-atlas-accent hover:underline" href={`tel:${clientProfileValue(client, 'phone')}`}>
                                                            {clientProfileValue(client, 'phone')}
                                                        </a>
                                                    ) : (
                                                        <span className="text-atlas-ink">{clientProfileValue(client, 'phone')}</span>
                                                    )}
                                                </dd>
                                            </div>
                                        )}
                                        {clientProfileValue(client, 'website') && (
                                            <div>
                                                <dt className="text-xs font-semibold uppercase tracking-wide text-atlas-ink-muted">Site web</dt>
                                                <dd className="mt-1 text-sm">
                                                    {client.status === 'Active' ? (
                                                        <a
                                                            className="break-all text-atlas-accent hover:underline"
                                                            href={clientProfileValue(client, 'website') ?? undefined}
                                                            target="_blank"
                                                            rel="noreferrer"
                                                        >
                                                            {clientProfileValue(client, 'website')}
                                                        </a>
                                                    ) : (
                                                        <span className="break-all text-atlas-ink">{clientProfileValue(client, 'website')}</span>
                                                    )}
                                                </dd>
                                            </div>
                                        )}
                                    </dl>
                                    {clientProfileValue(client, 'description') && (
                                        <div className="mt-5 border-t border-atlas-border pt-5">
                                            <p className="text-xs font-semibold uppercase tracking-wide text-atlas-ink-muted">Contexte commercial</p>
                                            <p className="mt-2 whitespace-pre-wrap text-sm text-atlas-ink">
                                                {clientProfileValue(client, 'description')}
                                            </p>
                                        </div>
                                    )}
                                </div>
                            )}

                            {client.status === 'Active' && editingClientProfile && (
                                <form
                                    aria-label="Modifier les informations du client"
                                    onSubmit={onUpdateClientProfile}
                                    className="rounded-2xl border border-atlas-border bg-atlas-card p-6 shadow-sm"
                                >
                                    <fieldset disabled={updatingClientProfile} className="space-y-4">
                                        <div className="grid gap-4 sm:grid-cols-2">
                                            <FormField label="Nom affiché">
                                                <input
                                                    required
                                                    minLength={2}
                                                    maxLength={160}
                                                    className={inputClassName}
                                                    value={clientDisplayName}
                                                    onChange={(event) => setClientDisplayName(event.target.value)}
                                                />
                                            </FormField>
                                            <FormField label="Raison sociale (optionnel)">
                                                <input
                                                    maxLength={160}
                                                    className={inputClassName}
                                                    value={clientLegalName}
                                                    onChange={(event) => setClientLegalName(event.target.value)}
                                                />
                                            </FormField>
                                            <FormField label="Email (optionnel)">
                                                <input
                                                    type="email"
                                                    autoComplete="email"
                                                    maxLength={254}
                                                    className={inputClassName}
                                                    value={clientEmail}
                                                    onChange={(event) => setClientEmail(event.target.value)}
                                                />
                                            </FormField>
                                            <FormField label="Téléphone (optionnel)">
                                                <input
                                                    type="tel"
                                                    autoComplete="tel"
                                                    maxLength={50}
                                                    className={inputClassName}
                                                    value={clientPhone}
                                                    onChange={(event) => setClientPhone(event.target.value)}
                                                />
                                            </FormField>
                                            <div className="sm:col-span-2">
                                                <FormField label="Site web (optionnel)">
                                                    <input
                                                        type="url"
                                                        maxLength={2048}
                                                        className={inputClassName}
                                                        value={clientWebsite}
                                                        onChange={(event) => setClientWebsite(event.target.value)}
                                                        placeholder="https://entreprise.fr"
                                                    />
                                                </FormField>
                                            </div>
                                            <div className="sm:col-span-2">
                                                <FormField label="Contexte commercial (optionnel)">
                                                    <textarea
                                                        maxLength={2000}
                                                        rows={4}
                                                        className={inputClassName}
                                                        value={clientDescription}
                                                        onChange={(event) => setClientDescription(event.target.value)}
                                                    />
                                                </FormField>
                                            </div>
                                        </div>
                                        <div className="flex flex-col gap-3 sm:flex-row">
                                            <SubmitButton loading={updatingClientProfile} loadingLabel="Enregistrement…">
                                                Enregistrer les informations
                                            </SubmitButton>
                                            <button
                                                type="button"
                                                onClick={() => setEditingClientProfile(false)}
                                                className="rounded-xl border border-atlas-border px-4 py-3 text-sm font-medium text-atlas-ink-muted"
                                            >
                                                Annuler
                                            </button>
                                        </div>
                                    </fieldset>
                                </form>
                            )}
                        </section>

                        <section className="mt-10" aria-labelledby="billing-profile-heading">
                            <div className="mb-4 flex flex-wrap items-center justify-between gap-4">
                                <div>
                                    <h3 id="billing-profile-heading" className="text-lg font-semibold text-atlas-ink">
                                        Informations de facturation
                                    </h3>
                                    <p className="mt-1 text-sm text-atlas-ink-muted">
                                        Elles seront copiées dans les prochains devis et factures.
                                    </p>
                                </div>
                                {client.status === 'Active' && !editingBillingProfile && !editingClientProfile && (
                                    <button
                                        type="button"
                                        onClick={openBillingProfileForm}
                                        className="rounded-xl border border-atlas-accent px-4 py-2 text-sm font-semibold text-atlas-accent hover:bg-atlas-accent/5"
                                    >
                                        Modifier la facturation
                                    </button>
                                )}
                            </div>

                            {!editingBillingProfile && (
                                <div className="rounded-2xl border border-atlas-border bg-atlas-card p-6 shadow-sm">
                                    {Object.keys(client.billing_profile ?? {}).length === 0 && (
                                        <p className="text-sm text-atlas-ink-muted">
                                            Aucune information administrative n’est encore renseignée.
                                        </p>
                                    )}
                                    {Object.keys(client.billing_profile ?? {}).length > 0 && (
                                        <div className="space-y-5">
                                            <dl className="grid gap-5 sm:grid-cols-2">
                                                {billingProfileValue(client, 'billing_name') && (
                                                    <div>
                                                        <dt className="text-xs font-semibold uppercase tracking-wide text-atlas-ink-muted">Nom de facturation</dt>
                                                        <dd className="mt-1 text-sm text-atlas-ink">{billingProfileValue(client, 'billing_name')}</dd>
                                                    </div>
                                                )}
                                                {billingProfileValue(client, 'billing_email') && (
                                                    <div>
                                                        <dt className="text-xs font-semibold uppercase tracking-wide text-atlas-ink-muted">Email de facturation</dt>
                                                        <dd className="mt-1 text-sm text-atlas-ink">{billingProfileValue(client, 'billing_email')}</dd>
                                                    </div>
                                                )}
                                            </dl>
                                            {client.billing_profile?.billing_address && (
                                                <div className="border-t border-atlas-border pt-5">
                                                    <p className="text-xs font-semibold uppercase tracking-wide text-atlas-ink-muted">Adresse de facturation</p>
                                                    <address className="mt-2 text-sm not-italic text-atlas-ink">
                                                        {billingAddressValue(client, 'line1') && <span className="block">{billingAddressValue(client, 'line1')}</span>}
                                                        {billingAddressValue(client, 'line2') && <span className="block">{billingAddressValue(client, 'line2')}</span>}
                                                        {(billingAddressValue(client, 'postal_code') || billingAddressValue(client, 'city')) && (
                                                            <span className="block">
                                                                {[billingAddressValue(client, 'postal_code'), billingAddressValue(client, 'city')].filter(Boolean).join(' ')}
                                                            </span>
                                                        )}
                                                        {billingAddressValue(client, 'country_code') && <span className="block">{billingAddressValue(client, 'country_code')}</span>}
                                                    </address>
                                                </div>
                                            )}
                                            {((client.billing_profile?.registration_identifiers?.length ?? 0) > 0
                                                || (client.billing_profile?.tax_identifiers?.length ?? 0) > 0) && (
                                                <div className="grid gap-5 border-t border-atlas-border pt-5 sm:grid-cols-2">
                                                    {(client.billing_profile?.registration_identifiers?.length ?? 0) > 0 && (
                                                        <div>
                                                            <p className="text-xs font-semibold uppercase tracking-wide text-atlas-ink-muted">Identifiants d’entreprise</p>
                                                            <ul className="mt-2 space-y-1 text-sm text-atlas-ink">
                                                                {client.billing_profile?.registration_identifiers?.map((identifier) => (
                                                                    <li key={identifier.type}>{identifier.type} : {identifier.value}</li>
                                                                ))}
                                                            </ul>
                                                        </div>
                                                    )}
                                                    {(client.billing_profile?.tax_identifiers?.length ?? 0) > 0 && (
                                                        <div>
                                                            <p className="text-xs font-semibold uppercase tracking-wide text-atlas-ink-muted">Identifiants fiscaux</p>
                                                            <ul className="mt-2 space-y-1 text-sm text-atlas-ink">
                                                                {client.billing_profile?.tax_identifiers?.map((identifier) => (
                                                                    <li key={identifier.type}>{identifier.type} : {identifier.value}</li>
                                                                ))}
                                                            </ul>
                                                        </div>
                                                    )}
                                                </div>
                                            )}
                                        </div>
                                    )}
                                </div>
                            )}

                            {client.status === 'Active' && editingBillingProfile && (
                                <form
                                    aria-label="Modifier les informations de facturation"
                                    onSubmit={onUpdateBillingProfile}
                                    className="rounded-2xl border border-atlas-border bg-atlas-card p-6 shadow-sm"
                                >
                                    <fieldset disabled={updatingBillingProfile} className="space-y-6">
                                        <div className="grid gap-4 sm:grid-cols-2">
                                            <FormField label="Nom de facturation (optionnel)">
                                                <input
                                                    maxLength={160}
                                                    className={inputClassName}
                                                    value={billingName}
                                                    onChange={(event) => setBillingName(event.target.value)}
                                                />
                                            </FormField>
                                            <FormField label="Email de facturation (optionnel)">
                                                <input
                                                    type="email"
                                                    maxLength={254}
                                                    className={inputClassName}
                                                    value={billingEmail}
                                                    onChange={(event) => setBillingEmail(event.target.value)}
                                                />
                                            </FormField>
                                        </div>

                                        <div>
                                            <h4 className="text-sm font-semibold text-atlas-ink">Adresse de facturation</h4>
                                            <div className="mt-3 grid gap-4 sm:grid-cols-2">
                                                <div className="sm:col-span-2">
                                                    <FormField label="Adresse (optionnel)">
                                                        <input
                                                            maxLength={160}
                                                            className={inputClassName}
                                                            value={billingAddressLine1}
                                                            onChange={(event) => setBillingAddressLine1(event.target.value)}
                                                        />
                                                    </FormField>
                                                </div>
                                                <div className="sm:col-span-2">
                                                    <FormField label="Complément d’adresse (optionnel)">
                                                        <input
                                                            maxLength={160}
                                                            className={inputClassName}
                                                            value={billingAddressLine2}
                                                            onChange={(event) => setBillingAddressLine2(event.target.value)}
                                                        />
                                                    </FormField>
                                                </div>
                                                <FormField label="Code postal (optionnel)">
                                                    <input
                                                        maxLength={32}
                                                        className={inputClassName}
                                                        value={billingPostalCode}
                                                        onChange={(event) => setBillingPostalCode(event.target.value)}
                                                    />
                                                </FormField>
                                                <FormField label="Ville (optionnel)">
                                                    <input
                                                        maxLength={100}
                                                        className={inputClassName}
                                                        value={billingCity}
                                                        onChange={(event) => setBillingCity(event.target.value)}
                                                    />
                                                </FormField>
                                                <FormField label="Code pays (optionnel)" hint="Deux lettres, par exemple FR.">
                                                    <input
                                                        minLength={2}
                                                        maxLength={2}
                                                        className={inputClassName}
                                                        value={billingCountryCode}
                                                        onChange={(event) => setBillingCountryCode(event.target.value.toUpperCase())}
                                                    />
                                                </FormField>
                                            </div>
                                        </div>

                                        <div>
                                            <div className="flex flex-wrap items-center justify-between gap-3">
                                                <h4 className="text-sm font-semibold text-atlas-ink">Identifiants d’entreprise</h4>
                                                <button
                                                    type="button"
                                                    onClick={() => setRegistrationIdentifiers([...registrationIdentifiers, { type: '', value: '' }])}
                                                    className="text-xs font-semibold text-atlas-accent hover:underline"
                                                >
                                                    Ajouter un identifiant
                                                </button>
                                            </div>
                                            {registrationIdentifiers.length === 0 && (
                                                <p className="mt-2 text-xs text-atlas-ink-muted">Par exemple SIRET, RCS ou CompanyNumber.</p>
                                            )}
                                            <div className="mt-3 space-y-3">
                                                {registrationIdentifiers.map((identifier, index) => (
                                                    <div key={`registration-${index}`} className="grid gap-3 rounded-xl bg-atlas-surface p-4 sm:grid-cols-[1fr_2fr_auto]">
                                                        <FormField label={`Type ${index + 1}`}>
                                                            <input
                                                                required
                                                                maxLength={32}
                                                                className={inputClassName}
                                                                value={identifier.type}
                                                                onChange={(event) => updateIdentifier(registrationIdentifiers, setRegistrationIdentifiers, index, 'type', event.target.value)}
                                                                placeholder="SIRET"
                                                            />
                                                        </FormField>
                                                        <FormField label={`Valeur ${index + 1}`}>
                                                            <input
                                                                required
                                                                maxLength={160}
                                                                className={inputClassName}
                                                                value={identifier.value}
                                                                onChange={(event) => updateIdentifier(registrationIdentifiers, setRegistrationIdentifiers, index, 'value', event.target.value)}
                                                            />
                                                        </FormField>
                                                        <button
                                                            type="button"
                                                            aria-label={`Retirer l’identifiant d’entreprise ${index + 1}`}
                                                            onClick={() => setRegistrationIdentifiers(registrationIdentifiers.filter((_, currentIndex) => currentIndex !== index))}
                                                            className="self-end rounded-xl border border-atlas-border px-3 py-3 text-xs font-semibold text-red-700"
                                                        >
                                                            Retirer
                                                        </button>
                                                    </div>
                                                ))}
                                            </div>
                                        </div>

                                        <div>
                                            <div className="flex flex-wrap items-center justify-between gap-3">
                                                <h4 className="text-sm font-semibold text-atlas-ink">Identifiants fiscaux</h4>
                                                <button
                                                    type="button"
                                                    onClick={() => setTaxIdentifiers([...taxIdentifiers, { type: '', value: '' }])}
                                                    className="text-xs font-semibold text-atlas-accent hover:underline"
                                                >
                                                    Ajouter un identifiant fiscal
                                                </button>
                                            </div>
                                            {taxIdentifiers.length === 0 && (
                                                <p className="mt-2 text-xs text-atlas-ink-muted">Par exemple VAT ou TVA.</p>
                                            )}
                                            <div className="mt-3 space-y-3">
                                                {taxIdentifiers.map((identifier, index) => (
                                                    <div key={`tax-${index}`} className="grid gap-3 rounded-xl bg-atlas-surface p-4 sm:grid-cols-[1fr_2fr_auto]">
                                                        <FormField label={`Type fiscal ${index + 1}`}>
                                                            <input
                                                                required
                                                                maxLength={32}
                                                                className={inputClassName}
                                                                value={identifier.type}
                                                                onChange={(event) => updateIdentifier(taxIdentifiers, setTaxIdentifiers, index, 'type', event.target.value)}
                                                                placeholder="VAT"
                                                            />
                                                        </FormField>
                                                        <FormField label={`Valeur fiscale ${index + 1}`}>
                                                            <input
                                                                required
                                                                maxLength={160}
                                                                className={inputClassName}
                                                                value={identifier.value}
                                                                onChange={(event) => updateIdentifier(taxIdentifiers, setTaxIdentifiers, index, 'value', event.target.value)}
                                                            />
                                                        </FormField>
                                                        <button
                                                            type="button"
                                                            aria-label={`Retirer l’identifiant fiscal ${index + 1}`}
                                                            onClick={() => setTaxIdentifiers(taxIdentifiers.filter((_, currentIndex) => currentIndex !== index))}
                                                            className="self-end rounded-xl border border-atlas-border px-3 py-3 text-xs font-semibold text-red-700"
                                                        >
                                                            Retirer
                                                        </button>
                                                    </div>
                                                ))}
                                            </div>
                                        </div>

                                        <div className="flex flex-col gap-3 sm:flex-row">
                                            <SubmitButton loading={updatingBillingProfile} loadingLabel="Enregistrement…">
                                                Enregistrer la facturation
                                            </SubmitButton>
                                            <button
                                                type="button"
                                                onClick={() => setEditingBillingProfile(false)}
                                                className="rounded-xl border border-atlas-border px-4 py-3 text-sm font-medium text-atlas-ink-muted"
                                            >
                                                Annuler
                                            </button>
                                        </div>
                                    </fieldset>
                                </form>
                            )}
                        </section>

                        <section className="mt-10" aria-labelledby="activities-heading">
                            <div className="mb-4 flex flex-wrap items-center justify-between gap-4">
                                <div>
                                    <h3 id="activities-heading" className="text-lg font-semibold text-atlas-ink">
                                        Chronologie commerciale
                                    </h3>
                                    <p className="mt-1 text-sm text-atlas-ink-muted">
                                        Les interactions passées utiles au suivi de ce client.
                                    </p>
                                </div>
                                {client.status === 'Active' && !showActivityForm && (
                                    <button
                                        type="button"
                                        onClick={openActivityForm}
                                        className="rounded-xl bg-atlas-accent px-4 py-2 text-sm font-semibold text-white hover:opacity-90"
                                    >
                                        Ajouter une activité
                                    </button>
                                )}
                            </div>

                            {client.status === 'Active' && showActivityForm && (
                                <form
                                    aria-label="Ajouter une activité commerciale"
                                    onSubmit={onRecordActivity}
                                    className="mb-6 rounded-2xl border border-atlas-border bg-atlas-card p-6 shadow-sm"
                                >
                                    <fieldset disabled={recordingActivity} className="space-y-4">
                                        <div className="grid gap-4 sm:grid-cols-2">
                                            <FormField label="Type d’activité">
                                                <select
                                                    className={inputClassName}
                                                    value={activityKind}
                                                    onChange={(event) => setActivityKind(event.target.value as ActivityKind)}
                                                >
                                                    {(Object.entries(activityKindLabels) as [ActivityKind, string][]).map(([value, label]) => (
                                                        <option key={value} value={value}>{label}</option>
                                                    ))}
                                                </select>
                                            </FormField>
                                            <FormField label="Date et heure">
                                                <input
                                                    required
                                                    type="datetime-local"
                                                    max={localDateTimeValue()}
                                                    className={inputClassName}
                                                    value={activityOccurredAt}
                                                    onChange={(event) => setActivityOccurredAt(event.target.value)}
                                                />
                                            </FormField>
                                            <FormField label="Contact concerné (optionnel)">
                                                <select
                                                    className={inputClassName}
                                                    value={activityContactId}
                                                    onChange={(event) => setActivityContactId(event.target.value)}
                                                >
                                                    <option value="">Aucun contact</option>
                                                    {sortedContacts.map((contact) => (
                                                        <option key={contact.contact_id} value={contact.contact_id}>
                                                            {contactProfileValue(contact, 'display_name') ?? 'Contact sans nom'}
                                                            {contact.status === 'Archived' ? ' — archivé' : ''}
                                                        </option>
                                                    ))}
                                                </select>
                                            </FormField>
                                            <FormField label="Opportunité concernée (optionnel)">
                                                <select
                                                    className={inputClassName}
                                                    value={activityOpportunityId}
                                                    onChange={(event) => setActivityOpportunityId(event.target.value)}
                                                >
                                                    <option value="">Aucune opportunité</option>
                                                    {sortedOpportunities.map((opportunity) => (
                                                        <option key={opportunity.opportunity_id} value={opportunity.opportunity_id}>
                                                            {opportunity.title}
                                                        </option>
                                                    ))}
                                                </select>
                                            </FormField>
                                        </div>
                                        <FormField
                                            label="Résumé"
                                            hint="Décrivez le fait passé et le résultat utile, sans planifier une action future."
                                        >
                                            <textarea
                                                required
                                                minLength={2}
                                                maxLength={2000}
                                                rows={4}
                                                className={inputClassName}
                                                value={activitySummary}
                                                onChange={(event) => setActivitySummary(event.target.value)}
                                                placeholder="Décision prise, besoin exprimé ou prochain contexte à connaître…"
                                            />
                                        </FormField>
                                        <div className="flex flex-col gap-3 sm:flex-row">
                                            <SubmitButton loading={recordingActivity} loadingLabel="Ajout à la chronologie…">
                                                Enregistrer l’activité
                                            </SubmitButton>
                                            <button
                                                type="button"
                                                onClick={() => setShowActivityForm(false)}
                                                className="rounded-xl border border-atlas-border px-4 py-3 text-sm font-medium text-atlas-ink-muted"
                                            >
                                                Annuler
                                            </button>
                                        </div>
                                    </fieldset>
                                </form>
                            )}

                            {activities.length === 0 && !showActivityForm && (
                                <EmptyState
                                    title="Aucune activité enregistrée"
                                    description="Ajoutez une note, un appel, une réunion ou un e-mail passé pour garder le contexte commercial."
                                    action={client.status === 'Active' ? (
                                        <button
                                            type="button"
                                            onClick={openActivityForm}
                                            className="rounded-xl bg-atlas-accent px-4 py-2 text-sm font-semibold text-white hover:opacity-90"
                                        >
                                            Ajouter la première activité
                                        </button>
                                    ) : undefined}
                                />
                            )}

                            {activities.length > 0 && (
                                <ol className="rounded-2xl border border-atlas-border bg-atlas-card px-5 py-2 shadow-sm sm:px-6">
                                    {activities.map((activity) => {
                                        const contact = contacts.find((candidate) => candidate.contact_id === activity.contact_id);
                                        const opportunity = opportunities.find((candidate) => candidate.opportunity_id === activity.opportunity_id);

                                        return (
                                            <li key={activity.activity_id} className="relative border-l-2 border-atlas-border py-5 pl-6 last:pb-6">
                                                <span className="absolute -left-[7px] top-7 h-3 w-3 rounded-full bg-atlas-accent" aria-hidden="true" />
                                                <div className="flex flex-wrap items-start justify-between gap-3">
                                                    <div className="flex flex-wrap items-center gap-x-3 gap-y-1">
                                                        <span className="rounded-full bg-atlas-surface px-2.5 py-1 text-xs font-semibold text-atlas-accent">
                                                            {activityKindLabels[activity.kind]}
                                                        </span>
                                                        <time dateTime={activity.occurred_at} className="text-xs text-atlas-ink-muted">
                                                            {formatActivityDate(activity.occurred_at)}
                                                        </time>
                                                        {activity.version > 1 && (
                                                            <span className="text-xs font-medium text-atlas-ink-muted">
                                                                Corrigée · révision {activity.version}
                                                            </span>
                                                        )}
                                                    </div>
                                                    {client.status === 'Active'
                                                        && editingActivityId === null
                                                        && removingActivityId === null && (
                                                        <div className="flex items-center gap-3">
                                                            <button
                                                                type="button"
                                                                onClick={() => openActivityCorrection(activity)}
                                                                className="text-xs font-semibold text-atlas-accent hover:underline"
                                                            >
                                                                Corriger
                                                            </button>
                                                            <button
                                                                type="button"
                                                                onClick={() => openActivityRemoval(activity)}
                                                                className="text-xs font-semibold text-red-700 hover:underline"
                                                            >
                                                                Retirer
                                                            </button>
                                                        </div>
                                                    )}
                                                </div>
                                                {editingActivityId !== activity.activity_id && (
                                                    <>
                                                        <p className="mt-3 whitespace-pre-wrap text-sm leading-relaxed text-atlas-ink">
                                                            {activity.summary}
                                                        </p>
                                                        {(contact || opportunity) && (
                                                            <p className="mt-2 text-xs text-atlas-ink-muted">
                                                                {contact && `Avec ${contactProfileValue(contact, 'display_name') ?? 'un contact'}`}
                                                                {contact && opportunity && ' · '}
                                                                {opportunity && `Opportunité : ${opportunity.title}`}
                                                            </p>
                                                        )}
                                                    </>
                                                )}
                                                {editingActivityId === activity.activity_id && (
                                                    <form
                                                        aria-label="Corriger une activité commerciale"
                                                        onSubmit={(event) => void onCorrectActivity(event, activity)}
                                                        className="mt-4 space-y-4 rounded-xl bg-atlas-surface p-4"
                                                    >
                                                        <fieldset disabled={correctingActivity} className="space-y-4">
                                                            <div className="grid gap-4 sm:grid-cols-2">
                                                                <FormField label="Type corrigé">
                                                                    <select
                                                                        className={inputClassName}
                                                                        value={editActivityKind}
                                                                        onChange={(event) => setEditActivityKind(event.target.value as ActivityKind)}
                                                                    >
                                                                        {(Object.entries(activityKindLabels) as [ActivityKind, string][]).map(([value, label]) => (
                                                                            <option key={value} value={value}>{label}</option>
                                                                        ))}
                                                                    </select>
                                                                </FormField>
                                                                <FormField label="Date et heure corrigées">
                                                                    <input
                                                                        required
                                                                        type="datetime-local"
                                                                        max={localDateTimeValue()}
                                                                        className={inputClassName}
                                                                        value={editActivityOccurredAt}
                                                                        onChange={(event) => setEditActivityOccurredAt(event.target.value)}
                                                                    />
                                                                </FormField>
                                                            </div>
                                                            <FormField label="Résumé corrigé">
                                                                <textarea
                                                                    required
                                                                    minLength={2}
                                                                    maxLength={2000}
                                                                    rows={4}
                                                                    className={inputClassName}
                                                                    value={editActivitySummary}
                                                                    onChange={(event) => setEditActivitySummary(event.target.value)}
                                                                />
                                                            </FormField>
                                                            <FormField
                                                                label="Motif de la correction"
                                                                hint="Ce motif est conservé dans l’audit interne."
                                                            >
                                                                <textarea
                                                                    required
                                                                    minLength={2}
                                                                    maxLength={500}
                                                                    rows={2}
                                                                    className={inputClassName}
                                                                    value={activityCorrectionReason}
                                                                    onChange={(event) => setActivityCorrectionReason(event.target.value)}
                                                                />
                                                            </FormField>
                                                            <div className="flex flex-col gap-3 sm:flex-row">
                                                                <SubmitButton loading={correctingActivity} loadingLabel="Correction…">
                                                                    Enregistrer la correction
                                                                </SubmitButton>
                                                                <button
                                                                    type="button"
                                                                    onClick={() => setEditingActivityId(null)}
                                                                    className="rounded-xl border border-atlas-border bg-white px-4 py-3 text-sm font-medium text-atlas-ink-muted"
                                                                >
                                                                    Annuler
                                                                </button>
                                                            </div>
                                                        </fieldset>
                                                    </form>
                                                )}
                                                {removingActivityId === activity.activity_id && (
                                                    <form
                                                        aria-label="Retirer une activité commerciale"
                                                        onSubmit={(event) => void onRemoveActivity(event, activity)}
                                                        className="mt-4 space-y-4 rounded-xl border border-red-200 bg-red-50 p-4"
                                                    >
                                                        <fieldset disabled={removingActivity} className="space-y-4">
                                                            <div>
                                                                <p className="text-sm font-semibold text-red-900">
                                                                    Retirer cette activité ?
                                                                </p>
                                                                <p className="mt-1 text-xs text-red-800">
                                                                    Elle disparaîtra de la chronologie. Le retrait est définitif, mais son contenu et ses corrections resteront dans l’audit interne.
                                                                </p>
                                                            </div>
                                                            <FormField
                                                                label="Motif du retrait"
                                                                hint="Ce motif est obligatoire et conservé dans l’audit interne."
                                                            >
                                                                <textarea
                                                                    required
                                                                    minLength={2}
                                                                    maxLength={500}
                                                                    rows={3}
                                                                    className={inputClassName}
                                                                    value={activityRemovalReason}
                                                                    onChange={(event) => setActivityRemovalReason(event.target.value)}
                                                                />
                                                            </FormField>
                                                            <div className="flex flex-col gap-2 sm:flex-row">
                                                                <button
                                                                    type="submit"
                                                                    className="rounded-xl bg-red-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-red-800 disabled:cursor-not-allowed disabled:opacity-60"
                                                                >
                                                                    {removingActivity ? 'Retrait…' : 'Confirmer le retrait'}
                                                                </button>
                                                                <button
                                                                    type="button"
                                                                    onClick={() => {
                                                                        setRemovingActivityId(null);
                                                                        setActivityRemovalReason('');
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
                                </ol>
                            )}
                        </section>

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
