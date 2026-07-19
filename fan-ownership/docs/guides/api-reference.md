# API Reference

Every HTTP surface the Fan Ownership Platform exposes. All routes live
under one namespace:

```
https://your-site.example/wp-json/prx3/v1/
```

This reference is also available inside the install at **Setup →
Developers**, generated from the same endpoint registry so the two can
never drift.

## Authentication

| Caller | How |
|---|---|
| Web front end | The WordPress session cookie plus an `X-WP-Nonce` header (the plugin localises `restUrl` and `nonce` into `prx3Config` for its own JS). |
| Mobile app | `POST /auth/login` with username + password returns a JWT pair. Send `Authorization: Bearer <access_token>` on every call; access tokens last **1 hour**, refresh tokens **30 days** — swap at `/auth/refresh`. |
| Data API (Claude, automation) | `X-Prx3-Data-Key: <key>` header. Keys are provisioned under **Setup → Data API & Claude**; the API must also be enabled there. |
| Power Platform sync | `X-Prx3-Api-Key: <key>` header, configured under **Setup → Power Platform**. |
| Shopify webhooks | `X-Shopify-Hmac-Sha256` signature verified against the webhook secret from **Setup → Commerce — Shopify**. |

"Owner" below means the caller must hold the `prx3_member` capability
(granted automatically by the money path) — the same registration wall
as the member site. Kill switches (Fan App Settings → Features) are
honoured by every route: a switched-off feature returns `prx3_off`.

## Errors

Standard WordPress REST envelope, codes prefixed `prx3_`:

```json
{ "code": "prx3_closed", "message": "This ballot is not open.", "data": { "status": 400 } }
```

Common codes: `prx3_login` (401 bad credentials), `prx3_lockout` (too
many sign-in attempts), `prx3_owner_only` (401/403), `prx3_off`
(feature switched off), `prx3_closed` (ballot not open), `prx3_rate`
(429 — write routes are per-user rate-limited per minute: votes 10,
ideas 5, chat 30), `prx3_data_auth` / `prx3_data_disabled` (Data API
key/state), `prx3_data_batch` (batch over 100).

## Auth & profile

### POST /auth/login
Public, throttled by IP and username with growing lockouts.

```json
{ "username": "mark@example.com", "password": "…" }
→ { "access_token": "…", "refresh_token": "…", "expires_in": 3600 }
```

### POST /auth/refresh
`{ "refresh_token": "…" }` → a fresh pair (same shape as login).

### GET /me — signed in
The app boots from this single call:

```json
{
  "id": 12, "name": "Mark C", "owner_number": 27,
  "shares": 3, "max_shares": 100, "next_share_price": 78.13,
  "is_owner": true, "badges": ["founder"],
  "discounts": { "tickets": 10 },
  "club": { "name": "…", "sport": "ice_hockey", "primary": "#1a1a2e", "badge": "…", "wordmark": "…" },
  "ticketing": { "provider": "…", "code": "…" },
  "checkout_url": "https://…myshopify.com/cart/…",
  "calendar_url": "https://…/owners-calendar/<token>.ics"
}
```

### POST /me/push-token — owner
`{ "token": "<fcm-token>" }` — registers a device for push (last five
devices kept per member).

## Ballots

### GET /ballots — owner
Open + scheduled ballots, then the last 20 published results. Each:

```json
{
  "id": 210, "title": "Which third kit?", "body": "…",
  "options": ["Teal", "White", "Purple"],
  "option_descriptions": ["Like 2019…", "", "…"],
  "type": "advisory", "state": "open",
  "opens": "2026-07-01 09:00:00", "closes": "2026-07-14 21:00:00",
  "my_choice": null, "my_weight": 3,
  "board_recommendation": "",
  "secret": true
}
```

While a ballot is secret, `secret: true` replaces the tallies; when the
caller may see results, `tallies` and `result` appear instead.

### POST /ballots/{id}/vote — owner, rate-limited
`{ "choice": 0 }` (option index). Weighted by shares at the electorate
snapshot, one vote per member, changeable while open. Returns a receipt
plus the refreshed ballot payload.

## Ideas, questions, meetings, decisions

| Route | Methods | Notes |
|---|---|---|
| `/ideas` | GET, POST | List with support counts and threshold; submit `{title, body}` (rate-limited, enters moderation). |
| `/ideas/{id}/support` | POST | Toggle support; at threshold the idea goes to the board. |
| `/questions` | GET, POST | List and submit questions. |
| `/questions/{id}/upvote` | POST | Ranks the live Q&A. |
| `/meetings` | GET | Upcoming meetings with start and RSVP state. |
| `/meetings/{id}/rsvp` | POST | Toggle attendance (feeds badges). |
| `/decisions` | GET | The public decision register. |

## Match centre, video, players

| Route | Methods | Notes |
|---|---|---|
| `/matches/{id}` | GET | Score, events, stream state. |
| `/matches/{id}/events` | POST | Reporter console only (`prx3_edit_content`). |
| `/matches/{id}/potm` | GET, POST | Candidates + results; vote `{player}`. |
| `/potm-month` | GET, POST | Monthly standings; vote `{player}`. |
| `/videos` | GET | Library with playback positions. |
| `/videos/{id}/position` | POST | Save a resume point. |

## Community (FanPress)

| Route | Methods | Notes |
|---|---|---|
| `/chat/{room}` | GET, POST | Moderated chat transport; `since` cursor on GET; posts rate-limited, word-filter holds apply. |
| `/forum/topics` | GET, POST | Chat list; start a topic. |
| `/forum/topics/{id}/replies` | GET, POST | Read a chat; reply (quotes via `reply_to`). |
| `/activity` | GET | Activity feed, filterable to followed members. |
| `/notifications` | GET | Replies, @mentions, DMs; mark-read on view. |
| `/members/suggest` | GET | @mention autosuggest (`q`, up to 8 owners). |
| `/messages/{with}` | GET, POST | Private messages with member `{with}`. |

## Data API (server-to-server writes)

Header `X-Prx3-Data-Key`, enabled + provisioned under **Setup → Data
API & Claude**. Full walkthrough with payload formats:
[data-api-guide.md](data-api-guide.md).

| Route | Method | Notes |
|---|---|---|
| `/data/schema` | GET | Self-description: allowed types, settings keys, formats. |
| `/data/content` | POST | Bare array of items, max 100, idempotent by type + title. |
| `/data/content-list` | GET | Read any allowed type. |
| `/data/members` | POST | Members with shares, bio, socials, identity; top-up semantics, never duplicates. |
| `/data/settings` | POST | Write known settings keys. |

Allowed content types (filter `prx3_data_api_types`): ballot, idea,
question, meeting, video, document, exclusive, decision, chapter,
match, player, forum topic.

## Power Platform sync

Header `X-Prx3-Api-Key`; see
[power-platform-sync-guide.md](power-platform-sync-guide.md).
`GET /sync/members`, `GET /sync/register`, `POST /sync/upsert`
(matching rules apply), `GET /sync/review` (the unmatched queue).

## Inbound webhooks

`POST /shopify/webhook` — point both Shopify webhooks (orders/paid,
refunds/create) here. HMAC-verified; drives share grants, the ladder,
gift codes, and chargeback handling.

## Other tokenised endpoints (not REST)

- `/owners-calendar/{token}.ics` — per-member calendar feed (meetings +
  ballot deadlines); token minted per member, returned by `/me`.
- `/verify-owner/{code}/` — public certificate verification page
  behind each certificate's QR code.
- `/brand-pack/`, `/my-agreement/` — member-facing rewrites installed
  by the plugin.
