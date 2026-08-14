#!/usr/bin/env bash
set -euo pipefail

repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
impl_dir="$repo_root/implementation"
app_dir="$impl_dir/app"
compose=(docker compose -f "$impl_dir/docker-compose.yml")

docker_exec() {
  "${compose[@]}" exec -T app bash -lc "$1"
}

ensure_docker() {
  if ! command -v docker >/dev/null 2>&1; then
    printf 'Docker is required but was not found in PATH.\n' >&2
    exit 1
  fi

  if ! docker info >/dev/null 2>&1; then
    printf 'Docker is not reachable. Start Docker Desktop and retry without sudo.\n' >&2
    exit 1
  fi
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

configure_container_git() {
  docker_exec '
    if ! git config --global --get-all safe.directory 2>/dev/null | grep -Fxq /workspace; then
      git config --global --add safe.directory /workspace
    fi
  '
}

create_laravel_project() {
  printf 'Creating Laravel project…\n'
  docker_exec '
    set -euo pipefail
    cd /workspace/implementation
    composer create-project laravel/laravel app --prefer-dist --no-interaction
    cd app
    composer require pestphp/pest pestphp/pest-plugin-laravel --dev --no-interaction
  '
  # pest --init hangs in Docker (no TTY) — pest-plugin-laravel configures tests via Composer.
}

install_dependencies() {
  printf 'Installing locked PHP and Node dependencies…\n'
  docker_exec '
    set -euo pipefail
    cd /workspace/implementation/app
    composer install --no-interaction --prefer-dist

    if [[ -f package-lock.json ]]; then
      npm ci
    else
      npm install
    fi
  '
}

configure_environment() {
  if [[ ! -f "$app_dir/.env" ]]; then
    printf 'Creating .env from .env.example…\n'
    cp "$app_dir/.env.example" "$app_dir/.env"
  else
    printf 'Keeping existing .env.\n'
  fi

  docker_exec '
    set -euo pipefail
    cd /workspace/implementation/app

    if ! grep -Eq "^APP_KEY=base64:.+" .env; then
      php artisan key:generate --force --no-interaction
    else
      printf "Keeping existing APP_KEY.\n"
    fi

    php artisan migrate --force --no-interaction
  '
}

ensure_module_structure() {
  mkdir -p "$app_dir/src/Modules" "$app_dir/src/Platform" "$app_dir/src/Composition"
}

ensure_docker
ensure_services
configure_container_git

if [[ -f "$app_dir/artisan" ]]; then
  printf 'Laravel already present in %s — finishing setup only.\n' "$app_dir"
else
  create_laravel_project
fi

install_dependencies
configure_environment
ensure_module_structure

printf '\nBootstrap complete.\n'
printf 'Next: make serve\n'
printf 'Application: http://localhost:8000/app\n'
