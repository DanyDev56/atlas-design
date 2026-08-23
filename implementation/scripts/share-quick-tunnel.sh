#!/usr/bin/env bash
set -euo pipefail

repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
impl_dir="$repo_root/implementation"
app_dir="$impl_dir/app"
compose=(docker compose --env-file "$app_dir/.env" -f "$impl_dir/docker-compose.yml")

tunnel_started=false
share_configured=false

fail() {
  printf 'Quick Tunnel error: %s\n' "$1" >&2
  exit 1
}

disable_vite_hot_reload() {
  # Laravel gives this generated marker precedence over the production
  # manifest. It would expose port 5173 to browsers outside the host machine.
  if [[ -f "$app_dir/public/hot" ]]; then
    printf 'Disabling Vite hot reload for the public demo…\n'
    rm -f -- "$app_dir/public/hot"
  fi
}

start_app_server() {
  if "${compose[@]}" exec -T app curl --fail --silent http://127.0.0.1:8000/up >/dev/null 2>&1; then
    return
  fi

  "${compose[@]}" exec -d app php artisan serve --no-reload --host=0.0.0.0 --port=8000

  for _ in $(seq 1 30); do
    if "${compose[@]}" exec -T app curl --fail --silent http://127.0.0.1:8000/up >/dev/null 2>&1; then
      return
    fi
    sleep 1
  done

  fail 'Laravel did not become ready on port 8000.'
}

verify_public_frontend() {
  local public_url="$1"
  local public_html
  local asset_path
  local asset_url
  local -a asset_paths=()

  public_html="$(curl --fail --silent --show-error --retry 10 --retry-delay 1 --retry-connrefused "$public_url/")" \
    || fail 'The public landing page is not reachable.'

  [[ "$public_html" == *'id="root"'* ]] \
    || fail 'The public landing page does not contain the Atlas application root.'

  if [[ "$public_html" == *':5173'* || "$public_html" == *'/@vite/client'* ]]; then
    fail 'The public page still references the local Vite development server. Stop make web-dev and retry.'
  fi

  mapfile -t asset_paths < <(
    printf '%s' "$public_html" \
      | grep -oE '(src|href)="[^"]*/build/[^"]+"' \
      | cut -d '"' -f 2 \
      | sort -u
  )

  [[ "${#asset_paths[@]}" -gt 0 ]] \
    || fail 'The public page does not reference the compiled web assets.'

  for asset_path in "${asset_paths[@]}"; do
    if [[ "$asset_path" == http://* ]]; then
      fail "The public page references an insecure asset: $asset_path."
    elif [[ "$asset_path" == https://* ]]; then
      [[ "$asset_path" == "$public_url"/build/* ]] \
        || fail "The public page references an asset on another origin: $asset_path."
      asset_url="$asset_path"
    else
      asset_url="$public_url$asset_path"
    fi

    curl --fail --silent --show-error --retry 3 --retry-delay 1 "$asset_url" >/dev/null \
      || fail "The public asset $asset_path is not reachable."
  done
}

restore_local_environment() {
  unset ATLAS_PUBLIC_URL
  unset ATLAS_SHARE_APP_ENV
  unset ATLAS_SHARE_APP_DEBUG
  unset ATLAS_SHARE_DEVELOPMENT_ROUTES
  unset ATLAS_SHARE_DEBUG_VERIFICATION_TOKENS
  unset ATLAS_SHARE_TRUST_PROXIES
  unset ATLAS_SHARE_FORCE_HTTPS

  "${compose[@]}" up -d --force-recreate app outbox-worker >/dev/null
  start_app_server
}

cleanup() {
  local exit_code=$?
  trap - EXIT

  if [[ "$tunnel_started" == true ]]; then
    printf '\nStopping the Quick Tunnel…\n'
    "${compose[@]}" --profile share stop quick-tunnel >/dev/null 2>&1 || true
  fi

  if [[ "$share_configured" == true ]]; then
    printf 'Restoring the local development configuration…\n'
    restore_local_environment || true
  fi

  exit "$exit_code"
}

trap cleanup EXIT
trap 'exit 130' INT TERM

command -v docker >/dev/null 2>&1 || fail 'Docker was not found in PATH.'
command -v curl >/dev/null 2>&1 || fail 'curl was not found in PATH.'
docker info >/dev/null 2>&1 || fail 'Docker is not reachable. Start Docker and retry.'
[[ -f "$app_dir/.env" ]] || fail 'implementation/app/.env is missing. Run make bootstrap first.'
[[ -f "$app_dir/vendor/autoload.php" ]] || fail 'PHP dependencies are missing. Run make bootstrap first.'
[[ -f "$app_dir/public/build/manifest.json" ]] || fail 'Web assets are missing. Run make web-check first.'

disable_vite_hot_reload

printf 'Starting Atlas and its background worker…\n'
"${compose[@]}" up -d --build app outbox-worker
start_app_server

printf 'Creating an ephemeral Cloudflare Quick Tunnel…\n'
tunnel_started=true
"${compose[@]}" --profile share up -d --force-recreate quick-tunnel

public_url=''
for _ in $(seq 1 30); do
  tunnel_logs="$("${compose[@]}" --profile share logs --no-color quick-tunnel 2>&1 || true)"
  if [[ "$tunnel_logs" =~ (https://[a-z0-9-]+\.trycloudflare\.com) ]]; then
    public_url="${BASH_REMATCH[1]}"
    break
  fi

  if ! "${compose[@]}" --profile share ps --status running quick-tunnel --format json | grep -q quick-tunnel; then
    printf '%s\n' "$tunnel_logs" >&2
    fail 'cloudflared stopped before providing a public URL.'
  fi
  sleep 1
done

[[ -n "$public_url" ]] || fail 'Cloudflare did not provide a public URL within 30 seconds.'

export ATLAS_PUBLIC_URL="$public_url"
export ATLAS_SHARE_APP_ENV=staging
export ATLAS_SHARE_APP_DEBUG=false
export ATLAS_SHARE_DEVELOPMENT_ROUTES=false
export ATLAS_SHARE_DEBUG_VERIFICATION_TOKENS=false
export ATLAS_SHARE_TRUST_PROXIES=true
export ATLAS_SHARE_FORCE_HTTPS=true

printf 'Applying the temporary public URL and staging safeguards…\n'
share_configured=true
"${compose[@]}" up -d --force-recreate app outbox-worker
disable_vite_hot_reload
start_app_server

if ! curl --fail --silent --show-error --retry 10 --retry-delay 1 --retry-connrefused "$public_url/up" >/dev/null; then
  fail 'The public health endpoint is not reachable.'
fi

verify_public_frontend "$public_url"

printf '\nAtlas is available for the demo:\n'
printf '  Landing: %s\n' "$public_url"
printf '  Login:   %s/app/login\n' "$public_url"
printf '\nSafeguards: APP_DEBUG=false, development routes disabled, proxy HTTPS trusted.\n'
printf 'Mailpit remains local at http://localhost:8025; only the Atlas web service is tunneled.\n'
printf 'Keep this command running. Press Ctrl+C or run make stop-share to stop sharing.\n\n'

while "${compose[@]}" --profile share ps --status running quick-tunnel --format json | grep -q quick-tunnel; do
  sleep 2
done
