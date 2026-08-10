#!/usr/bin/env bash
#
# Run the full test suite and, only if it passes, trigger the Forge deploy hook.
#
set -euo pipefail

cd "$(dirname "$0")/.."

if [[ -f .env ]]; then
    FORGE_DEPLOY_URL=$(grep -E '^FORGE_DEPLOY_URL=' .env | tail -n1 | cut -d= -f2- | sed -e 's/^"//' -e 's/"$//')
fi

if [[ -z "${FORGE_DEPLOY_URL:-}" ]]; then
    echo "==> FORGE_DEPLOY_URL is not set in .env" >&2
    exit 1
fi

echo "==> Running test suite"
php artisan test

echo "==> Tests passed, triggering Forge deploy"
http_code=$(curl -sS -o /dev/null -w "%{http_code}" "$FORGE_DEPLOY_URL")

if [[ "$http_code" == "200" ]]; then
    echo "==> Deploy triggered (HTTP $http_code)"
else
    echo "==> Deploy hook returned HTTP $http_code" >&2
    exit 1
fi
