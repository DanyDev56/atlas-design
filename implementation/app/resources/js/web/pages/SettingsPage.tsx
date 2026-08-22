import { FormEvent, useEffect, useState } from 'react';
import {
    createWorkspaceInvitation,
    getWorkspaceBillingIdentity,
    getWorkspacePreferences,
    getWorkspaceProfile,
    listWorkspaceMembers,
    listWorkspaceInvitations,
    revokeWorkspaceInvitation,
    updateWorkspaceBillingIdentity,
    updateWorkspacePreferences,
    updateWorkspaceProfile,
} from '@/api/workspace';
import { ErrorBanner, FormField, SuccessBanner, inputClassName } from '@/components/auth/AuthLayout';
import { StepUpPasswordDialog, useImportStepUp } from '@/components/auth/StepUpPasswordDialog';
import { RequireAuth } from '@/components/layout/RequireAuth';
import { PageSkeleton } from '@/components/ui/PageSkeleton';
import { useAuth } from '@/hooks/useAuth';
import type {
    WorkspaceBillingIdentityResponse,
    WorkspaceInvitation,
    WorkspaceMember,
    WorkspacePreferencesResponse,
    WorkspaceProfileResponse,
} from '@/types/api';

export function SettingsPage() {
    const { session } = useAuth();
    const token = session!.token;
    const workspaceId = session!.workspaceId!;
    const stepUp = useImportStepUp(token);

    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [profile, setProfile] = useState<WorkspaceProfileResponse | null>(null);
    const [billing, setBilling] = useState<WorkspaceBillingIdentityResponse | null>(null);
    const [preferences, setPreferences] = useState<WorkspacePreferencesResponse | null>(null);
    const [members, setMembers] = useState<WorkspaceMember[]>([]);
    const [invitations, setInvitations] = useState<WorkspaceInvitation[]>([]);
    const [invitationEmail, setInvitationEmail] = useState('');
    const [invitationSaving, setInvitationSaving] = useState(false);
    const [invitationSuccess, setInvitationSuccess] = useState<string | null>(null);
    const [debugInvitationLink, setDebugInvitationLink] = useState<string | null>(null);
    const [revokingInvitationId, setRevokingInvitationId] = useState<string | null>(null);
    const [displayName, setDisplayName] = useState('');
    const [tradingName, setTradingName] = useState('');
    const [activityDescription, setActivityDescription] = useState('');
    const [legalName, setLegalName] = useState('');
    const [administrativeEmail, setAdministrativeEmail] = useState('');
    const [locale, setLocale] = useState('fr-FR');
    const [timezone, setTimezone] = useState('Europe/Paris');
    const [currency, setCurrency] = useState('EUR');
    const [country, setCountry] = useState('FR');
    const [profileSaving, setProfileSaving] = useState(false);
    const [billingSaving, setBillingSaving] = useState(false);
    const [preferencesSaving, setPreferencesSaving] = useState(false);
    const [profileSuccess, setProfileSuccess] = useState<string | null>(null);
    const [billingSuccess, setBillingSuccess] = useState<string | null>(null);
    const [preferencesSuccess, setPreferencesSuccess] = useState<string | null>(null);

    useEffect(() => {
        let cancelled = false;

        async function load() {
            setLoading(true);
            setError(null);
            try {
                const [nextProfile, nextBilling, nextPreferences, nextMembers, nextInvitations] = await Promise.all([
                    getWorkspaceProfile(token, workspaceId),
                    getWorkspaceBillingIdentity(token, workspaceId),
                    getWorkspacePreferences(token, workspaceId),
                    listWorkspaceMembers(token, workspaceId),
                    listWorkspaceInvitations(token, workspaceId),
                ]);
                if (cancelled) return;
                setProfile(nextProfile);
                setBilling(nextBilling);
                setPreferences(nextPreferences);
                setMembers(nextMembers.members);
                setInvitations(nextInvitations.invitations);
                setDisplayName(nextProfile.display_name);
                setTradingName(nextProfile.trading_name ?? '');
                setActivityDescription(nextProfile.activity_description ?? '');
                setLegalName(nextBilling.legal_name ?? '');
                setAdministrativeEmail(nextBilling.administrative_email ?? '');
                setLocale(nextPreferences.locale);
                setTimezone(nextPreferences.timezone);
                setCurrency(nextPreferences.default_currency);
                setCountry(nextPreferences.establishment_country);
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
    }, [token, workspaceId]);

    async function onSaveProfile(event: FormEvent) {
        event.preventDefault();
        if (!profile) return;
        setProfileSaving(true);
        setProfileSuccess(null);
        setError(null);
        try {
            const updated = await updateWorkspaceProfile(token, workspaceId, {
                display_name: displayName,
                trading_name: tradingName || null,
                activity_description: activityDescription || null,
                expected_revision: profile.profile_version,
            });
            setProfile(updated);
            setProfileSuccess('Profil commercial enregistré.');
        } catch (err) {
            setError(err instanceof Error ? err.message : 'Enregistrement impossible');
        } finally {
            setProfileSaving(false);
        }
    }

    async function onSaveBilling(event: FormEvent) {
        event.preventDefault();
        if (!billing) return;
        setBillingSaving(true);
        setBillingSuccess(null);
        setError(null);
        try {
            await stepUp.runWithStepUp(async () => {
                const updated = await updateWorkspaceBillingIdentity(token, workspaceId, {
                    legal_name: legalName || null,
                    administrative_email: administrativeEmail || null,
                    expected_revision: billing.billing_identity_version,
                });
                setBilling(updated);
                setBillingSuccess('Identité de facturation enregistrée.');
            });
        } catch (err) {
            setError(err instanceof Error ? err.message : 'Enregistrement impossible');
        } finally {
            setBillingSaving(false);
        }
    }

    async function onSavePreferences(event: FormEvent) {
        event.preventDefault();
        if (!preferences) return;
        setPreferencesSaving(true);
        setPreferencesSuccess(null);
        setError(null);
        try {
            const updated = await updateWorkspacePreferences(token, workspaceId, {
                locale,
                timezone,
                default_currency: currency,
                establishment_country: country,
                expected_revision: preferences.preferences_version,
            });
            setPreferences(updated);
            setPreferencesSuccess('Préférences enregistrées.');
        } catch (err) {
            setError(err instanceof Error ? err.message : 'Enregistrement impossible');
        } finally {
            setPreferencesSaving(false);
        }
    }

    async function onInviteMember(event: FormEvent) {
        event.preventDefault();
        setInvitationSaving(true);
        setInvitationSuccess(null);
        setDebugInvitationLink(null);
        setError(null);

        try {
            const invitation = await createWorkspaceInvitation(
                token,
                workspaceId,
                invitationEmail,
            );
            setInvitations((current) => [invitation, ...current]);
            setInvitationEmail('');
            setInvitationSuccess('Invitation préparée pour 7 jours.');
            if (invitation.invitation_token) {
                setDebugInvitationLink(
                    `/app/invitations/${invitation.invitation_id}/accept?token=${encodeURIComponent(invitation.invitation_token)}`,
                );
            }
        } catch (err) {
            setError(err instanceof Error ? err.message : 'Invitation impossible');
        } finally {
            setInvitationSaving(false);
        }
    }

    async function onRevokeInvitation(invitationId: string) {
        setRevokingInvitationId(invitationId);
        setError(null);
        try {
            await revokeWorkspaceInvitation(token, workspaceId, invitationId);
            setInvitations((current) => current.map((invitation) => (
                invitation.invitation_id === invitationId
                    ? { ...invitation, status: 'Revoked' }
                    : invitation
            )));
        } catch (err) {
            setError(err instanceof Error ? err.message : 'Révocation impossible');
        } finally {
            setRevokingInvitationId(null);
        }
    }

    return (
        <RequireAuth>
            <div className="mx-auto max-w-3xl">
                <h2 className="text-3xl font-semibold tracking-tight text-atlas-ink">Paramètres</h2>
                <p className="mt-2 text-sm leading-relaxed text-atlas-ink-muted">
                    Profil commercial, identité de facturation, préférences et membres du workspace.
                    Les invitations attribuent le rôle membre standard ; les rôles avancés restent hors de cette surface.
                </p>

                {loading && <div className="mt-8"><PageSkeleton rows={4} /></div>}
                <div className="mt-6"><ErrorBanner message={error} /></div>

                {!loading && profile && billing && preferences && (
                    <div className="mt-8 space-y-8">
                        <form onSubmit={onSaveProfile} className="rounded-2xl border border-atlas-border bg-atlas-card p-6 shadow-sm">
                            <h3 className="text-lg font-semibold text-atlas-ink">Profil de l’activité</h3>
                            <SuccessBanner message={profileSuccess} />
                            <div className="mt-5 space-y-4">
                                <FormField label="Nom d’affichage">
                                    <input required minLength={2} maxLength={120} className={inputClassName} value={displayName} onChange={(event) => setDisplayName(event.target.value)} />
                                </FormField>
                                <FormField label="Nom commercial" hint="Optionnel">
                                    <input maxLength={160} className={inputClassName} value={tradingName} onChange={(event) => setTradingName(event.target.value)} />
                                </FormField>
                                <FormField label="Description" hint="Optionnel">
                                    <textarea maxLength={2000} rows={3} className={inputClassName} value={activityDescription} onChange={(event) => setActivityDescription(event.target.value)} />
                                </FormField>
                            </div>
                            <button type="submit" disabled={profileSaving} className="mt-6 rounded-xl bg-atlas-accent px-6 py-3 text-sm font-semibold text-white hover:opacity-90 disabled:opacity-50">
                                {profileSaving ? 'Enregistrement…' : 'Enregistrer le profil'}
                            </button>
                        </form>

                        <form onSubmit={onSaveBilling} className="rounded-2xl border border-atlas-border bg-atlas-card p-6 shadow-sm">
                            <h3 className="text-lg font-semibold text-atlas-ink">Identité de facturation</h3>
                            <p className="mt-1 text-sm text-atlas-ink-muted">
                                Action critique : une preuve récente par mot de passe est exigée. Les documents déjà émis ne changent pas.
                            </p>
                            <SuccessBanner message={billingSuccess} />
                            <div className="mt-5 space-y-4">
                                <FormField label="Raison sociale" hint="Optionnel">
                                    <input maxLength={160} className={inputClassName} value={legalName} onChange={(event) => setLegalName(event.target.value)} />
                                </FormField>
                                <FormField label="Email administratif" hint="Optionnel">
                                    <input type="email" className={inputClassName} value={administrativeEmail} onChange={(event) => setAdministrativeEmail(event.target.value)} />
                                </FormField>
                            </div>
                            <button type="submit" disabled={billingSaving} className="mt-6 rounded-xl bg-atlas-accent px-6 py-3 text-sm font-semibold text-white hover:opacity-90 disabled:opacity-50">
                                {billingSaving ? 'Enregistrement…' : 'Enregistrer l’identité'}
                            </button>
                        </form>

                        <form onSubmit={onSavePreferences} className="rounded-2xl border border-atlas-border bg-atlas-card p-6 shadow-sm">
                            <h3 className="text-lg font-semibold text-atlas-ink">Préférences</h3>
                            <SuccessBanner message={preferencesSuccess} />
                            <div className="mt-5 grid gap-4 sm:grid-cols-2">
                                <FormField label="Locale">
                                    <select className={inputClassName} value={locale} onChange={(event) => setLocale(event.target.value)}>
                                        <option value="fr-FR">fr-FR</option>
                                        <option value="en-GB">en-GB</option>
                                    </select>
                                </FormField>
                                <FormField label="Fuseau">
                                    <select className={inputClassName} value={timezone} onChange={(event) => setTimezone(event.target.value)}>
                                        <option value="Europe/Paris">Europe/Paris</option>
                                        <option value="UTC">UTC</option>
                                    </select>
                                </FormField>
                                <FormField label="Devise">
                                    <select className={inputClassName} value={currency} onChange={(event) => setCurrency(event.target.value)}>
                                        <option value="EUR">EUR</option>
                                    </select>
                                </FormField>
                                <FormField label="Pays d’établissement">
                                    <select className={inputClassName} value={country} onChange={(event) => setCountry(event.target.value)}>
                                        <option value="FR">FR</option>
                                        <option value="BE">BE</option>
                                        <option value="CH">CH</option>
                                    </select>
                                </FormField>
                            </div>
                            <button type="submit" disabled={preferencesSaving} className="mt-6 rounded-xl bg-atlas-accent px-6 py-3 text-sm font-semibold text-white hover:opacity-90 disabled:opacity-50">
                                {preferencesSaving ? 'Enregistrement…' : 'Enregistrer les préférences'}
                            </button>
                        </form>

                        <section className="rounded-2xl border border-atlas-border bg-atlas-card p-6 shadow-sm">
                            <h3 className="text-lg font-semibold text-atlas-ink">Membres</h3>
                            <form onSubmit={onInviteMember} className="mt-5 rounded-xl border border-atlas-border bg-atlas-surface p-4">
                                <h4 className="text-sm font-semibold text-atlas-ink">Inviter un membre</h4>
                                <p className="mt-1 text-xs text-atlas-ink-muted">
                                    L’invitation expire après 7 jours. Le destinataire doit utiliser un compte vérifié avec cette adresse.
                                </p>
                                <SuccessBanner message={invitationSuccess} />
                                <div className="mt-3 flex flex-col gap-3 sm:flex-row">
                                    <input
                                        type="email"
                                        required
                                        maxLength={254}
                                        placeholder="membre@entreprise.fr"
                                        className={inputClassName}
                                        value={invitationEmail}
                                        onChange={(event) => setInvitationEmail(event.target.value)}
                                    />
                                    <button
                                        type="submit"
                                        disabled={invitationSaving}
                                        className="shrink-0 rounded-xl bg-atlas-accent px-5 py-3 text-sm font-semibold text-white hover:opacity-90 disabled:opacity-50"
                                    >
                                        {invitationSaving ? 'Invitation…' : 'Inviter'}
                                    </button>
                                </div>
                                {debugInvitationLink && (
                                    <a
                                        href={debugInvitationLink}
                                        className="mt-3 inline-flex text-sm font-semibold text-atlas-accent hover:underline"
                                    >
                                        Ouvrir le lien d’acceptation (développement)
                                    </a>
                                )}
                            </form>

                            {invitations.length > 0 && (
                                <div className="mt-6">
                                    <h4 className="text-sm font-semibold text-atlas-ink">Invitations</h4>
                                    <ul className="mt-2 divide-y divide-atlas-border">
                                        {invitations.map((invitation) => (
                                            <li key={invitation.invitation_id} className="flex flex-wrap items-baseline justify-between gap-2 py-3">
                                                <div>
                                                    <p className="text-sm font-medium text-atlas-ink">{invitation.recipient_email}</p>
                                                    <p className="text-xs text-atlas-ink-muted">
                                                        Expire le {new Intl.DateTimeFormat('fr-FR', { dateStyle: 'medium' }).format(new Date(invitation.expires_at))}
                                                    </p>
                                                </div>
                                                <div className="flex items-center gap-3">
                                                    <p className="text-xs font-semibold uppercase tracking-wide text-atlas-ink-muted">
                                                        {invitation.role} · {invitation.status}
                                                    </p>
                                                    {invitation.status === 'Pending' && (
                                                        <button
                                                            type="button"
                                                            disabled={revokingInvitationId === invitation.invitation_id}
                                                            onClick={() => void onRevokeInvitation(invitation.invitation_id)}
                                                            className="text-xs font-semibold text-red-700 hover:underline disabled:opacity-50"
                                                        >
                                                            {revokingInvitationId === invitation.invitation_id ? 'Révocation…' : 'Révoquer'}
                                                        </button>
                                                    )}
                                                </div>
                                            </li>
                                        ))}
                                    </ul>
                                </div>
                            )}
                            <ul className="mt-4 divide-y divide-atlas-border">
                                {members.map((member) => (
                                    <li key={member.membership_id} className="flex flex-wrap items-baseline justify-between gap-2 py-3">
                                        <div>
                                            <p className="text-sm font-medium text-atlas-ink">{member.display_name}</p>
                                            <p className="text-xs text-atlas-ink-muted">{member.email}</p>
                                        </div>
                                        <p className="text-xs font-semibold uppercase tracking-wide text-atlas-ink-muted">
                                            {member.role} · {member.status}
                                        </p>
                                    </li>
                                ))}
                            </ul>
                        </section>
                    </div>
                )}

                <StepUpPasswordDialog
                    open={stepUp.promptOpen}
                    password={stepUp.password}
                    error={stepUp.promptError}
                    submitting={stepUp.submitting}
                    onPasswordChange={stepUp.setPassword}
                    onSubmit={(event) => void stepUp.submitPassword(event)}
                    onCancel={stepUp.closePrompt}
                    description="L’identité de facturation est une action critique. Saisissez à nouveau votre mot de passe pour continuer. Aucune nouvelle session n’est créée."
                    submitLabel="Confirmer et enregistrer"
                />
            </div>
        </RequireAuth>
    );
}
