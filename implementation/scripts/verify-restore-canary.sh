#!/usr/bin/env bash
set -euo pipefail

impl_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
repo_root="$(cd "$impl_dir/.." && pwd)"
compose=(docker compose -f "$impl_dir/docker-compose.yml")

if ! docker info >/dev/null 2>&1; then
  compose=(sudo docker compose -f "$impl_dir/docker-compose.yml")
fi

backup_file="${1:-}"

if [[ -z "$backup_file" ]]; then
  latest="$(ls -1t "$impl_dir/backups"/atlas-*.dump 2>/dev/null | head -1 || true)"
  if [[ -z "$latest" ]]; then
    printf 'No backup file provided and no dump found in implementation/backups/\n' >&2
    exit 1
  fi
  backup_file="$latest"
fi

printf '==> SEC-TEST-023 restore canary\n'
printf 'Backup: %s\n' "$backup_file"

"$impl_dir/scripts/backup-postgres.sh"
canary_backup="$(ls -1t "$impl_dir/backups"/atlas-*.dump | head -1)"

"$impl_dir/scripts/restore-postgres.sh" "$backup_file"

printf '\n==> Post-restore checks\n'

"${compose[@]}" exec -T postgres psql -U atlas -d atlas -v ON_ERROR_STOP=1 -c \
  "SELECT schema_name FROM information_schema.schemata WHERE schema_name IN ('identity','workspace','crm','billing','analytics','business_health','advisor','notifications','platform') ORDER BY 1;"

"${compose[@]}" exec -T app bash -c '
  set -euo pipefail
  cd /workspace/implementation/app
  php artisan migrate:status --no-interaction | tail -5
  ./vendor/bin/pest tests/Architecture/ReferenceFixturesOracleTest.php tests/Architecture/ModuleBoundariesTest.php --colors=never
'

if command -v jq >/dev/null 2>&1; then
  "$repo_root/scripts/check-mvp-reference-fixtures.sh"
else
  printf 'SKIP: jq not available on host for fixture oracle\n'
fi

printf '\n==> Restoring pre-canary snapshot\n'
"$impl_dir/scripts/restore-postgres.sh" "$canary_backup"

printf '\nSEC-TEST-023 canary passed.\n'
