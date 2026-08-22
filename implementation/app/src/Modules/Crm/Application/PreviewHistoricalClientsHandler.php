<?php

declare(strict_types=1);

namespace Atlas\Modules\Crm\Application;

use Atlas\Modules\Crm\Domain\Client;
use Atlas\Modules\Crm\Infrastructure\Persistence\PostgresClientHistoryImportPreviewRepository;
use Atlas\Platform\Security\WorkspaceAuthorizer;
use Illuminate\Support\Str;

final class PreviewHistoricalClientsHandler
{
    private const SCHEMA_VERSION = '1.0';

    private const MAX_BYTES = 262_144;

    private const MAX_ROWS = 100;

    private const REQUIRED_HEADERS = [
        'external_id',
        'kind',
        'status',
        'display_name',
        'source_created_at',
    ];

    private const OPTIONAL_HEADERS = [
        'legal_name',
        'email',
        'phone',
        'website',
    ];

    public function __construct(
        private readonly WorkspaceAuthorizer $authorizer,
        private readonly PostgresClientHistoryImportPreviewRepository $previews,
    ) {}

    /** @return array<string, mixed> */
    public function handle(
        string $actorUserId,
        string $workspaceId,
        string $sourceSystem,
        string $sourceExportedAt,
        string $contents,
    ): array {
        $this->authorizer->authorize($actorUserId, $workspaceId, 'crm.clients.import-history');

        $sourceSystem = trim($sourceSystem);

        if (! preg_match('/^[A-Za-z][A-Za-z0-9._-]{1,63}$/', $sourceSystem)) {
            throw new \DomainException('Source system invalid.');
        }

        if ($contents === '' || strlen($contents) > self::MAX_BYTES) {
            throw new \DomainException('Import file size invalid.');
        }

        if (! mb_check_encoding($contents, 'UTF-8') || str_contains($contents, "\0")) {
            throw new \DomainException('Import file must be safe UTF-8 text.');
        }

        try {
            $exportedAt = (new \DateTimeImmutable($sourceExportedAt))->setTimezone(new \DateTimeZone('UTC'));
        } catch (\Throwable) {
            throw new \DomainException('Source export date invalid.');
        }

        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        if ($exportedAt > $now) {
            throw new \DomainException('Source export date cannot be in the future.');
        }

        $parsed = $this->parseCsv($contents, $exportedAt);
        $records = $parsed['records'];
        $errors = $parsed['errors'];
        $duplicates = $this->findDuplicateCandidates($workspaceId, $sourceSystem, $records);
        $linesWithErrors = array_fill_keys(array_column($errors, 'line'), true);

        foreach ($records as &$record) {
            $record['validation_status'] = isset($linesWithErrors[$record['line']]) ? 'Invalid' : 'Valid';
        }
        unset($record);

        $packageHash = hash('sha256', json_encode([
            'schema_version' => self::SCHEMA_VERSION,
            'source_system' => $sourceSystem,
            'source_exported_at' => $exportedAt->format(DATE_ATOM),
            'records' => array_map(fn (array $record): array => [
                'external_id' => $record['external_id'],
                'kind' => $record['kind'],
                'status' => $record['status'],
                'profile' => $record['profile'],
                'source_created_at' => $record['source_created_at'],
                'canonical_record_hash' => $record['canonical_record_hash'],
            ], $records),
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        $previewId = (string) Str::uuid();
        $validRowCount = count(array_filter(
            $records,
            fn (array $record): bool => $record['validation_status'] === 'Valid',
        ));
        $expiresAt = $now->modify('+24 hours');
        $response = [
            'preview_id' => $previewId,
            'schema_version' => self::SCHEMA_VERSION,
            'source_system' => $sourceSystem,
            'source_exported_at' => $exportedAt->format(DATE_ATOM),
            'package_hash' => $packageHash,
            'row_count' => count($records),
            'valid_row_count' => $validRowCount,
            'validation_error_count' => count($errors),
            'duplicate_candidate_count' => count($duplicates),
            'valid_for_confirmation' => $records !== [] && $errors === [] && $duplicates === [],
            'records' => $records,
            'validation_errors' => $errors,
            'duplicate_candidates' => $duplicates,
            'expires_at' => $expiresAt->format(DATE_ATOM),
        ];

        $this->previews->insert([
            'id' => $previewId,
            'workspace_id' => $workspaceId,
            'source_system' => $sourceSystem,
            'source_exported_at' => $exportedAt->format('Y-m-d H:i:sP'),
            'schema_version' => self::SCHEMA_VERSION,
            'package_hash' => $packageHash,
            'row_count' => count($records),
            'valid_row_count' => $validRowCount,
            'validation_error_count' => count($errors),
            'records' => $records,
            'validation_errors' => $errors,
            'duplicate_candidates' => $duplicates,
            'created_by' => $actorUserId,
            'expires_at' => $expiresAt->format('Y-m-d H:i:sP'),
            'created_at' => $now->format('Y-m-d H:i:sP'),
        ]);

        return $response;
    }

    /**
     * @return array{
     *     records: list<array<string, mixed>>,
     *     errors: list<array{line: int, field: string, code: string, message: string}>
     * }
     */
    private function parseCsv(string $contents, \DateTimeImmutable $exportedAt): array
    {
        $contents = preg_replace('/^\xEF\xBB\xBF/', '', $contents) ?? $contents;
        $firstLine = strtok($contents, "\r\n") ?: '';
        $delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';
        $stream = fopen('php://temp', 'r+');

        if ($stream === false) {
            throw new \RuntimeException('Unable to prepare import preview.');
        }

        fwrite($stream, $contents);
        rewind($stream);
        $header = fgetcsv($stream, separator: $delimiter, escape: '\\');
        $errors = [];
        $records = [];

        if ($header === false) {
            fclose($stream);

            return [
                'records' => [],
                'errors' => [$this->error(1, 'file', 'missing_header', 'Le fichier ne contient aucun en-tête.')],
            ];
        }

        $headers = array_map(fn ($value): string => mb_strtolower(trim((string) $value)), $header);
        $allowedHeaders = [...self::REQUIRED_HEADERS, ...self::OPTIONAL_HEADERS];

        foreach (self::REQUIRED_HEADERS as $requiredHeader) {
            if (! in_array($requiredHeader, $headers, true)) {
                $errors[] = $this->error(1, $requiredHeader, 'missing_column', "La colonne {$requiredHeader} est obligatoire.");
            }
        }

        foreach ($headers as $column) {
            if ($column === '' || ! in_array($column, $allowedHeaders, true)) {
                $errors[] = $this->error(1, $column ?: 'header', 'unknown_column', 'Le fichier contient une colonne inconnue.');
            }
        }

        if (count($headers) !== count(array_unique($headers))) {
            $errors[] = $this->error(1, 'header', 'duplicate_column', 'Une colonne est présente plusieurs fois.');
        }

        if ($errors !== []) {
            fclose($stream);

            return ['records' => [], 'errors' => $errors];
        }

        $line = 1;
        $seenExternalIds = [];

        while (($values = fgetcsv($stream, separator: $delimiter, escape: '\\')) !== false) {
            $line++;

            if ($values === [null] || count(array_filter($values, fn ($value): bool => trim((string) $value) !== '')) === 0) {
                continue;
            }

            if (count($records) >= self::MAX_ROWS) {
                $errors[] = $this->error($line, 'file', 'row_limit_exceeded', 'Le fichier dépasse la limite de 100 clients.');
                break;
            }

            if (count($values) !== count($headers)) {
                $errors[] = $this->error($line, 'row', 'column_count_mismatch', 'Le nombre de valeurs ne correspond pas aux colonnes.');

                continue;
            }

            $row = array_combine($headers, array_map(fn ($value): string => trim((string) $value), $values));

            if ($row === false) {
                $errors[] = $this->error($line, 'row', 'invalid_row', 'La ligne ne peut pas être interprétée.');

                continue;
            }

            $record = $this->normalizeRecord($line, $row, $exportedAt, $errors);
            $externalId = mb_strtolower($record['external_id']);

            if ($externalId !== '' && isset($seenExternalIds[$externalId])) {
                $errors[] = $this->error($line, 'external_id', 'duplicate_external_id', 'Cet identifiant externe est déjà présent dans le fichier.');
                $errors[] = $this->error($seenExternalIds[$externalId], 'external_id', 'duplicate_external_id', 'Cet identifiant externe est présent plusieurs fois.');
            } elseif ($externalId !== '') {
                $seenExternalIds[$externalId] = $line;
            }

            $records[] = $record;
        }

        fclose($stream);

        return ['records' => $records, 'errors' => $errors];
    }

    /**
     * @param  array<string, string>  $row
     * @param  list<array{line: int, field: string, code: string, message: string}>  $errors
     * @return array<string, mixed>
     */
    private function normalizeRecord(
        int $line,
        array $row,
        \DateTimeImmutable $exportedAt,
        array &$errors,
    ): array {
        $externalId = $row['external_id'];
        $kind = $row['kind'];
        $status = $row['status'];
        $displayName = $row['display_name'];
        $legalName = $this->nullable($row['legal_name'] ?? '');
        $email = $this->nullable($row['email'] ?? '');
        $phone = $this->nullable($row['phone'] ?? '');
        $website = $this->nullable($row['website'] ?? '');
        $sourceCreatedAt = $this->parseSourceDate($row['source_created_at']);

        if ($externalId === '' || mb_strlen($externalId) > 160) {
            $errors[] = $this->error($line, 'external_id', 'invalid_external_id', 'L’identifiant externe est obligatoire et limité à 160 caractères.');
        }

        if (! in_array($kind, [Client::KIND_INDIVIDUAL, Client::KIND_ORGANIZATION], true)) {
            $errors[] = $this->error($line, 'kind', 'invalid_kind', 'Le type doit être Individual ou Organization.');
        }

        if (! in_array($status, [Client::STATUS_ACTIVE, Client::STATUS_ARCHIVED], true)) {
            $errors[] = $this->error($line, 'status', 'invalid_status', 'Le statut doit être Active ou Archived.');
        }

        if (mb_strlen($displayName) < 2 || mb_strlen($displayName) > 160) {
            $errors[] = $this->error($line, 'display_name', 'invalid_display_name', 'Le nom affiché doit contenir entre 2 et 160 caractères.');
        }

        if ($legalName !== null && mb_strlen($legalName) > 160) {
            $errors[] = $this->error($line, 'legal_name', 'invalid_legal_name', 'La raison sociale est limitée à 160 caractères.');
        }

        if ($email !== null && (mb_strlen($email) > 254 || filter_var($email, FILTER_VALIDATE_EMAIL) === false)) {
            $errors[] = $this->error($line, 'email', 'invalid_email', 'L’adresse e-mail est invalide.');
        }

        if ($phone !== null && mb_strlen($phone) > 50) {
            $errors[] = $this->error($line, 'phone', 'invalid_phone', 'Le téléphone est limité à 50 caractères.');
        }

        if ($website !== null && (! $this->isHttpUrl($website) || mb_strlen($website) > 2048)) {
            $errors[] = $this->error($line, 'website', 'invalid_website', 'Le site web doit être une URL HTTP ou HTTPS valide.');
        }

        if ($sourceCreatedAt === null) {
            $errors[] = $this->error($line, 'source_created_at', 'invalid_source_date', 'La date source doit être un instant ISO 8601 avec fuseau horaire.');
        } elseif ($sourceCreatedAt > $exportedAt) {
            $errors[] = $this->error($line, 'source_created_at', 'source_date_after_export', 'La date source ne peut pas être postérieure à l’export.');
        }

        $profile = array_filter([
            'display_name' => $displayName,
            'legal_name' => $legalName,
            'email' => $email,
            'phone' => $phone,
            'website' => $website,
        ], fn ($value): bool => $value !== null);
        $canonical = [
            'external_id' => $externalId,
            'kind' => $kind,
            'status' => $status,
            'profile' => $profile,
            'source_created_at' => $sourceCreatedAt?->setTimezone(new \DateTimeZone('UTC'))->format(DATE_ATOM)
                ?? $row['source_created_at'],
        ];

        return [
            'line' => $line,
            ...$canonical,
            'canonical_record_hash' => hash('sha256', json_encode(
                $canonical,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
            )),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $records
     * @return list<array<string, mixed>>
     */
    private function findDuplicateCandidates(string $workspaceId, string $sourceSystem, array $records): array
    {
        $duplicates = [];
        $packageNames = [];

        foreach ($records as $record) {
            $normalizedName = mb_strtolower($record['profile']['display_name']);

            if (isset($packageNames[$normalizedName])) {
                $duplicates[] = [
                    'line' => $record['line'],
                    'display_name' => $record['profile']['display_name'],
                    'kind' => 'Package',
                    'matched_line' => $packageNames[$normalizedName],
                ];
            } else {
                $packageNames[$normalizedName] = $record['line'];
            }
        }

        $historicalIdentities = $this->previews->findClientsByHistoricalIdentity(
            $workspaceId,
            $sourceSystem,
            array_values(array_filter(array_map(
                fn (array $record): string => (string) ($record['external_id'] ?? ''),
                $records,
            ))),
        );
        $historicalByExternalId = [];

        foreach ($historicalIdentities as $client) {
            $historicalByExternalId[$client['external_id']] = $client;
        }

        foreach ($records as $record) {
            $externalId = (string) ($record['external_id'] ?? '');

            if ($externalId === '' || ! isset($historicalByExternalId[$externalId])) {
                continue;
            }

            $matched = $historicalByExternalId[$externalId];

            if ($matched['canonical_record_hash'] === ($record['canonical_record_hash'] ?? null)) {
                continue;
            }

            $duplicates[] = [
                'line' => $record['line'],
                'display_name' => $record['profile']['display_name'],
                'kind' => 'ConflictingHistoricalIdentity',
                'matched_client_id' => $matched['id'],
                'matched_external_id' => $externalId,
            ];
        }

        $existingClients = $this->previews->findClientsByNormalizedDisplayNames(
            $workspaceId,
            array_keys($packageNames),
        );
        $existingByName = [];

        foreach ($existingClients as $client) {
            $existingByName[mb_strtolower($client['display_name'])] = $client;
        }

        foreach ($records as $record) {
            $normalizedName = mb_strtolower($record['profile']['display_name']);

            if (! isset($existingByName[$normalizedName])) {
                continue;
            }

            $matchedClientId = $existingByName[$normalizedName]['id'];
            $externalId = (string) ($record['external_id'] ?? '');
            $historicalMatch = $historicalByExternalId[$externalId] ?? null;

            if ($historicalMatch !== null && $historicalMatch['id'] === $matchedClientId) {
                continue;
            }

            $duplicates[] = [
                'line' => $record['line'],
                'display_name' => $record['profile']['display_name'],
                'kind' => 'ExistingClient',
                'matched_client_id' => $matchedClientId,
                'matched_display_name' => $existingByName[$normalizedName]['display_name'],
            ];
        }

        return $duplicates;
    }

    private function parseSourceDate(string $value): ?\DateTimeImmutable
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:\d{2})$/', $value)) {
            return null;
        }

        try {
            return new \DateTimeImmutable($value);
        } catch (\Throwable) {
            return null;
        }
    }

    private function nullable(string $value): ?string
    {
        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private function isHttpUrl(string $value): bool
    {
        if (filter_var($value, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        return in_array(parse_url($value, PHP_URL_SCHEME), ['http', 'https'], true);
    }

    /** @return array{line: int, field: string, code: string, message: string} */
    private function error(int $line, string $field, string $code, string $message): array
    {
        return compact('line', 'field', 'code', 'message');
    }
}
