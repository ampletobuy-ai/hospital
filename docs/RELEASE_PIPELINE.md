# Release pipeline (CI + Hostinger deploy)

## What runs

| Workflow | Trigger | Purpose |
|--|--|--|
| [`.github/workflows/ci.yml`](../.github/workflows/ci.yml) | PR / push to `main`, `master`, `Features` | PHP syntax lint + package sanity |
| [`.github/workflows/deploy-hostinger.yml`](../.github/workflows/deploy-hostinger.yml) | Push to `main`/`master`, manual dispatch, or release | rsync to Hostinger (keeps `.env` + `uploads/`) |
| [`.github/workflows/release.yml`](../.github/workflows/release.yml) | Tag `v*` | CI gate → GitHub Release + archive → production deploy |

Deploy jobs no-op until repository variable `HOSTINGER_DEPLOY_ENABLED=true`.

## One-time GitHub setup

### 1. Environments

Create environments: **staging** and **production** (Settings → Environments).  
Optional: add required reviewers on **production**.

### 2. Repository variable

| Variable | Value |
|--|--|
| `HOSTINGER_DEPLOY_ENABLED` | `true` when ready to deploy |

### 3. Secrets (repo or per-environment)

| Secret | Example / notes |
|--|--|
| `HOSTINGER_HOST` | `ssh.hostinger.com` or your VPS IP |
| `HOSTINGER_USER` | SSH username (e.g. `u123456789`) |
| `HOSTINGER_SSH_KEY` | Private key (PEM). Public key must be in Hostinger SSH Access |
| `HOSTINGER_REMOTE_PATH` | Absolute path, e.g. `/home/u123/domains/example.com/public_html` |
| `HOSTINGER_SSH_PORT` | Optional; default `22` |

On the server, create `.env` once (from `.env.example`) before the first deploy. Deploys never overwrite `.env` or `uploads/`.

### 4. Hostinger SSH

1. hPanel → Advanced → SSH Access → enable SSH  
2. Add the deploy public key  
3. Confirm remote path matches `HOSTINGER_REMOTE_PATH`

## Cut a feature release

```bash
# From a green Features / main branch
git checkout main
git pull
git merge Features   # or open a PR

git tag -a v1.2.0 -m "Plan feature gates Phase 2"
git push origin main
git push origin v1.2.0
```

Tag push runs **Release**: CI → GitHub Release (with `hospital-v1.2.0.tar.gz`) → Hostinger production deploy (if enabled).

## Manual deploy

Actions → **Deploy Hostinger** → Run workflow → choose `staging` or `production`.

## Notes

- `--delete` on rsync removes remote files that are gone from git (except excluded paths).
- Full Hostinger “import archive” overwrite is intentionally not used so live `.env` / uploads stay safe.
- After first production deploy, verify plan gates with a Starter tenant (no IPD/Pharmacy roles/menus) and a Business/Enterprise tenant.
