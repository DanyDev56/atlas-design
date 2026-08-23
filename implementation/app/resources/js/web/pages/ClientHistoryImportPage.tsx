import { FormEvent, useState, useEffect } from "react";
import { Link } from "react-router-dom";
import {
    previewHistoricalClients,
    confirmHistoricalClientsImport,
} from "@/api/crm";
import {
    ErrorBanner,
    FormField,
    SubmitButton,
    inputClassName,
} from "@/components/auth/AuthLayout";
import { StepUpPasswordDialog, useImportStepUp } from "@/components/auth/StepUpPasswordDialog";
import { RequireAuth } from "@/components/layout/RequireAuth";
import { PageHeader } from "@/components/ui/PageHeader";
import { useAuth } from "@/hooks/useAuth";
import type { ClientHistoryImportPreview } from "@/types/api";

const maxFileBytes = 256 * 1024;

function localDateTimeValue(date: Date): string {
    const local = new Date(date.getTime() - date.getTimezoneOffset() * 60_000);

    return local.toISOString().slice(0, 16);
}

function formatDateTime(value: string): string {
    return new Intl.DateTimeFormat("fr-FR", {
        dateStyle: "medium",
        timeStyle: "short",
    }).format(new Date(value));
}

export function ClientHistoryImportPage() {
    const { session } = useAuth();
    const token = session!.token;
    const workspaceId = session!.workspaceId!;
    const stepUp = useImportStepUp(token);
    const [sourceSystem, setSourceSystem] = useState("LegacyCRM");
    const [sourceExportedAt, setSourceExportedAt] = useState(() =>
        localDateTimeValue(new Date()),
    );
    const [file, setFile] = useState<File | null>(null);
    const [preview, setPreview] = useState<ClientHistoryImportPreview | null>(
        null,
    );
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [confirming, setConfirming] = useState(false);
    const [importRunId, setImportRunId] = useState<string | null>(null);
    const [importProgress, setImportProgress] = useState<{
        imported: number;
        total: number;
    } | null>(null);
    const [importComplete, setImportComplete] = useState(false);

    function replacePreview() {
        setPreview(null);
        setError(null);
        setConfirming(false);
        setImportRunId(null);
        setImportProgress(null);
        setImportComplete(false);
    }

    useEffect(() => {
        if (!importRunId || importComplete) return;

        let active = true;

        const checkStatus = async () => {
            try {
                const response = await fetch(
                    `/api/workspaces/${workspaceId}/client-history-imports/${importRunId}`,
                    {
                        headers: {
                            Authorization: `Bearer ${token}`,
                            Accept: "application/json",
                        },
                    },
                );
                if (!response.ok) return;
                const result = await response.json();
                if (!active) return;

                const total =
                    Number.isFinite(result.client_count) &&
                    result.client_count > 0
                        ? result.client_count
                        : (preview?.valid_row_count ?? 0);

                if (result.status === "Completed") {
                    setImportComplete(true);
                    setImportProgress({
                        imported: total,
                        total,
                    });
                } else if (result.status === "Processing") {
                    setImportProgress({
                        imported: result.processed_count || 0,
                        total,
                    });
                }
            } catch {
                // Continue polling on temporary error
            }
        };

        void checkStatus();
        const interval = setInterval(checkStatus, 1500);

        return () => {
            active = false;
            clearInterval(interval);
        };
    }, [importRunId, importComplete, token, workspaceId, preview]);

    async function onConfirm() {
        if (!preview) return;

        setConfirming(true);
        setError(null);

        try {
            await stepUp.runWithStepUp(async () => {
                const result = await confirmHistoricalClientsImport(
                    token,
                    workspaceId,
                    {
                        previewId: preview.preview_id,
                        packageHash: `sha256:${preview.package_hash}`,
                        sourceSystem: preview.source_system,
                        sourceExportedAt: preview.source_exported_at,
                    },
                );
                setImportRunId(result.import_run_id);
                setImportProgress({ imported: 0, total: preview.valid_row_count });
            });
        } catch (err) {
            setError(
                err instanceof Error ? err.message : "Confirmation impossible",
            );
        } finally {
            setConfirming(false);
        }
    }

    async function onPreview(event: FormEvent) {
        event.preventDefault();

        if (!file) {
            setError("Sélectionnez un fichier CSV à prévisualiser.");
            return;
        }

        if (file.size > maxFileBytes) {
            setError("Le fichier dépasse la limite de 256 Kio.");
            return;
        }

        setLoading(true);
        setError(null);
        setPreview(null);

        try {
            setPreview(
                await previewHistoricalClients(token, workspaceId, {
                    sourceSystem,
                    sourceExportedAt: new Date(sourceExportedAt).toISOString(),
                    file,
                }),
            );
        } catch (err) {
            setError(
                err instanceof Error
                    ? err.message
                    : "Prévisualisation impossible",
            );
        } finally {
            setLoading(false);
        }
    }

    return (
        <RequireAuth>
            <div className="atlas-page max-w-6xl">
                <Link
                    to="/app/crm"
                    className="inline-flex items-center rounded-lg px-1 py-1 text-sm font-semibold text-atlas-accent hover:text-[#055c50]"
                >
                    ← Retour aux clients
                </Link>

                <div className="mt-5">
                    <PageHeader
                        eyebrow="CRM · Reprise de données"
                        title="Prévisualiser des clients historiques"
                        description="Vérifiez un export avant toute création dans Atlas. Cette étape ne modifie aucun client et n’envoie aucune notification."
                    />
                </div>

                <ErrorBanner message={error} />

                <div className="mt-8 grid gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(18rem,1fr)]">
                    <form
                        aria-label="Prévisualiser un import historique de clients"
                        onSubmit={onPreview}
                        className="rounded-2xl border border-atlas-border bg-atlas-card p-6 shadow-sm"
                    >
                        <h3 className="text-lg font-semibold text-atlas-ink">
                            1. Décrire l’export
                        </h3>
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
                                        replacePreview();
                                    }}
                                    placeholder="LegacyCRM"
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
                                        replacePreview();
                                    }}
                                />
                            </FormField>
                        </div>

                        <div className="mt-5">
                            <FormField label="Fichier clients CSV">
                                <input
                                    required
                                    type="file"
                                    accept=".csv,text/csv,text/plain"
                                    className={`${inputClassName} file:mr-4 file:rounded-lg file:border-0 file:bg-atlas-surface file:px-3 file:py-2 file:text-sm file:font-semibold file:text-atlas-ink`}
                                    onChange={(event) => {
                                        setFile(
                                            event.target.files?.[0] ?? null,
                                        );
                                        replacePreview();
                                    }}
                                />
                            </FormField>
                            <p className="mt-2 text-xs leading-5 text-atlas-ink-muted">
                                CSV UTF‑8, virgule ou point-virgule, 100 clients
                                et 256 Kio maximum.
                            </p>
                        </div>

                        <div className="mt-6 flex flex-col gap-3 sm:flex-row sm:items-center">
                            <SubmitButton
                                loading={loading}
                                loadingLabel="Analyse du fichier…"
                            >
                                Prévisualiser le fichier
                            </SubmitButton>
                            <a
                                href="/templates/atlas-clients-import-v1.csv"
                                download
                                className="rounded-xl border border-atlas-border px-4 py-3 text-center text-sm font-semibold text-atlas-ink hover:bg-atlas-surface"
                            >
                                Télécharger le modèle CSV
                            </a>
                        </div>
                    </form>

                    <aside className="rounded-2xl border border-atlas-border bg-atlas-surface p-6">
                        <h3 className="font-semibold text-atlas-ink">
                            Colonnes acceptées
                        </h3>
                        <p className="mt-3 text-xs font-semibold uppercase tracking-wide text-atlas-ink-muted">
                            Obligatoires
                        </p>
                        <code className="mt-2 block break-words text-xs leading-6 text-atlas-ink">
                            external_id, kind, status, display_name,
                            source_created_at
                        </code>
                        <p className="mt-4 text-xs font-semibold uppercase tracking-wide text-atlas-ink-muted">
                            Optionnelles
                        </p>
                        <code className="mt-2 block break-words text-xs leading-6 text-atlas-ink">
                            legal_name, email, phone, website
                        </code>
                        <p className="mt-5 text-xs leading-5 text-atlas-ink-muted">
                            <strong className="text-atlas-ink">kind</strong> :
                            Individual ou Organization.
                            <br />
                            <strong className="text-atlas-ink">status</strong> :
                            Active ou Archived.
                            <br />
                            Les dates doivent être au format ISO 8601 avec
                            fuseau horaire.
                        </p>
                    </aside>
                </div>

                {preview && (
                    <section
                        aria-label="Résultat de la prévisualisation"
                        className="mt-8 space-y-6"
                    >
                        <div
                            className={`rounded-2xl border p-6 ${
                                preview.valid_for_confirmation
                                    ? "border-emerald-200 bg-emerald-50"
                                    : "border-amber-200 bg-amber-50"
                            }`}
                        >
                            <h3
                                className={`text-lg font-semibold ${
                                    preview.valid_for_confirmation
                                        ? "text-emerald-900"
                                        : "text-amber-900"
                                }`}
                            >
                                {preview.valid_for_confirmation
                                    ? "Aperçu prêt pour la confirmation"
                                    : "Aperçu à corriger avant confirmation"}
                            </h3>
                            <p
                                className={`mt-2 text-sm ${
                                    preview.valid_for_confirmation
                                        ? "text-emerald-800"
                                        : "text-amber-800"
                                }`}
                            >
                                Aucun client n’a encore été importé. Le package
                                canonique expire le{" "}
                                {formatDateTime(preview.expires_at)}.
                            </p>
                        </div>

                        <div className="grid gap-4 sm:grid-cols-3">
                            <SummaryCard
                                label="Lignes détectées"
                                value={preview.row_count}
                            />
                            <SummaryCard
                                label="Lignes valides"
                                value={preview.valid_row_count}
                            />
                            <SummaryCard
                                label="Points à résoudre"
                                value={
                                    preview.validation_error_count +
                                    preview.duplicate_candidate_count
                                }
                            />
                        </div>

                        <div className="rounded-2xl border border-atlas-border bg-atlas-card p-5 shadow-sm">
                            <p className="text-xs font-semibold uppercase tracking-wide text-atlas-ink-muted">
                                Empreinte du package
                            </p>
                            <code className="mt-2 block break-all text-xs leading-5 text-atlas-ink">
                                sha256:{preview.package_hash}
                            </code>
                            <p className="mt-2 text-xs text-atlas-ink-muted">
                                Cette empreinte devra être confirmée à l’étape
                                d’import afin de détecter toute modification.
                            </p>
                        </div>

                        {preview.validation_errors.length > 0 && (
                            <div className="rounded-2xl border border-red-200 bg-red-50 p-6">
                                <h3 className="font-semibold text-red-900">
                                    Erreurs de validation
                                </h3>
                                <ul className="mt-4 space-y-2 text-sm text-red-800">
                                    {preview.validation_errors.map(
                                        (validationError, index) => (
                                            <li
                                                key={`${validationError.line}-${validationError.code}-${index}`}
                                            >
                                                Ligne {validationError.line} ·{" "}
                                                {validationError.field} —{" "}
                                                {validationError.message}
                                            </li>
                                        ),
                                    )}
                                </ul>
                            </div>
                        )}

                        {preview.duplicate_candidates.length > 0 && (
                            <div className="rounded-2xl border border-amber-200 bg-amber-50 p-6">
                                <h3 className="font-semibold text-amber-900">
                                    Doublons probables à décider
                                </h3>
                                <ul className="mt-4 space-y-2 text-sm text-amber-800">
                                    {preview.duplicate_candidates.map(
                                        (candidate, index) => (
                                            <li
                                                key={`${candidate.line}-${candidate.kind}-${index}`}
                                            >
                                                Ligne {candidate.line} ·{" "}
                                                {candidate.display_name}{" "}
                                                correspond à{" "}
                                                {candidate.kind ===
                                                "ExistingClient"
                                                    ? `« ${candidate.matched_display_name} » déjà présent dans Atlas`
                                                    : `la ligne ${candidate.matched_line} du fichier`}
                                                .
                                            </li>
                                        ),
                                    )}
                                </ul>
                            </div>
                        )}

                        {preview.records.length > 0 && (
                            <div className="overflow-hidden rounded-2xl border border-atlas-border bg-atlas-card shadow-sm">
                                <div className="border-b border-atlas-border px-5 py-4">
                                    <h3 className="font-semibold text-atlas-ink">
                                        Lignes canoniques
                                    </h3>
                                </div>
                                <div className="overflow-x-auto">
                                    <table className="min-w-full divide-y divide-atlas-border text-left text-sm">
                                        <thead className="bg-atlas-surface text-xs uppercase tracking-wide text-atlas-ink-muted">
                                            <tr>
                                                <th className="px-5 py-3">
                                                    Ligne
                                                </th>
                                                <th className="px-5 py-3">
                                                    Client
                                                </th>
                                                <th className="px-5 py-3">
                                                    Type
                                                </th>
                                                <th className="px-5 py-3">
                                                    Statut source
                                                </th>
                                                <th className="px-5 py-3">
                                                    Validation
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-atlas-border">
                                            {preview.records.map((record) => (
                                                <tr
                                                    key={`${record.line}-${record.external_id}`}
                                                >
                                                    <td className="px-5 py-4 tabular-nums text-atlas-ink-muted">
                                                        {record.line}
                                                    </td>
                                                    <td className="px-5 py-4">
                                                        <p className="font-medium text-atlas-ink">
                                                            {
                                                                record.profile
                                                                    .display_name
                                                            }
                                                        </p>
                                                        <p className="mt-1 text-xs text-atlas-ink-muted">
                                                            {record.external_id}
                                                        </p>
                                                    </td>
                                                    <td className="px-5 py-4 text-atlas-ink-muted">
                                                        {record.kind ===
                                                        "Organization"
                                                            ? "Organisation"
                                                            : record.kind ===
                                                                "Individual"
                                                              ? "Particulier"
                                                              : record.kind}
                                                    </td>
                                                    <td className="px-5 py-4 text-atlas-ink-muted">
                                                        {record.status}
                                                    </td>
                                                    <td className="px-5 py-4">
                                                        <span
                                                            className={`rounded-full px-2.5 py-1 text-xs font-semibold ${
                                                                record.validation_status ===
                                                                "Valid"
                                                                    ? "bg-emerald-100 text-emerald-800"
                                                                    : "bg-red-100 text-red-800"
                                                            }`}
                                                        >
                                                            {record.validation_status ===
                                                            "Valid"
                                                                ? "Valide"
                                                                : "À corriger"}
                                                        </span>
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        )}

                        {preview &&
                            preview.valid_for_confirmation &&
                            !importRunId && (
                                <div className="mt-6 flex flex-col gap-3 sm:flex-row">
                                    <button
                                        onClick={onConfirm}
                                        disabled={confirming}
                                        className="rounded-xl bg-atlas-accent px-6 py-3 text-center text-sm font-semibold text-white hover:bg-opacity-90 disabled:opacity-50"
                                    >
                                        {confirming
                                            ? "Traitement en cours…"
                                            : "Confirmer et importer"}
                                    </button>
                                    <button
                                        onClick={replacePreview}
                                        disabled={confirming}
                                        className="rounded-xl border border-atlas-border px-6 py-3 text-center text-sm font-semibold text-atlas-ink hover:bg-atlas-surface disabled:opacity-50"
                                    >
                                        Annuler
                                    </button>
                                </div>
                            )}
                    </section>
                )}

                {importRunId && importProgress && (
                    <section
                        aria-label="Suivi de progression de l'import"
                        className="mt-8 rounded-2xl border border-atlas-border bg-atlas-card p-6 shadow-sm"
                    >
                        <h3 className="text-lg font-semibold text-atlas-ink">
                            Import en cours
                        </h3>
                        <p className="mt-2 text-sm text-atlas-ink-muted">
                            {importComplete
                                ? `Import terminé avec succès. ${importProgress.imported} client(s) importé(s).`
                                : `Traitement du package… ${importProgress.imported} / ${importProgress.total} client(s).`}
                        </p>

                        <div className="mt-6">
                            <div className="h-2 w-full overflow-hidden rounded-full bg-atlas-surface">
                                <div
                                    className="h-full bg-emerald-500 transition-all duration-300"
                                    style={{
                                        width: `${(importProgress.imported / importProgress.total) * 100}%`,
                                    }}
                                />
                            </div>
                            <p className="mt-2 text-xs tabular-nums text-atlas-ink-muted">
                                {Math.round(
                                    (importProgress.imported /
                                        importProgress.total) *
                                        100,
                                )}
                                %
                            </p>
                        </div>

                        {importComplete && (
                            <div className="mt-6 flex flex-col gap-3 sm:flex-row">
                                <Link
                                    to="/app/crm"
                                    className="rounded-xl bg-atlas-accent px-6 py-3 text-center text-sm font-semibold text-white hover:bg-opacity-90"
                                >
                                    Voir les clients importés
                                </Link>
                                <button
                                    onClick={() => {
                                        replacePreview();
                                        setFile(null);
                                    }}
                                    className="rounded-xl border border-atlas-border px-6 py-3 text-center text-sm font-semibold text-atlas-ink hover:bg-atlas-surface"
                                >
                                    Importer un autre fichier
                                </button>
                            </div>
                        )}
                    </section>
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

function SummaryCard({ label, value }: { label: string; value: number }) {
    return (
        <div className="rounded-2xl border border-atlas-border bg-atlas-card p-5 shadow-sm">
            <p className="text-xs font-semibold uppercase tracking-wide text-atlas-ink-muted">
                {label}
            </p>
            <p className="mt-2 text-2xl font-semibold tabular-nums text-atlas-ink">
                {value}
            </p>
        </div>
    );
}
