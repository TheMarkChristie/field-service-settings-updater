# Data API — Club Guide

A key-gated write API so trusted automation — Claude, migration
scripts, integration jobs — can insert and update platform data
(decision P104). Off by default.

## Enabling it — the ready-made Claude connection

Settings → API & Integrations → **Ready-made Claude connection** →
*Create connection*. One click generates a strong key, switches the
API on, and shows:

- a **connection card** — paste it straight into a Claude chat and
  Claude has everything it needs (base URL, auth header, key, and
  where to discover the surface);
- a **connection profile (JSON)** download for scripts and other
  tools;
- a **Revoke** button that clears the key and switches the API off —
  use it the moment the job is done. Enable-when-needed is the
  intended posture, and the card is an admin password: don't leave it
  in shared documents.

(The same settings can be managed by hand via the *Data API enabled*
and *Data API key* fields above the panel.)

## What it can do

All routes live under `/wp-json/prx3/v1/data/` and accept JSON. Batch
posts take one object or an array (capped at 100 per call).

| Route | Method | Purpose |
|---|---|---|
| `/data/schema` | GET | Discovery: writable types, fields, and settings keys |
| `/data/content-list` | GET | Read platform content with its meta (`?type=prx3_player&page=1`) — look before you write |
| `/data/content` | POST | Create/update any platform content type — players, matches, ballots, meetings, videos, documents, decisions, chapters, ideas, questions, behind-the-scenes — with title, content, status, `_prx3_` meta, and taxonomy terms |
| `/data/members` | POST | Create members (or find by email) and grant shares |
| `/data/settings` | POST | Update known settings keys |

Example — insert a player:

    curl -X POST https://club.example/wp-json/prx3/v1/data/content \
      -H "X-Prx3-Data-Key: YOUR-KEY" -H "Content-Type: application/json" \
      -d '{"type":"prx3_player","status":"publish","title":"Alexis Wight",
           "meta":{"_prx3_number":19,"_prx3_position":"Goaltender","_prx3_active":1}}'

## The guard rails

- **Off until keyed**, constant-time key comparison, every write in
  the audit log.
- **Content**: platform post types only (core posts/pages refused);
  meta writes only accept keys starting `_prx3_` — a payload trying to
  write `wp_capabilities` or other core meta is silently ignored.
- **Members**: share grants go through the same money path as the
  checkout — the 10-share cap, age gate, owner-number sequence, and
  register record all apply. The API cannot mint shares the platform
  wouldn't allow, and every grant appears in the statutory register
  with its source label (default `api_import`).
- **Settings**: allow-listed to the known settings keys; the API keys
  and webhook secret can never be rotated through the API itself.
- Board-only content types are NOT writable over this API.

## What it's for

- Seeding a fresh install (squad, fixtures, first ballots) — ask
  Claude to build the season and post it in one batch.
- Migrating an existing member list: `/data/members` with email, name,
  and holding creates accounts, allocates owner numbers, and writes
  the register — sources labelled so the migration is auditable.
- Keeping fixtures in step with a league feed via a scheduled job.

## What it's deliberately not

Not a public API, not for member devices (they use the member API with
JWT), and not a bypass: nothing inserted through it escapes the same
validation, capability, and register rules the admin screens enforce.

## The sample data pack

`integrations/sample-data/` is a complete demo club that loads through
this API — 20 owners with shares (via the money path), the squad,
fixtures, an open ballot, ideas, questions, the AGM, decisions,
videos, vault documents, an exclusive, two chapters, three FanPress
chats, and the targets + FanPress bubble colours.

```bash
cd integrations/sample-data
PRX3_SITE=https://your-site.example PRX3_KEY=your-key ./seed.sh
```

Provision the key under Settings → API & Integrations. Run once —
re-running duplicates content and re-grants shares. Every payload in
the pack is proven against the real API handlers by
`tests/test-sample-data.php`, so the pack cannot drift from the API.
