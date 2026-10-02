#!/usr/bin/env bash
# Hospital HMS — VPS deploy (pull GHCR images, or build on first boot)
# Usage:
#   HOSPITAL_IMAGE_TAG=latest ./scripts/deploy.sh
#   HOSPITAL_BUILD_ON_VPS=1 ./scripts/deploy.sh   # first bring-up without GHCR
set -euo pipefail

APP_DIR="${APP_DIR:-/opt/hospital}"
COMPOSE="docker compose -f docker-compose.prod.yml -f docker-compose.vps.yml"
HOSPITAL_IMAGE_TAG="${HOSPITAL_IMAGE_TAG:-latest}"
DEPLOY_HEALTHCHECK_URL="${DEPLOY_HEALTHCHECK_URL:-https://hospital.ampletobuy.com/}"
HOSPITAL_BUILD_ON_VPS="${HOSPITAL_BUILD_ON_VPS:-0}"

cd "$APP_DIR"

if [[ ! -f docker-compose.prod.yml || ! -f docker-compose.vps.yml ]]; then
  echo "Missing docker-compose.prod.yml or docker-compose.vps.yml in ${APP_DIR}" >&2
  exit 1
fi

if [[ ! -f .env ]]; then
  echo "Missing .env in ${APP_DIR} — copy from deploy/vps/hospital.env.example first" >&2
  exit 1
fi

if grep -q '^HOSPITAL_IMAGE_TAG=' .env 2>/dev/null; then
  sed -i.bak "s/^HOSPITAL_IMAGE_TAG=.*/HOSPITAL_IMAGE_TAG=${HOSPITAL_IMAGE_TAG}/" .env && rm -f .env.bak
else
  echo "HOSPITAL_IMAGE_TAG=${HOSPITAL_IMAGE_TAG}" >> .env
fi

export HOSPITAL_IMAGE_TAG

echo "==> Deploying hospital images @ ${HOSPITAL_IMAGE_TAG}"

if [[ "${HOSPITAL_BUILD_ON_VPS}" == "1" ]]; then
  echo "==> Building on VPS (HOSPITAL_BUILD_ON_VPS=1)"
  # Allow compose build: temporarily clear pull_policy-only override by building with prod file
  docker compose -f docker-compose.prod.yml -f docker-compose.vps.yml build --pull
  $COMPOSE up -d --remove-orphans
else
  if ! $COMPOSE pull; then
    echo "==> GHCR pull failed — falling back to local build" >&2
    docker compose -f docker-compose.prod.yml -f docker-compose.vps.yml build --pull
  fi
  $COMPOSE up -d --remove-orphans
fi

echo "==> Waiting for hospital-app..."
ready=0
for i in $(seq 1 40); do
  status="$(docker inspect -f '{{.State.Status}}' hospital-app 2>/dev/null || echo missing)"
  if [[ "$status" == "running" ]] && $COMPOSE exec -T app php -v >/dev/null 2>&1; then
    ready=1
    break
  fi
  if [[ "$status" == "restarting" || "$status" == "exited" ]]; then
    echo "hospital-app status=${status} — recent logs:" >&2
    docker logs hospital-app --tail 40 2>&1 || true
  fi
  sleep 2
done

if [[ "$ready" -ne 1 ]]; then
  echo "hospital-app did not become ready" >&2
  docker logs hospital-app --tail 80 2>&1 || true
  $COMPOSE ps
  exit 1
fi

$COMPOSE exec -T app sh -c '
  chown -R www-data:www-data application/cache application/logs application/sessions uploads temp 2>/dev/null || true
  chmod -R ug+rwX application/cache application/logs application/sessions uploads temp 2>/dev/null || true
' || true

echo "==> Healthcheck ${DEPLOY_HEALTHCHECK_URL}"
code="$(curl -sS -o /dev/null -w '%{http_code}' --max-time 30 -L "${DEPLOY_HEALTHCHECK_URL}" || true)"
echo "HTTP ${code}"
case "${code}" in
  200|301|302|303|307|308) echo "Deploy OK" ;;
  *) echo "Warning: unexpected HTTP ${code} (SSL/app may still be warming)" ;;
esac

$COMPOSE ps
echo "==> Done"
