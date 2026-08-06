#!/usr/bin/env bash

set -uo pipefail

repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
decisions_root="$repo_root/fondation/decisions"
catalog="$decisions_root/README.md"
errors=0
checks=0

tmp_ids="$(mktemp)"
tmp_files="$(mktemp)"
tmp_catalog="$(mktemp)"

cleanup() {
  rm -f "$tmp_ids" "$tmp_files" "$tmp_catalog"
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

if [[ ! -d "$decisions_root" ]]; then
  printf 'Decision directory not found: %s\n' "$decisions_root" >&2
  exit 2
fi

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
done < <(find "$decisions_root" -maxdepth 1 -type f -name '*.md' -print0)

if (( errors == structure_errors )); then
  pass "front matter, références, liens et blocs de code valides"
fi

duplicate_ids="$(cut -d'|' -f1 "$tmp_ids" | sort | uniq -d)"
if [[ -n "$duplicate_ids" ]]; then
  fail "IDs de décision dupliqués: $(printf '%s' "$duplicate_ids" | paste -sd, -)"
else
  pass "IDs de décision uniques"
fi

catalog_errors=$errors
while IFS= read -r adr_file; do
  basename "$adr_file" | sed -n 's/^\(ADR-[0-9][0-9][0-9]\)-.*\.md$/\1/p' >> "$tmp_files"

  file_id="$(awk 'BEGIN{block=0} /^---$/{block++; next} block==1 && /^id: /{sub(/^id: /, ""); print; exit}' "$adr_file")"
  file_prefix="$(basename "$adr_file" | sed -n 's/^\(ADR-[0-9][0-9][0-9]\)-.*\.md$/\1/p')"
  if [[ "$file_id" != "$file_prefix" ]]; then
    fail "ID et nom de fichier incohérents: ${adr_file#"$repo_root/"}"
  fi
done < <(find "$decisions_root" -maxdepth 1 -type f -name 'ADR-[0-9][0-9][0-9]-*.md' | sort)

rg -o '\[`ADR-[0-9]{3}`\]' "$catalog" | tr -d '[]`' | sort -u > "$tmp_catalog" || true
sort -u -o "$tmp_files" "$tmp_files"
catalog_drift="$(comm -3 "$tmp_files" "$tmp_catalog")"
if [[ -n "$catalog_drift" ]]; then
  fail "écart entre fichiers ADR et catalogue: $(printf '%s' "$catalog_drift" | paste -sd, -)"
fi
if (( errors == catalog_errors )); then
  pass "fichiers, IDs et catalogue ADR alignés"
fi

content_errors=$errors
while IFS= read -r adr_file; do
  status="$(awk 'BEGIN{block=0} /^---$/{block++; next} block==1 && /^status: /{sub(/^status: /, ""); print; exit}' "$adr_file")"
  decision_date="$(awk 'BEGIN{block=0} /^---$/{block++; next} block==1 && /^date: /{sub(/^date: /, ""); print; exit}' "$adr_file")"
  case "$status" in
    Proposed|Accepted|Rejected|Deprecated|Superseded) ;;
    *) fail "statut ADR invalide dans ${adr_file#"$repo_root/"}: $status" ;;
  esac
  if [[ ! "$decision_date" =~ ^[0-9]{4}-[0-9]{2}-[0-9]{2}$ ]]; then
    fail "date ADR invalide dans ${adr_file#"$repo_root/"}: $decision_date"
  fi

  for heading in '## Contexte' '## Forces de décision' '## Options étudiées' \
    '## Décision' '## Raisons' '## Conséquences' \
    "## Conditions d'implémentation" '## Réexamen'; do
    rg -q -F "$heading" "$adr_file" || \
      fail "section absente dans ${adr_file#"$repo_root/"}: $heading"
  done
done < <(find "$decisions_root" -maxdepth 1 -type f -name 'ADR-[0-9][0-9][0-9]-*.md' | sort)

if (( errors == content_errors )); then
  pass "statuts et sections normatives des ADR valides"
fi

topology="$decisions_root/ADR-001-mvp-application-topology.md"
topology_errors=$errors
[[ -f "$topology" ]] || fail "ADR-001 de topologie absent"
if [[ -f "$topology" ]]; then
  rg -q '^status: Accepted$' "$topology" || fail "ADR-001 non accepté"
  for term in 'modular monolith' 'transaction ne traverse jamais un module' \
    'at least once' 'chaque module possède son namespace ou schéma' \
    'Critères d.extraction future' 'Enforcement automatique'; do
    rg -q "$term" "$topology" || fail "contrainte de topologie absente: $term"
  done
  for module in identity workspace crm billing analytics business-health advisor notifications; do
    rg -q "  $module/" "$topology" || fail "module MVP absent de la topologie: $module"
  done
fi
if (( errors == topology_errors )); then
  pass "ADR-001 couvre modules, données, transactions, messaging et extraction"
fi

stack="$decisions_root/ADR-002-mvp-implementation-stack.md"
stack_errors=$errors
[[ -f "$stack" ]] || fail "ADR-002 de stack absent"
if [[ -f "$stack" ]]; then
  rg -q '^status: Proposed$' "$stack" || fail "ADR-002 ne reste pas Proposed avant son spike"
  for term in 'PHP 8.5 strict' 'Laravel 13' 'React 19' 'PostgreSQL 18' \
    'Laravel Database' 'Eloquent borné aux adapters' 'outbox, inbox' \
    'Laravel Queue avec driver database' 'OpenTelemetry' 'composer.lock' \
    'GitHub Actions' 'SBOM' 'douze conditions'; do
    rg -q "$term" "$stack" || fail "baseline de stack absente: $term"
  done
  for outbox_term in 'afterCommit()' 'transactional outbox' \
    "preuve atomique de l'intention"; do
    rg -q -F "$outbox_term" "$stack" || \
      fail "distinction entre afterCommit et transactional outbox absente: $outbox_term"
  done
  rg -q "Horizon n'est pas utilisé au MVP" "$stack" || \
    fail "exclusion de Horizon/Redis absente de la baseline"
  for module in identity workspace crm billing analytics business-health advisor notifications; do
    rg -q "  $module/" "$stack" || fail "module MVP absent de la stack: $module"
  done
fi
if (( errors == stack_errors )); then
  pass "ADR-002 propose Laravel, web, persistence, messaging, observabilité et CI"
fi

if (( errors > 0 )); then
  printf '\nDecision documentation checks: %d failure(s), %d passed group(s).\n' "$errors" "$checks"
  exit 1
fi

printf '\nDecision documentation checks: all %d groups passed.\n' "$checks"
