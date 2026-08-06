<?php

declare(strict_types=1);

namespace Atlas\Platform\Support;

use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;

final class CorrelationId
{
    /**
     * Outbox correlation_id is stored as PostgreSQL uuid.
     * Arbitrary trace keys are mapped deterministically via UUID v5.
     */
    public static function resolve(?string $headerValue): string
    {
        if ($headerValue === null || $headerValue === '') {
            return (string) Str::uuid();
        }

        if (Str::isUuid($headerValue)) {
            return Str::lower($headerValue);
        }

        return Uuid::uuid5(Uuid::NAMESPACE_URL, $headerValue)->toString();
    }
}
