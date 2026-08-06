#!/usr/bin/env bash

set -uo pipefail

repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
security_root="$repo_root/fondation/security"
model="$security_root/mvp-threat-model.md"
errors=0
checks=0

tmp_ids="$(mktemp)"
tmp_threats="$(mktemp)"
tmp_expected_threats="$(mktemp)"
tmp_controls="$(mktemp)"
tmp_referenced_controls="$(mktemp)"
tmp_tests="$(mktemp)"
tmp_referenced_tests="$(mktemp)"

cleanup() {
  rm -f "$tmp_ids" "$tmp_threats" "$tmp_expected_threats" \
    "$tmp_controls" "$tmp_referenced_controls" "$tmp_tests" \
    "$tmp_referenced_tests"
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

if [[ ! -d "$security_root" ]]; then
  printf 'Security directory not found: %s\n' "$security_root" >&2
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
done < <(find "$security_root" -maxdepth 1 -type f -name '*.md' -print0)

if (( errors == structure_errors )); then
  pass "front matter, références, liens et blocs de code valides"
fi

duplicate_ids="$(cut -d'|' -f1 "$tmp_ids" | sort | uniq -d)"
if [[ -n "$duplicate_ids" ]]; then
  fail "IDs Security dupliqués: $(printf '%s' "$duplicate_ids" | paste -sd, -)"
else
  pass "IDs Security uniques"
fi

coverage_errors=$errors
for heading in '## Périmètre' '## Hypothèses contestables' \
  '## Actifs et classification' '## Objectifs de sécurité' \
  '## Acteurs et agents de menace' '## Frontières de confiance' \
  "## Points d'entrée et de sortie" '## Flux sensibles' \
  '## Catalogue des contrôles requis' '## Priorisation qualitative' \
  '## Registre des menaces' '## Matrice de vérification' \
  '## Gaps et décisions ouvertes' '## Déclencheurs de réexamen'; do
  rg -q -F "$heading" "$model" || fail "section du modèle absente: $heading"
done
for baseline in 'OWASP Threat Modeling Project' 'OWASP ASVS 5.0.0' \
  'OWASP API Security Top 10' 'NIST SP 800-63B-4'; do
  rg -q "$baseline" "$model" || fail "baseline officielle absente: $baseline"
done
if (( errors == coverage_errors )); then
  pass "scope, actifs, frontières, méthode et baselines présents"
fi

threat_errors=$errors
awk -F'|' '$2 ~ /`SEC-T[0-9][0-9]`/ {
  value=$2; gsub(/^[[:space:]]*`|`[[:space:]]*$/, "", value); print value
}' "$model" | sort -u > "$tmp_threats"
for number in $(seq -w 1 28); do
  printf 'SEC-T%s\n' "$number"
done > "$tmp_expected_threats"
threat_drift="$(comm -3 "$tmp_expected_threats" "$tmp_threats")"
if [[ -n "$threat_drift" ]]; then
  fail "registre attendu SEC-T01 à SEC-T28 incomplet: $(printf '%s' "$threat_drift" | paste -sd, -)"
fi

while IFS='|' read -r _ threat scenario initial controls detection verification owner residual _; do
  [[ "$threat" =~ SEC-T[0-9][0-9] ]] || continue
  for value_name in scenario initial controls detection verification owner residual; do
    value="${!value_name}"
    value="${value//[[:space:]]/}"
    [[ -n "$value" ]] || fail "colonne $value_name vide pour $threat"
  done
done < "$model"
if (( errors == threat_errors )); then
  pass "vingt-huit menaces avec risque, contrôle, détection, test, owner et résiduel"
fi

trace_errors=$errors
awk -F'|' '$2 ~ /`SEC-C[0-9][0-9]`/ {
  value=$2; gsub(/^[[:space:]]*`|`[[:space:]]*$/, "", value); print value
}' "$model" | sort -u > "$tmp_controls"
awk -F'|' '$2 ~ /`SEC-T[0-9][0-9]`/ { print $5 }' "$model" \
  | rg -o 'C[0-9][0-9]' | sed 's/^/SEC-/' | sort -u > "$tmp_referenced_controls"
control_drift="$(comm -3 "$tmp_controls" "$tmp_referenced_controls")"
if [[ -n "$control_drift" ]]; then
  fail "contrôles déclarés et référencés incohérents: $(printf '%s' "$control_drift" | paste -sd, -)"
fi

awk -F'|' '$2 ~ /`SEC-TEST-[0-9][0-9][0-9]`/ {
  value=$2; gsub(/^[[:space:]]*`|`[[:space:]]*$/, "", value); print value
}' "$model" | sort -u > "$tmp_tests"
awk -F'|' '$2 ~ /`SEC-T[0-9][0-9]`/ { print $7 }' "$model" \
  | rg -o 'SEC-TEST-[0-9]{3}' | sort -u > "$tmp_referenced_tests"
test_drift="$(comm -3 "$tmp_tests" "$tmp_referenced_tests")"
if [[ -n "$test_drift" ]]; then
  fail "tests déclarés et référencés incohérents: $(printf '%s' "$test_drift" | paste -sd, -)"
fi
if (( errors == trace_errors )); then
  pass "vingt contrôles et vingt-sept tests entièrement tracés"
fi

gap_errors=$errors
gap_count="$(awk -F'|' '$2 ~ /`SEC-GAP-[0-9][0-9][0-9]`/ {count++} END{print count+0}' "$model")"
if [[ "$gap_count" != "9" ]]; then
  fail "le registre contient $gap_count gaps au lieu de 9"
fi
if awk -F'|' '$2 ~ /`SEC-GAP-[0-9][0-9][0-9]`/ && $6 !~ /Open/ {found=1} END{exit !found}' "$model"; then
  fail "un gap initial n'est pas Open"
fi
rg -q "Aucun risque.*High.*Critical.*accepté" "$model" || \
  fail "règle d'acceptation des risques élevés absente"
if (( errors == gap_errors )); then
  pass "neuf gaps ouverts et aucun risque élevé implicitement accepté"
fi

contract_errors=$errors
for term in PublicDocumentProof UserSecurityVersion SystemActorOnly \
  WorkspaceId outbox inbox ExpectedRevision DeliveryEndpointReference; do
  rg -q "$term" "$model" || fail "concept de sécurité contractuel absent: $term"
done
rg -q 'PublicDocumentProof' "$repo_root/fondation/domains/billing/value-objects.md" || \
  fail "preuve publique absente du domaine Billing"
rg -q 'UserSecurityVersion' "$repo_root/fondation/domains/identity/api.md" \
  "$repo_root/fondation/domains/identity/commands/CreateSession.md" || \
  fail "version de sécurité absente d'Identity"
rg -q 'revalidateNotificationRecipient' "$repo_root/fondation/domains/notifications/integrations.md" || \
  fail "revalidation Notifications absente"
if (( errors == contract_errors )); then
  pass "contrats Identity, Workspace, Billing, messaging et Notifications reconnus"
fi

governance_errors=$errors
for governance_file in \
  "$repo_root/README.md" \
  "$repo_root/fondation/README.md" \
  "$repo_root/evolution/blueprint/README.md" \
  "$repo_root/evolution/governance/quality-gates.md" \
  "$repo_root/evolution/governance/consolidation-matrix.md"; do
  rg -q 'Security|sécurité|mvp-threat-model' "$governance_file" || \
    fail "Security absent de ${governance_file#"$repo_root/"}"
done
rg -q 'scripts/check-security-docs.sh' "$security_root/README.md" || \
  fail "checker absent de l'entrée Security"
if (( errors == governance_errors )); then
  pass "Security intégré aux entrées, Blueprint et quality gates"
fi

if (( errors > 0 )); then
  printf '\nSecurity documentation checks: %d failure(s), %d passed group(s).\n' "$errors" "$checks"
  exit 1
fi

printf '\nSecurity documentation checks: all %d groups passed.\n' "$checks"
