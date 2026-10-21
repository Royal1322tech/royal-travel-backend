#!/usr/bin/env bash
set -Eeuo pipefail

# Prevent concurrent staging deployments
exec 9>/opt/royal-travel/staging/.deploy.lock
if ! flock -n 9; then
  echo "Another staging deployment is already running"
  exit 1
fi

: "${DEPLOY_SHA:?DEPLOY_SHA is required}"

if [[ ! "$DEPLOY_SHA" =~ ^[0-9a-f]{40}$ ]]; then
  echo "Invalid deployment commit SHA"
  exit 1
fi

# Verify the latest main commit while holding the deployment lock
: "${GITHUB_TOKEN:?GITHUB_TOKEN is required}"

echo "Verifying latest main commit before deployment"

LATEST_SHA="$(curl --fail --silent --show-error --max-time 15 \
  -H "Authorization: Bearer ${GITHUB_TOKEN}" \
  -H "Accept: application/vnd.github+json" \
  "https://api.github.com/repos/Royal1322tech/royal-travel-backend/git/ref/heads/main" \
  | python3 -c 'import json,sys; print(json.load(sys.stdin)["object"]["sha"])')"

if [[ "$DEPLOY_SHA" != "$LATEST_SHA" ]]; then
  echo "A newer commit exists on main. Deployment aborted."
  exit 1
fi

cd /opt/royal-travel/staging

export BACKEND_IMAGE="ghcr.io/royal1322tech/royal-travel-backend/php:${DEPLOY_SHA}"
export NGINX_IMAGE="ghcr.io/royal1322tech/royal-travel-backend/nginx:${DEPLOY_SHA}"

echo "Pulling images for commit ${DEPLOY_SHA}"
docker compose -f compose.backend.yaml pull

echo "Creating PostgreSQL backup"
BACKUP_DIR="/opt/royal-travel/staging/backups"
BACKUP_FILE="${BACKUP_DIR}/royal_staging_$(date -u +%Y%m%dT%H%M%SZ)_${DEPLOY_SHA:0:12}.dump"

mkdir -p "$BACKUP_DIR"
umask 077

if ! docker exec royal-staging-postgres pg_dump   -U royal_staging   -d royal_staging   -Fc > "${BACKUP_FILE}.tmp"; then
  rm -f "${BACKUP_FILE}.tmp"
  echo "Database backup failed. Deployment aborted."
  exit 1
fi

mv "${BACKUP_FILE}.tmp" "$BACKUP_FILE"
echo "Database backup completed successfully"

echo "Running Laravel migrations"
docker compose -f compose.backend.yaml run --rm --no-deps php \
  php artisan migrate --force

echo "Starting backend containers"
docker compose -f compose.backend.yaml up -d --no-build php nginx

echo "Checking Laravel API health"

HEALTH_URL="https://staging-api.royalis.tech/up"
HEALTH_OK=false

for attempt in $(seq 1 12); do
  if curl --fail --silent --show-error --max-time 10 --output /dev/null "$HEALTH_URL"; then
    HEALTH_OK=true
    break
  fi

  echo "Health check attempt ${attempt}/12 failed"
  sleep 5
done

if [[ "$HEALTH_OK" != "true" ]]; then
  echo "API health check failed. Deployment marked as failed."
  exit 1
fi

echo "Deployment completed successfully for ${DEPLOY_SHA}"
