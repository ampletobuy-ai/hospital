#!/usr/bin/env bash
# First-time production bootstrap for hospital.qubextrack.com on 129.121.125.118.
# Run AS the deploy user after the hospital production deploy public key is in
# ~/.ssh/authorized_keys (or use an existing deploy key that can reach this host).
#
# Usage:
#   ./deploy/vps/bootstrap-production.sh
#   HOSPITAL_GIT_REF=main ./deploy/vps/bootstrap-production.sh
set -euo pipefail

APP_DIR="${APP_DIR:-/opt/hospital}"
REPO_URL="${REPO_URL:-https://github.com/ampletobuy-ai/hospital.git}"
GIT_REF="${HOSPITAL_GIT_REF:-main}"
HOST="${HOSPITAL_HOST:-hospital.qubextrack.com}"

if [[ "$(id -u)" -eq 0 ]]; then
  echo "Run as deploy (not root)." >&2
  exit 1
fi

if [[ ! -d "$APP_DIR/.git" ]]; then
  echo "==> Cloning ${REPO_URL} → ${APP_DIR}"
  sudo mkdir -p "$(dirname "$APP_DIR")"
  sudo chown "$(id -un):$(id -gn)" "$(dirname "$APP_DIR")"
  git clone --branch "$GIT_REF" "$REPO_URL" "$APP_DIR"
fi

cd "$APP_DIR"
git fetch origin "$GIT_REF"
git checkout "$GIT_REF"
git reset --hard "origin/${GIT_REF}"

if [[ ! -f .env ]]; then
  echo "==> Creating .env from production example (EDIT PASSWORDS before deploy)"
  cp deploy/vps/hospital.production.env.example .env
  # Reuse platform-mysql app user password if present on this VPS
  if [[ -f /opt/platform-mysql/.env ]]; then
    # shellcheck disable=SC1091
    set -a
    # Prefer MYSQL_PASSWORD / MYSQL_USER from platform stack when present
    source /opt/platform-mysql/.env || true
    set +a
    if [[ -n "${MYSQL_PASSWORD:-}" ]]; then
      sed -i.bak "s/^DB_PASSWORD=.*/DB_PASSWORD=${MYSQL_PASSWORD}/" .env || true
      sed -i.bak "s/^DB_CENTRAL_PASSWORD=.*/DB_CENTRAL_PASSWORD=${MYSQL_PASSWORD}/" .env || true
      rm -f .env.bak
    fi
  fi
  echo "Review ${APP_DIR}/.env before continuing."
fi

grep -q "^HOSPITAL_HOST=${HOST}$" .env 2>/dev/null || \
  sed -i.bak "s/^HOSPITAL_HOST=.*/HOSPITAL_HOST=${HOST}/" .env
grep -q "^HOSPITAL_BASE_URL=https://${HOST}/$" .env 2>/dev/null || \
  sed -i.bak "s|^HOSPITAL_BASE_URL=.*|HOSPITAL_BASE_URL=https://${HOST}/|" .env
rm -f .env.bak

chmod +x scripts/deploy.sh docker/entrypoint.sh 2>/dev/null || true

echo "==> Ensure Docker networks exist"
docker network inspect edge >/dev/null 2>&1 || docker network create edge
docker network inspect shared_db >/dev/null 2>&1 || docker network create shared_db

echo "==> First build + up"
export HOSPITAL_BUILD_ON_VPS=1
export HOSPITAL_IMAGE_TAG=latest
export DEPLOY_HEALTHCHECK_URL="https://${HOST}/"
./scripts/deploy.sh

echo "Bootstrap complete. Open https://${HOST}/"
