#!/usr/bin/env bash
set -euo pipefail

impl_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
backup_dir="$impl_dir/backups"
compose=(docker compose -f "$impl_dir/docker-compose.yml")

if ! docker info >/dev/null 2>&1; then
  compose=(sudo docker compose -f "$impl_dir/docker-compose.yml")
fi

mkdir -p "$backup_dir"

timestamp="$(date -u +"%Y%m%dT%H%M%SZ")"
dump_file="$backup_dir/atlas-${timestamp}.dump"
manifest_file="$backup_dir/atlas-${timestamp}.manifest.json"

"${compose[@]}" exec -T postgres pg_dump \
  -U atlas \
  -d atlas \
  --format=custom \
  --no-owner \
  --role=atlas \
  > "$dump_file"

pg_version="$("${compose[@]}" exec -T postgres psql -U atlas -d atlas -tAc "SHOW server_version;" | tr -d '[:space:]')"
migration_count="$("${compose[@]}" exec -T postgres psql -U atlas -d atlas -tAc "SELECT COUNT(*) FROM public.migrations;" 2>/dev/null | tr -d '[:space:]' || echo "0")"

cat > "$manifest_file" <<EOF
{
  "backup_id": "atlas-${timestamp}",
  "created_at": "${timestamp}",
  "database": "atlas",
  "format": "pg_custom",
  "postgres_server_version": "${pg_version}",
  "applied_migrations_at_backup": ${migration_count},
  "schemas": [
    "identity",
    "workspace",
    "crm",
    "billing",
    "analytics",
    "business_health",
    "advisor",
    "notifications",
    "platform",
    "public"
  ]
}
EOF

printf 'Backup written: %s\n' "$dump_file"
printf 'Manifest written: %s\n' "$manifest_file"
