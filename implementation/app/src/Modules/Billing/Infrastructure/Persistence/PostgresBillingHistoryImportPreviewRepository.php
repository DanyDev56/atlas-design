<?php

declare(strict_types=1);

namespace Atlas\Modules\Billing\Infrastructure\Persistence;

use Illuminate\Support\Facades\DB;

final class PostgresBillingHistoryImportPreviewRepository
{
    /** @param array<string, mixed> $preview */
    public function insert(array $preview): void
    {
        foreach (['quotes', 'invoices', 'payments', 'validation_errors'] as $field) {
            $preview[$field] = json_encode($preview[$field], JSON_THROW_ON_ERROR);
        }

        DB::table('billing.history_import_previews')->insert($preview);
    }

    /** @return array<string, mixed>|null */
    public function findById(string $workspaceId, string $previewId): ?array
    {
        $row = DB::table('billing.history_import_previews')
            ->where('workspace_id', $workspaceId)
            ->where('id', $previewId)
            ->first();

        if ($row === null) {
            return null;
        }

        $result = (array) $row;
        foreach (['quotes', 'invoices', 'payments', 'validation_errors'] as $field) {
            $result[$field] = json_decode((string) $result[$field], true, 512, JSON_THROW_ON_ERROR);
        }
        $result['valid_for_confirmation'] = (int) $result['validation_error_count'] === 0
            && ((int) $result['quote_count'] + (int) $result['invoice_count'] + (int) $result['payment_count']) > 0
            && new \DateTimeImmutable((string) $result['expires_at']) > new \DateTimeImmutable('now');

        return $result;
    }

    /**
     * @param  list<string>  $externalIds
     * @return array<string, array{id: string, external_id: string, display_name: string}>
     */
    public function resolveClients(string $workspaceId, string $sourceSystem, array $externalIds): array
    {
        if ($externalIds === []) {
            return [];
        }

        return DB::table('crm.clients')
            ->select(['id', 'external_id', 'display_name'])
            ->where('workspace_id', $workspaceId)
            ->where('source_system', $sourceSystem)
            ->whereIn('external_id', array_values(array_unique($externalIds)))
            ->get()
            ->mapWithKeys(fn ($row): array => [(string) $row->external_id => [
                'id' => (string) $row->id,
                'external_id' => (string) $row->external_id,
                'display_name' => (string) $row->display_name,
            ]])
            ->all();
    }

    /** @return array<string, string> */
    public function existingIdentities(string $workspaceId, string $sourceSystem, string $table): array
    {
        return DB::table("billing.{$table}")
            ->where('workspace_id', $workspaceId)
            ->where('source_system', $sourceSystem)
            ->whereNotNull('external_id')
            ->pluck('canonical_record_hash', 'external_id')
            ->map(fn ($hash): string => (string) $hash)
            ->all();
    }
}
