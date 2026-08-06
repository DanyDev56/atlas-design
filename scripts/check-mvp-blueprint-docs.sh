#!/usr/bin/env bash

set -uo pipefail

repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
blueprint_root="$repo_root/evolution/blueprint"
roadmap_root="$repo_root/evolution/roadmap"
errors=0
checks=0

tmp_ids="$(mktemp)"

cleanup() {
  rm -f "$tmp_ids"
}
trap cleanup EXIT

fail() {
  printf 'FAIL: %s\n' "$1"
  errors=$((errors + 1))
}

pass() {
  printf 'PASS: %s\n' "$1"
  checks=$((checks + 1))
}

required_files=(
  "$roadmap_root/mvp-scope.md"
  "$roadmap_root/mvp-acceptance.md"
  "$blueprint_root/README.md"
  "$blueprint_root/modules.md"
  "$blueprint_root/product-map.md"
  "$blueprint_root/user-journeys.md"
  "$blueprint_root/lifecycle.md"
  "$blueprint_root/dashboard.md"
  "$blueprint_root/navigation.md"
  "$blueprint_root/permissions.md"
  "$blueprint_root/public-api.md"
  "$blueprint_root/integrations.md"
  "$blueprint_root/implementation-plan.md"
  "$blueprint_root/roadmap.md"
  "$repo_root/evolution/governance/quality-gates.md"
  "$repo_root/evolution/governance/consolidation-matrix.md"
)

for file in "${required_files[@]}"; do
  [[ -f "$file" ]] || fail "document requis absent: ${file#"$repo_root/"}"
done
if (( errors == 0 )); then
  pass "seize documents MVP et Blueprint présents"
fi

structure_errors=$errors
for file in "${required_files[@]}"; do
  [[ -f "$file" ]] || continue

  first_line="$(sed -n '1p' "$file")"
  frontmatter_delimiters="$(awk '/^---$/ {count++} count == 2 {print count; exit}' "$file")"
  if [[ "$first_line" != "---" || "$frontmatter_delimiters" != "2" ]]; then
    fail "front matter incomplet: ${file#"$repo_root/"}"
    continue
  fi

  document_id="$(awk 'BEGIN{block=0} /^---$/{block++; next} block==1 && /^id: /{sub(/^id: /, ""); print; exit}' "$file")"
  if [[ -z "$document_id" ]]; then
    fail "id absent: ${file#"$repo_root/"}"
  else
    printf '%s|%s\n' "$document_id" "${file#"$repo_root/"}" >> "$tmp_ids"
  fi

  for field in title status owner version last_updated; do
    if ! awk -v key="$field" 'BEGIN{block=0; found=0} /^---$/{block++; next} block==1 && index($0, key ": ")==1{found=1} END{exit !found}' "$file"; then
      fail "champ $field absent: ${file#"$repo_root/"}"
    fi
  done

  fence_count="$(awk '/^```/ {count++} END {print count+0}' "$file")"
  if (( fence_count % 2 != 0 )); then
    fail "bloc de code non fermé: ${file#"$repo_root/"}"
  fi

  while IFS= read -r reference; do
    [[ -z "$reference" ]] && continue
    if [[ ! -e "$(dirname "$file")/$reference" ]]; then
      fail "reference absente dans ${file#"$repo_root/"}: $reference"
    fi
  done < <(
    awk '
      BEGIN { block=0; refs=0 }
      /^---$/ { block++; next }
      block == 1 && /^references:/ { refs=1; next }
      block == 1 && refs && /^  - / { sub(/^  - /, ""); print; next }
      block == 1 && refs { refs=0 }
    ' "$file"
  )

  while IFS= read -r target; do
    [[ -z "$target" ]] && continue
    target="${target#<}"
    target="${target%>}"
    target="${target%%#*}"
    [[ -z "$target" || "$target" == http://* || "$target" == https://* || "$target" == mailto:* ]] && continue
    if [[ ! -e "$(dirname "$file")/$target" ]]; then
      fail "lien local absent dans ${file#"$repo_root/"}: $target"
    fi
  done < <(rg -o '\]\([^)]*\)' "$file" 2>/dev/null | sed -e 's/^](//' -e 's/)$//' || true)
done

if (( errors == structure_errors )); then
  pass "front matter, références, liens et blocs de code valides"
fi

duplicate_ids="$(cut -d'|' -f1 "$tmp_ids" | sort | uniq -d)"
if [[ -n "$duplicate_ids" ]]; then
  fail "IDs dupliqués: $(printf '%s' "$duplicate_ids" | paste -sd, -)"
else
  pass "IDs du périmètre uniques"
fi

journey_errors=$errors
for journey in MVP-J1 MVP-J2 MVP-J3; do
  rg -q "$journey" "$roadmap_root/mvp-acceptance.md" || fail "parcours absent de l'acceptation: $journey"
  rg -q "$journey" "$blueprint_root/user-journeys.md" || fail "parcours absent des journeys: $journey"
done
for state in NoData InsufficientData Unavailable; do
  rg -q "$state" "$roadmap_root/mvp-acceptance.md" "$blueprint_root/dashboard.md" || \
    fail "état transverse absent: $state"
done
if (( errors == journey_errors )); then
  pass "trois parcours et états d'absence explicitement acceptés"
fi

boundary_errors=$errors
rg -q "Analytics est indispensable.*MVP" "$roadmap_root/mvp-scope.md" || \
  fail "Analytics non déclaré comme moteur nécessaire au MVP"
rg -q "n'est pas un" "$blueprint_root/dashboard.md" || \
  fail "frontière Dashboard non explicite"
rg -q 'ne possède aucune vérité métier' "$blueprint_root/dashboard.md" || \
  fail "absence de propriété métier Dashboard non explicite"
rg -q 'Automation' "$roadmap_root/mvp-scope.md" "$blueprint_root/modules.md" "$blueprint_root/lifecycle.md" || \
  fail "Automation absente des exclusions"
rg -q 'post-MVP|hors MVP|Post-MVP' \
  "$roadmap_root/mvp-scope.md" "$blueprint_root/modules.md" "$blueprint_root/lifecycle.md" || \
  fail "Automation non différé"
if rg -q 'Recommendation[[:space:]]*->[[:space:]]*Automation' "$blueprint_root/lifecycle.md"; then
  fail "Automation encore terminale dans le lifecycle MVP"
fi
if (( errors == boundary_errors )); then
  pass "frontières Analytics, Dashboard et Automation cohérentes"
fi

contract_errors=$errors
declare -A contract_locations=(
  [CreateUser]="identity"
  [VerifyUserEmail]="identity"
  [CreateSession]="identity"
  [CreateRole]="identity"
  [GrantPermissionToRole]="identity"
  [CreateMembership]="identity"
  [CreateWorkspace]="workspace"
  [ActivateWorkspace]="workspace"
  [CreateClient]="crm"
  [AddContact]="crm"
  [CreateOpportunity]="crm"
  [QualifyOpportunity]="crm"
  [CreateQuote]="billing"
  [UpdateQuoteDraft]="billing"
  [SendQuote]="billing"
  [AcceptQuote]="billing"
  [CreateDepositInvoiceFromQuote]="billing"
  [CreateFinalInvoiceFromQuote]="billing"
  [IssueInvoice]="billing"
  [SendInvoice]="billing"
  [RecordPayment]="billing"
  [IngestSourceFact]="analytics"
  [PublishAnalyticsSnapshot]="analytics"
  [EvaluateBusinessHealth]="business-health"
  [EvaluateRecommendations]="advisor"
  [ProcessAdvisorNotificationSignal]="notifications"
)
for contract in "${!contract_locations[@]}"; do
  domain="${contract_locations[$contract]}"
  if ! rg -q "$contract" "$repo_root/fondation/domains/$domain" -g '*.md'; then
    fail "contrat Blueprint absent du domaine $domain: $contract"
  fi
done

events=(
  UserCreated UserEmailVerified UserActivated SessionCreated MembershipCreated
  WorkspaceCreated WorkspaceActivated WorkspaceAccessStateChanged
  ClientCreated ContactAdded OpportunityCreated OpportunityQualified OpportunityWon
  QuoteCreated QuoteSendRequested QuoteSent QuoteAccepted InvoiceCreated InvoiceIssued
  InvoiceDeliveryRequested InvoiceSent PaymentRecorded PaymentAppliedToInvoice
  InvoiceBalanceChanged InvoiceSettled InvoicePaid AnalyticsFactRecorded
  AnalyticsSnapshotPublished BusinessHealthAssessed RecommendationEvaluationCompleted
  NotificationPlanCompleted NotificationCreated
)
for event in "${events[@]}"; do
  if ! rg -q "$event" "$repo_root/fondation/domains" -g 'events.md' -g '*.md'; then
    fail "événement Blueprint absent des domaines: $event"
  fi
done
if (( errors == contract_errors )); then
  pass "commandes, processeurs et événements tracés vers leurs domaines"
fi

chain_errors=$errors
for marker in AnalyticsSnapshotPublished BusinessHealthAssessed RecommendationEvaluationCompleted NotificationPlanCompleted; do
  rg -q "$marker" "$blueprint_root/lifecycle.md" || fail "jalon absent du lifecycle: $marker"
done
rg -q 'RecommendationEvaluationCompleted' "$repo_root/fondation/domains/notifications" -g '*.md' || \
  fail "signal Advisor non reconnu par Notifications"
rg -q 'RecommendationGenerated' "$roadmap_root/mvp-acceptance.md" || \
  fail "non-déclenchement RecommendationGenerated non documenté"
if (( errors == chain_errors )); then
  pass "chaîne CRM-Billing-Analytics-Business Health-Advisor-Notifications complète"
fi

governance_errors=$errors
rg -q 'scripts/check-mvp-blueprint-docs.sh' "$repo_root/evolution/governance/quality-gates.md" || \
  fail "checker absent des quality gates"
rg -q 'modular monolith' "$blueprint_root/implementation-plan.md" || \
  fail "hypothèse de topologie absente"
rg -q 'confirmée dans un ADR' "$blueprint_root/implementation-plan.md" || \
  fail "gate ADR absent"
rg -q 'fixtures versionnées' "$blueprint_root/implementation-plan.md" || \
  fail "fixtures de référence absentes"
if (( errors == governance_errors )); then
  pass "gates d'architecture, fixtures et gouvernance présents"
fi

if (( errors > 0 )); then
  printf '\nMVP Blueprint documentation checks: %d failure(s), %d passed group(s).\n' "$errors" "$checks"
  exit 1
fi

printf '\nMVP Blueprint documentation checks: all %d groups passed.\n' "$checks"
