<?php

declare(strict_types=1);

namespace Atlas\Modules\Operations\Application;

use Atlas\Composition\Operations\WorkspaceDataExportBuilder;
use Atlas\Modules\Operations\Infrastructure\Persistence\PostgresOperatorAuditRepository;
use Atlas\Platform\Support\UuidGenerator;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Support\Facades\DB;

final class GenerateApprovedDataExportHandler
{
    public function __construct(
        private readonly WorkspaceDataExportBuilder $builder,
        private readonly Encrypter $encrypter,
        private readonly PostgresOperatorAuditRepository $audit,
    ) {}

    public function handle(string $exportId, ?string $correlationId = null): void
    {
        DB::transaction(function () use ($exportId, $correlationId): void {
            $export = DB::table('operations.data_exports as export')
                ->join('operations.data_requests as request', 'request.id', '=', 'export.data_request_id')
                ->where('export.id', $exportId)
                ->lockForUpdate()
                ->first([
                    'export.id', 'export.reference', 'export.data_request_id', 'export.workspace_id', 'export.requester_user_id', 'export.status',
                    'export.revision', 'request.reference as data_request_reference',
                ]);
            if ($export === null || in_array((string) $export->status, ['Ready', 'Delivered', 'Expired'], true)) {
                return;
            }
            if ((string) $export->status !== 'Generating') {
                throw new \RuntimeException('Data export is not ready for generation.');
            }

            $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
            $payload = $this->builder->build(
                (string) $export->workspace_id,
                (string) $export->requester_user_id,
                (string) $export->data_request_reference,
                $now,
            );
            $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            $maxBytes = max(1024, (int) config('operations.exports.max_bytes', 5_242_880));
            if (strlen($json) > $maxBytes) {
                $this->fail($export, 'export.size-limit-exceeded', $now, $correlationId);

                return;
            }
            $expiresAt = $now->modify('+'.max(1, (int) config('operations.exports.ttl_hours', 24)).' hours');
            $fingerprint = hash('sha256', $json);
            DB::table('operations.data_export_artifacts')->insert([
                'id' => UuidGenerator::generate(),
                'data_export_id' => (string) $export->id,
                'ciphertext' => $this->encrypter->encryptString($json),
                'content_fingerprint' => $fingerprint,
                'byte_size' => strlen($json),
                'media_type' => 'application/json',
                'generated_at' => $now->format('Y-m-d H:i:sP'),
                'expires_at' => $expiresAt->format('Y-m-d H:i:sP'),
            ]);
            DB::table('operations.data_exports')->where('id', $export->id)->update([
                'status' => 'Ready',
                'revision' => (int) $export->revision + 1,
                'ready_at' => $now->format('Y-m-d H:i:sP'),
                'expires_at' => $expiresAt->format('Y-m-d H:i:sP'),
                'updated_at' => $now->format('Y-m-d H:i:sP'),
            ]);
            DB::table('operations.data_requests')->where('id', $export->data_request_id)->update([
                'delivery_expires_at' => $expiresAt->format('Y-m-d H:i:sP'),
                'updated_at' => $now->format('Y-m-d H:i:sP'),
            ]);
            $this->event((string) $export->id, 'Generated', 'Ready', null, 'export.generated', $now);
            $this->audit->record(
                action: 'operator.data-export.generated',
                result: 'Succeeded',
                targetType: 'DataExport',
                targetIdHash: hash('sha256', (string) $export->reference),
                correlationId: $correlationId,
                metadata: ['byte_size' => strlen($json), 'expires_at' => $expiresAt->format(DATE_ATOM)],
                occurredAt: $now,
            );
        });
    }

    private function fail(object $export, string $code, \DateTimeImmutable $now, ?string $correlationId): void
    {
        DB::table('operations.data_exports')->where('id', $export->id)->update([
            'status' => 'Failed',
            'failure_code' => $code,
            'revision' => (int) $export->revision + 1,
            'updated_at' => $now->format('Y-m-d H:i:sP'),
        ]);
        $this->event((string) $export->id, 'Failed', 'Failed', null, $code, $now);
        $this->audit->record(
            action: 'operator.data-export.generation-failed',
            result: 'Failed',
            targetType: 'DataExport',
            targetIdHash: hash('sha256', (string) $export->reference),
            correlationId: $correlationId,
            metadata: ['failure_code' => $code],
            occurredAt: $now,
        );
    }

    private function event(string $exportId, string $type, string $status, ?string $actorId, string $detailCode, \DateTimeImmutable $now): void
    {
        DB::table('operations.data_export_events')->insert([
            'id' => UuidGenerator::generate(), 'data_export_id' => $exportId, 'event_type' => $type,
            'status' => $status, 'actor_operator_user_id' => $actorId, 'detail_code' => $detailCode,
            'occurred_at' => $now->format('Y-m-d H:i:sP'), 'created_at' => $now->format('Y-m-d H:i:sP'),
        ]);
    }
}
