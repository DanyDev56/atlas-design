<?php

declare(strict_types=1);

namespace Atlas\Modules\Identity\Application;

final class WorkspaceRoleDefaults
{
    /** @var list<string> */
    public const MEMBER_PERMISSIONS = [
        'workspace.members.read',
        'workspace.settings.read',
        'workspace.profile.read',
        'workspace.preferences.read',
        'workspace.billing-identity.read',
        'crm.clients.read',
        'crm.clients.create',
        'crm.clients.update-profile',
        'crm.clients.update-billing-profile',
        'crm.contacts.read',
        'crm.contacts.create',
        'crm.contacts.update',
        'crm.contacts.change-primary',
        'crm.opportunities.read',
        'crm.opportunities.create',
        'crm.opportunities.update',
        'crm.opportunities.qualify',
        'crm.opportunities.win',
        'crm.opportunities.lose',
        'crm.activities.read',
        'crm.activities.record',
        'billing.quotes.read',
        'billing.quotes.create',
        'billing.quotes.update-draft',
        'billing.quotes.send',
        'billing.invoices.read',
        'billing.invoices.create',
        'billing.invoices.update-draft',
        'billing.invoices.issue',
        'billing.invoices.send',
        'billing.invoices.remind',
        'billing.payments.read',
        'billing.payments.record',
        'billing.credit-notes.read',
        'billing.credit-notes.create',
        'billing.credit-notes.update-draft',
        'billing.credit-notes.issue',
        'billing.credit-notes.apply',
        'analytics.metrics.read',
        'business-health.assessments.read',
        'advisor.recommendations.read',
        'advisor.recommendations.complete',
        'advisor.recommendations.dismiss',
        'notifications.inbox.read',
        'notifications.inbox.mark-read',
        'notifications.preferences.read',
        'notifications.preferences.change',
    ];

    private function __construct() {}
}
