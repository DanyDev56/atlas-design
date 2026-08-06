#!/usr/bin/env bash
set -euo pipefail

impl_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
compose=(docker compose -f "$impl_dir/docker-compose.yml")

if ! docker info >/dev/null 2>&1; then
  compose=(sudo docker compose -f "$impl_dir/docker-compose.yml")
fi

exec "${compose[@]}" exec -T app bash -c '
  set -euo pipefail
  cd /workspace/implementation/app
  composer dump-autoload -o
  ./vendor/bin/pest "$@"
' _ "$@"
