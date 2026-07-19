# Developer Guide

How the plugin is built, how to extend it safely, and where everything
lives. Companion to the [API Reference](api-reference.md).

## Architecture

One plugin, `fan-ownership-platform`, boots ~45 single-responsibility
modules from `fan-ownership-platform.php` on `plugins_loaded` — each a
static class with an `init()` that wires its own hooks. Prefixes:
`prx3_` (functions, meta, options, capabilities), `PRX3_` (classes),
text domain `fan-ownership`.

Layers, bottom-up:

- **Foundations** — `helpers.php` (club config, owner checks, share
  maths), `PRX3_Config` (single `prx3_settings` option, defaults, kill
  switches), `PRX3_Roles` (roles + capabilities, self-healing),
  `PRX3_Post_Types`, `PRX3_Access` (the registration wall),
  `PRX3_Audit` (append-only log).
- **Own** — membership, the share ladder, Shopify money path, gifts,
  the statutory register (custom tables), certificates, badges,
  onboarding, comms, GDPR privacy, editorial.
- **Decide** — ballots + lifecycle (electorate snapshots, weighted
  secret voting, four-eyes approval), ideas, questions, meetings
  (incl. 8×8 JaaS video rooms), decisions, financials, community,
  FanPress (forum + social), moderation, chapters, board workspace.
- **Watch** — match centre, chat transport, media/VOD, ticketing,
  brand pack, commitments, players, agreements.
- **API** — `includes/api/`: JWT, the app REST API, the Data API.
- **Admin** — five menus (Owners, FanPress Chat, Board, Fan App
  Settings, FanPress Technical Setup), dashboard, admin columns,
  shortcodes, the member pages installer.

## Conventions that matter

- **Self-healing upgrades**: roles, member pages, and rewrites
  re-verify on every version change (stamped options like
  `prx3_pages_installed_v`). Never assume a manual re-activation.
- **Kill switches first**: every member-facing action checks
  `prx3_feature_on()` — honour it in anything you add.
- **Everything audited**: money, votes, identity views, merges, demo
  loads land in `PRX3_Audit::log()` permanently.
- **Owner gate**: member surfaces check `prx3_is_owner()` /
  `prx3_member`; don't leak content past `PRX3_Access`.
- **Block-theme safe rendering**: single-event pages key off
  `get_queried_object_id()`, never `in_the_loop()`.
- **Idempotency**: automated threads, demo data, numbering, and sync
  upserts are all safe to re-run; keep new writes that way.

## Extension points

Actions:

| Hook | Fires |
|---|---|
| `prx3_ballot_opened` | A ballot opens (electorate snapshot taken). |
| `prx3_member_became_owner` | First share lands. |
| `prx3_shares_granted` / `prx3_shares_surrendered` | Register movements. |
| `prx3_sha_accepted` | Shareholders' agreement signed. |
| `prx3_milestone_event` | Badge-worthy events (votes, meetings, watches). |
| `prx3_award_badge` | Hand-off to the club badge plugin. |
| `prx3_ticketing_entitlement` | Discount entitlements changed. |

Filters: `prx3_max_shares`, `prx3_sports`, `prx3_data_api_types`,
`prx3_get_member_badges`, `prx3_badge_provider_present`,
`prx3_2fa_provider_active`, `prx3_user_2fa_enrolled`,
`prx3_user_is_manager`, `prx3_render_certificate_pdf`.

Capabilities (grant caps, don't clone roles — roles self-heal):
`prx3_member`, `prx3_admin`, `prx3_governance`, `prx3_second_approve`,
`prx3_board`, `prx3_moderate`, `prx3_edit_content`, `prx3_view_tally`.

Post types: `prx3_ballot`, `prx3_idea`, `prx3_question`,
`prx3_meeting`, `prx3_board_meeting`, `prx3_decision`, `prx3_video`,
`prx3_document`, `prx3_exclusive`, `prx3_match`, `prx3_player`,
`prx3_chapter`, `prx3_forum_topic`.

## Working on the code

- **Tests**: `php tests/run-tests.php` — a dependency-free harness
  (`tests/bootstrap.php` shims WordPress) covering the money path,
  ballots, forum, social, meetings/JaaS, sample data, and render
  smoke tests over every member surface. Keep it green; add
  assertions with your change.
- **Standards**: full `WordPress` phpcs ruleset (`phpcs.xml.dist`),
  kept at 0 errors / 0 warnings.
- **CI**: `.github/workflows/fan-ownership-ci.yml` runs lint, the
  suite, JS checks, and WPCS on every PR.
- **Versioning**: four-part Major.Minor.Release.Fix, agreed before
  shipping; update the plugin header, `PRX3_VERSION`, readme stable
  tag + changelog, and the traceability tables together.
- **Genericity**: no hard-coded club identity outside
  `PRX3_Config::defaults()` — an automated test enforces it.

## In-install reference

**FanPress Technical Setup → Developers** inside wp-admin carries the endpoint reference
(generated from `PRX3_Admin::api_endpoints()`), the hook list, and the
capability model — so a webmaster always has the docs that match their
installed version.
