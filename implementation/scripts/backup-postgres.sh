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
started_at="$(date -u +"%Y-%m-%dT%H:%M:%SZ")"
run_reference="atlas-${timestamp}"
dump_file="$backup_dir/atlas-${timestamp}.dump"
manifest_file="$backup_dir/atlas-${timestamp}.manifest.json"
container_dump_file="/tmp/${run_reference}.dump"
partial_dump_file="${dump_file}.partial"

record_result() {
  local status="$1"
  local size_bytes="${2:-}"
  local args=(php artisan atlas:operations:record-maintenance Backup "$status" "$run_reference" --started-at="$started_at")
  if [[ -n "$size_bytes" ]]; then
    args+=(--size-bytes="$size_bytes")
  fi
  "${compose[@]}" exec -T app "${args[@]}" >/dev/null 2>&1 || true
}

on_exit() {
  local exit_code=$?
  rm -f "$partial_dump_file"
  "${compose[@]}" exec -T postgres rm -f "$container_dump_file" >/dev/null 2>&1 || true
  if [[ $exit_code -ne 0 ]]; then
    record_result Failed
  fi
}
trap on_exit EXIT

"${compose[@]}" exec -T postgres pg_dump \
  -U atlas \
  -d atlas \
  --format=custom \
  --no-owner \
  --role=atlas \
  --file="$container_dump_file"
"${compose[@]}" cp "postgres:${container_dump_file}" "$partial_dump_file"
mv "$partial_dump_file" "$dump_file"
"${compose[@]}" exec -T postgres rm -f "$container_dump_file"

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
    "subscriptions",
    "operations",
    "platform",
    "public"
  ]
}
EOF

record_result Succeeded "$(stat -c '%s' "$dump_file")"
trap - EXIT

printf 'Backup written: %s\n' "$dump_file"
printf 'Manifest written: %s\n' "$manifest_file"
