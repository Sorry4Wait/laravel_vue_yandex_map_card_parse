#!/usr/bin/env bash
# Run ONCE from the repo root on the server to generate production env files.
# Safe to re-run: never overwrites existing .env files.
set -euo pipefail

SERVER_IP="${1:?Usage: setup-env.sh <server-public-ip>}"
ORIGIN="http://${SERVER_IP}"

gen_secret() { openssl rand -base64 24 | tr -d '/+=' | cut -c1-24; }

if [ -f .env ]; then
  echo ".env already exists at repo root, leaving it untouched."
else
  DB_PASSWORD="$(gen_secret)"
  DB_ROOT_PASSWORD="$(gen_secret)"
  cat > .env <<EOF
MYSQL_DATABASE=yandex_reviews
MYSQL_USER=laravel
MYSQL_PASSWORD=${DB_PASSWORD}
MYSQL_ROOT_PASSWORD=${DB_ROOT_PASSWORD}
EOF
  echo "Wrote root .env (docker-compose MySQL credentials)."
fi

# shellcheck disable=SC1091
source .env

if [ -f backend/.env ]; then
  echo "backend/.env already exists, leaving it untouched."
else
  cp backend/.env.example backend/.env
  # Generated here (before the container ever starts) rather than via `artisan
  # key:generate` after `docker compose up`: docker injects env_file values as
  # the container's OS environment at creation time and never re-reads the
  # file, so a key written into backend/.env after the container is already
  # running is invisible to it (phpdotenv treats OS env as authoritative and
  # won't overwrite it) until the container is recreated.
  APP_KEY="base64:$(openssl rand -base64 32)"
  sed -i.bak \
    -e "s|^APP_ENV=.*|APP_ENV=production|" \
    -e "s|^APP_DEBUG=.*|APP_DEBUG=false|" \
    -e "s|^APP_URL=.*|APP_URL=${ORIGIN}|" \
    -e "s|^APP_KEY=.*|APP_KEY=${APP_KEY}|" \
    -e "s|^DB_DATABASE=.*|DB_DATABASE=${MYSQL_DATABASE}|" \
    -e "s|^DB_USERNAME=.*|DB_USERNAME=${MYSQL_USER}|" \
    -e "s|^DB_PASSWORD=.*|DB_PASSWORD=${MYSQL_PASSWORD}|" \
    -e "s|^SESSION_DOMAIN=.*|SESSION_DOMAIN=|" \
    -e "s|^SANCTUM_STATEFUL_DOMAINS=.*|SANCTUM_STATEFUL_DOMAINS=${SERVER_IP}|" \
    -e "s|^FRONTEND_URL=.*|FRONTEND_URL=${ORIGIN}|" \
    -e "s|^LOG_LEVEL=.*|LOG_LEVEL=error|" \
    backend/.env
  rm -f backend/.env.bak
  echo "Wrote backend/.env from backend/.env.example with production values."
fi

echo "Done. Review backend/.env, then run ./deploy/deploy.sh"
