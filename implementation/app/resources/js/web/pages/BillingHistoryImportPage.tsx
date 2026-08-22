import { FormEvent, useEffect, useMemo, useState } from 'react';
import { Link } from 'react-router-dom';
import {
    confirmHistoricalBillingImport,
    getHistoricalBillingImport,
    previewHistoricalBilling,
} from '@/api/billing';
import { ApiClientError } from '@/api/client';
import {
    ErrorBanner,
    FormField,
    SubmitButton,
    inputClassName,
} from '@/components/auth/AuthLayout';
import { StepUpPasswordDialog, useImportStepUp } from '@/components/auth/StepUpPasswordDialog';
import { RequireAuth } from '@/components/layout/RequireAuth';
import { useAuth } from '@/hooks/useAuth';
import type {
    BillingHistoryImportCounts,
    BillingHistoryImportPreview,
    BillingHistoryImportRecordKind,
    BillingHistoryImportRun,
    BillingHistoryImportValidationError,
} from '@/types/api';

const emptyCounts: BillingHistoryImportCounts = { quotes: 0, invoices: 0, payments: 0, credit_notes: 0 };

const recordLabels: Record<BillingHistoryImportRecordKind, string> = {
    quotes: 'Devis',
    invoices: 'Factures',
    payments: 'Paiements',
    credit_notes: 'Avoirs',
    package: 'Package',
};

const phaseLabels: Record<string, string> = {
    Pending: 'En attente',
    Quotes: 'Import des devis',
    Invoices: 'Import des factures',
    Payments: 'Import des paiements',
    CreditNotes: 'Import des avoirs',
    Validate: 'Vérification des soldes',
    Completed: 'Terminé',
    Failed: 'Échec',
};

function localDateTimeValue(date: Date): string {
    const local = new Date(date.getTime() - date.getTimezoneOffset() * 60_000);
    return local.toISOString().slice(0, 16);
}

function formatDateTime(value: string): string {
    return new Intl.DateTimeFormat('fr-FR', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}

function packageHash(value: string): string {
    return value.startsWith('sha256:') ? value : `sha256:${value}`;
}

function countTotal(counts: BillingHistoryImportCounts): number {
    return counts.quotes + counts.invoices + counts.payments + counts.credit_notes;
}

function resolvedClients(preview: BillingHistoryImportPreview) {
    const clients = new Map<string, { client_id: string; display_name?: string }>();
    for (const record of [...preview.quotes, ...preview.invoices]) {
        if (record.client_external_id && record.client_id) {
            clients.set(record.client_external_id, {
                client_id: record.client_id,
                display_name: record.client_display_name,
            });
        }
    }

    return [...clients].map(([client_external_id, client]) => ({
        client_external_id,
        ...client,
    }));
}

export function BillingHistoryImportPage() {
    const { session } = useAuth();
    const token = session!.token;
    const workspaceId = session!.workspaceId!;
    const stepUp = useImportStepUp(token);
    const [sourceSystem, setSourceSystem] = useState('LegacyBilling');
    const [sourceExportedAt, setSourceExportedAt] = useState(() => localDateTimeValue(new Date()));
    const [quotesFile, setQuotesFile] = useState<File | null>(null);
    const [invoicesFile, setInvoicesFile] = useState<File | null>(null);
    const [paymentsFile, setPaymentsFile] = useState<File | null>(null);
    const [creditNotesFile, setCreditNotesFile] = useState<File | null>(null);
    const [preview, setPreview] = useState<BillingHistoryImportPreview | null>(null);
    const [run, setRun] = useState<BillingHistoryImportRun | null>(null);
    const [previewing, setPreviewing] = useState(false);
    const [confirming, setConfirming] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [conflict, setConflict] = useState(false);

    const validationGroups = useMemo(() => {
        const groups = new Map<BillingHistoryImportRecordKind, BillingHistoryImportValidationError[]>();
        for (const issue of preview?.validation_errors ?? []) {
            const existing = groups.get(issue.file) ?? [];
            groups.set(issue.file, [...existing, issue]);
        }
        return groups;
    }, [preview]);

    const terminal = run?.status === 'Completed' || run?.status === 'Failed' || run?.status === 'Conflict';

    useEffect(() => {
        if (!run?.import_run_id || terminal) return;

        let active = true;
        const checkStatus = async () => {
            try {
                const result = await getHistoricalBillingImport(token, workspaceId, run.import_run_id);
                if (!active) return;
                setRun(result);
                if (result.status === 'Failed' || result.status === 'Conflict') {
                    setConflict(result.status === 'Conflict');
                    setError(result.error ?? 'L’import n’a pas pu être terminé.');
                }
            } catch (err) {
                if (!active) return;
                if (err instanceof ApiClientError && err.status === 409) {
                    setConflict(true);
                    setError(err.message);
                }
            }
        };

        void checkStatus();
        const interval = window.setInterval(checkStatus, 1500);
        return () => {
            active = false;
            window.clearInterval(interval);
        };
    }, [run?.import_run_id, terminal, token, workspaceId]);

    function resetResult() {
        setPreview(null);
        setRun(null);
        setError(null);
        setConflict(false);
    }

    function resetAll() {
        resetResult();
        setQuotesFile(null);
        setInvoicesFile(null);
        setPaymentsFile(null);
        setCreditNotesFile(null);
    }

    async function onPreview(event: FormEvent) {
        event.preventDefault();
        if (!quotesFile || !invoicesFile || !paymentsFile || !creditNotesFile) {
            setError('Sélectionnez les quatre fichiers CSV, même si l’un d’eux ne contient que ses en-têtes.');
            return;
        }

        setPreviewing(true);
        resetResult();
        try {
            setPreview(await previewHistoricalBilling(token, workspaceId, {
                sourceSystem,
                sourceExportedAt: new Date(sourceExportedAt).toISOString(),
                quotesFile,
                invoicesFile,
                paymentsFile,
                creditNotesFile,
            }));
        } catch (err) {
            setError(err instanceof Error ? err.message : 'Prévisualisation impossible.');
        } finally {
            setPreviewing(false);
        }
    }

    async function onConfirm() {
        if (!preview) return;
        setConfirming(true);
        setError(null);
        setConflict(false);
        try {
            await stepUp.runWithStepUp(async () => {
                setRun(await confirmHistoricalBillingImport(
                    token,
                    workspaceId,
                    preview.preview_id,
                    packageHash(preview.package_hash),
                    preview.source_system,
                    preview.source_exported_at,
                ));
            });
        } catch (err) {
            setConflict(err instanceof ApiClientError && err.status === 409);
            setError(err instanceof Error ? err.message : 'Confirmation impossible.');
        } finally {
            setConfirming(false);
        }
    }

    const previewCounts = preview
        ? {
            quotes: preview.quote_count,
            invoices: preview.invoice_count,
            payments: preview.payment_count,
            credit_notes: preview.credit_note_count,
        }
        : emptyCounts;
    const processed = countTotal(run
        ? {
            quotes: run.processed_quotes ?? 0,
            invoices: run.processed_invoices ?? 0,
            payments: run.processed_payments ?? 0,
            credit_notes: run.processed_credit_notes ?? 0,
        }
        : emptyCounts);
    const total = countTotal(run
        ? {
            quotes: run.quote_count ?? previewCounts.quotes,
            invoices: run.invoice_count ?? previewCounts.invoices,
            payments: run.payment_count ?? previewCounts.payments,
            credit_notes: run.credit_note_count ?? previewCounts.credit_notes,
        }
        : previewCounts);
    const progress = run?.status === 'Completed'
        ? 100
        : total > 0
          ? Math.min(99, Math.round((processed / total) * 100))
          : 0;

    return (
        <RequireAuth>
            <div className="mx-auto max-w-6xl">
                <Link to="/app/billing" className="text-sm font-medium text-atlas-accent hover:underline">
                    ← Retour à la facturation
                </Link>

                <header className="mt-5 max-w-3xl">
                    <p className="text-sm font-medium text-atlas-accent">Facturation · Reprise de données</p>
                    <h2 className="mt-1 text-3xl font-semibold tracking-tight text-atlas-ink">
                        Importer un historique de facturation
                    </h2>
                    <p className="mt-3 text-sm leading-6 text-atlas-ink-muted">
                        Prévisualisez ensemble vos devis, factures, paiements et avoirs. Aucun document n’est créé
                        avant votre confirmation et aucune notification n’est envoyée.
                    </p>
                </header>

                <ErrorBanner message={error} />
                {conflict && (
                    <p className="mt-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                        Conflit détecté : vérifiez que le package et son empreinte n’ont pas changé, puis
                        recommencez la prévisualisation.
                    </p>
                )}

                {!run && (
                    <div className="mt-8 grid gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(18rem,1fr)]">
                        <form
                            aria-label="Prévisualiser un import historique de facturation"
                            onSubmit={onPreview}
                            className="rounded-2xl border border-atlas-border bg-atlas-card p-6 shadow-sm"
                        >
                            <h3 className="text-lg font-semibold text-atlas-ink">1. Décrire le package</h3>
                            <div className="mt-5 grid gap-5 sm:grid-cols-2">
                                <FormField label="Système source">
                                    <input
                                        required
                                        minLength={2}
                                        maxLength={64}
                                        pattern="[A-Za-z][A-Za-z0-9._-]+"
                                        className={inputClassName}
                                        value={sourceSystem}
                                        onChange={(event) => {
                                            setSourceSystem(event.target.value);
                                            resetResult();
                                        }}
                                    />
                                </FormField>
                                <FormField label="Date de l’export">
                                    <input
                                        required
                                        type="datetime-local"
                                        max={localDateTimeValue(new Date())}
                                        className={inputClassName}
                                        value={sourceExportedAt}
                                        onChange={(event) => {
                                            setSourceExportedAt(event.target.value);
                                            resetResult();
                                        }}
                                    />
                                </FormField>
                            </div>

                            <div className="mt-6 grid gap-5">
                                <CsvFileField label="Fichier des devis" onChange={(file) => {
                                    setQuotesFile(file);
                                    resetResult();
                                }} />
                                <CsvFileField label="Fichier des factures" onChange={(file) => {
                                    setInvoicesFile(file);
                                    resetResult();
                                }} />
                                <CsvFileField label="Fichier des paiements" onChange={(file) => {
                                    setPaymentsFile(file);
                                    resetResult();
                                }} />
                                <CsvFileField label="Fichier des avoirs" onChange={(file) => {
                                    setCreditNotesFile(file);
                                    resetResult();
                                }} />
                            </div>

                            <p className="mt-4 text-xs leading-5 text-atlas-ink-muted">
                                Les quatre fichiers sont obligatoires. Un fichier peut ne contenir que ses
                                en-têtes, mais le package complet doit contenir au moins un enregistrement.
                            </p>

                            <div className="mt-6">
                                <SubmitButton loading={previewing} loadingLabel="Analyse du package…">
                                    Prévisualiser le package
                                </SubmitButton>
                            </div>
                        </form>

                        <aside className="rounded-2xl border border-atlas-border bg-atlas-surface p-6">
                            <h3 className="font-semibold text-atlas-ink">Modèles CSV</h3>
                            <p className="mt-2 text-sm leading-6 text-atlas-ink-muted">
                                Conservez exactement les en-têtes et utilisez des dates ISO 8601.
                            </p>
                            <div className="mt-5 grid gap-3">
                                <TemplateLink href="/templates/atlas-billing-quotes-import-v1.csv">
                                    Modèle des devis
                                </TemplateLink>
                                <TemplateLink href="/templates/atlas-billing-invoices-import-v1.csv">
                                    Modèle des factures
                                </TemplateLink>
                                <TemplateLink href="/templates/atlas-billing-payments-import-v1.csv">
                                    Modèle des paiements
                                </TemplateLink>
                                <TemplateLink href="/templates/atlas-billing-credit-notes-import-v1.csv">
                                    Modèle des avoirs
                                </TemplateLink>
                            </div>
                            <p className="mt-5 text-xs leading-5 text-atlas-ink-muted">
                                Les clients doivent déjà avoir été importés depuis le même système source.
                                Les montants sont exprimés en centimes.
                            </p>
                        </aside>
                    </div>
                )}

                {preview && !run && (
                    <PreviewSection
                        preview={preview}
                        validationGroups={validationGroups}
                        confirming={confirming}
                        onConfirm={onConfirm}
                        onCancel={resetResult}
                    />
                )}

                {run && (
                    <RunProgress
                        run={run}
                        processed={processed}
                        total={total}
                        progress={progress}
                        onRestart={resetAll}
                    />
                )}
                <StepUpPasswordDialog
                    open={stepUp.promptOpen}
                    password={stepUp.password}
                    error={stepUp.promptError}
                    submitting={stepUp.submitting}
                    onPasswordChange={stepUp.setPassword}
                    onSubmit={(event) => void stepUp.submitPassword(event)}
                    onCancel={stepUp.closePrompt}
                />
            </div>
        </RequireAuth>
    );
}

function CsvFileField({
    label,
    onChange,
}: {
    label: string;
    onChange: (file: File | null) => void;
}) {
    return (
        <FormField label={label}>
            <input
                required
                type="file"
                accept=".csv,text/csv,text/plain"
                className={`${inputClassName} file:mr-4 file:rounded-lg file:border-0 file:bg-atlas-surface file:px-3 file:py-2 file:text-sm file:font-semibold file:text-atlas-ink`}
                onChange={(event) => onChange(event.target.files?.[0] ?? null)}
            />
        </FormField>
    );
}

function TemplateLink({ href, children }: { href: string; children: string }) {
    return (
        <a
            href={href}
            download
            className="rounded-xl border border-atlas-border bg-atlas-card px-4 py-3 text-center text-sm font-semibold text-atlas-ink hover:bg-white"
        >
            Télécharger · {children}
        </a>
    );
}

function PreviewSection({
    preview,
    validationGroups,
    confirming,
    onConfirm,
    onCancel,
}: {
    preview: BillingHistoryImportPreview;
    validationGroups: Map<BillingHistoryImportRecordKind, BillingHistoryImportValidationError[]>;
    confirming: boolean;
    onConfirm: () => void;
    onCancel: () => void;
}) {
    const clients = resolvedClients(preview);

    return (
        <section aria-label="Résultat de la prévisualisation" className="mt-8 space-y-6">
            <div className={`rounded-2xl border p-6 ${
                preview.valid_for_confirmation
                    ? 'border-emerald-200 bg-emerald-50'
                    : 'border-amber-200 bg-amber-50'
            }`}>
                <h3 className={`text-lg font-semibold ${
                    preview.valid_for_confirmation ? 'text-emerald-900' : 'text-amber-900'
                }`}>
                    {preview.valid_for_confirmation
                        ? 'Package prêt pour la confirmation'
                        : 'Package à corriger avant confirmation'}
                </h3>
                <p className="mt-2 text-sm text-atlas-ink-muted">
                    Aucun élément n’a encore été importé. Cet aperçu expire le {formatDateTime(preview.expires_at)}.
                </p>
            </div>

            <div className="grid gap-4 sm:grid-cols-5">
                <SummaryCard label="Devis" value={preview.quote_count} />
                <SummaryCard label="Factures" value={preview.invoice_count} />
                <SummaryCard label="Paiements" value={preview.payment_count} />
                <SummaryCard label="Avoirs" value={preview.credit_note_count} />
                <SummaryCard label="Erreurs" value={preview.validation_error_count} />
            </div>

            <div className="rounded-2xl border border-atlas-border bg-atlas-card p-5 shadow-sm">
                <p className="text-xs font-semibold uppercase tracking-wide text-atlas-ink-muted">
                    Empreinte du package
                </p>
                <code className="mt-2 block break-all text-xs leading-5 text-atlas-ink">
                    {packageHash(preview.package_hash)}
                </code>
            </div>

            {clients.length > 0 && (
                <div className="rounded-2xl border border-atlas-border bg-atlas-card p-6 shadow-sm">
                    <h3 className="font-semibold text-atlas-ink">
                        Clients résolus ({clients.length})
                    </h3>
                    <ul className="mt-4 grid gap-2 text-sm sm:grid-cols-2">
                        {clients.map((client) => (
                            <li key={client.client_external_id} className="rounded-xl bg-atlas-surface px-4 py-3">
                                <p className="font-medium text-atlas-ink">{client.display_name ?? client.client_external_id}</p>
                                <p className="mt-1 text-xs text-atlas-ink-muted">
                                    {client.client_external_id} → {client.client_id}
                                </p>
                            </li>
                        ))}
                    </ul>
                </div>
            )}

            {[...validationGroups.entries()].map(([kind, issues]) => (
                <div key={kind} className="rounded-2xl border border-red-200 bg-red-50 p-6">
                    <h3 className="font-semibold text-red-900">
                        {recordLabels[kind]} · {issues.length} erreur(s)
                    </h3>
                    <ul className="mt-4 space-y-2 text-sm text-red-800">
                        {issues.map((issue, index) => (
                            <li key={`${issue.line}-${issue.code}-${index}`}>
                                Ligne {issue.line} · {issue.field} — {issue.message}
                            </li>
                        ))}
                    </ul>
                </div>
            ))}

            {preview.valid_for_confirmation && (
                <div className="flex flex-col gap-3 sm:flex-row">
                    <button
                        type="button"
                        onClick={onConfirm}
                        disabled={confirming}
                        className="rounded-xl bg-atlas-accent px-6 py-3 text-sm font-semibold text-white hover:opacity-90 disabled:opacity-50"
                    >
                        {confirming ? 'Confirmation en cours…' : 'Confirmer et importer'}
                    </button>
                    <button
                        type="button"
                        onClick={onCancel}
                        disabled={confirming}
                        className="rounded-xl border border-atlas-border px-6 py-3 text-sm font-semibold text-atlas-ink hover:bg-atlas-surface disabled:opacity-50"
                    >
                        Annuler
                    </button>
                </div>
            )}
        </section>
    );
}

function RunProgress({
    run,
    processed,
    total,
    progress,
    onRestart,
}: {
    run: BillingHistoryImportRun;
    processed: number;
    total: number;
    progress: number;
    onRestart: () => void;
}) {
    const complete = run.status === 'Completed';
    return (
        <section aria-label="Suivi de l’import" className="mt-8 rounded-2xl border border-atlas-border bg-atlas-card p-6 shadow-sm">
            <p className="text-xs font-semibold uppercase tracking-wide text-atlas-ink-muted">
                Phase · {phaseLabels[run.checkpoint] ?? run.checkpoint}
            </p>
            <h3 className="mt-2 text-lg font-semibold text-atlas-ink">
                {complete ? 'Import terminé' : run.status === 'Failed' ? 'Import interrompu' : 'Import en cours'}
            </h3>
            <p className="mt-2 text-sm text-atlas-ink-muted">
                {complete
                    ? `${total} élément(s) historique(s) importé(s).`
                    : `${processed} sur ${total} élément(s) traités.`}
            </p>
            <div className="mt-6">
                <div className="h-2 overflow-hidden rounded-full bg-atlas-surface">
                    <div
                        className={`h-full transition-all duration-300 ${complete ? 'bg-emerald-500' : 'bg-atlas-accent'}`}
                        style={{ width: `${progress}%` }}
                    />
                </div>
                <p className="mt-2 text-xs tabular-nums text-atlas-ink-muted">{progress} %</p>
            </div>
            {complete && (
                <div className="mt-6 flex flex-col gap-3 sm:flex-row">
                    <Link
                        to="/app/billing"
                        className="rounded-xl bg-atlas-accent px-6 py-3 text-center text-sm font-semibold text-white hover:opacity-90"
                    >
                        Voir la facturation
                    </Link>
                    <button
                        type="button"
                        onClick={onRestart}
                        className="rounded-xl border border-atlas-border px-6 py-3 text-sm font-semibold text-atlas-ink hover:bg-atlas-surface"
                    >
                        Importer un autre package
                    </button>
                </div>
            )}
            {(run.status === 'Failed' || run.status === 'Conflict') && (
                <button
                    type="button"
                    onClick={onRestart}
                    className="mt-6 rounded-xl border border-atlas-border px-6 py-3 text-sm font-semibold text-atlas-ink hover:bg-atlas-surface"
                >
                    Recommencer avec un nouveau package
                </button>
            )}
        </section>
    );
}

function SummaryCard({ label, value }: { label: string; value: number }) {
    return (
        <div className="rounded-2xl border border-atlas-border bg-atlas-card p-5 shadow-sm">
            <p className="text-xs font-semibold uppercase tracking-wide text-atlas-ink-muted">{label}</p>
            <p className="mt-2 text-2xl font-semibold tabular-nums text-atlas-ink">{value}</p>
        </div>
    );
}
