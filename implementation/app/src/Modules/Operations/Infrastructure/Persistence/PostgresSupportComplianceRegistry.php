<?php

declare(strict_types=1);

namespace Atlas\Modules\Operations\Infrastructure\Persistence;

use Atlas\Platform\Support\UuidGenerator;
use Illuminate\Support\Facades\DB;

final class PostgresSupportComplianceRegistry
{
    private const array CATEGORIES = ['Access', 'Security', 'Billing', 'DataRequest', 'Product', 'Other'];

    private const array SEVERITIES = ['P0', 'P1', 'P2', 'P3'];

    private const array DATA_REQUEST_TYPES = ['Access', 'Rectification', 'Erasure', 'Restriction', 'Objection', 'Portability'];

    /** @return array{reference: string, requester_verified: bool, ownership_verified: bool, response_due_at: string} */
    public function openCase(
        string $workspaceId,
        string $requesterEmail,
        string $category,
        string $severity,
        string $summaryCode,
        \DateTimeImmutable $openedAt,
    ): array {
        $category = match (strtolower(trim($category))) {
            'access' => 'Access',
            'security' => 'Security',
            'billing' => 'Billing',
            'datarequest', 'data-request' => 'DataRequest',
            'product' => 'Product',
            'other' => 'Other',
            default => '',
        };
        $severity = strtoupper(trim($severity));
        $summaryCode = trim($summaryCode);
        if (! in_array($category, self::CATEGORIES, true) || ! in_array($severity, self::SEVERITIES, true)) {
            throw new \DomainException('Unsupported support category or severity.');
        }
        if (preg_match('/^[a-z0-9._-]{1,64}$/', $summaryCode) !== 1) {
            throw new \DomainException('Summary must be a structured code without personal data.');
        }

        $requester = $this->verifiedRequester($workspaceId, $requesterEmail);
        $reference = $this->reference('SUP');
        $caseId = UuidGenerator::generate();
        $dueAt = $severity === 'P0'
            ? $this->addBusinessHours($openedAt, 4)
            : $this->addBusinessDays($openedAt, ['P1' => 1, 'P2' => 2, 'P3' => 3][$severity]);
        $timestamp = $openedAt->format('Y-m-d H:i:sP');

        DB::table('operations.support_cases')->insert([
            'id' => $caseId,
            'reference' => $reference,
            'workspace_id' => $workspaceId,
            'requester_user_id' => $requester['user_id'],
            'category' => $category,
            'severity' => $severity,
            'status' => 'Open',
            'requester_verified' => true,
            'ownership_verified' => $requester['owner'],
            'summary_code' => $summaryCode,
            'response_due_at' => $dueAt->format('Y-m-d H:i:sP'),
            'opened_at' => $timestamp,
            'resolved_at' => null,
            'closed_at' => null,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
        DB::table('operations.support_case_events')->insert([
            'id' => UuidGenerator::generate(),
            'support_case_id' => $caseId,
            'event_type' => 'Opened',
            'status' => 'Open',
            'actor_operator_user_id' => null,
            'detail_code' => $summaryCode,
            'occurred_at' => $timestamp,
            'created_at' => $timestamp,
        ]);

        return [
            'reference' => $reference,
            'requester_verified' => true,
            'ownership_verified' => $requester['owner'],
            'response_due_at' => $dueAt->format(DATE_ATOM),
        ];
    }

    /** @return array{reference: string, support_reference: string, status: string, due_at: string} */
    public function recordDataRequest(
        string $workspaceId,
        string $requesterEmail,
        string $requestType,
        string $summaryCode,
        \DateTimeImmutable $receivedAt,
    ): array {
        $requestType = ucfirst(strtolower(trim($requestType)));
        if (! in_array($requestType, self::DATA_REQUEST_TYPES, true)) {
            throw new \DomainException('Unsupported data request type.');
        }
        $requester = $this->verifiedRequester($workspaceId, $requesterEmail);
        $requiresOwner = in_array($requestType, ['Access', 'Erasure', 'Portability'], true);
        if ($requiresOwner && ! $requester['owner']) {
            throw new \DomainException('This Workspace-level request requires a verified Owner.');
        }

        $support = $this->openCase($workspaceId, $requesterEmail, 'DataRequest', 'P2', $summaryCode, $receivedAt);
        $supportCaseId = (string) DB::table('operations.support_cases')->where('reference', $support['reference'])->value('id');
        $reference = $this->reference('DR');
        $targetDays = in_array($requestType, ['Restriction', 'Objection'], true) ? 2 : 5;
        $dueAt = $this->addBusinessDays($receivedAt, $targetDays);
        $timestamp = $receivedAt->format('Y-m-d H:i:sP');
        DB::table('operations.data_requests')->insert([
            'id' => UuidGenerator::generate(),
            'reference' => $reference,
            'support_case_id' => $supportCaseId,
            'workspace_id' => $workspaceId,
            'requester_user_id' => $requester['user_id'],
            'request_type' => $requestType,
            'status' => 'Qualified',
            'identity_verified_at' => $timestamp,
            'ownership_verified_at' => $requester['owner'] ? $timestamp : null,
            'due_at' => $dueAt->format('Y-m-d H:i:sP'),
            'decision_code' => null,
            'evidence_fingerprint' => null,
            'delivery_expires_at' => null,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);

        return [
            'reference' => $reference,
            'support_reference' => $support['reference'],
            'status' => 'Qualified',
            'due_at' => $dueAt->format(DATE_ATOM),
        ];
    }

    /** @return array{id: string, version: string, lifecycle: string} */
    public function registerPolicy(
        string $kind,
        string $version,
        string $lifecycle,
        string $contentFingerprint,
        ?string $approvalReference,
        ?\DateTimeImmutable $effectiveAt,
    ): array {
        if (! in_array($kind, ['BetaTerms', 'PrivacyNotice'], true)) {
            throw new \DomainException('Policy kind must be BetaTerms or PrivacyNotice.');
        }
        if (! in_array($lifecycle, ['Draft', 'Published'], true)) {
            throw new \DomainException('Policy lifecycle must be Draft or Published.');
        }
        if (preg_match('/^[a-zA-Z0-9._-]{1,64}$/', $version) !== 1 || preg_match('/^[a-f0-9]{64}$/', $contentFingerprint) !== 1) {
            throw new \DomainException('Policy version or SHA-256 fingerprint is invalid.');
        }
        if ($lifecycle === 'Published' && ($approvalReference === null || preg_match('/^[a-zA-Z0-9._:-]{3,128}$/', $approvalReference) !== 1)) {
            throw new \DomainException('A structured Legal approval reference is required to publish a policy.');
        }
        if ($lifecycle === 'Published' && $effectiveAt === null) {
            throw new \DomainException('An effective date is required to publish a policy.');
        }

        $id = UuidGenerator::generate();
        DB::table('operations.policy_versions')->insert([
            'id' => $id,
            'document_kind' => $kind,
            'version' => $version,
            'lifecycle' => $lifecycle,
            'content_fingerprint' => $contentFingerprint,
            'approval_fingerprint' => $approvalReference !== null ? hash('sha256', $approvalReference) : null,
            'effective_at' => $effectiveAt?->format('Y-m-d H:i:sP'),
            'recorded_at' => now('UTC'),
        ]);

        return ['id' => $id, 'version' => $version, 'lifecycle' => $lifecycle];
    }

    public function recordPolicyAcknowledgement(
        string $kind,
        string $version,
        string $requesterEmail,
        ?string $workspaceId,
        string $proofType,
        string $evidenceReference,
        \DateTimeImmutable $occurredAt,
    ): void {
        if (! in_array($proofType, ['Informed', 'Accepted'], true)) {
            throw new \DomainException('Proof type must be Informed or Accepted.');
        }
        $policy = DB::table('operations.policy_versions')
            ->where('document_kind', $kind)
            ->where('version', $version)
            ->select(['id', 'lifecycle'])
            ->first();
        if ($policy === null) {
            throw new \DomainException('The policy version is not registered.');
        }
        if ((string) $policy->lifecycle !== 'Published') {
            throw new \DomainException('Proof can only be recorded against a Published policy.');
        }
        $userId = $this->verifiedUser($requesterEmail);
        if ($workspaceId !== null) {
            $this->verifiedRequester($workspaceId, $requesterEmail);
        }
        $this->assertEvidenceReference($evidenceReference);

        DB::table('operations.policy_acknowledgements')->insert([
            'id' => UuidGenerator::generate(),
            'policy_version_id' => (string) $policy->id,
            'user_id' => $userId,
            'workspace_id' => $workspaceId,
            'proof_type' => $proofType,
            'evidence_fingerprint' => hash('sha256', $evidenceReference),
            'occurred_at' => $occurredAt->format('Y-m-d H:i:sP'),
            'recorded_at' => now('UTC'),
        ]);
    }

    public function recordResearchConsent(
        string $requesterEmail,
        ?string $workspaceId,
        string $purpose,
        string $decision,
        string $evidenceReference,
        \DateTimeImmutable $occurredAt,
    ): void {
        if (! in_array($purpose, ['Interview', 'Recording', 'PublicQuote'], true)
            || ! in_array($decision, ['Granted', 'Withdrawn'], true)) {
            throw new \DomainException('Unsupported consent purpose or decision.');
        }
        $userId = $this->verifiedUser($requesterEmail);
        if ($workspaceId !== null) {
            $this->verifiedRequester($workspaceId, $requesterEmail);
        }
        if ($decision === 'Withdrawn' && ! DB::table('operations.research_consent_events')
            ->where('user_id', $userId)->where('purpose', $purpose)->where('decision', 'Granted')->exists()) {
            throw new \DomainException('A consent cannot be withdrawn before it was granted.');
        }
        $this->assertEvidenceReference($evidenceReference);

        DB::table('operations.research_consent_events')->insert([
            'id' => UuidGenerator::generate(),
            'user_id' => $userId,
            'workspace_id' => $workspaceId,
            'purpose' => $purpose,
            'decision' => $decision,
            'evidence_fingerprint' => hash('sha256', $evidenceReference),
            'occurred_at' => $occurredAt->format('Y-m-d H:i:sP'),
            'recorded_at' => now('UTC'),
        ]);
    }

    /** @return array{user_id: string, owner: bool} */
    private function verifiedRequester(string $workspaceId, string $email): array
    {
        $workspaceActive = DB::table('workspace.workspaces')->where('id', $workspaceId)->where('status', 'Active')->exists();
        $row = DB::table('identity.users as user')
            ->join('identity.memberships as membership', function ($join) use ($workspaceId): void {
                $join->on('membership.user_id', '=', 'user.id')->where('membership.workspace_id', '=', $workspaceId);
            })
            ->join('identity.roles as role', 'role.id', '=', 'membership.role_id')
            ->where('user.email', strtolower(trim($email)))
            ->where('user.status', 'Active')
            ->where('user.email_verification_status', 'Verified')
            ->where('membership.status', 'Active')
            ->where('role.status', 'Active')
            ->select(['user.id', 'role.name'])
            ->first();
        if (! $workspaceActive || $row === null) {
            throw new \DomainException('A verified active requester with an active Workspace membership is required.');
        }

        return ['user_id' => (string) $row->id, 'owner' => strtolower((string) $row->name) === 'owner'];
    }

    private function verifiedUser(string $email): string
    {
        $userId = DB::table('identity.users')
            ->where('email', strtolower(trim($email)))
            ->where('status', 'Active')
            ->where('email_verification_status', 'Verified')
            ->value('id');
        if (! is_string($userId)) {
            throw new \DomainException('A verified active user is required.');
        }

        return $userId;
    }

    private function assertEvidenceReference(string $reference): void
    {
        if (preg_match('/^[a-zA-Z0-9._:-]{3,128}$/', $reference) !== 1) {
            throw new \DomainException('Evidence must be an opaque structured reference.');
        }
    }

    private function reference(string $prefix): string
    {
        return $prefix.'-'.strtoupper(substr(str_replace('-', '', UuidGenerator::generate()), 0, 12));
    }

    private function addBusinessDays(\DateTimeImmutable $from, int $days): \DateTimeImmutable
    {
        $sourceTimezone = $from->getTimezone();
        $date = $this->normalizeBusinessStart($from->setTimezone(new \DateTimeZone('Europe/Paris')));
        while ($days > 0) {
            $date = $date->add(new \DateInterval('P1D'));
            if ((int) $date->format('N') <= 5) {
                $days--;
            }
        }

        return $date->setTimezone($sourceTimezone);
    }

    private function addBusinessHours(\DateTimeImmutable $from, int $hours): \DateTimeImmutable
    {
        $sourceTimezone = $from->getTimezone();
        $date = $this->normalizeBusinessStart($from->setTimezone(new \DateTimeZone('Europe/Paris')));

        while ($hours > 0) {
            $date = $date->add(new \DateInterval('PT1H'));
            $hours--;
            if ($hours > 0 && (int) $date->format('G') >= 18) {
                $date = $this->normalizeBusinessStart($date->add(new \DateInterval('P1D'))->setTime(9, 0));
            }
        }

        return $date->setTimezone($sourceTimezone);
    }

    private function normalizeBusinessStart(\DateTimeImmutable $date): \DateTimeImmutable
    {
        while ((int) $date->format('N') > 5) {
            $date = $date->add(new \DateInterval('P1D'))->setTime(9, 0);
        }

        $hour = (int) $date->format('G');
        if ($hour < 9) {
            return $date->setTime(9, 0);
        }
        if ($hour >= 18) {
            return $this->normalizeBusinessStart($date->add(new \DateInterval('P1D'))->setTime(9, 0));
        }

        return $date;
    }
}
