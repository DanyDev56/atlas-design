#!/usr/bin/env bash

set -uo pipefail

repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
identity_root="$repo_root/fondation/domains/identity"
commands_root="$identity_root/commands"
errors=0
checks=0

tmp_ids="$(mktemp)"
tmp_command_files="$(mktemp)"
tmp_command_links="$(mktemp)"
tmp_declared_invariants="$(mktemp)"
tmp_referenced_invariants="$(mktemp)"
tmp_catalog_events="$(mktemp)"
tmp_trace_events="$(mktemp)"
tmp_catalog_permissions="$(mktemp)"
tmp_trace_permissions="$(mktemp)"

cleanup() {
  rm -f "$tmp_ids" "$tmp_command_files" "$tmp_command_links" \
    "$tmp_declared_invariants" "$tmp_referenced_invariants" \
    "$tmp_catalog_events" "$tmp_trace_events" \
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

if [[ ! -d "$identity_root" ]]; then
  printf 'Identity directory not found: %s\n' "$identity_root" >&2
  exit 2
fi

# Front matter, document IDs, references and code fences.
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
done < <(find "$identity_root" -type f -name '*.md' -print0)

if (( errors == structure_errors )); then
  pass "front matter, références, liens et blocs de code valides"
fi

duplicate_ids="$(cut -d'|' -f1 "$tmp_ids" | sort | uniq -d)"
if [[ -n "$duplicate_ids" ]]; then
  fail "IDs de document dupliqués: $(printf '%s' "$duplicate_ids" | paste -sd, -)"
else
  pass "IDs de document uniques"
fi

# Command inventory must be exact and linked from the catalog.
find "$commands_root" -maxdepth 1 -type f -name '*.md' ! -name 'README.md' -printf '%f\n' | sort > "$tmp_command_files"
awk '
  /^# Répertoire$/ { catalog=1; next }
  /^# Évolution$/ { catalog=0 }
  catalog { print }
' "$commands_root/README.md" \
  | sed -n 's/.*](\([^)]*\.md\)).*/\1/p' \
  | sed 's#^.*/##' \
  | sort -u > "$tmp_command_links"

missing_command_links="$(comm -23 "$tmp_command_files" "$tmp_command_links")"
unknown_command_links="$(comm -13 "$tmp_command_files" "$tmp_command_links")"
if [[ -n "$missing_command_links" || -n "$unknown_command_links" ]]; then
  [[ -n "$missing_command_links" ]] && fail "commandes absentes du catalogue: $(printf '%s' "$missing_command_links" | paste -sd, -)"
  [[ -n "$unknown_command_links" ]] && fail "liens de commande inconnus: $(printf '%s' "$unknown_command_links" | paste -sd, -)"
else
  pass "catalogue des commandes complet"
fi

# Every command must reference declared invariants and document idempotence.
rg -o --no-filename 'IDN-INV-[0-9]{3}' "$identity_root/invariants.md" | sort -u > "$tmp_declared_invariants"

command_contract_errors=$errors
while IFS= read -r command_file; do
  if ! rg -q 'IDN-INV-[0-9]{3}' "$command_file"; then
    fail "aucun invariant référencé: ${command_file#"$repo_root/"}"
  fi
  rg -o --no-filename 'IDN-INV-[0-9]{3}' "$command_file" >> "$tmp_referenced_invariants" || true

  if ! rg -qi 'idempoten' "$command_file"; then
    fail "politique d'idempotence absente: ${command_file#"$repo_root/"}"
  fi
  if ! rg -qi '(RequestId|idempotency key|clé d.idempotence|idempotence naturelle|strictement idempotente)' "$command_file"; then
    fail "clé ou règle d'idempotence absente: ${command_file#"$repo_root/"}"
  fi
done < <(find "$commands_root" -maxdepth 1 -type f -name '*.md' ! -name 'README.md' | sort)

if (( errors == command_contract_errors )); then
  pass "invariants et idempotence documentés pour chaque commande"
fi

sort -u -o "$tmp_referenced_invariants" "$tmp_referenced_invariants"
unknown_invariants="$(comm -13 "$tmp_declared_invariants" "$tmp_referenced_invariants")"
if [[ -n "$unknown_invariants" ]]; then
  fail "invariants référencés mais non déclarés: $(printf '%s' "$unknown_invariants" | paste -sd, -)"
else
  pass "références d'invariants valides"
fi

# The traceability matrix is the machine-checkable command/event/permission map.
awk -F'|' '
  /^## Traçabilité des commandes/ { trace=1; next }
  /^## Commandes User requises/ { trace=0 }
  trace && NF == 7 && $2 ~ /`[A-Z][A-Za-z]+`/ { print $5 }
' "$identity_root/consolidation-matrix.md" \
  | rg -o '`[A-Z][A-Za-z0-9]+`' \
  | tr -d '`' \
  | sort -u > "$tmp_trace_events"

awk -F'|' '
  /^### Événements produits par un workflow/ { workflows=1; next }
  workflows && /^---$/ { workflows=0 }
  workflows && NF == 4 { print $3 }
' "$identity_root/consolidation-matrix.md" \
  | rg -o '`[A-Z][A-Za-z0-9]+`' \
  | tr -d '`' >> "$tmp_trace_events"
sort -u -o "$tmp_trace_events" "$tmp_trace_events"

awk -F'|' 'NF == 5 && $2 ~ /`[A-Z][A-Za-z0-9]+`/ {
  value=$2; gsub(/^[[:space:]]*`|`[[:space:]]*$/, "", value); print value
}' "$identity_root/events.md" | sort -u > "$tmp_catalog_events"

unknown_events="$(comm -13 "$tmp_catalog_events" "$tmp_trace_events")"
untraced_events="$(comm -23 "$tmp_catalog_events" "$tmp_trace_events")"
if [[ -n "$unknown_events" || -n "$untraced_events" ]]; then
  [[ -n "$unknown_events" ]] && fail "Domain Events tracés mais absents du catalogue: $(printf '%s' "$unknown_events" | paste -sd, -)"
  [[ -n "$untraced_events" ]] && fail "Domain Events du catalogue sans producteur tracé: $(printf '%s' "$untraced_events" | paste -sd, -)"
else
  pass "Domain Events et producteurs entièrement tracés"
fi

awk -F'|' '
  /^## Traçabilité des commandes/ { trace=1; next }
  /^## Commandes User requises/ { trace=0 }
  trace && NF == 7 && $2 ~ /`[A-Z][A-Za-z]+`/ { print $3 }
' "$identity_root/consolidation-matrix.md" \
  | rg -o '(workspace|identity)\.[a-z0-9.-]+' \
  | sort -u > "$tmp_trace_permissions"

awk -F'|' '$2 ~ /`(workspace|identity)\.[a-z0-9.-]+`/ {
  value=$2; gsub(/^[[:space:]]*`|`[[:space:]]*$/, "", value); print value
}' "$identity_root/permissions.md" | sort -u > "$tmp_catalog_permissions"

unknown_permissions="$(comm -13 "$tmp_catalog_permissions" "$tmp_trace_permissions")"
if [[ -n "$unknown_permissions" ]]; then
  fail "permissions tracées mais absentes du catalogue: $(printf '%s' "$unknown_permissions" | paste -sd, -)"
else
  pass "permissions tracées dans le catalogue"
fi

if rg -q '(workspace\.members\.manage|workspace\.roles\.manage|\bRemovedRole[A-Za-z]*\b|\bRoleRemoved\b)' "$commands_root"; then
  fail "concept courant déprécié dans une commande"
else
  pass "aucun concept courant déprécié dans les commandes"
fi

if (( errors > 0 )); then
  printf '\nIdentity documentation checks: %d failure(s), %d passed group(s).\n' "$errors" "$checks"
  exit 1
fi

printf '\nIdentity documentation checks: all %d groups passed.\n' "$checks"
