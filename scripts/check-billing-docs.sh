#!/usr/bin/env bash

set -uo pipefail

repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
billing_root="$repo_root/fondation/domains/billing"
commands_root="$billing_root/commands"
errors=0
checks=0

tmp_ids="$(mktemp)"
tmp_command_files="$(mktemp)"
tmp_command_links="$(mktemp)"
tmp_matrix_commands="$(mktemp)"
tmp_declared_invariants="$(mktemp)"
tmp_referenced_invariants="$(mktemp)"
tmp_catalog_events="$(mktemp)"
tmp_trace_events="$(mktemp)"
tmp_catalog_permissions="$(mktemp)"
tmp_trace_permissions="$(mktemp)"

cleanup() {
  rm -f "$tmp_ids" "$tmp_command_files" "$tmp_command_links" \
    "$tmp_matrix_commands" "$tmp_declared_invariants" \
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

if [[ ! -d "$billing_root" ]]; then
  printf 'Billing directory not found: %s\n' "$billing_root" >&2
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
done < <(find "$billing_root" -type f -name '*.md' -print0)

if (( errors == structure_errors )); then
  pass "front matter, références, liens et blocs de code valides"
fi

duplicate_ids="$(cut -d'|' -f1 "$tmp_ids" | sort | uniq -d)"
if [[ -n "$duplicate_ids" ]]; then
  fail "IDs de document dupliqués: $(printf '%s' "$duplicate_ids" | paste -sd, -)"
else
  pass "IDs de document uniques"
fi

# Files, command catalogue and traceability matrix must match.
find "$commands_root" -maxdepth 1 -type f -name '*.md' ! -name 'README.md' -printf '%f\n' \
  | sed 's/\.md$//' | sort > "$tmp_command_files"

awk '
  /^## Catalogue$/ { catalog=1; next }
  /^## Conventions communes$/ { catalog=0 }
  catalog { print }
' "$commands_root/README.md" \
  | sed -n 's/.*](\([^)]*\.md\)).*/\1/p' \
  | sed -e 's#^.*/##' -e 's/\.md$//' \
  | sort -u > "$tmp_command_links"

awk -F'|' '
  /^## Traçabilité des commandes$/ { trace=1; next }
  /^---$/ && trace { trace=0 }
  trace && $2 ~ /`[A-Z][A-Za-z]+`/ {
    value=$2; gsub(/^[[:space:]]*`|`[[:space:]]*$/, "", value); print value
  }
' "$billing_root/consolidation-matrix.md" | sort -u > "$tmp_matrix_commands"

missing_links="$(comm -23 "$tmp_command_files" "$tmp_command_links")"
unknown_links="$(comm -13 "$tmp_command_files" "$tmp_command_links")"
matrix_drift="$(comm -3 "$tmp_command_files" "$tmp_matrix_commands")"
if [[ -n "$missing_links" || -n "$unknown_links" || -n "$matrix_drift" ]]; then
  [[ -n "$missing_links" ]] && fail "commandes absentes du catalogue: $(printf '%s' "$missing_links" | paste -sd, -)"
  [[ -n "$unknown_links" ]] && fail "liens de commande inconnus: $(printf '%s' "$unknown_links" | paste -sd, -)"
  [[ -n "$matrix_drift" ]] && fail "écart entre commandes et matrice: $(printf '%s' "$matrix_drift" | paste -sd, -)"
else
  pass "catalogue, fichiers et matrice des commandes alignés"
fi

# Invariants, concurrency and idempotency.
rg -o --no-filename 'BIL-INV-[0-9]{3}' "$billing_root/invariants.md" | sort -u > "$tmp_declared_invariants"

command_errors=$errors
while IFS= read -r command_file; do
  if ! rg -q 'BIL-INV-[0-9]{3}' "$command_file"; then
    fail "aucun invariant référencé: ${command_file#"$repo_root/"}"
  fi
  rg -o --no-filename 'BIL-INV-[0-9]{3}' "$command_file" >> "$tmp_referenced_invariants" || true

  if ! rg -qi 'idempoten' "$command_file"; then
    fail "politique d'idempotence absente: ${command_file#"$repo_root/"}"
  fi
  if ! rg -q '[A-Za-z]+RequestId' "$command_file"; then
    fail "RequestId absent: ${command_file#"$repo_root/"}"
  fi

  case "$(basename "$command_file")" in
    CreateQuote.md|CreateInvoice.md|CreateCreditNote.md) ;;
    *)
      if ! rg -q 'ExpectedRevision' "$command_file"; then
        fail "ExpectedRevision absente: ${command_file#"$repo_root/"}"
      fi
      ;;
  esac
done < <(find "$commands_root" -maxdepth 1 -type f -name '*.md' ! -name 'README.md' | sort)

if (( errors == command_errors )); then
  pass "invariants, concurrence et idempotence documentés pour chaque commande"
fi

sort -u -o "$tmp_referenced_invariants" "$tmp_referenced_invariants"
unknown_invariants="$(comm -13 "$tmp_declared_invariants" "$tmp_referenced_invariants")"
if [[ -n "$unknown_invariants" ]]; then
  fail "invariants référencés mais non déclarés: $(printf '%s' "$unknown_invariants" | paste -sd, -)"
else
  pass "références d'invariants valides"
fi

# Event and permission catalogues must cover the command trace.
awk -F'|' '
  /^## Traçabilité des commandes$/ { trace=1; next }
  /^---$/ && trace { trace=0 }
  trace && $2 ~ /`[A-Z][A-Za-z]+`/ { print $6 }
' "$billing_root/consolidation-matrix.md" \
  | rg -o '`[A-Z][A-Za-z0-9]+`' | tr -d '`' | sort -u > "$tmp_trace_events"

awk -F'|' '$2 ~ /`(Quote|Invoice|Payment|CreditNote|BillingHistory)[A-Z][A-Za-z0-9]+`/ {
  value=$2; gsub(/^[[:space:]]*`|`[[:space:]]*$/, "", value); print value
}' "$billing_root/events.md" | sort -u > "$tmp_catalog_events"

unknown_events="$(comm -13 "$tmp_catalog_events" "$tmp_trace_events")"
untraced_events="$(comm -23 "$tmp_catalog_events" "$tmp_trace_events")"
if [[ -n "$unknown_events" || -n "$untraced_events" ]]; then
  [[ -n "$unknown_events" ]] && fail "Domain Events tracés mais absents: $(printf '%s' "$unknown_events" | paste -sd, -)"
  [[ -n "$untraced_events" ]] && fail "Domain Events sans producteur tracé: $(printf '%s' "$untraced_events" | paste -sd, -)"
else
  pass "Domain Events et producteurs entièrement tracés"
fi

awk -F'|' '
  /^## Traçabilité des commandes$/ { trace=1; next }
  /^---$/ && trace { trace=0 }
  trace && $2 ~ /`[A-Z][A-Za-z]+`/ { print $4 }
' "$billing_root/consolidation-matrix.md" \
  | rg -o 'billing\.[a-z0-9.-]+' | sort -u > "$tmp_trace_permissions"

awk -F'|' '$2 ~ /`billing\.[a-z0-9.-]+`/ {
  value=$2; gsub(/^[[:space:]]*`|`[[:space:]]*$/, "", value); print value
}' "$billing_root/permissions.md" | sort -u > "$tmp_catalog_permissions"

unknown_permissions="$(comm -13 "$tmp_catalog_permissions" "$tmp_trace_permissions")"
if [[ -n "$unknown_permissions" ]]; then
  fail "permissions tracées mais absentes: $(printf '%s' "$unknown_permissions" | paste -sd, -)"
else
  pass "permissions de commande présentes dans le catalogue"
fi

# Integration contracts must be recognized on both sides.
contract_errors=$errors
for contract in getClientBillingContext getOpportunityCommercialContext; do
  rg -q "$contract" "$repo_root/fondation/domains/crm/api.md" || fail "contrat CRM absent: $contract"
  rg -q "$contract" "$billing_root/integrations.md" || fail "contrat CRM non reconnu par Billing: $contract"
done
for contract in getWorkspaceBillingIdentity getWorkspacePreferences getWorkspaceAccessContext; do
  rg -q "$contract" "$repo_root/fondation/domains/workspace/api.md" || fail "contrat Workspace absent: $contract"
  rg -q "$contract" "$billing_root/integrations.md" || fail "contrat Workspace non reconnu par Billing: $contract"
done
if (( errors == contract_errors )); then
  pass "contrats publics CRM–Workspace–Billing symétriques"
fi

if rg -q '`(Proposal|Bill|InvoiceCancelled|PaymentReceived)`' "$commands_root"; then
  fail "concept Billing déprécié utilisé dans une commande"
else
  pass "aucun concept Billing déprécié dans les commandes"
fi

map_errors=$errors
for map_file in \
  "$repo_root/fondation/domains/README.md" \
  "$repo_root/fondation/domain-map/context-map.md" \
  "$repo_root/fondation/domain-map/ownership.md" \
  "$repo_root/fondation/domain-map/dependencies.md"; do
  if ! rg -q '\bBilling\b' "$map_file"; then
    fail "Billing absent de la cartographie: ${map_file#"$repo_root/"}"
  fi
done
if (( errors == map_errors )); then
  pass "Billing intégré aux cartes globales"
fi

if (( errors > 0 )); then
  printf '\nBilling documentation checks: %d failure(s), %d passed group(s).\n' "$errors" "$checks"
  exit 1
fi

printf '\nBilling documentation checks: all %d groups passed.\n' "$checks"
