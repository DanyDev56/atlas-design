<?php

declare(strict_types=1);

namespace Atlas\Composition\Demo;

use Illuminate\Support\Facades\DB;

final class SupportComplianceFixtureSeeder
{
    private const VERSION = '1';

    public function __construct(private readonly BetaCohortFixtureSeeder $betaCohort) {}

    /** @return array{support_cases: int, data_requests: int, policy_versions: int, policy_proofs: int, consent_events: int} */
    public function seed(): array
    {
        $participants = collect($this->betaCohort->seed())->keyBy('beta_code');
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        return DB::transaction(function () use ($participants, $now): array {
            $accounts = [];
            foreach ($participants as $code => $participant) {
                $userId = DB::table('identity.users')->where('email', $participant['email'])->value('id');
                if (! is_string($userId)) {
                    throw new \RuntimeException('The beta fixture '.$code.' has no verified user.');
                }
                $accounts[$code] = ['user_id' => $userId, 'workspace_id' => $participant['workspace_id']];
            }

            $cases = $this->supportCases();
            foreach ($cases as $case) {
                $account = $accounts[$case['participant']];
                $caseId = $this->uuid('support:'.$case['number']);
                $openedAt = $now->modify($case['opened']);
                $resolvedAt = $case['resolved'] !== null ? $now->modify($case['resolved']) : null;
                $closedAt = $case['closed'] !== null ? $now->modify($case['closed']) : null;

                DB::table('operations.support_cases')->insertOrIgnore([
                    'id' => $caseId,
                    'reference' => sprintf('SUP-DEMO%08d', $case['number']),
                    'workspace_id' => $account['workspace_id'],
                    'requester_user_id' => $account['user_id'],
                    'category' => $case['category'],
                    'severity' => $case['severity'],
                    'status' => $case['status'],
                    'requester_verified' => true,
                    'ownership_verified' => true,
                    'summary_code' => $case['summary'],
                    'response_due_at' => $now->modify($case['due'])->format('Y-m-d H:i:sP'),
                    'opened_at' => $openedAt->format('Y-m-d H:i:sP'),
                    'resolved_at' => $resolvedAt?->format('Y-m-d H:i:sP'),
                    'closed_at' => $closedAt?->format('Y-m-d H:i:sP'),
                    'created_at' => $openedAt->format('Y-m-d H:i:sP'),
                    'updated_at' => ($closedAt ?? $resolvedAt ?? $openedAt)->format('Y-m-d H:i:sP'),
                ]);
                $this->supportEvent($caseId, $case['number'], 'Opened', 'Open', $case['summary'], $openedAt);
                if ($case['status'] !== 'Open') {
                    $this->supportEvent(
                        $caseId,
                        $case['number'],
                        'StatusChanged',
                        $case['status'],
                        'fixture.status-'.$this->slug($case['status']),
                        $case['status'] === 'Closed'
                            ? ($closedAt ?? $openedAt->modify('+1 hour'))
                            : ($resolvedAt ?? $openedAt->modify('+1 hour')),
                    );
                }
            }

            $requests = $this->dataRequests();
            foreach ($requests as $request) {
                $account = $accounts[$request['participant']];
                DB::table('operations.data_requests')->insertOrIgnore([
                    'id' => $this->uuid('data-request:'.$request['number']),
                    'reference' => sprintf('DR-DEMO%08d', $request['number']),
                    'support_case_id' => $this->uuid('support:'.$request['support_number']),
                    'workspace_id' => $account['workspace_id'],
                    'requester_user_id' => $account['user_id'],
                    'request_type' => $request['type'],
                    'status' => $request['status'],
                    'identity_verified_at' => $now->modify($request['created'])->format('Y-m-d H:i:sP'),
                    'ownership_verified_at' => $request['owner_required'] ? $now->modify($request['created'])->format('Y-m-d H:i:sP') : null,
                    'due_at' => $now->modify($request['due'])->format('Y-m-d H:i:sP'),
                    'decision_code' => $request['decision'],
                    'evidence_fingerprint' => $request['decision'] !== null ? hash('sha256', 'fixture:'.$request['decision']) : null,
                    'delivery_expires_at' => null,
                    'created_at' => $now->modify($request['created'])->format('Y-m-d H:i:sP'),
                    'updated_at' => $now->modify($request['updated'])->format('Y-m-d H:i:sP'),
                ]);
            }

            $this->seedPoliciesAndProofs($accounts, $now);
            $this->seedConsents($accounts, $now);

            return [
                'support_cases' => count($cases),
                'data_requests' => count($requests),
                'policy_versions' => 3,
                'policy_proofs' => 7,
                'consent_events' => 7,
            ];
        });
    }

    /** @return list<array<string, int|string|null>> */
    private function supportCases(): array
    {
        return [
            ['number' => 1, 'participant' => 'BETA-001', 'category' => 'Security', 'severity' => 'P0', 'status' => 'Acknowledged', 'summary' => 'security.workspace-access', 'opened' => '-2 hours', 'due' => '+2 hours', 'resolved' => null, 'closed' => null],
            ['number' => 2, 'participant' => 'BETA-002', 'category' => 'Access', 'severity' => 'P1', 'status' => 'InProgress', 'summary' => 'access.login-blocked', 'opened' => '-3 weekdays', 'due' => '-2 weekdays', 'resolved' => null, 'closed' => null],
            ['number' => 3, 'participant' => 'BETA-003', 'category' => 'Product', 'severity' => 'P2', 'status' => 'WaitingRequester', 'summary' => 'product.import-question', 'opened' => '-1 day', 'due' => '+1 weekday', 'resolved' => null, 'closed' => null],
            ['number' => 4, 'participant' => 'BETA-004', 'category' => 'Billing', 'severity' => 'P3', 'status' => 'Resolved', 'summary' => 'billing.invoice-wording', 'opened' => '-5 weekdays', 'due' => '-2 weekdays', 'resolved' => '-1 weekday', 'closed' => null],
            ['number' => 5, 'participant' => 'BETA-001', 'category' => 'DataRequest', 'severity' => 'P2', 'status' => 'InProgress', 'summary' => 'rights.access-request', 'opened' => '-7 weekdays', 'due' => '-5 weekdays', 'resolved' => null, 'closed' => null],
            ['number' => 6, 'participant' => 'BETA-002', 'category' => 'DataRequest', 'severity' => 'P2', 'status' => 'Acknowledged', 'summary' => 'rights.rectification-request', 'opened' => '-1 weekday', 'due' => '+1 weekday', 'resolved' => null, 'closed' => null],
            ['number' => 7, 'participant' => 'BETA-003', 'category' => 'DataRequest', 'severity' => 'P2', 'status' => 'InProgress', 'summary' => 'rights.erasure-request', 'opened' => '-2 weekdays', 'due' => '+3 weekdays', 'resolved' => null, 'closed' => null],
            ['number' => 8, 'participant' => 'BETA-005', 'category' => 'DataRequest', 'severity' => 'P2', 'status' => 'Closed', 'summary' => 'rights.objection-request', 'opened' => '-8 weekdays', 'due' => '-6 weekdays', 'resolved' => '-6 weekdays', 'closed' => '-5 weekdays'],
        ];
    }

    /** @return list<array<string, int|string|bool|null>> */
    private function dataRequests(): array
    {
        return [
            ['number' => 1, 'support_number' => 5, 'participant' => 'BETA-001', 'type' => 'Access', 'status' => 'InPreparation', 'owner_required' => true, 'created' => '-7 weekdays', 'due' => '-2 weekdays', 'updated' => '-1 weekday', 'decision' => null],
            ['number' => 2, 'support_number' => 6, 'participant' => 'BETA-002', 'type' => 'Rectification', 'status' => 'Qualified', 'owner_required' => false, 'created' => '-1 weekday', 'due' => '+4 weekdays', 'updated' => '-1 weekday', 'decision' => null],
            ['number' => 3, 'support_number' => 7, 'participant' => 'BETA-003', 'type' => 'Erasure', 'status' => 'AwaitingApproval', 'owner_required' => true, 'created' => '-2 weekdays', 'due' => '+3 weekdays', 'updated' => '-1 day', 'decision' => null],
            ['number' => 4, 'support_number' => 8, 'participant' => 'BETA-005', 'type' => 'Objection', 'status' => 'Closed', 'owner_required' => false, 'created' => '-8 weekdays', 'due' => '-6 weekdays', 'updated' => '-5 weekdays', 'decision' => 'processing.restricted'],
        ];
    }

    /** @param array<string, array{user_id: string, workspace_id: string}> $accounts */
    private function seedPoliciesAndProofs(array $accounts, \DateTimeImmutable $now): void
    {
        $policies = [
            ['key' => 'terms-v1', 'kind' => 'BetaTerms', 'version' => 'beta-fixture-v1', 'lifecycle' => 'Published', 'effective' => '-30 days', 'approval' => 'LEGAL:fixture:terms-v1'],
            ['key' => 'privacy-v1', 'kind' => 'PrivacyNotice', 'version' => 'privacy-fixture-v1', 'lifecycle' => 'Published', 'effective' => '-30 days', 'approval' => 'LEGAL:fixture:privacy-v1'],
            ['key' => 'privacy-v2', 'kind' => 'PrivacyNotice', 'version' => 'privacy-fixture-v2-draft', 'lifecycle' => 'Draft', 'effective' => null, 'approval' => null],
        ];
        foreach ($policies as $policy) {
            DB::table('operations.policy_versions')->insertOrIgnore([
                'id' => $this->uuid('policy:'.$policy['key']),
                'document_kind' => $policy['kind'],
                'version' => $policy['version'],
                'lifecycle' => $policy['lifecycle'],
                'content_fingerprint' => hash('sha256', 'fixture-content:'.$policy['key']),
                'approval_fingerprint' => $policy['approval'] !== null ? hash('sha256', $policy['approval']) : null,
                'effective_at' => $policy['effective'] !== null ? $now->modify($policy['effective'])->format('Y-m-d H:i:sP') : null,
                'recorded_at' => $now->modify('-30 days')->format('Y-m-d H:i:sP'),
            ]);
        }

        $proofs = [
            ['terms-v1', 'BETA-001', 'Accepted'], ['terms-v1', 'BETA-002', 'Accepted'], ['terms-v1', 'BETA-003', 'Accepted'],
            ['terms-v1', 'BETA-004', 'Accepted'], ['terms-v1', 'BETA-005', 'Accepted'],
            ['privacy-v1', 'BETA-001', 'Informed'], ['privacy-v1', 'BETA-002', 'Informed'],
        ];
        foreach ($proofs as $index => [$policyKey, $participant, $proofType]) {
            $account = $accounts[$participant];
            DB::table('operations.policy_acknowledgements')->insertOrIgnore([
                'id' => $this->uuid('policy-proof:'.$index),
                'policy_version_id' => $this->uuid('policy:'.$policyKey),
                'user_id' => $account['user_id'],
                'workspace_id' => $account['workspace_id'],
                'proof_type' => $proofType,
                'evidence_fingerprint' => hash('sha256', 'fixture-proof:'.$policyKey.':'.$participant),
                'occurred_at' => $now->modify('-29 days')->format('Y-m-d H:i:sP'),
                'recorded_at' => $now->modify('-29 days')->format('Y-m-d H:i:sP'),
            ]);
        }
    }

    /** @param array<string, array{user_id: string, workspace_id: string}> $accounts */
    private function seedConsents(array $accounts, \DateTimeImmutable $now): void
    {
        $events = [
            ['BETA-001', 'Interview', 'Granted', '-20 days'],
            ['BETA-002', 'Interview', 'Granted', '-18 days'],
            ['BETA-003', 'Interview', 'Granted', '-15 days'],
            ['BETA-001', 'Recording', 'Granted', '-20 days'],
            ['BETA-004', 'Recording', 'Granted', '-10 days'],
            ['BETA-005', 'PublicQuote', 'Granted', '-8 days'],
            ['BETA-004', 'Recording', 'Withdrawn', '-2 days'],
        ];
        foreach ($events as $index => [$participant, $purpose, $decision, $occurred]) {
            $account = $accounts[$participant];
            DB::table('operations.research_consent_events')->insertOrIgnore([
                'id' => $this->uuid('consent:'.$index),
                'user_id' => $account['user_id'],
                'workspace_id' => $account['workspace_id'],
                'purpose' => $purpose,
                'decision' => $decision,
                'evidence_fingerprint' => hash('sha256', 'fixture-consent:'.$participant.':'.$purpose.':'.$decision),
                'occurred_at' => $now->modify($occurred)->format('Y-m-d H:i:sP'),
                'recorded_at' => $now->modify($occurred)->format('Y-m-d H:i:sP'),
            ]);
        }
    }

    private function supportEvent(string $caseId, int $number, string $type, string $status, string $detail, \DateTimeImmutable $occurredAt): void
    {
        DB::table('operations.support_case_events')->insertOrIgnore([
            'id' => $this->uuid('support-event:'.$number.':'.$type),
            'support_case_id' => $caseId,
            'event_type' => $type,
            'status' => $status,
            'actor_operator_user_id' => null,
            'detail_code' => $detail,
            'occurred_at' => $occurredAt->format('Y-m-d H:i:sP'),
            'created_at' => $occurredAt->format('Y-m-d H:i:sP'),
        ]);
    }

    private function uuid(string $key): string
    {
        $hex = hash('sha256', 'atlas:support-compliance-fixture:v'.self::VERSION.':'.$key);

        return sprintf('%s-%s-5%s-a%s-%s', substr($hex, 0, 8), substr($hex, 8, 4), substr($hex, 13, 3), substr($hex, 17, 3), substr($hex, 20, 12));
    }

    private function slug(string $value): string
    {
        return strtolower((string) preg_replace('/(?<!^)[A-Z]/', '-$0', $value));
    }
}
