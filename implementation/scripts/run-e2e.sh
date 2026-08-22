#!/usr/bin/env bash
set -euo pipefail

repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
impl_dir="$repo_root/implementation"
app_dir="$impl_dir/app"
compose=(docker compose -f "$impl_dir/docker-compose.yml")

if ! docker info >/dev/null 2>&1; then
  compose=(sudo docker compose -f "$impl_dir/docker-compose.yml")
fi

"${compose[@]}" up -d --build
"${compose[@]}" exec -T postgres \
  sh -c 'until pg_isready -U atlas -d atlas; do sleep 1; done'

if [[ ! -f "$app_dir/.env" ]]; then
  cp "$app_dir/.env.example" "$app_dir/.env"
fi

"${compose[@]}" exec -T app bash -c '
  set -euo pipefail
  cd /workspace/implementation/app
  composer install --no-interaction --prefer-dist
  app_key=""
  while IFS="=" read -r key value; do
    if [[ "$key" == "APP_KEY" ]]; then
      app_key="$value"
      break
    fi
  done < .env
  if [[ "$app_key" != base64:* ]]; then
    php artisan key:generate --force --no-interaction
  fi
  npm ci --ignore-scripts
  npm run typecheck
  rm -f public/hot
  npm run build
  php artisan migrate --force --no-interaction
  php artisan atlas:demo:seed
  php artisan atlas:demo:seed --profile=empty
'

if ! "${compose[@]}" exec -T app curl --fail --silent http://127.0.0.1:8000/up >/dev/null 2>&1; then
  "${compose[@]}" exec -d app \
    php artisan serve --host=0.0.0.0 --port=8000
fi

"${compose[@]}" exec -T app \
  sh -c 'until curl --fail --silent http://127.0.0.1:8000/up >/dev/null; do sleep 1; done'

"${compose[@]}" --profile e2e run --rm e2e \
  bash -c 'npm ci && npm test'
