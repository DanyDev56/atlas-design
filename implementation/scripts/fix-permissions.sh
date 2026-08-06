#!/usr/bin/env bash
# Corrige les permissions après un bootstrap lancé avec sudo.
set -euo pipefail

repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
target_user="${SUDO_USER:-$(whoami)}"
target_group="$(id -gn "$target_user")"

if [[ "$target_user" == "root" ]]; then
  printf 'Run with sudo from a normal user account.\n' >&2
  exit 1
fi

sudo chown -R "$target_user:$target_group" "$repo_root/implementation/app"
printf 'Ownership restored to %s:%s\n' "$target_user" "$target_group"
