<?php

declare(strict_types=1);

namespace Atlas\Modules\Operations\Infrastructure\Persistence;

use Atlas\Modules\Operations\Domain\BetaCohortCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class PostgresBetaCohortRegistry
{
    /** @return array{beta_code: string, pricing_cell: string} */
    public function enroll(
        string $betaCode,
        string $userId,
        string $workspaceId,
        ?string $pricingCell,
        string $packagingVersion,
        string $segment,
        string $channel,
        \DateTimeImmutable $invitedAt,
    ): array {
        $betaCode = strtoupper(trim($betaCode));
        if (preg_match('/^BETA-[0-9]{3}$/', $betaCode) !== 1) {
            throw new \DomainException('Beta code must match BETA-001.');
        }
        if ((int) DB::table('operations.beta_participants')->count() >= 5) {
            throw new \DomainException('The closed beta registry is limited to five participants.');
        }
        $eligibleUser = DB::table('identity.users')
            ->where('id', $userId)
            ->where('status', 'Active')
            ->where('email_verification_status', 'Verified')
            ->exists();
        $eligibleWorkspace = DB::table('workspace.workspaces')
            ->where('id', $workspaceId)
            ->where('status', 'Active')
            ->exists();
        $membership = DB::table('identity.memberships')
            ->where('user_id', $userId)
            ->where('workspace_id', $workspaceId)
            ->where('status', 'Active')
            ->exists();
        if (! $eligibleUser || ! $eligibleWorkspace || ! $membership) {
            throw new \DomainException('A verified active user and active membership in the target Workspace are required.');
        }

        $cell = $pricingCell !== null ? strtoupper($pricingCell) : $this->leastUsedCell();
        if (! in_array($cell, BetaCohortCatalog::PRICING_CELLS, true)) {
            throw new \DomainException('Pricing cell must be P19, P24 or P29.');
        }
        if (! in_array($segment, ['primary', 'secondary', 'anti-persona'], true)) {
            throw new \DomainException('Segment must be primary, secondary or anti-persona.');
        }
        if (! in_array($channel, ['recommendation', 'organic', 'community', 'outbound', 'other'], true)) {
            throw new \DomainException('Unknown recruitment channel.');
        }
        if (trim($packagingVersion) === '') {
            throw new \DomainException('Packaging version is required.');
        }
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        DB::table('operations.beta_participants')->insert([
            'id' => (string) Str::uuid(),
            'beta_code' => $betaCode,
            'user_id' => $userId,
            'workspace_id' => $workspaceId,
            'pricing_cell' => $cell,
            'packaging_version' => trim($packagingVersion),
            'segment' => trim($segment),
            'channel' => trim($channel),
            'status' => 'Active',
            'invited_at' => $invitedAt->format('Y-m-d H:i:sP'),
            'enrolled_at' => $now->format('Y-m-d H:i:sP'),
            'created_at' => $now->format('Y-m-d H:i:sP'),
            'updated_at' => $now->format('Y-m-d H:i:sP'),
        ]);

        return ['beta_code' => $betaCode, 'pricing_cell' => $cell];
    }

    public function review(
        string $betaCode,
        string $milestone,
        ?string $blockageCode,
        int $supportMinutes,
        string $nextAction,
        \DateTimeImmutable $reviewedAt,
    ): void {
        $participant = $this->participant($betaCode);
        $milestone = strtoupper($milestone);
        if (! in_array($milestone, BetaCohortCatalog::MILESTONES, true)) {
            throw new \DomainException('Milestone must be J2, J7, J14, J21 or J30.');
        }
        $latestSupport = (int) (DB::table('operations.beta_reviews')
            ->where('participant_id', $participant->id)
            ->max('support_minutes') ?? 0);
        if ($supportMinutes < $latestSupport) {
            throw new \DomainException('Cumulative support minutes cannot decrease.');
        }
        if (preg_match('/^[a-z0-9._-]{1,96}$/', trim($nextAction)) !== 1) {
            throw new \DomainException('Next action must be a structured code.');
        }
        if ($blockageCode !== null && trim($blockageCode) !== '' && preg_match('/^[a-z0-9._-]{1,64}$/', trim($blockageCode)) !== 1) {
            throw new \DomainException('Blockage must be a structured code.');
        }

        DB::table('operations.beta_reviews')->insert([
            'id' => (string) Str::uuid(),
            'participant_id' => $participant->id,
            'milestone' => $milestone,
            'blockage_code' => $blockageCode !== null && trim($blockageCode) !== '' ? trim($blockageCode) : null,
            'support_minutes' => $supportMinutes,
            'next_action' => trim($nextAction),
            'reviewed_at' => $reviewedAt->format('Y-m-d H:i:sP'),
            'created_at' => now(),
        ]);
    }

    public function recordDecision(
        string $betaCode,
        string $decision,
        string $primaryReason,
        string $preference,
        ?string $evidenceRef,
        \DateTimeImmutable $observedOn,
    ): void {
        $participant = $this->participant($betaCode);
        $decision = strtoupper($decision);
        if (! in_array($decision, BetaCohortCatalog::DECISIONS, true)) {
            throw new \DomainException('Unknown pricing decision.');
        }
        if (! in_array($preference, BetaCohortCatalog::PREFERENCES, true)) {
            throw new \DomainException('Preference must be Monthly, Annual or Indifferent.');
        }
        if (! in_array($primaryReason, ['price', 'value', 'scope', 'trust', 'context'], true)) {
            throw new \DomainException('Primary reason must be price, value, scope, trust or context.');
        }
        if ($evidenceRef !== null && trim($evidenceRef) !== '' && preg_match('/^[A-Za-z0-9._:\/-]{1,128}$/', trim($evidenceRef)) !== 1) {
            throw new \DomainException('Evidence reference must be opaque and contain no personal data.');
        }
        if ($decision === 'PAID' && ! DB::table('subscriptions.subscriptions')->where('workspace_id', $participant->workspace_id)->where('status', 'Active')->exists()) {
            throw new \DomainException('PAID requires an active verified subscription for the participant Workspace.');
        }

        DB::table('operations.pricing_observations')->insert([
            'id' => (string) Str::uuid(),
            'participant_id' => $participant->id,
            'observed_on' => $observedOn->format('Y-m-d'),
            'decision' => $decision,
            'primary_reason' => trim($primaryReason),
            'preference' => $preference,
            'evidence_ref' => $evidenceRef !== null && trim($evidenceRef) !== '' ? trim($evidenceRef) : null,
            'created_at' => now(),
        ]);
    }

    private function participant(string $betaCode): object
    {
        $participant = DB::table('operations.beta_participants')->where('beta_code', strtoupper($betaCode))->first();
        if ($participant === null) {
            throw new \DomainException('Unknown beta participant.');
        }

        return $participant;
    }

    private function leastUsedCell(): string
    {
        $counts = array_fill_keys(BetaCohortCatalog::PRICING_CELLS, 0);
        foreach (DB::table('operations.beta_participants')->selectRaw('pricing_cell, COUNT(*) AS aggregate')->groupBy('pricing_cell')->get() as $row) {
            $counts[(string) $row->pricing_cell] = (int) $row->aggregate;
        }
        asort($counts);

        return (string) array_key_first($counts);
    }
}
