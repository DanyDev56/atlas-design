#!/usr/bin/env bash

set -uo pipefail

repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

required_commands=(rg jq)
checkers=(
  check-identity-docs.sh
  check-workspace-docs.sh
  check-crm-docs.sh
  check-billing-docs.sh
  check-analytics-docs.sh
  check-business-health-docs.sh
  check-advisor-docs.sh
  check-notifications-docs.sh
  check-decisions-docs.sh
  check-security-docs.sh
  check-mvp-blueprint-docs.sh
  check-mvp-reference-fixtures.sh
)

missing_commands=()
failed_checkers=()

for command_name in "${required_commands[@]}"; do
  command -v "$command_name" >/dev/null 2>&1 || missing_commands+=("$command_name")
done

if (( ${#missing_commands[@]} > 0 )); then
  printf 'Missing required command(s): %s\n' "${missing_commands[*]}" >&2
  exit 2
fi

for checker in "${checkers[@]}"; do
  printf '\n==> %s\n' "$checker"
  if ! "$repo_root/scripts/$checker"; then
    failed_checkers+=("$checker")
  fi
done

if (( ${#failed_checkers[@]} > 0 )); then
  printf '\nDocumentation quality gates failed (%d/%d): %s\n' \
    "${#failed_checkers[@]}" "${#checkers[@]}" "${failed_checkers[*]}" >&2
  exit 1
fi

printf '\nDocumentation quality gates: all %d checkers passed.\n' "${#checkers[@]}"
