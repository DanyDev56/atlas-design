#!/usr/bin/env bash

set -uo pipefail

repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
notifications_root="$repo_root/fondation/domains/notifications"
commands_root="$notifications_root/commands"
processors_root="$notifications_root/processors"
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

cleanup() {
  rm -f "$tmp_ids" "$tmp_command_files" "$tmp_command_links" \
    "$tmp_matrix_commands" "$tmp_processor_files" "$tmp_processor_links" \
    "$tmp_matrix_processors" "$tmp_declared_invariants" \
    "$tmp_referenced_invariants" "$tmp_catalog_events" "$tmp_trace_events"
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

if [[ ! -d "$notifications_root" ]]; then
  printf 'Notifications directory not found: %s\n' "$notifications_root" >&2
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
done < <(find "$notifications_root" -type f -name '*.md' -print0)

if (( errors == structure_errors )); then
  pass "front matter, références, liens et blocs de code valides"
fi

duplicate_ids="$(cut -d'|' -f1 "$tmp_ids" | sort | uniq -d)"
if [[ -n "$duplicate_ids" ]]; then
  fail "IDs de document dupliqués: $(printf '%s' "$duplicate_ids" | paste -sd, -)"
else
  pass "IDs de document uniques"
fi

# Command and processor inventories must match their catalogues and matrix.
find "$commands_root" -maxdepth 1 -type f -name '*.md' ! -name 'README.md' -printf '%f\n' \
  | sed 's/\.md$//' | sort > "$tmp_command_files"
awk '/^## Catalogue$/ {catalog=1; next} /^## Conventions communes$/ {catalog=0} catalog {print}' \
  "$commands_root/README.md" \
  | sed -n 's/.*](\([^)]*\.md\)).*/\1/p' | sed -e 's#^.*/##' -e 's/\.md$//' \
  | sort -u > "$tmp_command_links"
awk '/^## Traçabilité des commandes$/ {trace=1; next} /^## Traçabilité des processeurs$/ {trace=0} trace {print}' \
  "$notifications_root/consolidation-matrix.md" \
  | sed -n 's/^| `\([^`]*\)`.*/\1/p' | sort -u > "$tmp_matrix_commands"

command_drift="$(comm -3 "$tmp_command_files" "$tmp_command_links"; comm -3 "$tmp_command_files" "$tmp_matrix_commands")"
if [[ -n "$command_drift" ]]; then
  fail "écart entre fichiers, catalogue et matrice des commandes: $(printf '%s' "$command_drift" | paste -sd, -)"
else
  pass "catalogue, fichiers et matrice des commandes alignés"
fi

find "$processors_root" -maxdepth 1 -type f -name '*.md' ! -name 'README.md' -printf '%f\n' \
  | sed 's/\.md$//' | sort > "$tmp_processor_files"
awk '/^## Catalogue$/ {catalog=1; next} /^## Conventions communes$/ {catalog=0} catalog {print}' \
  "$processors_root/README.md" \
  | sed -n 's/.*](\([^)]*\.md\)).*/\1/p' | sed -e 's#^.*/##' -e 's/\.md$//' \
  | sort -u > "$tmp_processor_links"
awk '/^## Traçabilité des processeurs$/ {trace=1; next} /^Les numéros abrégés/ {trace=0} trace {print}' \
  "$notifications_root/consolidation-matrix.md" \
  | sed -n 's/^| `\([^`]*\)`.*/\1/p' | sort -u > "$tmp_matrix_processors"

processor_drift="$(comm -3 "$tmp_processor_files" "$tmp_processor_links"; comm -3 "$tmp_processor_files" "$tmp_matrix_processors")"
if [[ -n "$processor_drift" ]]; then
  fail "écart entre fichiers, catalogue et matrice des processeurs: $(printf '%s' "$processor_drift" | paste -sd, -)"
else
  pass "catalogue, fichiers et matrice des processeurs alignés"
fi

# Every executable contract references declared invariants and idempotency.
rg -o --no-filename 'NTF-INV-[0-9]{3}' "$notifications_root/invariants.md" | sort -u > "$tmp_declared_invariants"
contract_errors=$errors
while IFS= read -r contract_file; do
  rg -q 'NTF-INV-[0-9]{3}' "$contract_file" || \
    fail "aucun invariant référencé: ${contract_file#"$repo_root/"}"
  rg -o --no-filename 'NTF-INV-[0-9]{3}' "$contract_file" >> "$tmp_referenced_invariants" || true
  rg -qi 'idempoten' "$contract_file" || \
    fail "politique d'idempotence absente: ${contract_file#"$repo_root/"}"
  rg -q '(RequestId|ProviderOutcomeId|ProviderIdempotencyKey)' "$contract_file" || \
    fail "clé d'idempotence absente: ${contract_file#"$repo_root/"}"
done < <(find "$commands_root" "$processors_root" -maxdepth 1 -type f -name '*.md' ! -name 'README.md' | sort)

sort -u -o "$tmp_referenced_invariants" "$tmp_referenced_invariants"
unknown_invariants="$(comm -13 "$tmp_declared_invariants" "$tmp_referenced_invariants")"
if [[ -n "$unknown_invariants" ]]; then
  fail "invariants référencés mais non déclarés: $(printf '%s' "$unknown_invariants" | paste -sd, -)"
fi
if (( errors == contract_errors )); then
  pass "invariants, concurrence et idempotence documentés"
fi

# Event catalogue and producer trace must match exactly.
awk '/^## Catalogue$/ {catalog=1; next} /^## Contrat public de création$/ {catalog=0} catalog {print}' \
  "$notifications_root/events.md" \
  | sed -n 's/^| `\(Notification[A-Za-z0-9]*\)`.*/\1/p' | sort -u > "$tmp_catalog_events"
awk '/^## Traçabilité des commandes$/ {trace=1; next} /^Les numéros abrégés/ {trace=0} trace {print}' \
  "$notifications_root/consolidation-matrix.md" \
  | rg -o '`Notification[A-Z][A-Za-z0-9]*`' | tr -d '`' | sort -u > "$tmp_trace_events"

event_drift="$(comm -3 "$tmp_catalog_events" "$tmp_trace_events")"
if [[ -n "$event_drift" ]]; then
  fail "écart entre événements et producteurs: $(printf '%s' "$event_drift" | paste -sd, -)"
else
  pass "Domain Events et producteurs entièrement tracés"
fi

# Capabilities, policies and cross-domain contracts.
permission_errors=$errors
for permission in \
  notifications.inbox.read notifications.inbox.mark-read \
  notifications.preferences.read notifications.preferences.change \
  notifications.signals.process notifications.deliveries.dispatch \
  notifications.deliveries.record-outcome notifications.items.expire; do
  rg -q "\`$permission\`" "$notifications_root/permissions.md" || \
    fail "capacité absente: $permission"
done
if (( errors == permission_errors )); then
  pass "huit capacités humaines et système présentes"
fi

policy_errors=$errors
rg -q 'InAppMode = Enabled' "$notifications_root/notification-policy.md" || fail "default InApp absent"
rg -q 'EmailMode = Disabled' "$notifications_root/notification-policy.md" || fail "default Email absent"
rg -q 'Priority High ou Critical' "$notifications_root/notification-policy.md" || fail "importance Email absente"
rg -q 'fenêtre glissante de 24 heures' "$notifications_root/notification-policy.md" || fail "fréquence Email absente"
rg -q 'High.*Critical' "$notifications_root/notification-policy.md" || fail "escalade Critical absente"
rg -q 'Accepted signifie prise en charge, jamais Delivered' "$notifications_root/notification-policy.md" || \
  fail "distinction Accepted/Delivered absente"
if (( errors == policy_errors )); then
  pass "defaults, importance, fréquence et preuve de livraison cohérents"
fi

integration_errors=$errors
rg -q 'AdvisorOverviewChanged' "$repo_root/fondation/domains/advisor/events.md" || \
  fail "événement de convergence Advisor absent"
rg -q 'AdvisorOverviewChanged' "$notifications_root/integrations.md" || \
  fail "événement de convergence Advisor non reconnu par Notifications"
rg -q 'getAdvisorOverviewForNotification' "$repo_root/fondation/domains/advisor/api.md" || \
  fail "contrat Advisor absent"
rg -q 'getAdvisorOverviewForNotification' "$notifications_root/integrations.md" || \
  fail "contrat Advisor non reconnu par Notifications"
rg -q 'AdvisorOverviewVersion' "$repo_root/fondation/domains/advisor/events.md" || \
  fail "ordre AdvisorOverviewVersion absent des événements Advisor"
rg -q 'NotificationTopicCursor' "$notifications_root/aggregates.md" || \
  fail "cursor monotone Notifications absent"
rg -q 'resolveWorkspaceNotificationAudience' "$repo_root/fondation/domains/identity/api.md" || \
  fail "contrat audience Identity absent"
rg -q 'resolveWorkspaceNotificationAudience' "$notifications_root/integrations.md" || \
  fail "contrat audience Identity non reconnu par Notifications"
rg -q 'getWorkspaceAccessContext' "$repo_root/fondation/domains/workspace/api.md" || \
  fail "contrat Workspace absent"
rg -q 'getWorkspaceAccessContext' "$notifications_root/integrations.md" || \
  fail "contrat Workspace non reconnu par Notifications"
if (( errors == integration_errors )); then
  pass "contrats Advisor–Identity–Workspace–Notifications symétriques"
fi

boundary_errors=$errors
if rg -q '\bNotificationSent\b' "$notifications_root"; then
  fail "événement ambigu NotificationSent utilisé"
fi
if rg -q '\bRecommendationGenerated\b.*(déclenche|planifi)' "$notifications_root" -g '*.md' \
  | rg -vq '(ne déclenche|n.est pas un déclencheur|Aucune planification)'; then
  fail "RecommendationGenerated pourrait déclencher une planification"
fi
if rg -q '\bEmailAddress\b' "$notifications_root" -g '*.md'; then
  fail "EmailAddress brute modélisée dans Notifications"
fi
if (( errors == boundary_errors )); then
  pass "frontières source, endpoint et terminologie respectées"
fi

map_errors=$errors
for map_file in \
  "$repo_root/fondation/domains/README.md" \
  "$repo_root/fondation/domain-map/context-map.md" \
  "$repo_root/fondation/domain-map/ownership.md" \
  "$repo_root/fondation/domain-map/dependencies.md"; do
  rg -q 'Notifications' "$map_file" || \
    fail "Notifications absent de la cartographie: ${map_file#"$repo_root/"}"
done
if (( errors == map_errors )); then
  pass "Notifications intégré aux cartes globales"
fi

if (( errors > 0 )); then
  printf '\nNotifications documentation checks: %d failure(s), %d passed group(s).\n' "$errors" "$checks"
  exit 1
fi

printf '\nNotifications documentation checks: all %d groups passed.\n' "$checks"
