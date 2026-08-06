#!/usr/bin/env bash

set -uo pipefail

repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
analytics_root="$repo_root/fondation/domains/analytics"
processors_root="$analytics_root/processors"
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
tmp_metric_keys="$(mktemp)"

cleanup() {
  rm -f "$tmp_ids" "$tmp_processor_files" "$tmp_processor_links" \
    "$tmp_matrix_processors" "$tmp_declared_invariants" \
    "$tmp_referenced_invariants" "$tmp_catalog_events" "$tmp_trace_events" \
    "$tmp_catalog_permissions" "$tmp_trace_permissions" "$tmp_metric_keys"
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

if [[ ! -d "$analytics_root" ]]; then
  printf 'Analytics directory not found: %s\n' "$analytics_root" >&2
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
done < <(find "$analytics_root" -type f -name '*.md' -print0)

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
' "$analytics_root/consolidation-matrix.md" | sort -u > "$tmp_matrix_processors"

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
rg -o --no-filename 'ANL-INV-[0-9]{3}' "$analytics_root/invariants.md" | sort -u > "$tmp_declared_invariants"

processor_errors=$errors
while IFS= read -r processor_file; do
  if ! rg -q 'ANL-INV-[0-9]{3}' "$processor_file"; then
    fail "aucun invariant référencé: ${processor_file#"$repo_root/"}"
  fi
  rg -o --no-filename 'ANL-INV-[0-9]{3}' "$processor_file" >> "$tmp_referenced_invariants" || true

  if ! rg -qi 'idempoten' "$processor_file"; then
    fail "politique d'idempotence absente: ${processor_file#"$repo_root/"}"
  fi
  if ! rg -q '[A-Za-z]+RequestId' "$processor_file"; then
    fail "RequestId absent: ${processor_file#"$repo_root/"}"
  fi

  case "$(basename "$processor_file")" in
    CompleteAnalyticsProjectionRebuild.md)
      rg -q 'ExpectedRevision' "$processor_file" || \
        fail "ExpectedRevision absente: ${processor_file#"$repo_root/"}"
      ;;
    IngestSourceFact.md)
      rg -q 'AggregateVersion' "$processor_file" || \
        fail "version source absente: ${processor_file#"$repo_root/"}"
      ;;
  esac
done < <(find "$processors_root" -maxdepth 1 -type f -name '*.md' ! -name 'README.md' | sort)

if (( errors == processor_errors )); then
  pass "invariants, concurrence et idempotence documentés pour chaque processeur"
fi

sort -u -o "$tmp_referenced_invariants" "$tmp_referenced_invariants"
unknown_invariants="$(comm -13 "$tmp_declared_invariants" "$tmp_referenced_invariants")"
if [[ -n "$unknown_invariants" ]]; then
  fail "invariants référencés mais non déclarés: $(printf '%s' "$unknown_invariants" | paste -sd, -)"
else
  pass "références d'invariants valides"
fi

# Event and permission catalogues must cover the processor trace.
awk -F'|' '
  /^## Traçabilité des processeurs$/ { trace=1; next }
  /^---$/ && trace { trace=0 }
  trace && $2 ~ /`[A-Z][A-Za-z]+`/ { print $6 }
' "$analytics_root/consolidation-matrix.md" \
  | rg -o '`[A-Z][A-Za-z0-9]+`' | tr -d '`' | sort -u > "$tmp_trace_events"

awk -F'|' '$2 ~ /`Analytics[A-Z][A-Za-z0-9]+`/ {
  value=$2; gsub(/^[[:space:]]*`|`[[:space:]]*$/, "", value); print value
}' "$analytics_root/events.md" | sort -u > "$tmp_catalog_events"

unknown_events="$(comm -13 "$tmp_catalog_events" "$tmp_trace_events")"
untraced_events="$(comm -23 "$tmp_catalog_events" "$tmp_trace_events")"
if [[ -n "$unknown_events" || -n "$untraced_events" ]]; then
  [[ -n "$unknown_events" ]] && fail "Domain Events tracés mais absents: $(printf '%s' "$unknown_events" | paste -sd, -)"
  [[ -n "$untraced_events" ]] && fail "Domain Events sans producteur tracé: $(printf '%s' "$untraced_events" | paste -sd, -)"
else
  pass "Domain Events et producteurs entièrement tracés"
fi

awk -F'|' '
  /^## Traçabilité des processeurs$/ { trace=1; next }
  /^---$/ && trace { trace=0 }
  trace && $2 ~ /`[A-Z][A-Za-z]+`/ { print $4 }
' "$analytics_root/consolidation-matrix.md" \
  | rg -o 'analytics\.[a-z0-9.-]+' | sort -u > "$tmp_trace_permissions"

awk -F'|' '$2 ~ /`analytics\.[a-z0-9.-]+`/ {
  value=$2; gsub(/^[[:space:]]*`|`[[:space:]]*$/, "", value); print value
}' "$analytics_root/permissions.md" | sort -u > "$tmp_catalog_permissions"

unknown_permissions="$(comm -13 "$tmp_catalog_permissions" "$tmp_trace_permissions")"
if [[ -n "$unknown_permissions" ]]; then
  fail "capacités tracées mais absentes: $(printf '%s' "$unknown_permissions" | paste -sd, -)"
else
  pass "capacités de processeur présentes dans le catalogue"
fi

# Metric catalogue.
awk -F'|' '$2 ~ /`analytics\.[a-z0-9.-]+`/ {
  value=$2; gsub(/^[[:space:]]*`|`[[:space:]]*$/, "", value); print value
}' "$analytics_root/metric-catalog.md" | sort -u > "$tmp_metric_keys"

metric_count="$(wc -l < "$tmp_metric_keys")"
if [[ "$metric_count" != "13" ]]; then
  fail "catalogue métrique inattendu: $metric_count clés au lieu de 13"
elif ! rg -q '\| \*\*Total\*\* \| \*\*13\*\* \|' "$analytics_root/consolidation-matrix.md"; then
  fail "total de métriques absent ou incohérent dans la matrice"
else
  pass "13 MetricKeys canoniques présentes et comptabilisées"
fi

metric_semantic_errors=$errors
for required_term in MetricDefinition DataFreshness DataCompleteness NoData CurrencyCode ObservationPeriod; do
  if ! rg -q "$required_term" "$analytics_root/metric-catalog.md" "$analytics_root/api.md"; then
    fail "sémantique métrique absente: $required_term"
  fi
done
for profile_term in 'SnapshotProfileVersion = 1.0.0' 'CurrentLagThreshold = PT1H' \
  'MaximumAcceptedLag = PT24H' 'BusinessHealthBaselineV1@1.0.0'; do
  if ! rg -q "$profile_term" "$analytics_root/metric-catalog.md" \
    "$analytics_root/value-objects.md" "$analytics_root/processors/PublishAnalyticsSnapshot.md"; then
    fail "contrat SnapshotProfile absent: $profile_term"
  fi
done
if (( errors == metric_semantic_errors )); then
  pass "définition, période, devise, absence, fraîcheur, profil et complétude contractuels"
fi

# Source contracts must be documented on both sides.
contract_errors=$errors
crm_contract=getOpportunityAnalyticsFact
rg -q "$crm_contract" "$repo_root/fondation/domains/crm/api.md" || fail "contrat CRM absent: $crm_contract"
rg -q "$crm_contract" "$analytics_root/integrations.md" || fail "contrat CRM non reconnu: $crm_contract"

for contract in getQuoteAnalyticsFact getInvoiceAnalyticsFact getPaymentAnalyticsFact getCreditNoteAnalyticsFact; do
  rg -q "$contract" "$repo_root/fondation/domains/billing/api.md" || fail "contrat Billing absent: $contract"
  rg -q "$contract" "$analytics_root/integrations.md" || fail "contrat Billing non reconnu: $contract"
done

for contract in getWorkspaceAccessContext getWorkspacePreferences; do
  rg -q "$contract" "$repo_root/fondation/domains/workspace/api.md" || fail "contrat Workspace absent: $contract"
  rg -q "$contract" "$analytics_root/integrations.md" || fail "contrat Workspace non reconnu: $contract"
done

if (( errors == contract_errors )); then
  pass "contrats publics CRM–Billing–Workspace–Analytics symétriques"
fi

if rg -q '`(Revenue|CashBalance|Treasury|Forecast|RealTimeMetric)`' "$analytics_root/processors"; then
  fail "concept analytique trompeur utilisé dans un processeur"
else
  pass "aucun concept analytique trompeur dans les processeurs"
fi

map_errors=$errors
for map_file in \
  "$repo_root/fondation/domains/README.md" \
  "$repo_root/fondation/domain-map/context-map.md" \
  "$repo_root/fondation/domain-map/ownership.md" \
  "$repo_root/fondation/domain-map/dependencies.md"; do
  if ! rg -q '\bAnalytics\b' "$map_file"; then
    fail "Analytics absent de la cartographie: ${map_file#"$repo_root/"}"
  fi
done
if (( errors == map_errors )); then
  pass "Analytics intégré aux cartes globales"
fi

if (( errors > 0 )); then
  printf '\nAnalytics documentation checks: %d failure(s), %d passed group(s).\n' "$errors" "$checks"
  exit 1
fi

printf '\nAnalytics documentation checks: all %d groups passed.\n' "$checks"
