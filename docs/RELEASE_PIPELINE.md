# Release pipeline — Hospital HMS (staging + production)

## Environments

| Lane | URL | Branch | VPS | App dir |
|--|--|--|--|--|
| Features / staging | https://hospital.ampletobuy.com | `Features` | `50.6.44.85` | `/opt/hospital` |
| Production | https://hospital.qubextrack.com | `main` | `129.121.125.118` | `/opt/hospital` |

Shared platform on each VPS: Traefik (`edge`), `platform-mysql` (`shared_db`).

## What runs

| Workflow | Trigger | Purpose |
|--|--|--|
| [`.github/workflows/ci.yml`](../.github/workflows/ci.yml) | PR / push `Features`, `main` | PHP syntax + package sanity |
| [`.github/workflows/deploy-vps-features.yml`](../.github/workflows/deploy-vps-features.yml) | Push `Features` / manual | Staging deploy |
| [`.github/workflows/deploy-vps-production.yml`](../.github/workflows/deploy-vps-production.yml) | Push `main` / manual (`confirm=deploy-production`) | Production deploy |
| [`.github/workflows/release.yml`](../.github/workflows/release.yml) | Tag `v*` | GitHub Release archive |
| [`.github/workflows/deploy-hostinger.yml`](../.github/workflows/deploy-hostinger.yml) | Legacy rsync — unused when VPS Docker is active | Optional |

## GitHub Environment secrets

### `features` (branch: `Features`)

| Secret | Value |
|--|--|
| `VPS_HOST` | `50.6.44.85` |
| `VPS_USER` | `deploy` |
| `VPS_SSH_KEY` | Deploy private key for staging VPS |
| `DEPLOY_HEALTHCHECK_URL` | `https://hospital.ampletobuy.com/` |

### `production` (branch: `main`)

| Secret | Value |
|--|--|
| `VPS_HOST` | `129.121.125.118` |
| `VPS_USER` | `deploy` |
| `VPS_SSH_KEY` | Same private key as retail-pos / hotel production (`retail-pos/.deploy-keys/github-actions-retail-pos-production`) |
| `DEPLOY_HEALTHCHECK_URL` | `https://hospital.qubextrack.com/` |

CI excludes legacy `application/libraries/Zend*` from PHP lint (PHP 8 incompatible).

## Deploy Features (staging)

```bash
git checkout Features
git push origin Features
# → CI + Deploy Features VPS → https://hospital.ampletobuy.com
```

## Deploy Production

```bash
git checkout main
git merge Features   # when ready to promote
git push origin main
# → CI + Deploy Production VPS → https://hospital.qubextrack.com
```

Manual (Actions → **Deploy Production VPS** → Run workflow):

- Branch: `main`
- `confirm`: `deploy-production`
- `build_on_vps`: `true` for first boot / no GHCR images

### First-time production VPS bootstrap

1. DNS: `hospital.qubextrack.com` → `129.121.125.118` (already set).
2. SSH as `deploy` using the **same** production key as retail/hotel (`github-actions-retail-pos-production`).
3. Create `/opt/hospital` (if missing), clone `main`, copy `deploy/vps/hospital.production.env.example` → `.env`, align DB password with hotel/retail `platform` user, import `deploy/vps/hospital-schema.sql.gz`, then:

```bash
cd /opt/hospital
HOSPITAL_BUILD_ON_VPS=1 ./scripts/deploy.sh
```

4. Confirm GitHub Environment **production** secrets use that same SSH key, then push `main` or run **Deploy Production VPS** (`confirm=deploy-production`).

## Staging VPS notes (already done)

- `/opt/hospital` on `50.6.44.85` from `Features`
- `.env` for `hospital.ampletobuy.com`
- MySQL DB `hospital` + central `trackpossystem` on `platform-mysql`
- Traefik `Host(hospital.ampletobuy.com)` via `docker-compose.vps.yml`

If Let’s Encrypt still shows a temporary/self-signed cert, wait for Traefik ACME or check that port 80 reaches Traefik for HTTP-01.
