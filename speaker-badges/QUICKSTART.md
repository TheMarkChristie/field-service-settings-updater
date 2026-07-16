# Quickstart — see it running

Two ways, fastest first. Full production setup (custom domain, Access, Turnstile,
email) is in [DEPLOY.md](./DEPLOY.md).

## A. Run it locally — no Cloudflare account needed

`wrangler dev` simulates KV + R2 on your machine, so nothing is created on any
account and the admin console works via a **local-only** bypass.

```bash
cd speaker-badges
npm install
printf 'ALLOW_INSECURE_ADMIN = "true"\n' > .dev.vars   # local admin bypass — NEVER deploy this
npx wrangler dev
```

Open the printed URL (usually `http://localhost:8787`) and try the whole flow:

- **`/admin`** — save an event, upload the four role artworks, paste a small roster
  (`name,role,email`), click **Issue**.
- **`/b/<token>`** — open an issued badge; "Add to LinkedIn" and the artwork render.
- **`/request`** — the public form (Turnstile is disabled when unconfigured).

Local KV/R2 data lives under `.wrangler/` and is a throwaway sandbox. (If wrangler
objects to the placeholder KV `id`, any non-empty string works in local mode.)

## B. Put a public URL up on workers.dev

Creates two account resources and deploys — public pages go live immediately.

```bash
cd speaker-badges
npm install
npx wrangler login
npx wrangler kv namespace create BADGES        # paste the printed id into wrangler.toml
npx wrangler r2 bucket create speaker-badge-images
npx wrangler deploy
```

You get `https://speaker-badges.<you>.workers.dev`. The public pages (`/request`,
`/b/<token>`, `/img/…`) work right away.

⚠️ The **admin console returns 403** until you configure Cloudflare Access — that is
the intended fail-closed default. **Do not** set `ALLOW_INSECURE_ADMIN` on a public
deploy; it would expose the admin to everyone. To issue badges either:

- add Cloudflare Access (5 minutes — [DEPLOY.md](./DEPLOY.md) step 3), or
- issue locally with option A and rely on the public URL just for viewing badges.

When you're ready for `badges.scottishsummit.com` + Access + Turnstile + email,
follow [DEPLOY.md](./DEPLOY.md) Part 2.
