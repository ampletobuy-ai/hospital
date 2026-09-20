# Release pipeline (CI + Hostinger deploy)

## Targets

| Branch / trigger | GitHub Environment | Site |
|--|--|--|
| `Features` push | **staging** | https://hospital.ampletobuy.com |
| `main` / `master` push | **production** | set `PRODUCTION_URL` repo variable |
| Tag `v*` | release + **production** deploy | same as production |
| Manual “Deploy Hostinger” | choose staging / production | as selected |

## What runs

| Workflow | Trigger | Purpose |
|--|--|--|
| [`.github/workflows/ci.yml`](../.github/workflows/ci.yml) | PR / push to `main`, `master`, `Features` | PHP syntax lint + package sanity |
| [`.github/workflows/deploy-hostinger.yml`](../.github/workflows/deploy-hostinger.yml) | Push `Features`→staging, `main`→production, dispatch, release | rsync (keeps `.env` + `uploads/`) |
| [`.github/workflows/release.yml`](../.github/workflows/release.yml) | Tag `v*` | CI → GitHub Release + archive → production deploy |

Deploy jobs no-op until repository variable `HOSTINGER_DEPLOY_ENABLED=true`.

## Hostinger: create feature site (one-time)

In hPanel for `ampletobuy.com`:

1. **Domains → Subdomains** → create `hospital` → full host `hospital.ampletobuy.com`
2. Note the document root (typical):  
   `/home/<user>/domains/hospital.ampletobuy.com/public_html`  
   or  
   `/home/<user>/domains/ampletobuy.com/public_html/hospital`
3. **Advanced → SSH Access** → enable SSH and add your deploy public key
4. Upload a first `.env` into that docroot (copy from `.env.example`, set):
   - `HOSPITAL_BASE_URL=https://hospital.ampletobuy.com/`
   - DB credentials for this feature environment
5. Point DNS if needed: `hospital` CNAME/A to Hostinger (auto if subdomain created in hPanel)

## GitHub setup

### 1. Environments

Create **staging** and **production** (Settings → Environments).  
Optional: required reviewers on **production**.

Put **staging** secrets so they target `hospital.ampletobuy.com` only.

### 2. Repository variable

| Variable | Value |
|--|--|
| `HOSTINGER_DEPLOY_ENABLED` | `true` when ready |
| `PRODUCTION_URL` | optional, e.g. `https://your-prod-host` (for smoke check) |

### 3. Secrets (prefer **per-environment**)

| Secret | Staging (Features) | Production |
|--|--|--|
| `HOSTINGER_HOST` | SSH host / IP | same or prod host |
| `HOSTINGER_USER` | SSH user | … |
| `HOSTINGER_SSH_KEY` | Private key PEM | … |
| `HOSTINGER_REMOTE_PATH` | Docroot of **hospital.ampletobuy.com** | prod docroot |
| `HOSTINGER_SSH_PORT` | optional (`22`) | optional |

Deploys never overwrite `.env` or `uploads/`.

## Cut a feature release to staging

```bash
git checkout Features
git push origin Features
# → CI + deploy to https://hospital.ampletobuy.com
```

## Promote to production

```bash
git checkout main
git merge Features
git push origin main
# → production environment deploy

git tag -a v1.2.0 -m "Plan feature gates Phase 2"
git push origin v1.2.0
# → GitHub Release + production deploy
```

## Manual deploy

Actions → **Deploy Hostinger** → Run workflow → `staging` (hospital.ampletobuy.com) or `production`.

## Notes

- `--delete` on rsync removes remote files gone from git (except excluded paths).
- Archive overwrite deploy is not used so live `.env` / uploads stay safe.
- After staging deploy, verify plan gates with a Starter tenant on https://hospital.ampletobuy.com
