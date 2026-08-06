#!/usr/bin/env bash

set -uo pipefail

repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
fixture_root="$repo_root/evolution/reference-fixtures"
fixture_file="$fixture_root/mvp-v1.json"
oracle_file="$repo_root/scripts/lib/check-mvp-reference-fixtures.jq"
metric_catalog="$repo_root/fondation/domains/analytics/metric-catalog.md"
errors=0
checks=0

tmp_catalog_metrics="$(mktemp)"
tmp_fixture_metrics="$(mktemp)"
tmp_case_metrics="$(mktemp)"
tmp_fixture_ids="$(mktemp)"

cleanup() {
  rm -f "$tmp_catalog_metrics" "$tmp_fixture_metrics" "$tmp_case_metrics" "$tmp_fixture_ids"
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
  "$fixture_root/README.md"
  "$fixture_file"
  "$oracle_file"
  "$metric_catalog"
  "$repo_root/fondation/domains/business-health/health-policy.md"
  "$repo_root/fondation/domains/advisor/recommendation-policy.md"
  "$repo_root/fondation/domains/advisor/recommendation-score.md"
  "$repo_root/fondation/domains/notifications/notification-policy.md"
)

for file in "${required_files[@]}"; do
  [[ -f "$file" ]] || fail "fichier requis absent: ${file#"$repo_root/"}"
done
if (( errors == 0 )); then
  pass "catalogue, fixture, oracle et politiques propriétaires présents"
fi

if ! command -v jq >/dev/null 2>&1; then
  printf 'jq is required to validate MVP reference fixtures.\n' >&2
  exit 2
fi

if ! jq empty "$fixture_file" >/dev/null 2>&1; then
  fail "JSON invalide: ${fixture_file#"$repo_root/"}"
else
  pass "fixture JSON syntaxiquement valide"
fi

metadata_errors=$errors
if ! jq -e '
  .fixture_set_id == "atlas.mvp.reference-fixtures"
  and .fixture_schema_version == "1.0.0"
  and .fixture_set_version == "1.2.0"
  and .as_of == "2026-06-30T00:00:00Z"
  and .reporting_time_zone == "UTC"
  and .reporting_calendar_version == "1.0.0"
  and .currency == "EUR"
  and .money_unit == "minor"
  and .policy_versions.analytics_metric_catalog_document == "1.2.0"
  and .policy_versions.analytics_metric_definition == "1.0"
  and .policy_versions.analytics_snapshot_profile_key == "BusinessHealthBaselineV1"
  and .policy_versions.analytics_snapshot_profile_version == "1.0.0"
  and .policy_versions.business_health == "1.0.0"
  and .policy_versions.advisor == "1.0.0"
  and .policy_versions.notifications == "1.1.0"
  and .snapshot_profile == {
    key: "BusinessHealthBaselineV1",
    version: "1.0.0",
    required_completeness: "Complete",
    current_lag_threshold_seconds: 3600,
    maximum_accepted_lag_seconds: 86400
  }
  and .known_contract_gaps == []
' "$fixture_file" >/dev/null; then
  fail "métadonnées, versions de politique ou seuils SnapshotProfile incorrects"
fi

rg -q '^version: 1\.2\.0$' "$metric_catalog" || \
  fail "version du catalogue Analytics différente de la fixture"
rg -q 'HealthPolicyVersion = 1\.0\.0' "$repo_root/fondation/domains/business-health/health-policy.md" || \
  fail "HealthPolicyVersion différente de la fixture"
rg -q '^version: 1\.0\.0$' "$repo_root/fondation/domains/advisor/recommendation-policy.md" || \
  fail "RecommendationPolicyVersion différente de la fixture"
rg -q '^version: 1\.1\.0$' "$repo_root/fondation/domains/notifications/notification-policy.md" || \
  fail "NotificationPolicyVersion différente de la fixture"
if (( errors == metadata_errors )); then
  pass "contexte de calcul, versions et seuils SnapshotProfile canoniques"
fi

scenario_errors=$errors
jq -r '.cases[].fixture_id' "$fixture_file" | sort > "$tmp_fixture_ids"
expected_ids="$(printf '%s\n' FIX-001 FIX-002 FIX-003 FIX-004 FIX-005 FIX-006 FIX-007 FIX-008 FIX-009 FIX-010)"
actual_ids="$(cat "$tmp_fixture_ids")"
if [[ "$actual_ids" != "$expected_ids" ]]; then
  fail "catalogue attendu FIX-001 à FIX-010 incomplet ou dupliqué"
fi

expected_scenarios="$(printf '%s\n' \
  accepted-quote-without-payment \
  dominant-client \
  empty-activity \
  high-recommendation-email-eligible \
  opportunity-without-quote \
  partial-payment \
  recipient-revoked-before-dispatch \
  settled-invoice \
  stale-data \
  sufficient-data-without-recommendation)"
actual_scenarios="$(jq -r '.cases[].scenario_key' "$fixture_file" | sort)"
if [[ "$actual_scenarios" != "$expected_scenarios" ]]; then
  fail "les dix scénarios obligatoires ne sont pas couverts exactement une fois"
fi

if ! jq -e '
  (.cases | length) == 10
  and all(.cases[];
    (.title | type == "string" and length > 0)
    and (.purpose | type == "string" and length > 0)
    and (.source_fact_summary | keys | sort) == ([
      "collected_rolling_30_days",
      "collections_rolling_365_days",
      "net_invoiced_rolling_30_days",
      "pending_quotes",
      "pipeline",
      "quote_decisions_rolling_90_days",
      "receivables",
      "settlements_rolling_90_days"
    ] | sort)
    and (.expected | keys | sort) == (["advisor", "analytics", "business_health", "notifications"] | sort)
  )
' "$fixture_file" >/dev/null; then
  fail "structure obligatoire absente dans au moins une fixture"
fi
if (( errors == scenario_errors )); then
  pass "dix scénarios obligatoires, identifiants et sections présents"
fi

metric_errors=$errors
rg -o --no-filename 'analytics\.[a-z-]+\.[a-z-]+' "$metric_catalog" | sort -u > "$tmp_catalog_metrics"
jq -r '.metric_keys[]' "$fixture_file" | sort -u > "$tmp_fixture_metrics"
if ! cmp -s "$tmp_catalog_metrics" "$tmp_fixture_metrics"; then
  fail "MetricKeys de la fixture différentes des treize clés du catalogue"
fi

while IFS= read -r fixture_id; do
  jq -r --arg fixture_id "$fixture_id" \
    '.cases[] | select(.fixture_id == $fixture_id) | .expected.analytics.metrics | keys[]' \
    "$fixture_file" | sort -u > "$tmp_case_metrics"
  if ! cmp -s "$tmp_fixture_metrics" "$tmp_case_metrics"; then
    fail "$fixture_id ne contient pas exactement les treize MetricKeys"
  fi
done < "$tmp_fixture_ids"

if ! jq -e '
  all(.cases[].expected.analytics.metrics[];
    (.state == "Available" or .state == "NoData")
    and (.unit == "Money" or .unit == "Count" or .unit == "Ratio" or .unit == "Duration")
    and (.change_kind == "Increased" or .change_kind == "Stable" or .change_kind == "Decreased"
      or .change_kind == "Started" or .change_kind == "Stopped" or .change_kind == "NotComparable")
    and if .state == "Available" then has("exact_value") else (has("exact_value") | not) end
    and if .state == "Available" and .unit == "Money" then .currency == "EUR" else true end
    and if .state == "Available" and .unit == "Ratio" then
      has("numerator") and has("denominator") and .denominator > 0 and .sample_size > 0
    else true end
    and if .state == "Available" and .unit == "Duration" then .sample_size > 0 else true end
  )
' "$fixture_file" >/dev/null; then
  fail "forme MetricValue invalide"
fi
if (( errors == metric_errors )); then
  pass "treize MetricKeys exactes et MetricValue bien formées dans chaque cas"
fi

source_errors=$errors
if ! jq -e '
  def non_negative_or_null: . == null or (type == "number" and . >= 0);
  all(.cases[].source_fact_summary;
    (.pipeline.current_amount_minor | non_negative_or_null)
    and (.pipeline.baseline_amount_minor | non_negative_or_null)
    and (.pending_quotes.current_amount_minor | non_negative_or_null)
    and (.pending_quotes.baseline_amount_minor | non_negative_or_null)
    and (.net_invoiced_rolling_30_days.current_amount_minor | non_negative_or_null)
    and (.net_invoiced_rolling_30_days.baseline_amount_minor | non_negative_or_null)
    and (.collected_rolling_30_days.current_amount_minor | non_negative_or_null)
    and (.collected_rolling_30_days.baseline_amount_minor | non_negative_or_null)
    and all(.quote_decisions_rolling_90_days[] | select(. != null);
      .accepted >= 0 and .rejected >= 0 and .expired >= 0
      and .response_sample_size >= 0 and .response_seconds_total >= 0
      and .response_sample_size <= (.accepted + .rejected)
    )
    and all(.receivables[] | select(. != null);
      .outstanding_amount_minor >= 0 and .overdue_amount_minor >= 0
      and .overdue_amount_minor <= .outstanding_amount_minor
      and .overdue_count >= 0 and .due_soon_amount_minor >= 0
    )
    and all(.settlements_rolling_90_days[] | select(. != null);
      .settled_count >= 0 and .on_time_count >= 0 and .on_time_count <= .settled_count
      and .duration_seconds_total >= 0
    )
    and all(.collections_rolling_365_days[] | select(. != null);
      .total_amount_minor > 0
      and ([.by_client_minor[]] | add) == .total_amount_minor
      and all(.by_client_minor[]; . > 0)
    )
  )
' "$fixture_file" >/dev/null; then
  fail "résumé de faits incohérent ou non recalculable"
fi

if jq -e '.. | objects | select(has("email") or has("address") or has("client_name") or has("secret"))' \
  "$fixture_file" >/dev/null; then
  fail "champ personnel ou secret interdit dans les fixtures synthétiques"
fi
if (( errors == source_errors )); then
  pass "faits normalisés cohérents, équilibrés et dépourvus de données sensibles"
fi

run_oracle_section() {
  local section="$1"
  local success_message="$2"
  local output
  local status

  output="$(jq -r --arg section "$section" -f "$oracle_file" "$fixture_file" 2>&1)"
  status=$?
  if (( status != 0 )); then
    fail "oracle $section inexécutable: $output"
  elif [[ -n "$output" ]]; then
    while IFS= read -r message; do
      [[ -n "$message" ]] && fail "$message"
    done <<< "$output"
  else
    pass "$success_message"
  fi
}

run_oracle_section analytics "treize observations Analytics recalculées depuis les faits"
run_oracle_section health "composants, facteurs, couverture, score, attention et risques recalculés"
run_oracle_section advisor "déclencheurs, rangs, priorités, validités et ordre Advisor recalculés"
run_oracle_section notifications "consentement, canal Email, révocation et effets externes vérifiés"

if (( errors > 0 )); then
  printf '\nMVP reference fixture checks: %d failure(s), %d passed group(s).\n' "$errors" "$checks"
  exit 1
fi

printf '\nMVP reference fixture checks: all %d groups passed.\n' "$checks"
