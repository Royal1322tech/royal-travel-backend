#!/usr/bin/env bash
set -Eeuo pipefail

: "${DEPLOY_SHA:?DEPLOY_SHA is required}"
: "${GHCR_USER:?GHCR_USER is required}"

export DOCKER_CONFIG
DOCKER_CONFIG="$(mktemp -d)"
chmod 700 "$DOCKER_CONFIG"

cleanup() {
  rm -rf -- "$DOCKER_CONFIG"
}
trap cleanup EXIT

echo "Authenticating to GHCR"

IFS= read -r GITHUB_TOKEN || [[ -n "${GITHUB_TOKEN:-}" ]]
export GITHUB_TOKEN
: "${GITHUB_TOKEN:?GitHub token is required}"

printf '%s' "$GITHUB_TOKEN" | docker login ghcr.io \
  --username "$GHCR_USER" \
  --password-stdin >/dev/null

echo "Starting staging deployment"

/opt/royal-travel/staging/scripts/deploy-staging.sh
