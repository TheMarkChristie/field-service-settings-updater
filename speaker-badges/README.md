# Speaker Badges

Self-hosted, verifiable **event badges** — Speaker, Volunteer, Organiser, and a
5-Year Speaker milestone — that recipients add to their LinkedIn profile in one
click. Built entirely on Cloudflare: one Worker serves the admin console, the
public pages, and the API; storage is **KV** (records) and **R2** (images); admin
login is **Cloudflare Access**. No per-badge fees, no separate server.

First user: **Scottish Summit** (a Microsoft community conference). Every page is
styled to match [scottishsummit.com](https://scottishsummit.com).

## How it works

1. An organiser signs in (Cloudflare Access) and uploads a CSV roster for an event.
2. The Worker mints an unguessable token per person, stores the badge, and returns
   each person's **badge link** + **LinkedIn "Add to profile" link**.
3. Badge emails are sent automatically (or exported as CSV to mail-merge).
4. Anyone opening a badge link sees a server-rendered, verified badge page.
5. People who were missed self-serve at `/request`; the organiser approves in the
   console, which mints the badge. **Only rostered or approved people can be
   verified — the roster is the anti-forgery allow-list.**

## Project layout

```
src/
  worker.js     Router / fetch handler — wires every route
  lib.js        esc, slug, CSV parse/serialise, JSON helpers, token
  badges.js     Roles, cert IDs, LinkedIn URL builder, mintAward, KV accessors
  theme.js      Design tokens (scottishsummit.com) + WCAG contrast utils
  render.js     Site shell (header/footer) + badge / request / 404 pages
  admin.js      The Access-protected admin console (single page)
  access.js     Cloudflare Access JWT verification (jose)
  images.js     R2 artwork upload + serve
  roster.js     Roster issue, bulk multi-year import, 5-year milestones
  requests.js   Request queue: submit (honeypot+Turnstile), approve, reject
  email.js      Transactional email (Resend-shaped), fire-and-forget
  turnstile.js  Cloudflare Turnstile verification
test/           Vitest suite (in-memory KV/R2 mocks) — 41 tests
```

## Data model (KV)

- **Award** `award:<token>` — `{ token, certId, eventId, name, role, email,
  eventName, year, month, orgId, orgName, artKey, issued, emailedAt }`.
- **Event** `event:<eventId>` (`eventId = slug(name)-year`) — form fields plus
  `artKeys:{ speaker, volunteer, organiser, milestone5 }` and optional `logoKey`.
- **Request** `request:<uuid>` — `{ id, name, email, eventId, role, message,
  status, created, token?, badgeUrl?, linkedInUrl? }`.
- **Index** `idx:<eventId>:<role>:<email>` → token — makes (re)imports idempotent.
- **Images (R2)** `art/<eventId>/<role>.<ext>`, `logo/<eventId>.<ext>`.

## Routes

| Method | Path | Auth | Purpose |
|---|---|---|---|
| GET | `/`, `/admin` | Access | Admin console |
| GET | `/request` | public | Request-a-badge form |
| GET | `/b/<token>` | public | Server-rendered verify page (OG meta) |
| GET | `/img/<key>` | public | Serve an R2 image |
| GET | `/og/<token>.png` | public | OG image (redirects to artwork; composed card is phase 2) |
| GET | `/api/verify?token=` | public | Badge JSON |
| GET | `/api/events` | public | Event list for the request dropdown |
| POST | `/api/request` | public | Submit a request (honeypot + Turnstile) |
| POST | `/api/event` | Access | Create/update an event |
| POST | `/api/roster` | Access | Issue a single event's roster |
| POST | `/api/import` | Access | Bulk import events + people across years |
| POST | `/api/milestones/recompute` | Access | Derive & issue 5-Year badges |
| POST | `/api/upload` | Access | Upload artwork/logo to R2 |
| POST | `/api/email/unsent` | Access | Email anyone issued but not yet emailed |
| GET | `/api/requests` | Access | List requests |
| POST | `/api/requests/approve` | Access | Mint the badge for a request |
| POST | `/api/requests/reject` | Access | Decline a request |

## CSV formats

**Single roster** (`/api/roster`, uses the event selected in the console):

```
name,role,email
Ada Lovelace,speaker,ada@example.com
Grace Hopper,volunteer,grace@example.com
```

Optional extra columns: `month,orgId,orgName`. Roles: `speaker`, `volunteer`,
`organiser` (milestone5 is derived, never rostered).

**Bulk multi-year import** (`/api/import`):

```
event,year,name,role,email
Scottish Summit,2020,Ada Lovelace,speaker,ada@example.com
Scottish Summit,2026,Ada Lovelace,speaker,ada@example.com
```

Rows **must** have an email (used as the identity for idempotency + milestones);
email-less rows are rejected and returned in the response so they can be fixed.
Re-importing the same file creates no duplicates. A person holding a badge of any
role in ≥ 5 distinct years automatically receives one `milestone5` badge.

## Setup & deploy

```bash
npm install
npx wrangler login
npx wrangler kv namespace create BADGES          # paste id into wrangler.toml
npx wrangler r2 bucket create speaker-badge-images
npx wrangler secret put ACCESS_AUD               # from the Access app
npx wrangler secret put ACCESS_TEAM_DOMAIN       # yourteam.cloudflareaccess.com
npx wrangler secret put EMAIL_API_KEY            # Resend/Postmark key (optional)
npx wrangler secret put EMAIL_FROM               # e.g. badges@scottishsummit.com
npx wrangler secret put TURNSTILE_SECRET         # from the Turnstile widget
npx wrangler deploy
```

Then, in the dashboard (human steps):

1. **Cloudflare Access** — create a Zero Trust *self-hosted* application covering
   `/admin*`, `/` and the admin `/api/*` paths on the deployed hostname. Add an
   identity provider (Entra ID for the Microsoft stack) and a policy allowing only
   the organiser's email. Note the **Application Audience (AUD) tag** and team
   domain `<team>.cloudflareaccess.com` → set as the `ACCESS_AUD` /
   `ACCESS_TEAM_DOMAIN` secrets above.
2. **Custom domain** — attach `badges.scottishsummit.com` (Workers → Domains) and
   set `PUBLIC_ORIGIN` in `wrangler.toml` to match (used for absolute badge links
   and `og:image`).
3. **Email** — verify the `scottishsummit.com` sending domain with the provider
   (SPF/DKIM DNS) before launch. With no `EMAIL_API_KEY`, sends are a silent no-op.
4. **Turnstile** — create a widget for `badges.scottishsummit.com`; put the site
   key in `wrangler.toml [vars] TURNSTILE_SITE_KEY` and the secret via
   `wrangler secret put TURNSTILE_SECRET`.

### Local dev

`wrangler dev` runs everything locally. The admin console is normally behind
Access; for local work set `ALLOW_INSECURE_ADMIN=true` (in `.dev.vars`) to bypass
the JWT check — **never set this in production.** When `ACCESS_AUD` /
`ACCESS_TEAM_DOMAIN` are set, the Worker verifies the `Cf-Access-Jwt-Assertion`
header against the team JWKS and returns 403 on any missing/forged token.

## Testing

```bash
npm test
```

41 vitest tests cover: cert IDs + unguessable tokens, the LinkedIn URL keys,
roster issue, multi-year bulk import + idempotency, milestone derivation, the R2
artwork round-trip + placeholder fallback + absolute `og:image`, Access 403s,
Turnstile accept/reject, honeypot, request→approve/reject, email send/no-op/unsent,
the site shell (charity line **SC052785**, nav, socials) on every page type, and
WCAG AA contrast for the palette. Tests use in-memory KV/R2 mocks so they run
under plain Node; swap in `@cloudflare/vitest-pool-workers` for integration runs.

## Continuous deployment

`deploy.yml.example` is a ready-to-use GitHub Actions workflow. When this project
lives in its own repository, move it to `.github/workflows/deploy.yml`. It runs the
tests and `wrangler deploy` on every push to `main`, failing the deploy if tests
fail. Auth via a repo secret `CLOUDFLARE_API_TOKEN` (scoped: Workers Scripts Edit,
KV + R2 Edit on the account).

## Accessibility & branding

WCAG 2.1 AA throughout: `lang`, unique titles, one heading tree, real labels,
visible focus rings, skip links, polite live regions, `prefers-reduced-motion`,
and 320px reflow. The brand button pink is minimally deepened from the accent pink
(`#EC1878` → `#C4157C`) to hold 4.5:1 with white text — see the comment in
`theme.js` and the contrast test. Colours, the tartan background, the dashed-border
buttons and the "Scottish (white) Summit (pink)" wordmark all live in `theme.js`.

**Logo.** The real Scottish Summit hexagonal badge lives at `assets/logo.png`,
base64-embedded into `src/logo-data.js` and served by the Worker from
`/brand/logo.png` (one cached, same-origin request — CSP-friendly, no external
fetch). To swap it, replace `assets/logo.png` and run
`node scripts/embed-logo.mjs`.

## Phase 2 (optional)

A composed OG card (artwork + recipient name + event) generated with `workers-og`
and cached in R2 at `og/<token>.png`, replacing the current `og:image` (which is
the raw artwork). The `/og/<token>.png` route is stubbed to redirect to the
artwork today.

MIT licensed.
