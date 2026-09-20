# Release pipeline — Hospital features on VPS

## Live staging

| Item | Value |
|--|--|
| URL | https://hospital.ampletobuy.com |
| Branch | `Features` |
| VPS | `50.6.44.85` (`retail-pos-features` / deploy user) |
| App dir | `/opt/hospital` |
| Edge | Traefik on Docker network `edge` |
| DB | `platform-mysql` on `shared_db` (DB `hospital` + central `trackpossystem`) |

## What runs

| Workflow | Trigger | Purpose |
|--|--|--|
| [`.github/workflows/ci.yml`](../.github/workflows/ci.yml) | PR / push `Features`, `main` | PHP syntax + package sanity |
| [`.github/workflows/deploy-features.yml`](../.github/workflows/deploy-features.yml) | Push `Features` / manual | `git pull` + `scripts/deploy.sh` on VPS |
| [`.github/workflows/release.yml`](../.github/workflows/release.yml) | Tag `v*` | GitHub Release archive |
| [`.github/workflows/deploy-hostinger.yml`](../.github/workflows/deploy-hostinger.yml) | `main` only (optional) | Legacy rsync path — not used for Features |

## GitHub Environment `features` secrets

Same pattern as retail-pos / bookingengine:

| Secret / var | Value |
|--|--|
| `VPS_HOST` | `50.6.44.85` |
| `VPS_USER` | `deploy` |
| `VPS_SSH_KEY` | Private key for deploy user |
| `DEPLOY_HEALTHCHECK_URL` | `https://hospital.ampletobuy.com/site/login` (optional) |

## Deploy Features

```bash
git checkout Features
git push origin Features
# → CI + deploy-features → https://hospital.ampletobuy.com
```

Manual on VPS:

```bash
ssh retail-pos-features
cd /opt/hospital
git fetch origin Features && git reset --hard origin/Features
HOSPITAL_BUILD_ON_VPS=1 ./scripts/deploy.sh
```

## First-time notes (already done on VPS)

- `/opt/hospital` cloned from `Features`
- `.env` created (not in git)
- MySQL DB `hospital` + user `hospital` on `platform-mysql`
- Schema imported from `deploy/vps/hospital-schema.sql.gz` + theme table patch
- Traefik router `Host(hospital.ampletobuy.com)` via `docker-compose.vps.yml`

If Let’s Encrypt still shows a temporary/self-signed cert, wait for Traefik ACME or check that port 80 reaches Traefik for HTTP-01.
