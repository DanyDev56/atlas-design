import { useCallback, useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { ApiClientError } from '@/api/client';
import { approveOperatorDataExport, downloadOperatorDataExport, fetchOperatorCompliance, fetchOperatorDataRequests, fetchOperatorSupport, previewOperatorDataExportApproval, previewOperatorDataExportRequest, previewOperatorSupportCase, requestOperatorDataExport, updateOperatorSupportCase, type OperatorDataExportPreview, type OperatorDataRequestItem, type OperatorPage, type OperatorPolicyPage, type OperatorSupportCaseItem, type OperatorSupportCasePreview, type OperatorSupportCaseProposal } from '@/api/operator';
import { OperatorFrame } from '@/components/operator/OperatorFrame';
import { useOperatorAuth } from '@/hooks/useOperatorAuth';

function formatDate(value: string | null): string {
    return value ? new Intl.DateTimeFormat('fr-FR', { dateStyle: 'short', timeStyle: 'short' }).format(new Date(value)) : '—';
}

function isOverdue(value: string): boolean {
    return new Date(value).getTime() < Date.now();
}

function nextStatuses(status: OperatorSupportCaseItem['status']): OperatorSupportCaseProposal['status'][] {
    const transitions: Record<OperatorSupportCaseItem['status'], OperatorSupportCaseProposal['status'][]> = {
        Open: ['Acknowledged', 'InProgress'],
        Acknowledged: ['InProgress', 'WaitingRequester', 'Resolved'],
        InProgress: ['WaitingRequester', 'Resolved'],
        WaitingRequester: ['InProgress', 'Resolved'],
        Resolved: ['InProgress', 'Closed'],
        Closed: [],
    };
    return transitions[status];
}

function Pagination({ page, totalPages, onChange }: { page: number; totalPages: number; onChange: (page: number) => void }) {
    if (totalPages <= 1) return null;

    return <nav aria-label="Pagination" className="flex items-center justify-between border-t border-atlas-border px-5 py-4 text-sm">
        <button type="button" disabled={page <= 1} onClick={() => onChange(page - 1)} className="font-semibold text-atlas-accent disabled:cursor-not-allowed disabled:opacity-40">← Précédent</button>
        <span className="text-atlas-ink-muted">Page {page} sur {totalPages}</span>
        <button type="button" disabled={page >= totalPages} onClick={() => onChange(page + 1)} className="font-semibold text-atlas-accent disabled:cursor-not-allowed disabled:opacity-40">Suivant →</button>
    </nav>;
}

export function OperatorSupportCompliancePage() {
    const { session } = useOperatorAuth();
    const canReadSupport = session?.permissions.includes('operations.support.read') ?? false;
    const canReadCompliance = session?.permissions.includes('operations.compliance.read') ?? false;
    const canManageSupport = session?.permissions.includes('operations.support.manage') ?? false;
    const canRequestExport = session?.permissions.includes('operations.exports.request') ?? false;
    const canApproveExport = session?.permissions.includes('operations.exports.approve') ?? false;
    const canDownloadExport = session?.permissions.includes('operations.exports.download') ?? false;
    const stepUpActive = session?.stepUpExpiresAt ? Date.parse(session.stepUpExpiresAt) > Date.now() : false;
    const [support, setSupport] = useState<OperatorPage<OperatorSupportCaseItem> | null>(null);
    const [dataRequests, setDataRequests] = useState<OperatorPage<OperatorDataRequestItem> | null>(null);
    const [policies, setPolicies] = useState<OperatorPolicyPage | null>(null);
    const [supportStatus, setSupportStatus] = useState('All');
    const [severity, setSeverity] = useState('All');
    const [dataStatus, setDataStatus] = useState('All');
    const [dataType, setDataType] = useState('All');
    const [supportPage, setSupportPage] = useState(1);
    const [dataPage, setDataPage] = useState(1);
    const [policyPage, setPolicyPage] = useState(1);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [managedCase, setManagedCase] = useState<OperatorSupportCaseItem | null>(null);
    const [proposal, setProposal] = useState<OperatorSupportCaseProposal | null>(null);
    const [preview, setPreview] = useState<OperatorSupportCasePreview | null>(null);
    const [actionIdempotencyKey, setActionIdempotencyKey] = useState<string | null>(null);
    const [actionLoading, setActionLoading] = useState(false);
    const [actionError, setActionError] = useState<string | null>(null);
    const [actionSuccess, setActionSuccess] = useState<string | null>(null);
    const [exportTarget, setExportTarget] = useState<OperatorDataRequestItem | null>(null);
    const [exportMode, setExportMode] = useState<'request' | 'approve' | null>(null);
    const [exportPreview, setExportPreview] = useState<OperatorDataExportPreview | null>(null);
    const [exportIdempotencyKey, setExportIdempotencyKey] = useState<string | null>(null);
    const [exportBusy, setExportBusy] = useState(false);
    const [exportError, setExportError] = useState<string | null>(null);

    const load = useCallback(async () => {
        if (!session?.token) return;
        setLoading(true);
        setError(null);
        try {
            const [nextSupport, nextDataRequests, nextPolicies] = await Promise.all([
                canReadSupport ? fetchOperatorSupport(session.token, supportStatus, severity, supportPage) : Promise.resolve(null),
                canReadCompliance ? fetchOperatorDataRequests(session.token, dataStatus, dataType, dataPage) : Promise.resolve(null),
                canReadCompliance ? fetchOperatorCompliance(session.token, policyPage) : Promise.resolve(null),
            ]);
            setSupport(nextSupport);
            setDataRequests(nextDataRequests);
            setPolicies(nextPolicies);
        } catch (caught) {
            setError(caught instanceof ApiClientError ? caught.message : 'Les registres Support et Conformité n’ont pas pu être chargés.');
        } finally {
            setLoading(false);
        }
    }, [canReadCompliance, canReadSupport, dataPage, dataStatus, dataType, policyPage, session?.token, severity, supportPage, supportStatus]);

    useEffect(() => {
        const previousTitle = document.title;
        document.title = 'Support et conformité — Atlas';
        void load();
        return () => { document.title = previousTitle; };
    }, [load]);

    function openManagement(item: OperatorSupportCaseItem) {
        const status = nextStatuses(item.status)[0] ?? 'Keep';
        setManagedCase(item);
        setProposal({ expected_revision: item.revision, status, assignment: 'Keep', reason_code: 'case.triage' });
        setPreview(null);
        setActionIdempotencyKey(null);
        setActionError(null);
        setActionSuccess(null);
    }

    async function requestPreview() {
        if (!session?.token || !managedCase || !proposal) return;
        setActionLoading(true);
        setActionError(null);
        try {
            setPreview(await previewOperatorSupportCase(session.token, managedCase.reference, proposal));
            setActionIdempotencyKey(crypto.randomUUID());
        } catch (caught) {
            setActionError(caught instanceof ApiClientError ? caught.message : 'La prévisualisation a échoué.');
        } finally {
            setActionLoading(false);
        }
    }

    async function confirmAction() {
        if (!session?.token || !managedCase || !proposal || !preview || !actionIdempotencyKey) return;
        setActionLoading(true);
        setActionError(null);
        try {
            const result = await updateOperatorSupportCase(session.token, managedCase.reference, proposal, preview.preview_fingerprint, actionIdempotencyKey);
            setActionSuccess(`Le dossier ${result.reference} est maintenant ${result.status}.`);
            setManagedCase(null);
            setProposal(null);
            setPreview(null);
            setActionIdempotencyKey(null);
            await load();
        } catch (caught) {
            if (caught instanceof ApiClientError) {
                setPreview(null);
                setActionIdempotencyKey(null);
                setActionError(`${caught.message} Prévisualisez à nouveau.`);
            } else {
                setActionError('La confirmation n’a pas pu être vérifiée. Réessayez : la même clé d’idempotence sera utilisée.');
            }
        } finally {
            setActionLoading(false);
        }
    }

    async function prepareExport(item: OperatorDataRequestItem, mode: 'request' | 'approve') {
        if (!session?.token) return;
        setExportTarget(item);
        setExportMode(mode);
        setExportPreview(null);
        setExportError(null);
        setExportIdempotencyKey(null);
        setExportBusy(true);
        const reason = mode === 'request' ? 'export.subject-request-qualified' : 'export.scope-and-owner-reviewed';
        try {
            const next = mode === 'request'
                ? await previewOperatorDataExportRequest(session.token, item.reference, reason)
                : await previewOperatorDataExportApproval(session.token, item.export!.reference, reason);
            setExportPreview(next);
            setExportIdempotencyKey(crypto.randomUUID());
        } catch (caught) {
            setExportError(caught instanceof ApiClientError ? caught.message : 'La préparation de l’export a échoué.');
        } finally {
            setExportBusy(false);
        }
    }

    async function confirmExport() {
        if (!session?.token || !exportTarget || !exportMode || !exportPreview || !exportIdempotencyKey) return;
        setExportBusy(true);
        setExportError(null);
        const reason = exportMode === 'request' ? 'export.subject-request-qualified' : 'export.scope-and-owner-reviewed';
        try {
            const result = exportMode === 'request'
                ? await requestOperatorDataExport(session.token, exportTarget.reference, reason, exportPreview.preview_fingerprint, exportIdempotencyKey)
                : await approveOperatorDataExport(session.token, exportTarget.export!.reference, reason, exportPreview.preview_fingerprint, exportIdempotencyKey);
            setActionSuccess(result.status === 'AwaitingApproval'
                ? `L’export ${result.reference} attend l’approbation d’un autre opérateur.`
                : `L’export ${result.reference} est confié au worker de génération.`);
            setExportTarget(null);
            setExportMode(null);
            setExportPreview(null);
            setExportIdempotencyKey(null);
            await load();
        } catch (caught) {
            setExportError(caught instanceof ApiClientError ? caught.message : 'La confirmation est incertaine. Réessayez avec la même clé.');
        } finally {
            setExportBusy(false);
        }
    }

    async function downloadExport(item: OperatorDataRequestItem) {
        if (!session?.token || !item.export) return;
        setExportBusy(true);
        setExportError(null);
        try {
            const result = await downloadOperatorDataExport(session.token, item.export.reference, 'export.secure-delivery', crypto.randomUUID());
            const url = URL.createObjectURL(result.blob);
            const anchor = document.createElement('a');
            anchor.href = url;
            anchor.download = result.filename;
            anchor.click();
            URL.revokeObjectURL(url);
            setActionSuccess(`L’export ${item.export.reference} a été téléchargé et sa remise a été auditée.`);
            await load();
        } catch (caught) {
            setExportError(caught instanceof ApiClientError ? caught.message : 'Le téléchargement sécurisé a échoué.');
        } finally {
            setExportBusy(false);
        }
    }

    return (
        <OperatorFrame>
            <main className="mx-auto max-w-[90rem] px-5 py-10 sm:px-8 lg:px-10 lg:py-14">
                <Link to="/backoffice" className="text-sm font-semibold text-atlas-accent">← Retour à la vue d’ensemble</Link>
                <div className="mt-8"><p className="atlas-kicker">Accompagnement beta</p><h1 className="mt-3 text-4xl font-semibold tracking-[-0.045em]">Support et conformité</h1><p className="mt-4 max-w-3xl leading-7 text-atlas-ink-muted">Registres pseudonymisés, échéances internes et preuves minimisées. La gestion Support et l’export assisté sont des actions séparées, explicitement autorisées et auditées. La fermeture ou l’effacement d’un Workspace restent indisponibles.</p></div>
                {error && <div role="alert" className="mt-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{error} <button type="button" className="font-semibold underline" onClick={() => void load()}>Réessayer</button></div>}
                {actionSuccess && <div role="status" className="mt-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{actionSuccess}</div>}
                {loading && <p className="mt-8 text-sm font-semibold text-atlas-accent">Actualisation des registres…</p>}

                {canManageSupport && !session?.actionsEnabled && <div className="mt-8 rounded-2xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-900"><p className="font-semibold">Actions Support désactivées</p><p className="mt-1 leading-6">Le grant autorise la gestion, mais cet environnement reste en lecture seule. Activez explicitement les actions uniquement pour une recette encadrée.</p></div>}
                {canManageSupport && session?.actionsEnabled && !stepUpActive && <div className="mt-8 rounded-2xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-900"><p className="font-semibold">Authentification renforcée requise</p><p className="mt-1 leading-6">Renouvelez le step-up avant de préparer une action.</p><Link to="/backoffice/security" className="mt-3 inline-flex font-semibold underline">Ouvrir la sécurité opérateur →</Link></div>}

                {(canRequestExport || canApproveExport || canDownloadExport) && !session?.actionsEnabled && <div className="mt-6 rounded-2xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-900"><p className="font-semibold">Exports assistés désactivés</p><p className="mt-1 leading-6">Les permissions sont présentes, mais les actions du back-office restent verrouillées par la configuration.</p></div>}
                {(canRequestExport || canApproveExport || canDownloadExport) && session?.actionsEnabled && !stepUpActive && <div className="mt-6 rounded-2xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-900"><p className="font-semibold">Step-up requis pour les exports</p><p className="mt-1 leading-6">Renouvelez l’authentification renforcée avant une demande, une approbation ou un téléchargement.</p><Link to="/backoffice/security" className="mt-3 inline-flex font-semibold underline">Ouvrir la sécurité opérateur →</Link></div>}

                {exportTarget && exportMode && <section className="mt-8 rounded-2xl border border-violet-200 bg-violet-50/60 p-5 shadow-sm sm:p-6" aria-labelledby="data-export-action-title"><div className="flex flex-wrap items-start justify-between gap-4"><div><p className="atlas-kicker">Export assisté · double contrôle</p><h2 id="data-export-action-title" className="mt-2 text-xl font-semibold">{exportMode === 'request' ? `Demander l’export de ${exportTarget.reference}` : `Approuver ${exportTarget.export?.reference}`}</h2><p className="mt-1 text-sm text-atlas-ink-muted">Périmètre WorkspaceDataV1 · aucun envoi automatique au participant.</p></div><button type="button" className="text-sm font-semibold underline" onClick={() => { setExportTarget(null); setExportMode(null); setExportPreview(null); setExportError(null); setExportIdempotencyKey(null); }}>Annuler</button></div>
                    {exportBusy && !exportPreview && <p className="mt-5 text-sm font-semibold text-atlas-accent">Vérification des préconditions…</p>}
                    {exportError && <p role="alert" className="mt-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{exportError}</p>}
                    {exportPreview && <div className="mt-5 rounded-xl border border-atlas-border bg-white p-5"><div className="grid gap-4 sm:grid-cols-2"><div><p className="text-xs font-semibold uppercase tracking-wider text-atlas-ink-muted">Avant</p><p className="mt-2 font-semibold">{exportPreview.current_status}</p></div><div><p className="text-xs font-semibold uppercase tracking-wider text-atlas-ink-muted">Après</p><p className="mt-2 font-semibold">{exportPreview.proposed_status}</p></div></div><ul className="mt-4 space-y-1 text-sm text-atlas-ink-muted">{exportPreview.effects.map((effect) => <li key={effect}>✓ {effect}</li>)}</ul><button type="button" disabled={exportBusy} onClick={() => void confirmExport()} className="mt-5 min-h-11 rounded-xl bg-atlas-sidebar px-5 text-sm font-semibold text-white disabled:opacity-50">{exportBusy ? 'Confirmation…' : exportMode === 'request' ? 'Confirmer la demande' : 'Approuver et générer'}</button></div>}
                </section>}

                {managedCase && proposal && <section className="mt-8 rounded-2xl border border-atlas-accent/30 bg-white p-5 shadow-sm sm:p-6" aria-labelledby="support-action-title"><div className="flex flex-wrap items-start justify-between gap-4"><div><p className="atlas-kicker">Action bornée</p><h2 id="support-action-title" className="mt-2 text-xl font-semibold">Gérer {managedCase.reference}</h2><p className="mt-1 text-sm text-atlas-ink-muted">Révision {managedCase.revision} · aucune communication ou donnée Workspace ne sera modifiée.</p></div><button type="button" onClick={() => { setManagedCase(null); setPreview(null); setActionIdempotencyKey(null); }} className="text-sm font-semibold text-atlas-ink-muted">Annuler</button></div>
                    <div className="mt-6 grid gap-4 md:grid-cols-3"><label className="text-sm font-medium">Nouveau statut<select value={proposal.status} onChange={(event) => { setProposal({ ...proposal, status: event.target.value as OperatorSupportCaseProposal['status'] }); setPreview(null); setActionIdempotencyKey(null); }} className="mt-2 min-h-11 w-full rounded-xl border border-atlas-border bg-white px-3"><option value="Keep">Conserver</option>{nextStatuses(managedCase.status).map((status) => <option key={status} value={status}>{status}</option>)}</select></label><label className="text-sm font-medium">Assignation<select value={proposal.assignment} onChange={(event) => { setProposal({ ...proposal, assignment: event.target.value as OperatorSupportCaseProposal['assignment'] }); setPreview(null); setActionIdempotencyKey(null); }} className="mt-2 min-h-11 w-full rounded-xl border border-atlas-border bg-white px-3"><option value="Keep">Conserver</option><option value="Self">Me l’assigner</option><option value="Unassigned">Désassigner</option></select></label><label className="text-sm font-medium">Motif structuré<select value={proposal.reason_code} onChange={(event) => { setProposal({ ...proposal, reason_code: event.target.value }); setPreview(null); setActionIdempotencyKey(null); }} className="mt-2 min-h-11 w-full rounded-xl border border-atlas-border bg-white px-3"><option value="case.triage">Qualification effectuée</option><option value="investigation.started">Investigation démarrée</option><option value="requester.response-needed">Réponse du demandeur requise</option><option value="case.resolved">Résolution confirmée</option><option value="case.reopened">Dossier rouvert</option><option value="case.closed">Dossier clos</option><option value="assignment.updated">Assignation mise à jour</option></select></label></div>
                    {actionError && <p role="alert" className="mt-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{actionError}</p>}
                    {!preview && <button type="button" disabled={actionLoading} onClick={() => void requestPreview()} className="mt-5 inline-flex min-h-11 items-center justify-center rounded-xl bg-atlas-sidebar px-5 text-sm font-semibold text-white disabled:opacity-50">{actionLoading ? 'Préparation…' : 'Prévisualiser l’action'}</button>}
                    {preview && <div className="mt-6 rounded-xl border border-atlas-border bg-atlas-surface p-5"><div className="grid gap-4 sm:grid-cols-2"><div><p className="text-xs font-semibold uppercase tracking-wider text-atlas-ink-muted">Avant</p><p className="mt-2 font-semibold">{preview.current.status} · {preview.current.assignment}</p></div><div><p className="text-xs font-semibold uppercase tracking-wider text-atlas-ink-muted">Après</p><p className="mt-2 font-semibold">{preview.proposed.status} · {preview.proposed.assignment}</p></div></div><ul className="mt-4 space-y-1 text-sm text-atlas-ink-muted">{preview.effects.map((effect) => <li key={effect}>✓ {effect}</li>)}</ul><div className="mt-5 flex flex-wrap gap-3"><button type="button" disabled={actionLoading} onClick={() => void confirmAction()} className="inline-flex min-h-11 items-center justify-center rounded-xl bg-atlas-sidebar px-5 text-sm font-semibold text-white disabled:opacity-50">{actionLoading ? 'Confirmation…' : 'Confirmer'}</button><button type="button" disabled={actionLoading} onClick={() => { setPreview(null); setActionIdempotencyKey(null); }} className="inline-flex min-h-11 items-center justify-center rounded-xl border border-atlas-border bg-white px-5 text-sm font-semibold">Modifier</button></div></div>}
                </section>}

                {canReadSupport && <section className="mt-10"><div className="flex flex-wrap items-end justify-between gap-4"><div><h2 className="text-xl font-semibold">Dossiers support</h2><p className="mt-1 text-sm text-atlas-ink-muted">Priorité, cible de réponse et vérification, sans nom ni adresse.</p></div><div className="flex w-full flex-col gap-2 sm:w-auto sm:flex-row"><select aria-label="Statut support" value={supportStatus} onChange={(event) => { setSupportStatus(event.target.value); setSupportPage(1); }} className="min-h-10 rounded-xl border border-atlas-border bg-white px-3 text-sm"><option value="All">Tous les statuts</option><option value="Open">Ouverts</option><option value="Acknowledged">Pris en compte</option><option value="InProgress">En cours</option><option value="WaitingRequester">En attente du demandeur</option><option value="Resolved">Résolus</option><option value="Closed">Clos</option></select><select aria-label="Priorité support" value={severity} onChange={(event) => { setSeverity(event.target.value); setSupportPage(1); }} className="min-h-10 rounded-xl border border-atlas-border bg-white px-3 text-sm"><option value="All">Toutes priorités</option>{['P0', 'P1', 'P2', 'P3'].map((value) => <option key={value} value={value}>{value}</option>)}</select></div></div>
                    <div className="mt-5 overflow-hidden rounded-2xl border border-atlas-border bg-white shadow-sm">{!loading && support?.items.length === 0 && <div className="p-10 text-center text-sm text-atlas-ink-muted">Aucun dossier pour ces filtres.</div>}{support && <><div className="divide-y divide-atlas-border">{support.items.map((item) => <article key={item.reference} className="grid gap-3 p-5 lg:grid-cols-[1fr_.6fr_.8fr_1fr_auto] lg:items-center"><div><p className="font-mono text-sm font-semibold">{item.reference}</p><p className="mt-1 text-xs text-atlas-ink-muted">{item.workspace_reference} · {item.requester_reference}</p></div><div><span className={`rounded-full border px-2.5 py-1 text-xs font-semibold ${item.severity === 'P0' || item.severity === 'P1' ? 'border-red-200 bg-red-50 text-red-700' : 'border-atlas-border bg-atlas-surface text-atlas-ink-muted'}`}>{item.severity}</span><p className="mt-2 text-xs text-atlas-ink-muted">{item.category}</p></div><div><p className="text-sm font-semibold">{item.summary_code}</p><p className="mt-1 text-xs text-atlas-ink-muted">{item.status} · {item.event_count} événement(s) · {item.assigned ? 'assigné' : 'non assigné'}</p></div><div><p className={`text-sm ${isOverdue(item.response_due_at) && !['Resolved', 'Closed'].includes(item.status) ? 'font-semibold text-red-700' : 'text-atlas-ink-muted'}`}>Cible : {formatDate(item.response_due_at)}</p><p className="mt-1 text-xs text-atlas-ink-muted">Identité vérifiée · Owner {item.ownership_verified ? 'vérifié' : 'non requis'}</p></div>{canManageSupport && session?.actionsEnabled && stepUpActive && item.status !== 'Closed' && <button type="button" onClick={() => openManagement(item)} className="min-h-10 rounded-xl border border-atlas-border px-3 text-sm font-semibold hover:bg-atlas-surface">Gérer</button>}</article>)}</div><Pagination page={support.page} totalPages={support.total_pages} onChange={setSupportPage} /></>}</div>
                </section>}

                {canReadCompliance && <section className="mt-10"><div className="flex flex-wrap items-end justify-between gap-4"><div><h2 className="text-xl font-semibold">Demandes relatives aux données</h2><p className="mt-1 text-sm text-atlas-ink-muted">Les demandes d’accès et de portabilité vérifiées peuvent suivre le parcours d’export à double contrôle.</p></div><div className="flex w-full flex-col gap-2 sm:w-auto sm:flex-row"><select aria-label="Statut demande" value={dataStatus} onChange={(event) => { setDataStatus(event.target.value); setDataPage(1); }} className="min-h-10 rounded-xl border border-atlas-border bg-white px-3 text-sm"><option value="All">Tous les statuts</option><option value="Received">Reçues</option><option value="IdentityPending">Identité à vérifier</option><option value="Qualified">Qualifiées</option><option value="InPreparation">En préparation</option><option value="AwaitingApproval">À approuver</option><option value="Delivered">Remises</option><option value="Rejected">Rejetées</option><option value="Closed">Closes</option></select><select aria-label="Type de demande" value={dataType} onChange={(event) => { setDataType(event.target.value); setDataPage(1); }} className="min-h-10 rounded-xl border border-atlas-border bg-white px-3 text-sm"><option value="All">Tous les types</option>{['Access', 'Rectification', 'Erasure', 'Restriction', 'Objection', 'Portability'].map((value) => <option key={value} value={value}>{value}</option>)}</select></div></div>
                    {exportError && !exportTarget && <p role="alert" className="mt-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{exportError}</p>}
                    <div className="mt-5 overflow-hidden rounded-2xl border border-atlas-border bg-white shadow-sm">{!loading && dataRequests?.items.length === 0 && <div className="p-10 text-center text-sm text-atlas-ink-muted">Aucune demande pour ces filtres.</div>}{dataRequests && <><div className="divide-y divide-atlas-border">{dataRequests.items.map((item) => {
                        const exportExpired = Boolean(item.export?.expires_at && Date.parse(item.export.expires_at) <= Date.now());
                        const canAct = Boolean(session?.actionsEnabled && stepUpActive);
                        const requestEligible = ['Access', 'Portability'].includes(item.request_type) && item.status === 'Qualified' && item.identity_verified && item.ownership_verified && !item.export;
                        return <article key={item.reference} className="grid gap-4 p-5 lg:grid-cols-[1fr_.75fr_1fr_1fr_auto] lg:items-center"><div><p className="font-mono text-sm font-semibold">{item.reference}</p><p className="mt-1 text-xs text-atlas-ink-muted">Dossier {item.support_reference}</p></div><div><p className="font-semibold">{item.request_type}</p><p className="mt-1 text-xs text-atlas-ink-muted">{item.status}</p></div><div><p className="text-sm">{item.workspace_reference}</p><p className="mt-1 text-xs text-atlas-ink-muted">Identité {item.identity_verified ? 'vérifiée' : 'à vérifier'} · Owner {item.ownership_verified ? 'vérifié' : 'non vérifié'}</p></div><div><p className={`text-sm ${isOverdue(item.due_at) && !['Rejected', 'Closed'].includes(item.status) ? 'font-semibold text-red-700' : 'text-atlas-ink-muted'}`}>Cible : {formatDate(item.due_at)}</p>{item.export && <p className="mt-1 text-xs font-semibold text-violet-700">{item.export.reference} · {exportExpired && ['Ready', 'Delivered'].includes(item.export.status) ? 'Expiré' : item.export.status}{item.export.byte_size ? ` · ${Math.ceil(item.export.byte_size / 1024)} Ko` : ''}</p>}</div><div className="flex flex-wrap gap-2 lg:justify-end">{requestEligible && canRequestExport && canAct && <button type="button" disabled={exportBusy} onClick={() => void prepareExport(item, 'request')} className="min-h-10 rounded-xl border border-atlas-border px-3 text-sm font-semibold hover:bg-atlas-surface disabled:opacity-50">Demander l’export</button>}{item.export?.status === 'AwaitingApproval' && canApproveExport && canAct && <button type="button" disabled={exportBusy} onClick={() => void prepareExport(item, 'approve')} className="min-h-10 rounded-xl bg-atlas-sidebar px-3 text-sm font-semibold text-white disabled:opacity-50">Approuver</button>}{item.export && ['Ready', 'Delivered'].includes(item.export.status) && !exportExpired && canDownloadExport && canAct && <button type="button" disabled={exportBusy} onClick={() => void downloadExport(item)} className="min-h-10 rounded-xl bg-atlas-accent px-3 text-sm font-semibold text-white disabled:opacity-50">Télécharger pour remise</button>}</div></article>;
                    })}</div><Pagination page={dataRequests.page} totalPages={dataRequests.total_pages} onChange={setDataPage} /></>}</div>
                </section>}

                {canReadCompliance && <section className="mt-10"><h2 className="text-xl font-semibold">Documents et consentements facultatifs</h2><p className="mt-1 text-sm text-atlas-ink-muted">Empreintes de versions et totaux courants uniquement ; le contenu et les preuves restent hors de cette vue.</p><div className="mt-5 grid gap-4 sm:grid-cols-3">{policies && Object.entries(policies.consent_counts).map(([purpose, count]) => <article key={purpose} className="rounded-2xl border border-atlas-border bg-white p-5 shadow-sm"><p className="text-sm text-atlas-ink-muted">Consentement actif · {purpose}</p><p className="mt-2 text-3xl font-semibold">{count}</p></article>)}</div><div className="mt-4 overflow-hidden rounded-2xl border border-atlas-border bg-white shadow-sm">{!loading && policies?.items.length === 0 && <div className="p-10 text-center text-sm text-atlas-ink-muted">Aucune version enregistrée. Les projets actuels ne sont pas publiables.</div>}{policies && <><div className="divide-y divide-atlas-border">{policies.items.map((item) => <article key={`${item.document_kind}-${item.version}`} className="grid gap-3 p-5 sm:grid-cols-[1fr_1fr_.7fr_1fr] sm:items-center"><p className="font-semibold">{item.document_kind === 'BetaTerms' ? 'Conditions beta' : 'Notice de confidentialité'}</p><p className="font-mono text-sm">{item.version}</p><span className={`w-fit rounded-full border px-2.5 py-1 text-xs font-semibold ${item.lifecycle === 'Published' ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-amber-200 bg-amber-50 text-amber-700'}`}>{item.lifecycle === 'Published' ? 'Publiée' : 'Projet'}</span><p className="text-sm text-atlas-ink-muted">{item.proof_count} preuve(s) · effet {formatDate(item.effective_at)}</p></article>)}</div><Pagination page={policies.page} totalPages={policies.total_pages} onChange={setPolicyPage} /></>}</div></section>}

                {!canReadSupport && !canReadCompliance && <div className="mt-10 rounded-2xl border border-atlas-border bg-white p-8 text-atlas-ink-muted">Votre grant n’autorise aucun registre Support ou Conformité.</div>}
            </main>
        </OperatorFrame>
    );
}
