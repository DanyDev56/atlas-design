#!/usr/bin/env bash
set -euo pipefail
repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$repo_root"

python3 scripts/check-repository-docs.py
for check in scripts/check-*-docs.sh; do
  printf '\n==> %s\n' "$check"
  bash "$check"
done

git diff --check
printf '\nPASS: tous les contrôles Atlas sont réussis\n'
