#!/usr/bin/env bash

set -uo pipefail

repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
advisor_root="$repo_root/fondation/domains/advisor"
commands_root="$advisor_root/commands"
processors_root="$advisor_root/processors"
errors=0
checks=0

tmp_ids="$(mktemp)"
tmp_command_files="$(mktemp)"
tmp_command_links="$(mktemp)"
tmp_matrix_commands="$(mktemp)"
tmp_processor_files="$(mktemp)"
tmp_processor_links="$(mktemp)"
tmp_matrix_processors="$(mktemp)"
tmp_declared_invariants="$(mktemp)"
tmp_referenced_invariants="$(mktemp)"
tmp_catalog_events="$(mktemp)"
tmp_trace_events="$(mktemp)"
tmp_catalog_permissions="$(mktemp)"
tmp_trace_permissions="$(mktemp)"

cleanup() {
  rm -f "$tmp_ids" "$tmp_command_files" "$tmp_command_links" \
    "$tmp_matrix_commands" "$tmp_processor_files" "$tmp_processor_links" \
    "$tmp_matrix_processors" "$tmp_declared_invariants" \
    "$tmp_referenced_invariants" "$tmp_catalog_events" "$tmp_trace_events" \
    "$tmp_catalog_permissions" "$tmp_trace_permissions"
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

if [[ ! -d "$advisor_root" ]]; then
  printf 'Advisor directory not found: %s\n' "$advisor_root" >&2
  exit 2
fi

# Structure, references, links and code fences.
structure_errors=$errors
while IFS= read -r -d '' file; do
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
done < <(find "$advisor_root" -type f -name '*.md' -print0)

if (( errors == structure_errors )); then
  pass "front matter, références, liens et blocs de code valides"
fi

duplicate_ids="$(cut -d'|' -f1 "$tmp_ids" | sort | uniq -d)"
if [[ -n "$duplicate_ids" ]]; then
  fail "IDs de document dupliqués: $(printf '%s' "$duplicate_ids" | paste -sd, -)"
else
  pass "IDs de document uniques"
fi

# Command files, catalogue and matrix.
find "$commands_root" -maxdepth 1 -type f -name '*.md' ! -name 'README.md' -printf '%f\n' \
  | sed 's/\.md$//' | sort > "$tmp_command_files"
awk '/^## Catalogue$/ {catalog=1; next} /^## Conventions communes$/ {catalog=0} catalog {print}' \
  "$commands_root/README.md" \
  | sed -n 's/.*](\([^)]*\.md\)).*/\1/p' | sed -e 's#^.*/##' -e 's/\.md$//' \
  | sort -u > "$tmp_command_links"
awk -F'|' '
  /^## Traçabilité des commandes$/ {trace=1; next}
  /^## Traçabilité des processeurs$/ {trace=0}
  trace && $2 ~ /`[A-Z][A-Za-z]+`/ {
    value=$2; gsub(/^[[:space:]]*`|`[[:space:]]*$/, "", value); print value
  }
' "$advisor_root/consolidation-matrix.md" | sort -u > "$tmp_matrix_commands"

command_drift="$(comm -3 "$tmp_command_files" "$tmp_command_links"; comm -3 "$tmp_command_files" "$tmp_matrix_commands")"
if [[ -n "$command_drift" ]]; then
  fail "écart entre fichiers, catalogue et matrice des commandes: $(printf '%s' "$command_drift" | paste -sd, -)"
else
  pass "catalogue, fichiers et matrice des commandes alignés"
fi

# Processor files, catalogue and matrix.
find "$processors_root" -maxdepth 1 -type f -name '*.md' ! -name 'README.md' -printf '%f\n' \
  | sed 's/\.md$//' | sort > "$tmp_processor_files"
awk '/^## Catalogue$/ {catalog=1; next} /^## Conventions communes$/ {catalog=0} catalog {print}' \
  "$processors_root/README.md" \
  | sed -n 's/.*](\([^)]*\.md\)).*/\1/p' | sed -e 's#^.*/##' -e 's/\.md$//' \
  | sort -u > "$tmp_processor_links"
awk -F'|' '
  /^## Traçabilité des processeurs$/ {trace=1; next}
  /^---$/ && trace {trace=0}
  trace && $2 ~ /`[A-Z][A-Za-z]+`/ {
    value=$2; gsub(/^[[:space:]]*`|`[[:space:]]*$/, "", value); print value
  }
' "$advisor_root/consolidation-matrix.md" | sort -u > "$tmp_matrix_processors"

processor_drift="$(comm -3 "$tmp_processor_files" "$tmp_processor_links"; comm -3 "$tmp_processor_files" "$tmp_matrix_processors")"
if [[ -n "$processor_drift" ]]; then
  fail "écart entre fichiers, catalogue et matrice des processeurs: $(printf '%s' "$processor_drift" | paste -sd, -)"
else
  pass "catalogue, fichiers et matrice des processeurs alignés"
fi

# Invariants, concurrency and idempotency.
rg -o --no-filename 'ADV-INV-[0-9]{3}' "$advisor_root/invariants.md" | sort -u > "$tmp_declared_invariants"

intent_errors=$errors
while IFS= read -r intent_file; do
  rg -q 'ADV-INV-[0-9]{3}' "$intent_file" || \
    fail "aucun invariant référencé: ${intent_file#"$repo_root/"}"
  rg -o --no-filename 'ADV-INV-[0-9]{3}' "$intent_file" >> "$tmp_referenced_invariants" || true
  rg -qi 'idempoten' "$intent_file" || \
    fail "politique d'idempotence absente: ${intent_file#"$repo_root/"}"
  rg -q '[A-Za-z]+RequestId' "$intent_file" || \
    fail "RequestId absent: ${intent_file#"$repo_root/"}"

  case "$(basename "$intent_file")" in
    CompleteRecommendation.md|DismissRecommendation.md|ExpireRecommendation.md)
      rg -q 'ExpectedRevision' "$intent_file" || \
        fail "ExpectedRevision absente: ${intent_file#"$repo_root/"}"
      ;;
    EvaluateRecommendations.md)
      rg -q 'BusinessHealthAssessedEventId' "$intent_file" || \
        fail "causalité Business Health absente: ${intent_file#"$repo_root/"}"
      ;;
  esac
done < <(find "$commands_root" "$processors_root" -maxdepth 1 -type f -name '*.md' ! -name 'README.md' | sort)

if (( errors == intent_errors )); then
  pass "invariants, concurrence, causalité et idempotence documentés"
fi

sort -u -o "$tmp_referenced_invariants" "$tmp_referenced_invariants"
unknown_invariants="$(comm -13 "$tmp_declared_invariants" "$tmp_referenced_invariants")"
if [[ -n "$unknown_invariants" ]]; then
  fail "invariants référencés mais non déclarés: $(printf '%s' "$unknown_invariants" | paste -sd, -)"
else
  pass "références d'invariants valides"
fi

# Events and permissions used by the trace.
awk -F'|' '
  /^## Traçabilité des commandes$/ {trace=1; next}
  /^## Traçabilité des processeurs$/ {trace=1; next}
  /^---$/ && trace {trace=0}
  trace && $2 ~ /`[A-Z][A-Za-z]+`/ {print $6}
' "$advisor_root/consolidation-matrix.md" \
  | rg -o '`[A-Z][A-Za-z0-9]+`' | tr -d '`' | sort -u > "$tmp_trace_events"
awk -F'|' '$2 ~ /`(Recommendation|AdvisorOverview)[A-Z][A-Za-z0-9]+`/ {
  value=$2; gsub(/^[[:space:]]*`|`[[:space:]]*$/, "", value); print value
}' "$advisor_root/events.md" | sort -u > "$tmp_catalog_events"

event_drift="$(comm -3 "$tmp_catalog_events" "$tmp_trace_events")"
if [[ -n "$event_drift" ]]; then
  fail "écart entre événements et trace: $(printf '%s' "$event_drift" | paste -sd, -)"
else
  pass "Domain Events et producteurs entièrement tracés"
fi

awk -F'|' '
  /^## Traçabilité des commandes$/ {trace=1; next}
  /^## Traçabilité des processeurs$/ {trace=1; next}
  /^---$/ && trace {trace=0}
  trace && $2 ~ /`[A-Z][A-Za-z]+`/ {print $4}
' "$advisor_root/consolidation-matrix.md" \
  | rg -o 'advisor\.[a-z0-9.-]+' | sort -u > "$tmp_trace_permissions"
awk -F'|' '$2 ~ /`advisor\.[a-z0-9.-]+`/ {
  value=$2; gsub(/^[[:space:]]*`|`[[:space:]]*$/, "", value); print value
}' "$advisor_root/permissions.md" | sort -u > "$tmp_catalog_permissions"

unknown_permissions="$(comm -13 "$tmp_catalog_permissions" "$tmp_trace_permissions")"
if [[ -n "$unknown_permissions" ]]; then
  fail "capacités tracées mais absentes: $(printf '%s' "$unknown_permissions" | paste -sd, -)"
else
  pass "capacités de commande et processeur présentes dans le catalogue"
fi

# Policy, ranking and lifecycle.
policy_errors=$errors
for rule in CollectOverdueInvoices ReduceClientConcentration RebuildCommercialPipeline RestoreBillingMomentum AddressPrimaryAttention; do
  rg -q "\`$rule\`" "$advisor_root/recommendation-policy.md" || fail "RuleKey absente: $rule"
done
rg -q '| \*\*Total\*\* | \*\*5 règles\*\* | \*\*0\.\.3 Recommendations publiées\*\* |' \
  "$advisor_root/consolidation-matrix.md" || fail "total des règles ou limite de publication absent"
for weight in 0.40 0.30 0.20 0.10; do
  rg -q "$weight" "$advisor_root/recommendation-score.md" || fail "poids de rang absent: $weight"
done
rg -q 'roundHalfUp' "$advisor_root/recommendation-score.md" || fail "règle d'arrondi absente"
if (( errors == policy_errors )); then
  pass "cinq règles, score de rang et limite top trois cohérents"
fi

lifecycle_errors=$errors
for status in Generated Completed Dismissed Expired; do
  rg -q "$status" "$advisor_root/recommendation-lifecycle.md" "$advisor_root/value-objects.md" || \
    fail "RecommendationStatus absent: $status"
done
if rg -q '`Recommendation(Executed|Displayed|Opened|Clicked)`' "$advisor_root/events.md" "$commands_root" "$processors_root"; then
  fail "événement Advisor non canonique détecté"
fi
for converging_intent in \
  "$commands_root/CompleteRecommendation.md" \
  "$commands_root/DismissRecommendation.md" \
  "$processors_root/ExpireRecommendation.md" \
  "$processors_root/EvaluateRecommendations.md"; do
  rg -q 'RebuildAdvisorOverview' "$converging_intent" || \
    fail "convergence AdvisorOverview absente: ${converging_intent#"$repo_root/"}"
done
rg -q 'SourceBecameIneligible' "$advisor_root/invariants.md" "$advisor_root/workflows.md" || \
  fail "invalidation d'une source courante insuffisante absente"
rg -q 'AdvisorOverviewChanged' "$repo_root/fondation/domains/notifications/integrations.md" || \
  fail "convergence Advisor non reconnue par Notifications"
if (( errors == lifecycle_errors )); then
  pass "cycle de vie terminal et frontière de télémétrie cohérents"
fi

# Integration contracts and action capabilities.
contract_errors=$errors
rg -q 'getBusinessHealthAssessment' "$repo_root/fondation/domains/business-health/api.md" || \
  fail "contrat Business Health absent: getBusinessHealthAssessment"
rg -q 'getBusinessHealthAssessment' "$advisor_root/integrations.md" || \
  fail "contrat Business Health non reconnu par Advisor"
rg -q 'getBusinessHealthAssessment' "$repo_root/fondation/domains/business-health/integrations.md" || \
  fail "consommateur Advisor non reconnu par Business Health"
rg -q 'getWorkspaceAccessContext' "$repo_root/fondation/domains/workspace/api.md" || \
  fail "contrat Workspace absent: getWorkspaceAccessContext"
rg -q 'getWorkspaceAccessContext' "$advisor_root/integrations.md" || \
  fail "contrat Workspace non reconnu par Advisor"

for capability in crm.opportunities.read crm.opportunities.create; do
  rg -q "\`$capability\`" "$repo_root/fondation/domains/crm/permissions.md" || \
    fail "capacité CRM absente: $capability"
  rg -q "$capability" "$advisor_root/actions.md" "$advisor_root/integrations.md" || \
    fail "capacité CRM non reconnue par Advisor: $capability"
done
rg -q '`billing.invoices.read`' "$repo_root/fondation/domains/billing/permissions.md" || \
  fail "capacité Billing absente: billing.invoices.read"
rg -q 'billing.invoices.read' "$advisor_root/actions.md" "$advisor_root/integrations.md" || \
  fail "capacité Billing non reconnue par Advisor"
rg -q '`business-health.assessments.read`' "$repo_root/fondation/domains/business-health/permissions.md" || \
  fail "capacité Business Health absente: business-health.assessments.read"
if (( errors == contract_errors )); then
  pass "contrats Business Health–Workspace et capacités d'action symétriques"
fi

if rg -q 'getAnalyticsSnapshot|getInvoice\(|getOpportunity\(' \
  "$advisor_root/integrations.md" "$advisor_root/recommendation-engine.md" "$processors_root"; then
  fail "lecture directe d'un domaine interdit détectée"
else
  pass "aucune lecture directe Analytics, CRM ou Billing"
fi

map_errors=$errors
for map_file in \
  "$repo_root/fondation/domains/README.md" \
  "$repo_root/fondation/domain-map/context-map.md" \
  "$repo_root/fondation/domain-map/ownership.md" \
  "$repo_root/fondation/domain-map/dependencies.md"; do
  if ! rg -q '\bAdvisor\b' "$map_file"; then
    fail "Advisor absent de la cartographie: ${map_file#"$repo_root/"}"
  fi
done
if (( errors == map_errors )); then
  pass "Advisor intégré aux cartes globales"
fi

if (( errors > 0 )); then
  printf '\nAdvisor documentation checks: %d failure(s), %d passed group(s).\n' "$errors" "$checks"
  exit 1
fi

printf '\nAdvisor documentation checks: all %d groups passed.\n' "$checks"
