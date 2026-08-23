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

exec "${compose[@]}" exec -T \
  -e APP_ENV=testing \
  -e DB_DATABASE=atlas_test \
  -e ATLAS_DEVELOPMENT_ROUTES=true \
  -e ATLAS_DEBUG_VERIFICATION_TOKENS=true \
  -e BACKOFFICE_ENABLED=true \
  -e BACKOFFICE_ALLOW_PASSWORD_ONLY_LOCAL=true \
  -e BACKOFFICE_REQUIRE_MFA=false \
  -e BACKOFFICE_ALLOW_TOTP_EXTERNAL=false \
  -e BACKOFFICE_READ_ONLY=true \
  -e BACKOFFICE_ACTIONS_ENABLED=false \
  -e BACKOFFICE_BETA_BLOCKED_AFTER_DAYS=7 \
  -e CACHE_STORE=array \
  -e MAIL_MAILER=array \
  -e OTEL_TRACES_EXPORTER=none \
  -e SUBSCRIPTIONS_GATEWAY=fake \
  app bash -c '
  set -euo pipefail
  cd /workspace/implementation/app
  composer dump-autoload -o
  php artisan config:clear
  ./vendor/bin/pest "$@"
' _ "$@"
