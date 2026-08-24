<?php

declare(strict_types=1);

namespace Atlas\Modules\Operations\Domain;

final class OperatorPermissionCatalog
{
    public const BACKOFFICE_ACCESS = 'operations.backoffice.access';

    public const DASHBOARD_READ = 'operations.dashboard.read';

    public const BETA_READ = 'operations.beta.read';

    public const BETA_MANAGE = 'operations.beta.manage';

    public const METRICS_READ_PRODUCT = 'operations.metrics.read-product';

    public const METRICS_READ_FINANCIAL = 'operations.metrics.read-financial';

    public const WORKSPACES_READ_SUMMARY = 'operations.workspaces.read-summary';

    public const USERS_READ_SUMMARY = 'operations.users.read-summary';

    public const SENSITIVE_DATA_REVEAL = 'operations.sensitive-data.reveal';

    public const SESSIONS_READ = 'operations.sessions.read';

    public const SESSIONS_REVOKE = 'operations.sessions.revoke';

    public const SUPPORT_READ = 'operations.support.read';

    public const SUPPORT_MANAGE = 'operations.support.manage';

    public const COMPLIANCE_READ = 'operations.compliance.read';

    public const COMPLIANCE_MANAGE = 'operations.compliance.manage';

    public const EXPORTS_REQUEST = 'operations.exports.request';

    public const EXPORTS_APPROVE = 'operations.exports.approve';

    public const EXPORTS_DOWNLOAD = 'operations.exports.download';

    public const WORKSPACE_CLOSURE_REQUEST = 'operations.workspace-closure.request';

    public const WORKSPACE_CLOSURE_APPROVE = 'operations.workspace-closure.approve';

    public const OUTBOX_READ = 'operations.outbox.read';

    public const OUTBOX_RETRY = 'operations.outbox.retry';

    public const EMAIL_READ = 'operations.email.read';

    public const SUBSCRIPTIONS_READ = 'operations.subscriptions.read';

    public const AUDIT_READ = 'operations.audit.read';

    public const INCIDENTS_MANAGE = 'operations.incidents.manage';

    /** @return list<string> */
    public static function all(): array
    {
        return [
            self::BACKOFFICE_ACCESS,
            self::DASHBOARD_READ,
            self::BETA_READ,
            self::BETA_MANAGE,
            self::METRICS_READ_PRODUCT,
            self::METRICS_READ_FINANCIAL,
            self::WORKSPACES_READ_SUMMARY,
            self::USERS_READ_SUMMARY,
            self::SENSITIVE_DATA_REVEAL,
            self::SESSIONS_READ,
            self::SESSIONS_REVOKE,
            self::SUPPORT_READ,
            self::SUPPORT_MANAGE,
            self::COMPLIANCE_READ,
            self::COMPLIANCE_MANAGE,
            self::EXPORTS_REQUEST,
            self::EXPORTS_APPROVE,
            self::EXPORTS_DOWNLOAD,
            self::WORKSPACE_CLOSURE_REQUEST,
            self::WORKSPACE_CLOSURE_APPROVE,
            self::OUTBOX_READ,
            self::OUTBOX_RETRY,
            self::EMAIL_READ,
            self::SUBSCRIPTIONS_READ,
            self::AUDIT_READ,
            self::INCIDENTS_MANAGE,
        ];
    }

    /** @return list<string> */
    public static function initialReadOnly(): array
    {
        return [
            self::BACKOFFICE_ACCESS,
            self::DASHBOARD_READ,
        ];
    }

    /** @param list<string> $permissions */
    public static function normalize(array $permissions): array
    {
        $normalized = array_values(array_unique(array_map('trim', $permissions)));
        sort($normalized);

        foreach ($normalized as $permission) {
            if (! in_array($permission, self::all(), true)) {
                throw new \DomainException("Unknown operator permission [{$permission}].");
            }
        }

        if (! in_array(self::BACKOFFICE_ACCESS, $normalized, true)) {
            throw new \DomainException('Operator grants must include backoffice access.');
        }

        return $normalized;
    }
}
