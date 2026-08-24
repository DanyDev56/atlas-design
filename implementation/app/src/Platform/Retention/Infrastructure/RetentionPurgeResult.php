<?php

declare(strict_types=1);

namespace Atlas\Platform\Retention\Infrastructure;

final readonly class RetentionPurgeResult
{
    public function __construct(
        public int $sessions,
        public int $outboxDispatched,
        public int $idempotencyKeys,
        public int $dataExportArtifacts,
        public bool $dryRun,
    ) {}

    public function total(): int
    {
        return $this->sessions + $this->outboxDispatched + $this->idempotencyKeys + $this->dataExportArtifacts;
    }
}
