# Perth Panthers Fan Ownership Platform

The fan-owned club platform, built to `spec/specification.md` (v1.1, 139
decisions). This folder migrates wholesale to its own dedicated repository
per decision T51.

## Layout

| Path | What it is |
|---|---|
| `spec/` | Specification v1.1, the 139-decision log, phase backlogs (64 stories), and build traceability |
| `plugin/fan-ownership-platform/` | The WordPress plugin: all three phases' server side — shares ladder, ballot engine, board workspace, match centre, app API |
| `app/panthers_app/` | Flutter app skeleton (login, dashboard, Boardroom voting, Match Centre, TV library) on the plugin's `prx3/v1` API |
| `services/chat-server/` | Self-hosted websocket chat relay (dependency-free Node) that swaps in for the polling transport |
| `fan-ownership/` (repo root sibling) | The original parked prototype — superseded by the platform plugin |

## Plugin quick start

1. Copy `plugin/fan-ownership-platform` into `wp-content/plugins/` and activate.
2. Install companions: WooCommerce (+ Stripe gateway), bbPress, the club's
   badge plugin (implementing `prx3_award_badge` / `prx3_get_member_badges`), and
   a 2FA plugin (setting `prx3_2fa_provider_active`).
3. In **Fan Ownership → Settings**: club identity, share product ID, checkout
   and join page IDs, launch moment (Founders cutoff), Cloudflare Stream and
   FCM credentials, board chair.
4. Create pages using the shortcodes: `[prx3_register]`, `[prx3_share_ladder]`,
   `[prx3_account]`, `[prx3_dashboard]`, `[prx3_ballots]`, `[prx3_ideas]`,
   `[prx3_questions]`, `[prx3_meetings]`, `[prx3_decisions]`, `[prx3_videos]`,
   `[prx3_board_directory]`, `[prx3_redeem_gift]`, `[prx3_match id="123"]`.
5. Permalinks: visit Settings → Permalinks once (rewrite rules for the
   certificate verifier, calendar feed, and document viewer).

## App quick start

```
cd app/panthers_app
flutter create .            # generates platform folders (set iOS 15 / Android 8 floors)
flutter run --dart-define=PRX3_API=https://yourclub.example/wp-json/prx3/v1
```

## Chat relay (optional, swaps in for polling)

```
cd services/chat-server
WP_BASE=https://yourclub.example node server.js
```

## Before launch (from `spec/backlog/traceability.md`)

1. Automated tests on the checkout and ballot paths (T35) — top priority.
2. The documentation suite (admin guide, matchday runbook, dev docs, API
   reference, volunteer handbooks) (T75).
3. Securities-law sign-off on the share checkout (spec §9 risk 1).
4. Firebase project + store accounts for the apps; Jitsi embed for board
   meetings (FO-228).
