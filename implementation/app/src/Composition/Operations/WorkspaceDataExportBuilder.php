<?php

declare(strict_types=1);

namespace Atlas\Composition\Operations;

use Illuminate\Support\Facades\DB;

final class WorkspaceDataExportBuilder
{
    /** @return array<string, mixed> */
    public function build(string $workspaceId, string $requesterUserId, string $dataRequestReference, \DateTimeImmutable $generatedAt): array
    {
        $workspace = DB::table('workspace.workspaces')->where('id', $workspaceId)->first([
            'id', 'name', 'status', 'access_state', 'trading_name', 'activity_description', 'legal_name',
            'administrative_email', 'locale', 'timezone', 'default_currency', 'establishment_country',
            'created_at', 'updated_at', 'activated_at',
        ]);
        if ($workspace === null) {
            throw new \RuntimeException('Workspace export source is unavailable.');
        }

        $members = DB::table('identity.memberships as membership')
            ->join('identity.users as user', 'user.id', '=', 'membership.user_id')
            ->join('identity.roles as role', 'role.id', '=', 'membership.role_id')
            ->where('membership.workspace_id', $workspaceId)
            ->orderBy('membership.created_at')
            ->get([
                'membership.id as membership_id', 'membership.status as membership_status', 'membership.created_at as joined_at',
                'user.id as user_id', 'user.email', 'user.display_name', 'user.status as user_status',
                'user.email_verification_status', 'user.created_at as user_created_at', 'role.name as role_name',
            ]);

        return [
            'schema' => 'atlas.workspace-data-export',
            'schema_version' => 1,
            'generated_at' => $generatedAt->format(DATE_ATOM),
            'data_request_reference' => $dataRequestReference,
            'subject_user_id' => $requesterUserId,
            'workspace' => $this->row($workspace),
            'identity' => ['members' => $this->rows($members->all())],
            'crm' => [
                'clients' => $this->table('crm.clients', $workspaceId, [
                    'id', 'kind', 'display_name', 'profile', 'billing_profile', 'status', 'primary_contact_id', 'created_at', 'updated_at',
                ], ['profile', 'billing_profile']),
                'contacts' => $this->table('crm.contacts', $workspaceId, [
                    'id', 'client_id', 'profile', 'status', 'created_at',
                ], ['profile']),
                'opportunities' => $this->table('crm.opportunities', $workspaceId, [
                    'id', 'client_id', 'contact_id', 'title', 'estimated_amount_cents', 'currency', 'status', 'created_at', 'updated_at', 'qualified_at',
                ]),
                'activities' => $this->table('crm.activities', $workspaceId, [
                    'id', 'client_id', 'contact_id', 'opportunity_id', 'kind', 'summary', 'occurred_at', 'status', 'created_at', 'updated_at',
                ]),
            ],
            'billing' => [
                'quotes' => $this->table('billing.quotes', $workspaceId, [
                    'id', 'client_id', 'opportunity_id', 'status', 'lines', 'total_cents', 'currency', 'client_snapshot',
                    'opportunity_snapshot', 'created_at', 'updated_at', 'sent_at', 'accepted_at', 'valid_until',
                ], ['lines', 'client_snapshot', 'opportunity_snapshot']),
                'invoices' => $this->table('billing.invoices', $workspaceId, [
                    'id', 'client_id', 'quote_id', 'status', 'settlement_status', 'invoice_number', 'lines', 'total_cents',
                    'balance_cents', 'currency', 'client_snapshot', 'created_at', 'updated_at', 'issued_at', 'sent_at',
                ], ['lines', 'client_snapshot']),
                'payments' => $this->table('billing.payments', $workspaceId, [
                    'id', 'invoice_id', 'amount_cents', 'currency', 'reference', 'recorded_at', 'created_at',
                ]),
                'credit_notes' => $this->table('billing.credit_notes', $workspaceId, [
                    'id', 'invoice_id', 'client_id', 'status', 'credit_note_number', 'lines', 'total_cents', 'amount_applied_cents',
                    'unapplied_amount_cents', 'remainder_disposition', 'currency', 'client_snapshot', 'reason', 'created_at',
                    'updated_at', 'issued_at', 'applied_at', 'discarded_at', 'is_historical_import', 'source_system', 'external_id',
                ], ['lines', 'client_snapshot']),
            ],
            'subscription' => $this->subscription($workspaceId),
            'excluded' => [
                'authentication credentials, sessions and security tokens',
                'idempotency records, internal outbox payloads and operator audit data',
                'provider payment references and generated binary documents',
                'derived analytics that can be recalculated from exported source records',
            ],
        ];
    }

    /** @param list<string> $columns @param list<string> $jsonColumns @return list<array<string, mixed>> */
    private function table(string $table, string $workspaceId, array $columns, array $jsonColumns = []): array
    {
        $rows = DB::table($table)->where('workspace_id', $workspaceId)->orderBy('created_at')->get($columns)->all();

        return array_map(fn (object $row): array => $this->row($row, $jsonColumns), $rows);
    }

    /** @return array<string, mixed>|null */
    private function subscription(string $workspaceId): ?array
    {
        $row = DB::table('subscriptions.recurring_subscriptions as subscription')
            ->join('subscriptions.plans as plan', 'plan.id', '=', 'subscription.plan_id')
            ->join('subscriptions.plan_prices as price', 'price.id', '=', 'subscription.plan_price_id')
            ->where('subscription.workspace_id', $workspaceId)
            ->first([
                'subscription.status', 'subscription.current_period_start', 'subscription.current_period_end',
                'subscription.cancel_at_period_end', 'subscription.canceled_at', 'subscription.created_at',
                'plan.code as plan_code', 'plan.display_name as plan_name', 'price.billing_interval',
                'price.currency', 'price.amount_minor',
            ]);

        return $row === null ? null : $this->row($row);
    }

    /** @param list<object> $rows @return list<array<string, mixed>> */
    private function rows(array $rows): array
    {
        return array_map(fn (object $row): array => $this->row($row), $rows);
    }

    /** @param list<string> $jsonColumns @return array<string, mixed> */
    private function row(object $row, array $jsonColumns = []): array
    {
        $values = (array) $row;
        foreach ($jsonColumns as $column) {
            if (isset($values[$column]) && is_string($values[$column])) {
                $values[$column] = json_decode($values[$column], true, 512, JSON_THROW_ON_ERROR);
            }
        }

        return $values;
    }
}
