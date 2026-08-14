#!/usr/bin/env bash

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
IMPLEMENTATION_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"
COMPOSE=(docker compose -f "${IMPLEMENTATION_DIR}/docker-compose.yml")

if [[ -z "${RUNTIME_APP_KEY:-}" ]]; then
  echo "ERROR: RUNTIME_APP_KEY is required for the immutable runtime smoke test." >&2
  exit 78
fi

cleanup() {
  local status=$?

  if [[ ${status} -ne 0 ]]; then
    "${COMPOSE[@]}" --profile runtime logs --no-color --tail=100 api worker scheduler || true
  fi

  "${COMPOSE[@]}" --profile runtime stop api worker scheduler >/dev/null || true

  return "${status}"
}

trap cleanup EXIT

if [[ "${SKIP_RUNTIME_BUILD:-0}" != "1" ]]; then
  "${COMPOSE[@]}" --profile runtime build api
fi

"${COMPOSE[@]}" up -d postgres
"${COMPOSE[@]}" --profile runtime run --rm api php artisan migrate --force
"${COMPOSE[@]}" --profile runtime up -d --no-build api worker scheduler

api_container_id="$("${COMPOSE[@]}" --profile runtime ps -q api)"
worker_container_id="$("${COMPOSE[@]}" --profile runtime ps -q worker)"
scheduler_container_id="$("${COMPOSE[@]}" --profile runtime ps -q scheduler)"

for _ in {1..30}; do
  health="$(docker inspect --format '{{if .State.Health}}{{.State.Health.Status}}{{else}}missing{{end}}' "${api_container_id}")"

  if [[ "${health}" == "healthy" ]]; then
    break
  fi

  sleep 1
done

if [[ "${health:-missing}" != "healthy" ]]; then
  echo "ERROR: immutable API did not become healthy (status: ${health:-missing})." >&2
  exit 1
fi

runtime_image_id="$(docker inspect --format '{{.Image}}' "${api_container_id}")"

for role_and_container in \
  "api:${api_container_id}" \
  "worker:${worker_container_id}" \
  "scheduler:${scheduler_container_id}"; do
  role="${role_and_container%%:*}"
  container_id="${role_and_container#*:}"
  mount_count="$(docker inspect --format '{{len .Mounts}}' "${container_id}")"
  image_id="$(docker inspect --format '{{.Image}}' "${container_id}")"

  if [[ "${mount_count}" != "0" ]]; then
    echo "ERROR: immutable ${role} unexpectedly has ${mount_count} mount(s)." >&2
    exit 1
  fi

  if [[ "${image_id}" != "${runtime_image_id}" ]]; then
    echo "ERROR: ${role} does not use the same runtime image as the API." >&2
    exit 1
  fi
done

"${COMPOSE[@]}" --profile runtime exec -T api sh -c '
  set -eu
  assert() {
    description="$1"
    shift

    if ! "$@"; then
      echo "ERROR: ${description}." >&2
      exit 1
    fi
  }

  assert "runtime user is not www-data" test "$(id -u)" = "33"
  assert "Composer dependencies are missing" test -f vendor/autoload.php
  assert "compiled web assets are missing" test -f public/build/manifest.json
  assert "test sources leaked into the runtime image" test ! -d tests
  assert "Node.js leaked into the runtime image" test ! -x /usr/bin/node
  assert "Composer leaked into the runtime image" test ! -x /usr/bin/composer
  assert "application sources are writable" test ! -w artisan
  assert "compiled web assets are writable" test ! -w public/build/manifest.json
  assert "bootstrap/cache is not writable" test -w bootstrap/cache
  assert "storage is not writable" test -w storage
  assert "health endpoint is unreachable" php -r '\''exit(@file_get_contents("http://127.0.0.1:8000/up") === false ? 1 : 0);'\''
  assert "application endpoint is unreachable" php -r '\''exit(@file_get_contents("http://127.0.0.1:8000/app") === false ? 1 : 0);'\''
'

"${COMPOSE[@]}" --profile runtime run --rm --no-deps worker \
  php artisan atlas:outbox:work --sleep=0 --max-cycles=1
"${COMPOSE[@]}" --profile runtime run --rm --no-deps scheduler \
  php artisan schedule:list

echo "Immutable runtime smoke test passed."
