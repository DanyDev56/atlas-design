import { useCallback, useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { ApiClientError } from '@/api/client';
import { fetchOperatorCompliance, fetchOperatorDataRequests, fetchOperatorSupport, type OperatorDataRequestItem, type OperatorPage, type OperatorPolicyPage, type OperatorSupportCaseItem } from '@/api/operator';
import { OperatorFrame } from '@/components/operator/OperatorFrame';
import { useOperatorAuth } from '@/hooks/useOperatorAuth';

function formatDate(value: string | null): string {
    return value ? new Intl.DateTimeFormat('fr-FR', { dateStyle: 'short', timeStyle: 'short' }).format(new Date(value)) : '—';
}

function isOverdue(value: string): boolean {
    return new Date(value).getTime() < Date.now();
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

    return (
        <OperatorFrame>
            <main className="mx-auto max-w-[90rem] px-5 py-10 sm:px-8 lg:px-10 lg:py-14">
                <Link to="/backoffice" className="text-sm font-semibold text-atlas-accent">← Retour à la vue d’ensemble</Link>
                <div className="mt-8"><p className="atlas-kicker">Accompagnement beta</p><h1 className="mt-3 text-4xl font-semibold tracking-[-0.045em]">Support et conformité</h1><p className="mt-4 max-w-3xl leading-7 text-atlas-ink-muted">Registres pseudonymisés, échéances internes et preuves minimisées. Cette surface est strictement en lecture seule : elle ne lance ni export, ni fermeture, ni effacement.</p></div>
                {error && <div role="alert" className="mt-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{error} <button type="button" className="font-semibold underline" onClick={() => void load()}>Réessayer</button></div>}
                {loading && <p className="mt-8 text-sm font-semibold text-atlas-accent">Actualisation des registres…</p>}

                {canReadSupport && <section className="mt-10"><div className="flex flex-wrap items-end justify-between gap-4"><div><h2 className="text-xl font-semibold">Dossiers support</h2><p className="mt-1 text-sm text-atlas-ink-muted">Priorité, cible de réponse et vérification, sans nom ni adresse.</p></div><div className="flex w-full flex-col gap-2 sm:w-auto sm:flex-row"><select aria-label="Statut support" value={supportStatus} onChange={(event) => { setSupportStatus(event.target.value); setSupportPage(1); }} className="min-h-10 rounded-xl border border-atlas-border bg-white px-3 text-sm"><option value="All">Tous les statuts</option><option value="Open">Ouverts</option><option value="Acknowledged">Pris en compte</option><option value="InProgress">En cours</option><option value="WaitingRequester">En attente du demandeur</option><option value="Resolved">Résolus</option><option value="Closed">Clos</option></select><select aria-label="Priorité support" value={severity} onChange={(event) => { setSeverity(event.target.value); setSupportPage(1); }} className="min-h-10 rounded-xl border border-atlas-border bg-white px-3 text-sm"><option value="All">Toutes priorités</option>{['P0', 'P1', 'P2', 'P3'].map((value) => <option key={value} value={value}>{value}</option>)}</select></div></div>
                    <div className="mt-5 overflow-hidden rounded-2xl border border-atlas-border bg-white shadow-sm">{!loading && support?.items.length === 0 && <div className="p-10 text-center text-sm text-atlas-ink-muted">Aucun dossier pour ces filtres.</div>}{support && <><div className="divide-y divide-atlas-border">{support.items.map((item) => <article key={item.reference} className="grid gap-3 p-5 lg:grid-cols-[1fr_.6fr_.8fr_1fr] lg:items-center"><div><p className="font-mono text-sm font-semibold">{item.reference}</p><p className="mt-1 text-xs text-atlas-ink-muted">{item.workspace_reference} · {item.requester_reference}</p></div><div><span className={`rounded-full border px-2.5 py-1 text-xs font-semibold ${item.severity === 'P0' || item.severity === 'P1' ? 'border-red-200 bg-red-50 text-red-700' : 'border-atlas-border bg-atlas-surface text-atlas-ink-muted'}`}>{item.severity}</span><p className="mt-2 text-xs text-atlas-ink-muted">{item.category}</p></div><div><p className="text-sm font-semibold">{item.summary_code}</p><p className="mt-1 text-xs text-atlas-ink-muted">{item.status} · {item.event_count} événement(s)</p></div><div><p className={`text-sm ${isOverdue(item.response_due_at) && !['Resolved', 'Closed'].includes(item.status) ? 'font-semibold text-red-700' : 'text-atlas-ink-muted'}`}>Cible : {formatDate(item.response_due_at)}</p><p className="mt-1 text-xs text-atlas-ink-muted">Identité vérifiée · Owner {item.ownership_verified ? 'vérifié' : 'non requis'}</p></div></article>)}</div><Pagination page={support.page} totalPages={support.total_pages} onChange={setSupportPage} /></>}</div>
                </section>}

                {canReadCompliance && <section className="mt-10"><div className="flex flex-wrap items-end justify-between gap-4"><div><h2 className="text-xl font-semibold">Demandes relatives aux données</h2><p className="mt-1 text-sm text-atlas-ink-muted">Qualification et préparation uniquement ; aucune action destructive disponible.</p></div><div className="flex w-full flex-col gap-2 sm:w-auto sm:flex-row"><select aria-label="Statut demande" value={dataStatus} onChange={(event) => { setDataStatus(event.target.value); setDataPage(1); }} className="min-h-10 rounded-xl border border-atlas-border bg-white px-3 text-sm"><option value="All">Tous les statuts</option><option value="Received">Reçues</option><option value="IdentityPending">Identité à vérifier</option><option value="Qualified">Qualifiées</option><option value="InPreparation">En préparation</option><option value="AwaitingApproval">À approuver</option><option value="Delivered">Remises</option><option value="Rejected">Rejetées</option><option value="Closed">Closes</option></select><select aria-label="Type de demande" value={dataType} onChange={(event) => { setDataType(event.target.value); setDataPage(1); }} className="min-h-10 rounded-xl border border-atlas-border bg-white px-3 text-sm"><option value="All">Tous les types</option>{['Access', 'Rectification', 'Erasure', 'Restriction', 'Objection', 'Portability'].map((value) => <option key={value} value={value}>{value}</option>)}</select></div></div>
                    <div className="mt-5 overflow-hidden rounded-2xl border border-atlas-border bg-white shadow-sm">{!loading && dataRequests?.items.length === 0 && <div className="p-10 text-center text-sm text-atlas-ink-muted">Aucune demande pour ces filtres.</div>}{dataRequests && <><div className="divide-y divide-atlas-border">{dataRequests.items.map((item) => <article key={item.reference} className="grid gap-3 p-5 lg:grid-cols-[1fr_.8fr_.8fr_1fr] lg:items-center"><div><p className="font-mono text-sm font-semibold">{item.reference}</p><p className="mt-1 text-xs text-atlas-ink-muted">Dossier {item.support_reference}</p></div><div><p className="font-semibold">{item.request_type}</p><p className="mt-1 text-xs text-atlas-ink-muted">{item.status}</p></div><div><p className="text-sm">{item.workspace_reference}</p><p className="mt-1 text-xs text-atlas-ink-muted">Identité {item.identity_verified ? 'vérifiée' : 'à vérifier'} · Owner {item.ownership_verified ? 'vérifié' : 'non requis'}</p></div><p className={`text-sm ${isOverdue(item.due_at) && !['Rejected', 'Closed'].includes(item.status) ? 'font-semibold text-red-700' : 'text-atlas-ink-muted'}`}>Cible : {formatDate(item.due_at)}</p></article>)}</div><Pagination page={dataRequests.page} totalPages={dataRequests.total_pages} onChange={setDataPage} /></>}</div>
                </section>}

                {canReadCompliance && <section className="mt-10"><h2 className="text-xl font-semibold">Documents et consentements facultatifs</h2><p className="mt-1 text-sm text-atlas-ink-muted">Empreintes de versions et totaux courants uniquement ; le contenu et les preuves restent hors de cette vue.</p><div className="mt-5 grid gap-4 sm:grid-cols-3">{policies && Object.entries(policies.consent_counts).map(([purpose, count]) => <article key={purpose} className="rounded-2xl border border-atlas-border bg-white p-5 shadow-sm"><p className="text-sm text-atlas-ink-muted">Consentement actif · {purpose}</p><p className="mt-2 text-3xl font-semibold">{count}</p></article>)}</div><div className="mt-4 overflow-hidden rounded-2xl border border-atlas-border bg-white shadow-sm">{!loading && policies?.items.length === 0 && <div className="p-10 text-center text-sm text-atlas-ink-muted">Aucune version enregistrée. Les projets actuels ne sont pas publiables.</div>}{policies && <><div className="divide-y divide-atlas-border">{policies.items.map((item) => <article key={`${item.document_kind}-${item.version}`} className="grid gap-3 p-5 sm:grid-cols-[1fr_1fr_.7fr_1fr] sm:items-center"><p className="font-semibold">{item.document_kind === 'BetaTerms' ? 'Conditions beta' : 'Notice de confidentialité'}</p><p className="font-mono text-sm">{item.version}</p><span className={`w-fit rounded-full border px-2.5 py-1 text-xs font-semibold ${item.lifecycle === 'Published' ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-amber-200 bg-amber-50 text-amber-700'}`}>{item.lifecycle === 'Published' ? 'Publiée' : 'Projet'}</span><p className="text-sm text-atlas-ink-muted">{item.proof_count} preuve(s) · effet {formatDate(item.effective_at)}</p></article>)}</div><Pagination page={policies.page} totalPages={policies.total_pages} onChange={setPolicyPage} /></>}</div></section>}

                {!canReadSupport && !canReadCompliance && <div className="mt-10 rounded-2xl border border-atlas-border bg-white p-8 text-atlas-ink-muted">Votre grant n’autorise aucun registre Support ou Conformité.</div>}
            </main>
        </OperatorFrame>
    );
}
