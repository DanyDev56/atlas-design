#!/usr/bin/env bash
set -euo pipefail

repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
impl_dir="$repo_root/implementation"
app_dir="$impl_dir/app"
compose=(docker compose -f "$impl_dir/docker-compose.yml")

docker_exec() {
  "${compose[@]}" exec -T app bash -c "$1"
}

ensure_services() {
  if ! "${compose[@]}" ps --status running app 2>/dev/null | grep -q app; then
    printf 'Starting Docker services…\n'
    "${compose[@]}" up -d --build
  fi

  printf 'Waiting for PostgreSQL…\n'
  "${compose[@]}" exec -T postgres \
    sh -c 'until pg_isready -U atlas -d atlas; do sleep 1; done'
}

create_laravel_project() {
  printf 'Creating Laravel project…\n'
  docker_exec "
    set -euo pipefail
    git config --global --add safe.directory /workspace 2>/dev/null || true
    cd /workspace/implementation
    composer create-project laravel/laravel app --prefer-dist --no-interaction
    cd app
    composer require pestphp/pest pestphp/pest-plugin-laravel --dev --no-interaction
  "
  # pest --init hangs in Docker (no TTY) — pest-plugin-laravel configures tests via Composer.
}

configure_environment() {
  cat > "$app_dir/.env" <<'EOF'
APP_NAME=Atlas
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost:8000

LOG_CHANNEL=stack
LOG_LEVEL=debug

DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=atlas
DB_USERNAME=atlas
DB_PASSWORD=atlas_dev

QUEUE_CONNECTION=database
SESSION_DRIVER=database
CACHE_STORE=database
EOF

  docker_exec "
    set -euo pipefail
    cd /workspace/implementation/app
    composer dump-autoload
    php artisan key:generate --force
    php artisan migrate --force
  "
}

create_module_structure() {
  mkdir -p "$app_dir/atlas/Modules" "$app_dir/atlas/Platform" "$app_dir/atlas/Composition"

  cat > "$app_dir/atlas/README.md" <<'EOF'
# Structure modulaire Atlas

Code modulaire du MVP (ADR-001 / ADR-002).

```text
atlas/
  Modules/       # bounded contexts
  Platform/      # persistence, messaging, security…
  Composition/   # onboarding, dashboard, settings
```
EOF
}

ensure_services

if [[ -f "$app_dir/artisan" ]]; then
  printf 'Laravel already present in %s — finishing setup only.\n' "$app_dir"
else
  create_laravel_project
fi

configure_environment
create_module_structure

printf '\nBootstrap complete.\n'
printf 'Next: make serve\n'
printf 'See implementation/spike-checklist.md for increment 0 tasks.\n'
