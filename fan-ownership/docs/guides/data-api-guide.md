# Data API — Club Guide

A key-gated write API so trusted automation — Claude, migration
scripts, integration jobs — can insert and update platform data
(decision P104). Off by default.

## Enabling it

Settings → Integrations:

- *Data API enabled* — `1`.
- *Data API key* — a long random value. Every call sends it as the
  `X-Prx3-Data-Key` header. Treat it like an admin password: it can
  write content, members, and settings.

Switch it off (or blank the key) the moment an import job is done —
enable-when-needed is the intended posture.

## What it can do

All routes live under `/wp-json/prx3/v1/data/` and accept JSON. Batch
posts take one object or an array (capped at 100 per call).

| Route | Method | Purpose |
|---|---|---|
| `/data/schema` | GET | Discovery: writable types, fields, and settings keys |
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
