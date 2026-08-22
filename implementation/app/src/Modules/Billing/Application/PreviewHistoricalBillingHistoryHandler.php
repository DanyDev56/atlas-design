<?php

declare(strict_types=1);

namespace Atlas\Modules\Billing\Application;

use Atlas\Modules\Billing\Infrastructure\Persistence\PostgresBillingHistoryImportPreviewRepository;
use Atlas\Platform\Security\WorkspaceAuthorizer;
use Illuminate\Support\Str;

final class PreviewHistoricalBillingHistoryHandler
{
    private const HEADERS = [
        'quotes' => [
            'external_id', 'client_external_id', 'original_number', 'status', 'created_at',
            'sent_at', 'responded_at', 'valid_until', 'net_amount_cents',
            'tax_amount_cents', 'gross_amount_cents', 'currency',
        ],
        'invoices' => [
            'external_id', 'client_external_id', 'original_number', 'issued_at', 'due_date',
            'net_amount_cents', 'tax_amount_cents', 'gross_amount_cents', 'currency',
        ],
        'payments' => [
            'external_id', 'invoice_external_id', 'amount_received_cents',
            'amount_applied_cents', 'currency', 'received_at', 'status',
        ],
    ];

    public function __construct(
        private readonly WorkspaceAuthorizer $authorizer,
        private readonly PostgresBillingHistoryImportPreviewRepository $previews,
    ) {}

    /** @return array<string, mixed> */
    public function handle(
        string $actorUserId,
        string $workspaceId,
        string $sourceSystem,
        string $sourceExportedAt,
        string $quotesContents,
        string $invoicesContents,
        string $paymentsContents,
    ): array {
        $this->authorizer->authorize($actorUserId, $workspaceId, 'billing.history.import');
        $sourceSystem = trim($sourceSystem);
        if (! preg_match('/^[A-Za-z][A-Za-z0-9._-]{1,63}$/', $sourceSystem)) {
            throw new \DomainException('Source system invalid.');
        }

        try {
            $exportedAt = (new \DateTimeImmutable($sourceExportedAt))->setTimezone(new \DateTimeZone('UTC'));
        } catch (\Throwable) {
            throw new \DomainException('Source export date invalid.');
        }
        if ($exportedAt > new \DateTimeImmutable('now', new \DateTimeZone('UTC'))) {
            throw new \DomainException('Source export date cannot be in the future.');
        }

        $errors = [];
        $records = [];
        foreach ([
            'quotes' => $quotesContents,
            'invoices' => $invoicesContents,
            'payments' => $paymentsContents,
        ] as $kind => $contents) {
            $records[$kind] = $this->parseCsv($kind, $contents, $errors);
        }

        if (array_sum(array_map('count', $records)) === 0) {
            $errors[] = $this->error('package', 1, 'package', 'empty_package', 'Le package doit contenir au moins un enregistrement.');
        }

        $this->validateRecords($workspaceId, $sourceSystem, $exportedAt, $records, $errors);
        $invalid = [];
        foreach ($errors as $error) {
            $invalid[$error['file'].':'.$error['line']] = true;
        }
        foreach ($records as $kind => &$kindRecords) {
            foreach ($kindRecords as &$record) {
                $record['validation_status'] = isset($invalid[$kind.':'.$record['line']]) ? 'Invalid' : 'Valid';
            }
        }
        unset($kindRecords, $record);

        $packageHash = hash('sha256', json_encode([
            'schema_version' => '1.0',
            'source_system' => $sourceSystem,
            'source_exported_at' => $exportedAt->format(DATE_ATOM),
            'quotes' => $this->canonicalRecords($records['quotes']),
            'invoices' => $this->canonicalRecords($records['invoices']),
            'payments' => $this->canonicalRecords($records['payments']),
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $previewId = (string) Str::uuid();
        $response = [
            'preview_id' => $previewId,
            'schema_version' => '1.0',
            'source_system' => $sourceSystem,
            'source_exported_at' => $exportedAt->format(DATE_ATOM),
            'package_hash' => $packageHash,
            'quote_count' => count($records['quotes']),
            'invoice_count' => count($records['invoices']),
            'payment_count' => count($records['payments']),
            'validation_error_count' => count($errors),
            'valid_for_confirmation' => $errors === [],
            ...$records,
            'validation_errors' => $errors,
            'expires_at' => $now->modify('+24 hours')->format(DATE_ATOM),
        ];

        $this->previews->insert([
            'id' => $previewId,
            'workspace_id' => $workspaceId,
            'source_system' => $sourceSystem,
            'source_exported_at' => $exportedAt->format('Y-m-d H:i:sP'),
            'schema_version' => '1.0',
            'package_hash' => $packageHash,
            'quote_count' => count($records['quotes']),
            'invoice_count' => count($records['invoices']),
            'payment_count' => count($records['payments']),
            'validation_error_count' => count($errors),
            ...$records,
            'validation_errors' => $errors,
            'created_by' => $actorUserId,
            'expires_at' => $now->modify('+24 hours')->format('Y-m-d H:i:sP'),
            'created_at' => $now->format('Y-m-d H:i:sP'),
        ]);

        return $response;
    }

    /**
     * @param  list<array<string, mixed>>  $errors
     * @return list<array<string, mixed>>
     */
    private function parseCsv(string $kind, string $contents, array &$errors): array
    {
        if ($contents === '' || strlen($contents) > 524_288 || ! mb_check_encoding($contents, 'UTF-8') || str_contains($contents, "\0")) {
            $errors[] = $this->error($kind, 1, 'file', 'invalid_file', 'Le fichier est vide, trop volumineux ou invalide.');

            return [];
        }

        $contents = preg_replace('/^\xEF\xBB\xBF/', '', $contents) ?? $contents;
        $stream = fopen('php://temp', 'r+');
        if ($stream === false) {
            throw new \RuntimeException('Unable to prepare import preview.');
        }
        fwrite($stream, $contents);
        rewind($stream);
        $header = fgetcsv($stream, separator: ',', escape: '\\');
        $headers = $header === false ? [] : array_map(fn ($value): string => mb_strtolower(trim((string) $value)), $header);
        if ($headers !== self::HEADERS[$kind]) {
            $errors[] = $this->error($kind, 1, 'header', 'invalid_headers', 'Les en-têtes doivent correspondre exactement au schéma canonique.');
            fclose($stream);

            return [];
        }

        $result = [];
        $seen = [];
        $line = 1;
        while (($values = fgetcsv($stream, separator: ',', escape: '\\')) !== false) {
            $line++;
            if ($values === [null] || count(array_filter($values, fn ($value): bool => trim((string) $value) !== '')) === 0) {
                continue;
            }
            if (count($result) >= 1000) {
                $errors[] = $this->error($kind, $line, 'file', 'row_limit_exceeded', 'Le fichier dépasse 1000 lignes.');
                break;
            }
            if (count($values) !== count($headers)) {
                $errors[] = $this->error($kind, $line, 'row', 'column_count_mismatch', 'Le nombre de valeurs ne correspond pas aux colonnes.');

                continue;
            }
            $row = array_combine($headers, array_map(fn ($value): string => trim((string) $value), $values));
            if ($row === false) {
                continue;
            }
            $externalId = $row['external_id'];
            if ($externalId === '' || mb_strlen($externalId) > 160) {
                $errors[] = $this->error($kind, $line, 'external_id', 'invalid_external_id', 'L’identifiant externe est invalide.');
            } elseif (isset($seen[mb_strtolower($externalId)])) {
                $errors[] = $this->error($kind, $line, 'external_id', 'duplicate_external_id', 'L’identifiant externe est dupliqué.');
            }
            $seen[mb_strtolower($externalId)] = $line;
            $canonicalHash = hash('sha256', json_encode($row, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
            $result[] = ['line' => $line, ...$row, 'canonical_record_hash' => $canonicalHash];
        }
        fclose($stream);

        return $result;
    }

    /**
     * @param  array<string, list<array<string, mixed>>>  $records
     * @param  list<array<string, mixed>>  $errors
     */
    private function validateRecords(
        string $workspaceId,
        string $sourceSystem,
        \DateTimeImmutable $exportedAt,
        array &$records,
        array &$errors,
    ): void {
        $clientIds = array_values(array_unique(array_merge(
            array_column($records['quotes'], 'client_external_id'),
            array_column($records['invoices'], 'client_external_id'),
        )));
        $clients = $this->previews->resolveClients($workspaceId, $sourceSystem, $clientIds);
        $invoiceRecords = [];
        foreach ($records['invoices'] as &$record) {
            $invoiceRecords[$record['external_id']] = &$record;
        }
        unset($record);

        foreach (['quotes', 'invoices'] as $kind) {
            $existing = $this->previews->existingIdentities($workspaceId, $sourceSystem, $kind);
            foreach ($records[$kind] as &$record) {
                if (! isset($clients[$record['client_external_id']])) {
                    $errors[] = $this->error($kind, $record['line'], 'client_external_id', 'unresolved_client', 'Le client historique est introuvable pour ce système source.');
                } else {
                    $record['client_id'] = $clients[$record['client_external_id']]['id'];
                    $record['client_display_name'] = $clients[$record['client_external_id']]['display_name'];
                }
                $this->validateAmounts($kind, $record, $errors);
                if (isset($existing[$record['external_id']]) && $existing[$record['external_id']] !== $record['canonical_record_hash']) {
                    $errors[] = $this->error($kind, $record['line'], 'external_id', 'identity_conflict', 'Cette identité historique existe avec un autre contenu.');
                }
            }
            unset($record);
        }

        $existingPayments = $this->previews->existingIdentities($workspaceId, $sourceSystem, 'payments');
        $applied = [];
        foreach ($records['payments'] as &$payment) {
            $invoice = $invoiceRecords[$payment['invoice_external_id']] ?? null;
            if ($invoice === null) {
                $errors[] = $this->error('payments', $payment['line'], 'invoice_external_id', 'unresolved_invoice', 'La facture référencée doit être présente dans le package.');
            } else {
                $payment['invoice_client_id'] = $invoice['client_id'] ?? null;
                $payment['invoice_currency'] = $invoice['currency'];
                $applied[$payment['invoice_external_id']] = ($applied[$payment['invoice_external_id']] ?? 0) + (int) $payment['amount_applied_cents'];
                if ($payment['currency'] !== $invoice['currency']) {
                    $errors[] = $this->error('payments', $payment['line'], 'currency', 'currency_mismatch', 'La devise du paiement diffère de la facture.');
                }
            }
            if (! ctype_digit($payment['amount_received_cents']) || ! ctype_digit($payment['amount_applied_cents'])
                || (int) $payment['amount_applied_cents'] <= 0
                || (int) $payment['amount_received_cents'] < (int) $payment['amount_applied_cents']) {
                $errors[] = $this->error('payments', $payment['line'], 'amount_applied_cents', 'invalid_amount', 'Les montants du paiement sont incohérents.');
            }
            if ($payment['status'] !== 'Active') {
                $errors[] = $this->error('payments', $payment['line'], 'status', 'invalid_status', 'Le statut du paiement doit être Active.');
            }
            $this->validateDate('payments', $payment, 'received_at', $exportedAt, $errors);
            if (isset($existingPayments[$payment['external_id']]) && $existingPayments[$payment['external_id']] !== $payment['canonical_record_hash']) {
                $errors[] = $this->error('payments', $payment['line'], 'external_id', 'identity_conflict', 'Cette identité historique existe avec un autre contenu.');
            }
        }
        unset($payment);

        foreach ($records['invoices'] as $invoice) {
            if (($applied[$invoice['external_id']] ?? 0) > (int) $invoice['gross_amount_cents']) {
                $errors[] = $this->error('invoices', $invoice['line'], 'gross_amount_cents', 'overpayment', 'Les paiements appliqués dépassent le montant de la facture.');
            }
        }

        foreach ($records['quotes'] as $quote) {
            if (! in_array($quote['status'], ['Sent', 'Accepted', 'Rejected', 'Withdrawn', 'Expired'], true)) {
                $errors[] = $this->error('quotes', $quote['line'], 'status', 'invalid_status', 'Le statut du devis est invalide.');
            }
            foreach (['created_at', 'sent_at', 'valid_until'] as $field) {
                $this->validateDate('quotes', $quote, $field, $exportedAt, $errors);
            }
            if ($quote['responded_at'] !== '') {
                $this->validateDate('quotes', $quote, 'responded_at', $exportedAt, $errors);
            }
        }
        foreach ($records['invoices'] as $invoice) {
            $this->validateDate('invoices', $invoice, 'issued_at', $exportedAt, $errors);
            $this->validateDate('invoices', $invoice, 'due_date', $exportedAt, $errors, false);
        }
    }

    /** @param list<array<string, mixed>> $errors */
    private function validateAmounts(string $kind, array $record, array &$errors): void
    {
        foreach (['net_amount_cents', 'tax_amount_cents', 'gross_amount_cents'] as $field) {
            if (! ctype_digit((string) $record[$field])) {
                $errors[] = $this->error($kind, $record['line'], $field, 'invalid_amount', 'Le montant doit être un entier positif ou nul.');
            }
        }
        if ((int) $record['net_amount_cents'] + (int) $record['tax_amount_cents'] !== (int) $record['gross_amount_cents']) {
            $errors[] = $this->error($kind, $record['line'], 'gross_amount_cents', 'calculation_conflict', 'Net + taxe doit être égal au brut.');
        }
        if (! preg_match('/^[A-Z]{3}$/', (string) $record['currency'])) {
            $errors[] = $this->error($kind, $record['line'], 'currency', 'invalid_currency', 'La devise doit être un code ISO en majuscules.');
        }
    }

    /** @param list<array<string, mixed>> $errors */
    private function validateDate(string $kind, array $record, string $field, \DateTimeImmutable $exportedAt, array &$errors, bool $mustPrecedeExport = true): void
    {
        try {
            $date = new \DateTimeImmutable((string) $record[$field]);
            if ($mustPrecedeExport && $date > $exportedAt) {
                throw new \DomainException;
            }
        } catch (\Throwable) {
            $errors[] = $this->error($kind, $record['line'], $field, 'invalid_date', 'La date est invalide ou postérieure à l’export.');
        }
    }

    /** @param list<array<string, mixed>> $records */
    private function canonicalRecords(array $records): array
    {
        return array_map(function (array $record): array {
            unset($record['line'], $record['validation_status'], $record['client_id'], $record['client_display_name'], $record['invoice_client_id'], $record['invoice_currency']);

            return $record;
        }, $records);
    }

    /** @return array{file: string, line: int, field: string, code: string, message: string} */
    private function error(string $file, int $line, string $field, string $code, string $message): array
    {
        return compact('file', 'line', 'field', 'code', 'message');
    }
}
