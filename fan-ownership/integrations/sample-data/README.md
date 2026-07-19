# Sample data pack

A complete demo club — loaded through the platform's own Data API, so
the data travels the same audited, guard-railed paths as any trusted
automation (money-path member imports, platform-meta-only content
writes, allow-listed settings).

## What it seeds

| File | Route | Contents |
|---|---|---|
| `members.json` | `POST /data/members` | 20 owners with share grants (1–10 shares) — register entries, owner numbers, and badges issue through the money path |
| `content.json` | `POST /data/content` | 8 squad players, 3 matches (upcoming home/away + a finished 4–2 win), 2 ballots (one open with options and a close date, one draft from a forum conversion), 3 ideas, 2 questions (one answered), the 2026 AGM, 2 decision-register entries, 3 videos (highlights / weekly show / teaser), 2 vault documents, 1 owner exclusive, 2 chapters (Glasgow, Toronto), 3 FanPress chats with boards |
| `settings.json` | `POST /data/settings` | Owner + financial targets and the three FanPress bubble colours |

Publishing the matches and the open ballot triggers the platform's own
automation — match-day and ballot-discussion chats appear in FanPress
automatically, which is why `content.json` doesn't need to seed those
threads itself.

## How to run

1. Enable the Data API and provision a key: **Settings → API &
   Integrations** (the one-click Claude connection provisions one).
2. From this directory:

```bash
PRX3_SITE=https://your-site.example PRX3_KEY=your-key ./seed.sh
```

Run it once — re-running inserts content again and re-grants member
shares. Every write lands in the audit log tagged `data_api_*`.

## Guard rails you'll see working

- Members are created through the money path: capped by the share
  ladder, register rows written, owner numbers sequential.
- Content writes accept only platform post types and `_prx3_` meta —
  the pack is validated against the same rules in the automated test
  suite (`tests/test-sample-data.php`).
- Settings accept only known keys.
