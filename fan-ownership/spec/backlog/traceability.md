# Build Traceability — stories vs implementation

Status of every backlog story against the code in `plugin/fan-ownership-platform`,
`app/panthers_app`, and `services/chat-server`, as at build pass 1.
Statuses: **Done** (all ACs implemented), **Partial** (core implemented,
named gaps), **Operational** (satisfied by process/services, not code),
**Not built** (honestly outstanding).

## Phase 1 — Own

| Story | Status | Implementation | Gaps / notes |
|---|---|---|---|
| FO-101 Members-only areas | Done | `class-prx3-access.php` — redirect to join page, search/feed exclusion, teaser layer, instant loss on closure | |
| FO-102 Roles & permissions | Done | `class-prx3-roles.php` — six roles, capability matrix in file header, audited changes, session destroy on board removal | 2FA enforced via provider contract (`prx3_2fa_provider_active`); pair with a 2FA plugin at deploy |
| FO-103 Kill switches | Done | `class-prx3-config.php` — per-feature toggles, audit with reason, member notice; API routes honour switches | |
| FO-104 Identity as config | Partial | `helpers.php` (`prx3_club_name`), used across emails/certificates/API; historical identity frozen on certificates | AC2's automated hard-coded-name check not built (add a CI grep) |
| FO-105 Registration | Partial | `class-prx3-membership.php` — 18+ confirm, terms, email verification gate, duplicate email routing | Apple/Google sign-in needs a social-login companion plugin; app login is email/password until Firebase project exists |
| FO-106 Tiered share checkout | Done | **Shopify (P105)**: `class-prx3-shopify.php` — tier-per-variant cart permalinks, HMAC webhooks, ladder re-verification (mismatches held), sign-to-claim gate, refund clawback; WooCommerce path dormant behind `commerce_provider` | Requires the Shopify store, two webhooks, and tier variant IDs (Settings → Shares & Checkout) |
| FO-107 Top-ups | Done | Ladder continues from held position; cap explained at max | |
| FO-108 Gifting | Done | `class-prx3-gifts.php` — codes, indefinite validity, recipient cap/age at redemption, failure preserves code | Giver's unredeemed-gift list is API-only (`PRX3_Gifts::unredeemed_for`), not yet on the account page |
| FO-109 Invoices | Partial | Gapless sequential numbering (atomic), permanent order records | PDF rendering delegated to a WooCommerce invoice plugin per T58 |
| FO-110 One person one account | Partial | Email-heuristic + payment-fingerprint flagging (`prx3_payment_identity` hook), audited | Admin merge/close UI is manual via Users screen; no dedicated merge tool |
| FO-111 Share register | Done | `class-prx3-register.php` — append-only table, holding-after, consideration, CSV export, audited | |
| FO-112 Owner numbers | Done | Atomic sequence, never reused, on certificate/profile/API | |
| FO-113 Certificates | Partial | Instant issue + re-issue with history, print-to-PDF view, public verification endpoint with consent-gated name, surrendered state | Verify link is textual; QR image generation not yet rendered on the certificate |
| FO-114 Badges | Done | `class-prx3-badges.php` — contract (`prx3_award_badge`/`prx3_get_member_badges`), queue + hourly retry, founders cutoff, configurable milestone rules | Needs the club's badge plugin to implement the contract |
| FO-115 Onboarding | Partial | Journey state, 3-step email series stopping early, dismissible, starter-ballot prompt via dashboard | Welcome video is club content on the join/dashboard page rather than a bespoke first-run screen |
| FO-116 Email foundations | Done | `class-prx3-comms.php` — one branded template from config, category prefs (email+push in one centre), governance always sends, prefs link | Campaign sending itself rides Brevo SMTP site-wide |
| FO-117 My data | Done | `class-prx3-privacy.php` — core exporter/eraser integration, self-serve closure with surrender + session destroy, retention sweeps | |
| FO-118 Exclusive content | Done | `prx3_exclusive` CPT + teaser meta + gating | |
| FO-119 Editorial workflow | Done | `class-prx3-editorial.php` — author≠approver enforcement at publish, manager gate on sensitive, audited approvals | |
| FO-120 Dashboard v1 | Done | `admin/class-prx3-dashboard.php` — owners vs 1,000 target, shares by holding, revenue from register, gifts, surrenders | |
| FO-121 Accept & sign the SHA | Done | `class-prx3-agreements.php` + `prx3-signature.js` — checkout/gift/re-accept all require tick + drawn signature (validated PNG), append-only acceptance log (version/at/IP/context/order/signed), version-bump re-accept banner, no lockout while outstanding | Agreement text itself awaits solicitor (operations handbook draft) |
| FO-122 Executed copy | Done | `/my-agreement/` — full page text + execution block: member signature/name/owner #/date, club stamp (`brand_club_stamp_id`), board countersignature (Legal settings), print-to-PDF, stale-version note, self-only access with board/admin override | |
| FO-123 Board signatures register | Done | Board Workspace → Owner Signatures — owner #, version (out-of-date flag), acceptance count/date/context, signature thumbnail, link to executed copy, `prx3_board` capability, personal-data warning; exporter includes acceptances, eraser removes signature image and retains the log | |
| FO-124 Outbound CRM sync | Done | `class-prx3-sync.php` — change hooks queue signed webhooks (HMAC-SHA256, 8-try retry, capped outbox, 5-min tick), paged `/sync/members` delta, `/sync/register` append-only feed, signatures excluded from payloads, admin health page, off until configured | Dataverse solution (columns/table/flows/connector) built by the club from `spec/power-platform-sync-design.md` |
| FO-125 Inbound matching rules | Done | `/sync/upsert` — ID → email → owner number exact matching, allow-listed enrichment fields (`prx3_sync_inbound_fields`), review queue for no-match/ambiguous/conflict with admin link-or-discard (audited), permanent linking, no outbound echo | |
| FO-126 Guarded automation API | Done | `class-prx3-data-api.php` — key-gated data routes (schema/content-list/content/members/settings), one-click provision/revoke + connection card + JSON profile, platform-types/prx3-meta/allow-list guard rails, money-path member imports, full audit | |
| FO-127 Data-rich work screens | Done | `class-prx3-admin-columns.php` (all 16 types audited, P103), interactive tile dashboard with configurable targets, three-menu structure with the Board menu gathering the board's tools | |

## Phase 2 — Decide

| Story | Status | Implementation | Gaps / notes |
|---|---|---|---|
| FO-201 Author & schedule | Done | `class-prx3-ballots.php` + lifecycle — options/type/window, default 7 days, max-2 live with queue, hard lock once voting starts, second approval | Annual voting calendar is the scheduled-ballot queue; no separate calendar entity |
| FO-202 Cast my votes | Done | Snapshot-weighted casting, unlimited revision to close, unique-key concurrency safety, REST + accessible web form | |
| FO-203 Secret until closed | Done | Tally access requires `prx3_view_tally`; members get secrecy notice; named records never surfaced | |
| FO-204 Eligibility snapshot | Done | Electorate + weights frozen at open; clear explanation for mid-ballot joiners | |
| FO-205 Quorum & re-run | Done | Active-owner denominator (P76), one automatic re-run, unresolved → board action, at-risk reminders to non-voters | |
| FO-206 Automated lifecycle | Done | 5-minute cron: open/close/publish, notifications each stage, pre-publish snapshot + audit | Weekly video wrap is editorial output; result pages link once the video is attached |
| FO-207 Constitutional | Done | 75% check at close, labelled in web/app UI | |
| FO-208 Ties | Done | Result withheld, board casting-vote flow with mandatory reasoning, published with trail | Escalation timer for overdue casting votes not built |
| FO-209 Voting record | Partial | Closed ballots with results via API + archive pages; aggregates only | Dedicated searchable archive UI is the plain CPT archive for now |
| FO-210 Ideas pipeline | Done | Pending → moderation → support → 5% auto-draft ballot + staff/supporter notifications, milestone event | Declining to schedule the drafted ballot records a reason on the idea, not a separate published statement |
| FO-211 Idea lifecycle | Done | Six statuses, history, notifications, decline reason required | |
| FO-212 Questions | Done | Submission, upvotes, monthly video selection + link, written answers, SLA flagging | |
| FO-213 Meetings & RSVP | Done | RSVP toggle, per-member tokenised ICS feed (meetings + ballot closes), reminders | App shows site-timezone datetimes; device-local conversion at app build-out |
| FO-214 Live participation | Partial | Gated stream embed + meeting chat room via the shared chat service; questions module handles upvoting | Presenter view ranking questions in real time not built — presenter uses the questions admin list |
| FO-215 Meeting record | Done | Recording + action minutes fields, permanent archive, audited recording publication | 24h is an operational commitment; platform flags nothing yet |
| FO-216 AGM resolutions | Done | AGM-flagged meetings; resolutions as ballots with formal recorded outcomes; articles-dependence explicitly flagged | |
| FO-217 Financial publishing | Done | Monthly-summary document type, in-portal inline streaming viewer (no download route), missed-month flag, second approval | |
| FO-218 Decision register | Done | Auto entry on pass, statuses + dated updates, stall detection + board alerts, board releases | |
| FO-219 Annual report | Partial | `assemble_annual_report()` compiles ballots/decisions/growth from records | Draft-edit-publish UI not built; staff publish via a document for now |
| FO-220 Forum & comments | Done | bbPress gated, owner-only comments, [Board]/[Club] tags, reporting into queue | |
| FO-221 Moderation & sanctions | Done | Queue, warn/mute/expel ladder, reasons mandatory, expel = admin-only + surrender + session destroy, audited | Edit-with-note moderation action not built |
| FO-222 Chapters | Partial | 5+ founders, naming convention, approval, membership toggle, annual re-affirmation, de-recognition | Directory map is a list; no geographic map rendering |
| FO-223 Referrals | Partial | Links, cookie attribution, credit on first purchase, milestone badges, zero price impact | Opt-in leaderboard not built |
| FO-224 Board role & directory | Done | Role caps exactly per P79, votes only via own shares, directory shortcode with conflicts, tagged posts | |
| FO-225 Structured board actions | Done | Recommendations on ballots, casting votes, reserved/failed-quorum decisions with mandatory published reasoning → register | |
| FO-226 Board workspace | Done | Board-only CPTs (papers/threads/votes/vault/meetings), capability-walled incl. admins-except-break-glass (audited), open internal voting, chair casting vote, auto-minutes | |
| FO-227 Conflicts | Done | Public conflicts register on profile/directory, declare-or-confirm step, recusal lockout on papers/threads/votes, chair-applied recusal | |
| FO-228 Board meetings & observers | Partial | Per-item expiring observer grants, recusal exclusion | In-platform WebRTC video (Jitsi embed) not built — meeting entity + agenda exist; video lands with Phase 3 real-time work as specced |
| FO-229 Vault & departures | Done | View-only rendering, per-view name+time watermark, chair-visible access log (audit), instant revoke + session destroy, records preserved | |

## Phase 3 — Watch

| Story | Status | Implementation | Gaps / notes |
|---|---|---|---|
| FO-301 App sign-in | Partial | JWT access/refresh with rotation + revocation version, secure storage, 401-refresh flow | Apple/Google sign-in and enforced-2FA-in-app await Firebase/entitlement config |
| FO-302 Member parity | Partial | **API: complete** for every member capability; app: login, dashboard, ballots (voting), match centre (timeline+chat), TV library screens; ideas/questions/meetings/decisions are API-wired stubs | Per the B2 decision — remaining screens are structured build-out |
| FO-303 Notifications | Partial | Server: FCM fan-out, category prefs (one centre), deep-link payloads, token registration API | App-side FCM + universal links need the Firebase project and domain association files |
| FO-304 Shipping | Operational | Sentry dependency included; store accounts, monthly train, phased rollout are operational setup | Documented in the spec; nothing to code until store accounts exist |
| FO-305 Live match stream | Done | Cloudflare signed playback tokens (per-member, 4h), state machine (countdown/live/delayed/ended), gated web player, staff alert path via monitoring | App-side player embed pending a webview/player package choice |
| FO-306 Matchday chat | Done | Chat service: identity-tagged, word filter + hold, slow mode, reporting, delete/timeout/mute in-chat; polling transport + websocket relay (`services/chat-server`) persisting through the same API | |
| FO-307 Reporter console | Done | Idempotent `client_key` events (DB unique), retry queue (web localStorage; app mirrors pattern), visible corrections, push on key events, vetted reporter lists | Dedicated big-button console UI is the API + `prx3Reporter` JS; a styled console page is cosmetic build-out |
| FO-308 Away audio | Done | Audio URL per away match, gated page, background-capable native audio element | |
| FO-309 Stream sponsorship | Done | Staff-controlled ad slot URLs per match, served via API, no ad network | |
| FO-310 Replays in the hour | Done | Auto-publish 15-min retries after full-time, Cloudflare recording lookup, one-hour staff alert, push on publish | |
| FO-311 TV library | Done | Types, search, resume positions, teaser layer, signed/gated playback | |
| FO-312 Weekly show pipeline | Partial | Interviews attach to fixtures via video-type + match meta; sensitive gating applies | Standing weekly slot with missed-slot flag not built |
| FO-313 Matchday health | Partial | Kill switches, delayed-stream member messaging, replay/stream failure alerts, Sentry hooks | UptimeRobot/status page are external services to configure; pre-kickoff checklist lives in the runbook |
| FO-314 Documentation | Not built | — | The five-document suite (admin guide, matchday runbook, dev docs, API reference, volunteer handbooks) is the next writing task |
| FO-315 Full dashboard | Partial | Membership/revenue/ballot-health/community/moderation/stalled-decisions | Stream concurrents + episode completion need the analytics/Cloudflare data feeds |
| FO-316 The squad | Partial | `class-prx3-players.php` — `prx3_player` CPT at `/squad/` (number, position, active flag, featured-image photo), staff-only editing, active-only poll options | Honours (POTM/month wins) stored in meta; front-end profile rendering of honours is theme-template build-out |
| FO-317 Player of the match live | Done | Opens on match live, closes 30 min after `_prx3_ended_at`, one changeable vote per member, live tallies, roster validation, auto winner + push + player honours on ballot tick; REST GET/POST `/matches/{id}/potm` | Web/app poll UI consumes the REST routes; native screen is scheduled app build-out |
| FO-318 Player of the month | Done | Last-7-days window, one changeable vote per member, per-month archive option, auto winner + push on daily tick; REST GET/POST `/potm-month` | Same UI note as FO-317 |

## Cross-cutting requirements

- **Coding standards**: the committed gate (`phpcs.xml.dist`) is the **full
  `WordPress` ruleset** (Core + Docs + Extra) with the plugin's registered
  capabilities, `fan-ownership` text domain, and `prx3` prefix rules — all
  44 plugin files pass with zero errors and zero warnings, every class and
  function carries a docblock, and every custom-table query carries a
  justified inline annotation. The test harness (`tests/`) is excluded as
  it shims core functions by design.

- **WCAG 2.1 AA (P44/T71)**: labels on all inputs, `fieldset/legend` on ballots, `aria-live` feedback, visible focus (3px outline), 44px targets, reflow-safe CSS, screen-reader text. CI axe-core run not yet configured.
- **Kill switches (T74)**: registration, checkout, voting, forum, chat, streams, meetings, ideas, questions — all honoured at both web and API layers.
- **Data residency/GDPR (T29/T30)**: exporter/eraser/retention implemented; residency is a hosting choice.
- **Rebrand-proofing (T46)**: club name/colours from config everywhere member-facing; app reads identity from `/me`.
- **Straight-to-production risk (T34)**: the automated money/vote-path suite (T35) now exists — `plugin/fan-ownership-platform/tests/` (`php tests/run-tests.php`, 88 assertions over the ladder, cap, register, ballot casting/weighting/secrecy, agreement signatures/versioning, and sync matching rules). Wire it into the reviewed-PR pipeline (T55) so it gates every deploy.

## Honest summary

Done 43 · Partial 19 · Operational 1 · Not built 1 (remainder of the
documentation suite — the first guides now exist in `docs/guides/`).
The most important follow-ups: (1) run the money/vote test suite in CI
on every pull request, (2) the remainder of the documentation suite. The board
video embed (FO-228) and remaining app screens are scheduled build-out,
consistent with the phasing decisions.
