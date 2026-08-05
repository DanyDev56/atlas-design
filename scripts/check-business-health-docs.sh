#!/usr/bin/env bash

set -uo pipefail

repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
health_root="$repo_root/fondation/domains/business-health"
processors_root="$health_root/processors"
errors=0
checks=0

tmp_ids="$(mktemp)"
tmp_processor_files="$(mktemp)"
tmp_processor_links="$(mktemp)"
tmp_matrix_processors="$(mktemp)"
tmp_declared_invariants="$(mktemp)"
tmp_referenced_invariants="$(mktemp)"
tmp_catalog_events="$(mktemp)"
tmp_trace_events="$(mktemp)"
tmp_catalog_permissions="$(mktemp)"
tmp_trace_permissions="$(mktemp)"
tmp_policy_metrics="$(mktemp)"

cleanup() {
  rm -f "$tmp_ids" "$tmp_processor_files" "$tmp_processor_links" \
    "$tmp_matrix_processors" "$tmp_declared_invariants" \
    "$tmp_referenced_invariants" "$tmp_catalog_events" "$tmp_trace_events" \
    "$tmp_catalog_permissions" "$tmp_trace_permissions" "$tmp_policy_metrics"
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

if [[ ! -d "$health_root" ]]; then
  printf 'Business Health directory not found: %s\n' "$health_root" >&2
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
done < <(find "$health_root" -type f -name '*.md' -print0)

if (( errors == structure_errors )); then
  pass "front matter, références, liens et blocs de code valides"
fi

duplicate_ids="$(cut -d'|' -f1 "$tmp_ids" | sort | uniq -d)"
if [[ -n "$duplicate_ids" ]]; then
  fail "IDs de document dupliqués: $(printf '%s' "$duplicate_ids" | paste -sd, -)"
else
  pass "IDs de document uniques"
fi

# Files, processor catalogue and traceability matrix must match.
find "$processors_root" -maxdepth 1 -type f -name '*.md' ! -name 'README.md' -printf '%f\n' \
  | sed 's/\.md$//' | sort > "$tmp_processor_files"

awk '
  /^## Catalogue$/ { catalog=1; next }
  /^## Conventions communes$/ { catalog=0 }
  catalog { print }
' "$processors_root/README.md" \
  | sed -n 's/.*](\([^)]*\.md\)).*/\1/p' \
  | sed -e 's#^.*/##' -e 's/\.md$//' \
  | sort -u > "$tmp_processor_links"

awk -F'|' '
  /^## Traçabilité des processeurs$/ { trace=1; next }
  /^---$/ && trace { trace=0 }
  trace && $2 ~ /`[A-Z][A-Za-z]+`/ {
    value=$2; gsub(/^[[:space:]]*`|`[[:space:]]*$/, "", value); print value
  }
' "$health_root/consolidation-matrix.md" | sort -u > "$tmp_matrix_processors"

missing_links="$(comm -23 "$tmp_processor_files" "$tmp_processor_links")"
unknown_links="$(comm -13 "$tmp_processor_files" "$tmp_processor_links")"
matrix_drift="$(comm -3 "$tmp_processor_files" "$tmp_matrix_processors")"
if [[ -n "$missing_links" || -n "$unknown_links" || -n "$matrix_drift" ]]; then
  [[ -n "$missing_links" ]] && fail "processeurs absents du catalogue: $(printf '%s' "$missing_links" | paste -sd, -)"
  [[ -n "$unknown_links" ]] && fail "liens de processeur inconnus: $(printf '%s' "$unknown_links" | paste -sd, -)"
  [[ -n "$matrix_drift" ]] && fail "écart entre processeurs et matrice: $(printf '%s' "$matrix_drift" | paste -sd, -)"
else
  pass "catalogue, fichiers et matrice des processeurs alignés"
fi

# Invariants and idempotency.
rg -o --no-filename 'BHL-INV-[0-9]{3}' "$health_root/invariants.md" | sort -u > "$tmp_declared_invariants"

processor_errors=$errors
while IFS= read -r processor_file; do
  if ! rg -q 'BHL-INV-[0-9]{3}' "$processor_file"; then
    fail "aucun invariant référencé: ${processor_file#"$repo_root/"}"
  fi
  rg -o --no-filename 'BHL-INV-[0-9]{3}' "$processor_file" >> "$tmp_referenced_invariants" || true
  rg -qi 'idempoten' "$processor_file" || \
    fail "politique d'idempotence absente: ${processor_file#"$repo_root/"}"
  rg -q 'EvaluateBusinessHealthRequestId' "$processor_file" || \
    fail "EvaluateBusinessHealthRequestId absent: ${processor_file#"$repo_root/"}"
  rg -q 'AnalyticsSnapshotPublishedEventId' "$processor_file" || \
    fail "causalité Analytics absente: ${processor_file#"$repo_root/"}"
done < <(find "$processors_root" -maxdepth 1 -type f -name '*.md' ! -name 'README.md' | sort)

if (( errors == processor_errors )); then
  pass "invariants, concurrence, causalité et idempotence documentés"
fi

sort -u -o "$tmp_referenced_invariants" "$tmp_referenced_invariants"
unknown_invariants="$(comm -13 "$tmp_declared_invariants" "$tmp_referenced_invariants")"
if [[ -n "$unknown_invariants" ]]; then
  fail "invariants référencés mais non déclarés: $(printf '%s' "$unknown_invariants" | paste -sd, -)"
else
  pass "références d'invariants valides"
fi

# Events and processor permissions must match the trace.
awk -F'|' '
  /^## Traçabilité des processeurs$/ { trace=1; next }
  /^---$/ && trace { trace=0 }
  trace && $2 ~ /`[A-Z][A-Za-z]+`/ { print $6 }
' "$health_root/consolidation-matrix.md" \
  | rg -o '`[A-Z][A-Za-z0-9]+`' | tr -d '`' | sort -u > "$tmp_trace_events"

awk -F'|' '$2 ~ /`BusinessHealth[A-Z][A-Za-z0-9]+`/ {
  value=$2; gsub(/^[[:space:]]*`|`[[:space:]]*$/, "", value); print value
}' "$health_root/events.md" | sort -u > "$tmp_catalog_events"

event_drift="$(comm -3 "$tmp_catalog_events" "$tmp_trace_events")"
if [[ -n "$event_drift" ]]; then
  fail "écart entre événements et trace: $(printf '%s' "$event_drift" | paste -sd, -)"
else
  pass "Domain Events et producteurs entièrement tracés"
fi

awk -F'|' '
  /^## Traçabilité des processeurs$/ { trace=1; next }
  /^---$/ && trace { trace=0 }
  trace && $2 ~ /`[A-Z][A-Za-z]+`/ { print $4 }
' "$health_root/consolidation-matrix.md" \
  | rg -o 'business-health\.[a-z0-9.-]+' | sort -u > "$tmp_trace_permissions"

awk -F'|' '$2 ~ /`business-health\.[a-z0-9.-]+`/ {
  value=$2; gsub(/^[[:space:]]*`|`[[:space:]]*$/, "", value); print value
}' "$health_root/permissions.md" | sort -u > "$tmp_catalog_permissions"

unknown_permissions="$(comm -13 "$tmp_catalog_permissions" "$tmp_trace_permissions")"
if [[ -n "$unknown_permissions" ]]; then
  fail "capacités tracées mais absentes: $(printf '%s' "$unknown_permissions" | paste -sd, -)"
else
  pass "capacités de processeur présentes dans le catalogue"
fi

# Policy factors, weights and metrics.
policy_errors=$errors
for factor in CommercialMomentum BillingMomentum ReceivablesDiscipline ClientDiversification; do
  rg -q "| \`$factor\` |" "$health_root/health-policy.md" || fail "FactorKey absent: $factor"
done
rg -q '| \*\*Total\*\* | \*\*100\*\* | \*\*7\*\* |' "$health_root/consolidation-matrix.md" || \
  fail "poids total 100 ou total de composants absent"
rg -q 'au moins 70 %' "$health_root/health-policy.md" || fail "couverture globale minimale absente"

rg -o --no-filename 'analytics\.[a-z0-9.-]+' "$health_root/health-policy.md" | sort -u > "$tmp_policy_metrics"
policy_metric_count="$(wc -l < "$tmp_policy_metrics")"
if [[ "$policy_metric_count" != "8" ]]; then
  fail "HealthPolicy référence $policy_metric_count MetricKeys au lieu de 8"
fi
while IFS= read -r metric_key; do
  rg -q "\`$metric_key\`" "$repo_root/fondation/domains/analytics/metric-catalog.md" || \
    fail "MetricKey Business Health absente du catalogue Analytics: $metric_key"
done < "$tmp_policy_metrics"

if (( errors == policy_errors )); then
  pass "quatre facteurs, poids, couverture et huit MetricKeys cohérents"
fi

for term in AssessmentStatus AssessmentReliability OverallScore HealthBand HealthTrend PrimaryAttention HealthRisk EvidenceReference; do
  if ! rg -q "$term" "$health_root/value-objects.md" "$health_root/api.md"; then
    fail "concept contractuel absent: $term"
  fi
done
if rg -q 'missing.*(=|comme).*zéro|NoData.*(=|comme).*zéro' "$health_root" -g '*.md'; then
  fail "assimilation potentielle d'une absence à zéro"
else
  pass "score, fiabilité, tendance, attention, risque et preuve contractuels"
fi

# Integration contracts must be recognized by both sides.
contract_errors=$errors
rg -q 'getAnalyticsSnapshot' "$repo_root/fondation/domains/analytics/api.md" || \
  fail "contrat Analytics absent: getAnalyticsSnapshot"
rg -q 'getAnalyticsSnapshot' "$health_root/integrations.md" || \
  fail "contrat Analytics non reconnu par Business Health"
rg -q 'getWorkspaceAccessContext' "$repo_root/fondation/domains/workspace/api.md" || \
  fail "contrat Workspace absent: getWorkspaceAccessContext"
rg -q 'getWorkspaceAccessContext' "$health_root/integrations.md" || \
  fail "contrat Workspace non reconnu par Business Health"
rg -q 'getBusinessHealthAssessment' "$health_root/api.md" || \
  fail "contrat Business Health absent: getBusinessHealthAssessment"
rg -q 'getBusinessHealthAssessment' "$repo_root/fondation/domains/advisor/recommendation-engine.md" || \
  fail "contrat Business Health non reconnu par Advisor"
if (( errors == contract_errors )); then
  pass "contrats Analytics–Workspace–Business Health–Advisor symétriques"
fi

if rg -q '`(Recommendation|Action|RecommendationPriority)`' "$health_root/processors"; then
  fail "concept Advisor utilisé dans un processeur Business Health"
else
  pass "frontière Advisor respectée dans les processeurs"
fi

map_errors=$errors
for map_file in \
  "$repo_root/fondation/domains/README.md" \
  "$repo_root/fondation/domain-map/context-map.md" \
  "$repo_root/fondation/domain-map/ownership.md" \
  "$repo_root/fondation/domain-map/dependencies.md"; do
  if ! rg -q 'Business Health' "$map_file"; then
    fail "Business Health absent de la cartographie: ${map_file#"$repo_root/"}"
  fi
done
if (( errors == map_errors )); then
  pass "Business Health intégré aux cartes globales"
fi

if (( errors > 0 )); then
  printf '\nBusiness Health documentation checks: %d failure(s), %d passed group(s).\n' "$errors" "$checks"
  exit 1
fi

printf '\nBusiness Health documentation checks: all %d groups passed.\n' "$checks"
