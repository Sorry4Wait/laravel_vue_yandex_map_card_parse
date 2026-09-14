#!/usr/bin/env bash
# Run from the repo root on the server for every deploy (first time and updates).
set -euo pipefail

if [ ! -f .env ] || [ ! -f backend/.env ]; then
  echo "Run ./deploy/setup-env.sh <server-ip> first." >&2
  exit 1
fi

echo "==> Building frontend (same-origin API, served by nginx)"
(cd frontend && npm ci && VITE_API_URL= npm run build)

echo "==> Building & starting containers"
docker compose -f docker-compose.prod.yml up -d --build

echo "==> Waiting for MySQL to accept connections"
# shellcheck disable=SC1091
set -a; source .env; set +a
mysql_ready=0
for _ in $(seq 1 60); do
  if docker compose -f docker-compose.prod.yml exec -T mysql \
      mysqladmin ping -h localhost -uroot -p"${MYSQL_ROOT_PASSWORD}" --silent 2>/dev/null; then
    mysql_ready=1
    break
  fi
  sleep 2
done
if [ "$mysql_ready" -ne 1 ]; then
  echo "MySQL did not become ready in time" >&2
  exit 1
fi

echo "==> Fixing storage/cache ownership for php-fpm (www-data)"
# storage/ and bootstrap/cache are bind-mounted from the host (owned by
# whoever ran `git clone`), but php-fpm inside the container runs as
# www-data — without this, every request that writes logs/sessions/cache
# fails with a bare 500 and nothing in laravel.log.
docker compose -f docker-compose.prod.yml exec -T -u root app chown -R www-data:www-data storage bootstrap/cache

echo "==> Installing PHP dependencies for production"
docker compose -f docker-compose.prod.yml exec -T app composer install --no-dev --optimize-autoloader --no-interaction

if ! grep -q '^APP_KEY=base64' backend/.env; then
  echo "==> Generating APP_KEY"
  docker compose -f docker-compose.prod.yml exec -T app php artisan key:generate --force
  echo "==> Recreating app/queue so they pick up the newly written APP_KEY"
  # docker injects env_file values as the container's OS environment at
  # creation time and never re-reads the file; phpdotenv won't overwrite an
  # OS env var that already exists (even an empty one), so a key written
  # into backend/.env after the container is already running stays invisible
  # to it until the container is recreated. Prefer running
  # ./deploy/setup-env.sh first so APP_KEY is already set before this point
  # and this branch never triggers.
  docker compose -f docker-compose.prod.yml up -d --force-recreate app queue
fi

echo "==> Running migrations"
docker compose -f docker-compose.prod.yml exec -T app php artisan migrate --force

echo "==> Caching config/routes"
docker compose -f docker-compose.prod.yml exec -T app php artisan config:cache
docker compose -f docker-compose.prod.yml exec -T app php artisan route:cache
# No view:cache: this is an API-only app, resources/views doesn't exist.

echo "==> Restarting nginx"
# nginx resolves the `app` upstream hostname once and caches the IP for the
# life of its worker processes. Any recreate of the app container above (or
# of the app image via --build) leaves nginx pointing at a dead IP —
# "502 Bad Gateway" / "connect() failed (111: Connection refused)" — until
# nginx itself restarts and re-resolves. Always do this last.
docker compose -f docker-compose.prod.yml restart nginx

echo "==> Done. App should be reachable at http://<server-ip>/"
