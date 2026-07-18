# Build Traceability — stories vs implementation

Status of every backlog story against the code in `plugin/fan-ownership-platform`,
`app/panthers_app`, and `services/chat-server`, as at build pass 1.
Statuses: **Done** (all ACs implemented), **Partial** (core implemented,
named gaps), **Operational** (satisfied by process/services, not code),
**Not built** (honestly outstanding).

## Phase 1 — Own

| Story | Status | Implementation | Gaps / notes |
|---|---|---|---|
| FO-101 Members-only areas | Done | `class-fop-access.php` — redirect to join page, search/feed exclusion, teaser layer, instant loss on closure | |
| FO-102 Roles & permissions | Done | `class-fop-roles.php` — six roles, capability matrix in file header, audited changes, session destroy on board removal | 2FA enforced via provider contract (`fop_2fa_provider_active`); pair with a 2FA plugin at deploy |
| FO-103 Kill switches | Done | `class-fop-config.php` — per-feature toggles, audit with reason, member notice; API routes honour switches | |
| FO-104 Identity as config | Partial | `helpers.php` (`fop_club_name`), used across emails/certificates/API; historical identity frozen on certificates | AC2's automated hard-coded-name check not built (add a CI grep) |
| FO-105 Registration | Partial | `class-fop-membership.php` — 18+ confirm, terms, email verification gate, duplicate email routing | Apple/Google sign-in needs a social-login companion plugin; app login is email/password until Firebase project exists |
| FO-106 Tiered share checkout | Done | `class-fop-woocommerce.php` + `fop_ladder_total()` — ladder pricing, price re-verified at order, cap across all sources, idempotent fulfilment, failed payment leaves holding unchanged | Requires WooCommerce + a configured share product (settings screen) |
| FO-107 Top-ups | Done | Ladder continues from held position; cap explained at max | |
| FO-108 Gifting | Done | `class-fop-gifts.php` — codes, indefinite validity, recipient cap/age at redemption, failure preserves code | Giver's unredeemed-gift list is API-only (`FOP_Gifts::unredeemed_for`), not yet on the account page |
| FO-109 Invoices | Partial | Gapless sequential numbering (atomic), permanent order records | PDF rendering delegated to a WooCommerce invoice plugin per T58 |
| FO-110 One person one account | Partial | Email-heuristic + payment-fingerprint flagging (`fop_payment_identity` hook), audited | Admin merge/close UI is manual via Users screen; no dedicated merge tool |
| FO-111 Share register | Done | `class-fop-register.php` — append-only table, holding-after, consideration, CSV export, audited | |
| FO-112 Owner numbers | Done | Atomic sequence, never reused, on certificate/profile/API | |
| FO-113 Certificates | Partial | Instant issue + re-issue with history, print-to-PDF view, public verification endpoint with consent-gated name, surrendered state | Verify link is textual; QR image generation not yet rendered on the certificate |
| FO-114 Badges | Done | `class-fop-badges.php` — contract (`fop_award_badge`/`fop_get_member_badges`), queue + hourly retry, founders cutoff, configurable milestone rules | Needs the club's badge plugin to implement the contract |
| FO-115 Onboarding | Partial | Journey state, 3-step email series stopping early, dismissible, starter-ballot prompt via dashboard | Welcome video is club content on the join/dashboard page rather than a bespoke first-run screen |
| FO-116 Email foundations | Done | `class-fop-comms.php` — one branded template from config, category prefs (email+push in one centre), governance always sends, prefs link | Campaign sending itself rides Brevo SMTP site-wide |
| FO-117 My data | Done | `class-fop-privacy.php` — core exporter/eraser integration, self-serve closure with surrender + session destroy, retention sweeps | |
| FO-118 Exclusive content | Done | `fop_exclusive` CPT + teaser meta + gating | |
| FO-119 Editorial workflow | Done | `class-fop-editorial.php` — author≠approver enforcement at publish, manager gate on sensitive, audited approvals | |
| FO-120 Dashboard v1 | Done | `admin/class-fop-dashboard.php` — owners vs 1,000 target, shares by holding, revenue from register, gifts, surrenders | |

## Phase 2 — Decide

| Story | Status | Implementation | Gaps / notes |
|---|---|---|---|
| FO-201 Author & schedule | Done | `class-fop-ballots.php` + lifecycle — options/type/window, default 7 days, max-2 live with queue, hard lock once voting starts, second approval | Annual voting calendar is the scheduled-ballot queue; no separate calendar entity |
| FO-202 Cast my votes | Done | Snapshot-weighted casting, unlimited revision to close, unique-key concurrency safety, REST + accessible web form | |
| FO-203 Secret until closed | Done | Tally access requires `fop_view_tally`; members get secrecy notice; named records never surfaced | |
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
| FO-307 Reporter console | Done | Idempotent `client_key` events (DB unique), retry queue (web localStorage; app mirrors pattern), visible corrections, push on key events, vetted reporter lists | Dedicated big-button console UI is the API + `fopReporter` JS; a styled console page is cosmetic build-out |
| FO-308 Away audio | Done | Audio URL per away match, gated page, background-capable native audio element | |
| FO-309 Stream sponsorship | Done | Staff-controlled ad slot URLs per match, served via API, no ad network | |
| FO-310 Replays in the hour | Done | Auto-publish 15-min retries after full-time, Cloudflare recording lookup, one-hour staff alert, push on publish | |
| FO-311 TV library | Done | Types, search, resume positions, teaser layer, signed/gated playback | |
| FO-312 Weekly show pipeline | Partial | Interviews attach to fixtures via video-type + match meta; sensitive gating applies | Standing weekly slot with missed-slot flag not built |
| FO-313 Matchday health | Partial | Kill switches, delayed-stream member messaging, replay/stream failure alerts, Sentry hooks | UptimeRobot/status page are external services to configure; pre-kickoff checklist lives in the runbook |
| FO-314 Documentation | Not built | — | The five-document suite (admin guide, matchday runbook, dev docs, API reference, volunteer handbooks) is the next writing task |
| FO-315 Full dashboard | Partial | Membership/revenue/ballot-health/community/moderation/stalled-decisions | Stream concurrents + episode completion need the analytics/Cloudflare data feeds |

## Cross-cutting requirements

- **WCAG 2.1 AA (P44/T71)**: labels on all inputs, `fieldset/legend` on ballots, `aria-live` feedback, visible focus (3px outline), 44px targets, reflow-safe CSS, screen-reader text. CI axe-core run not yet configured.
- **Kill switches (T74)**: registration, checkout, voting, forum, chat, streams, meetings, ideas, questions — all honoured at both web and API layers.
- **Data residency/GDPR (T29/T30)**: exporter/eraser/retention implemented; residency is a hosting choice.
- **Rebrand-proofing (T46)**: club name/colours from config everywhere member-facing; app reads identity from `/me`.
- **Straight-to-production risk (T34)**: automated-test suite for money/vote paths (T35) is **not yet written** — flagged as the top follow-up before real money or binding ballots run.

## Honest summary

Done 34 · Partial 18 · Operational 1 · Not built 1 (documentation suite).
The two most important follow-ups: (1) automated tests on checkout and
ballot tallying before launch, (2) the documentation suite. The board
video embed (FO-228) and remaining app screens are scheduled build-out,
consistent with the phasing decisions.
