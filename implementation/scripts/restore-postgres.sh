#!/usr/bin/env bash
set -euo pipefail

if [[ $# -lt 1 ]]; then
  printf 'Usage: %s <path-to-backup.dump>\n' "$(basename "$0")" >&2
  exit 1
fi

backup_file="$1"

if [[ ! -f "$backup_file" ]]; then
  printf 'Backup file not found: %s\n' "$backup_file" >&2
  exit 1
fi

impl_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
compose=(docker compose -f "$impl_dir/docker-compose.yml")

if ! docker info >/dev/null 2>&1; then
  compose=(sudo docker compose -f "$impl_dir/docker-compose.yml")
fi

printf 'Terminating active connections to atlas…\n'
"${compose[@]}" exec -T postgres psql -U atlas -d postgres -v ON_ERROR_STOP=1 <<'SQL'
SELECT pg_terminate_backend(pid)
FROM pg_stat_activity
WHERE datname = 'atlas'
  AND pid <> pg_backend_pid();
SQL

printf 'Restoring from %s…\n' "$backup_file"
cat "$backup_file" | "${compose[@]}" exec -T postgres pg_restore \
  -U atlas \
  -d atlas \
  --clean \
  --if-exists \
  --no-owner \
  --role=atlas

printf 'Restore complete. Run verify-restore-canary.sh to validate (SEC-TEST-023).\n'
