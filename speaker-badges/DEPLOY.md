# Deploying Speaker Badges

Two parts: (1) promote this folder to its own Git repository, then (2) ship it to
Cloudflare. Run everything from your own machine — you'll need a browser for
`wrangler login` and the Cloudflare dashboard.

---

## Part 1 — Make this its own repository

Right now the project lives in `speaker-badges/` inside the
`field-service-settings-updater` repo (it was scaffolded there for lack of a home).
Everything under `speaker-badges/` is self-contained — its own `package.json`,
`wrangler.toml`, tests and `.gitignore` — so it becomes a repo root as-is.

### Option A — fresh start (simplest, recommended)

History for this folder is only a few commits; a clean initial commit is fine.

```bash
# from wherever you cloned field-service-settings-updater (on the branch
# claude/project-review-fwq0r2)
cp -r field-service-settings-updater/speaker-badges ~/speaker-badges
cd ~/speaker-badges
rm -rf node_modules            # will be reinstalled; not committed anyway
git init -b main
git add .
git commit -m "Speaker Badges: initial commit"

# create + push in one step with the GitHub CLI:
gh repo create speaker-badges --private --source=. --push
#   ...or create the empty repo in the GitHub UI first, then:
# git remote add origin git@github.com:TheMarkChristie/speaker-badges.git
# git push -u origin main
```

### Option B — keep the folder's commit history

```bash
cd field-service-settings-updater
git checkout claude/project-review-fwq0r2
git subtree split --prefix=speaker-badges -b speaker-badges-only
# push that split branch as main of the new repo:
gh repo create speaker-badges --private
git push git@github.com:TheMarkChristie/speaker-badges.git speaker-badges-only:main
```

### After extracting

- Move the CI workflow into place: `git mv deploy.yml.example .github/workflows/deploy.yml`
  (see Part 3), commit, push.
- Optional cleanup: delete `speaker-badges/` from the Field Service branch so the
  two projects don't overlap.
- `npm install && npm test` in the new repo to confirm the 43 tests pass.

---

## Part 2 — Deploy to Cloudflare

### 0. Prerequisites
- Node 18+, and `scottishsummit.com` already on Cloudflare (needed for the custom
  domain and email DNS).
- `npm install`
- `npx wrangler login`

### 1. Storage + first deploy
```bash
npx wrangler kv namespace create BADGES        # paste the id into wrangler.toml
npx wrangler r2 bucket create speaker-badge-images
npm test
npx wrangler deploy                            # creates the Worker + workers.dev URL
```
The admin console returns **403 until Access is configured (step 3)** — that is the
fail-closed default, not a bug.

### 2. Custom domain
Dashboard → **Workers & Pages → speaker-badges → Settings → Domains & Routes → Add
Custom Domain** → `badges.scottishsummit.com`. Then set the origin used for badge
links / `og:image` in `wrangler.toml`:
```toml
PUBLIC_ORIGIN = "https://badges.scottishsummit.com"
```
`npx wrangler deploy` again.

### 3. Cloudflare Access (required — locks the admin console)
Dashboard → **Zero Trust → Access → Applications → Add → Self-hosted**:
- Domain `badges.scottishsummit.com`, protecting `/`, `/admin`, admin `/api/*`.
  (Protect the whole host, then add a **bypass** policy for the public paths:
  `/b`, `/img`, `/brand`, `/request`, `/og`, `/api/verify`, `/api/events`,
  `/api/request`.)
- Add **Microsoft Entra ID** as the identity provider.
- Policy: Allow → Emails → **your address only**.
- Copy the app's **AUD tag** and note the team domain `‹team›.cloudflareaccess.com`.

```bash
npx wrangler secret put ACCESS_AUD             # the AUD tag
npx wrangler secret put ACCESS_TEAM_DOMAIN     # e.g. scottishsummit.cloudflareaccess.com
```
Secrets apply immediately (no redeploy). Never set `ALLOW_INSECURE_ADMIN` in
production — that's the local-dev bypass only.

### 4. Turnstile (required for launch)
Dashboard → **Turnstile → Add widget** for `badges.scottishsummit.com`.
- Site key → `wrangler.toml`: `TURNSTILE_SITE_KEY = "0x…"`
- `npx wrangler secret put TURNSTILE_SECRET`
- `npx wrangler deploy` (site key is a var).

### 5. Email (required for launch)
- **Resend → Domains → Add** `scottishsummit.com`; add the SPF/DKIM records to
  Cloudflare DNS; wait for "Verified".
- `npx wrangler secret put EMAIL_API_KEY` and `npx wrangler secret put EMAIL_FROM`
  (`badges@scottishsummit.com`).
- Until the key is set, sends are a silent no-op — issuing still works.

### 6. Smoke test
1. `/admin` → Entra login → console loads.
2. Save an event, upload the four role artworks.
3. Issue a small roster → open a `/b/<token>` link → badge renders, "Add to
   LinkedIn" works, `og:image` is the artwork.
4. Public `/request` → Turnstile → lands pending → approve in the console.

---

## Part 3 — Continuous deployment (optional)

`deploy.yml.example` → `.github/workflows/deploy.yml`. Add a repo secret
`CLOUDFLARE_API_TOKEN` (scoped: *Workers Scripts Edit*, *KV Edit*, *R2 Edit* on the
account). It runs the tests and `wrangler deploy` on every push to `main`, failing
if tests fail.

## Secrets & vars reference

| Name | Set in | Purpose |
|---|---|---|
| KV `id` | `wrangler.toml` | records store |
| `PUBLIC_ORIGIN` | `wrangler.toml` var | absolute links / og:image |
| `TURNSTILE_SITE_KEY` | `wrangler.toml` var | Turnstile widget |
| `ACCESS_AUD`, `ACCESS_TEAM_DOMAIN` | `wrangler secret` | admin auth |
| `TURNSTILE_SECRET` | `wrangler secret` | spam verification |
| `EMAIL_API_KEY`, `EMAIL_FROM` | `wrangler secret` | badge emails |
| `CLOUDFLARE_API_TOKEN` | GitHub repo secret | CI deploy |

## Troubleshooting

- **`/admin` returns 403** — Access not configured yet, or `ACCESS_AUD` /
  `ACCESS_TEAM_DOMAIN` don't match the app. Confirm the AUD tag and team domain.
- **Badge links / og:image use the wrong host** — set `PUBLIC_ORIGIN` and redeploy.
- **Emails not arriving** — domain not verified in Resend, or `EMAIL_API_KEY`
  unset (no-op by design). Check `npx wrangler tail` for `[email]` log lines.
- **Live logs** — `npx wrangler tail` streams the Worker's console output.
- **Turnstile always fails** — site key (var) and secret must be from the *same*
  widget; redeploy after changing the site key.
