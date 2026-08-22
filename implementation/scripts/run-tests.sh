#!/usr/bin/env bash
set -euo pipefail

impl_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
compose=(docker compose -f "$impl_dir/docker-compose.yml")

if ! docker info >/dev/null 2>&1; then
  compose=(sudo docker compose -f "$impl_dir/docker-compose.yml")
fi

if ! "${compose[@]}" exec -T postgres psql -U atlas -d postgres -tAc \
  "SELECT 1 FROM pg_database WHERE datname = 'atlas_test'" | grep -q 1; then
  "${compose[@]}" exec -T postgres createdb -U atlas atlas_test
fi

exec "${compose[@]}" exec -T -e DB_DATABASE=atlas_test app bash -c '
  set -euo pipefail
  cd /workspace/implementation/app
  composer dump-autoload -o
  ./vendor/bin/pest "$@"
' _ "$@"
