# Build Traceability — stories vs implementation

Status of every backlog story against the code in `plugin/fan-ownership-platform`,
`app/panthers_app`, and `services/chat-server`, as at build pass 1.
Statuses: **Done** (all ACs implemented), **Partial** (core implemented,
named gaps), **Operational** (satisfied by process/services, not code),
**Not built** (honestly outstanding).

**Built in** records the plugin build that completed the story (P119).
The original platform build is 0.1.0; later builds are exact from the
release changelog. Ranges mean the story landed across those releases;
"X; feature Y" means the story was later extended in build Y. From
build 4.0.0.0 onward, version numbers are four-part
**Major.Minor.Release.Fix** and each number is agreed with the club
before shipping.

## Release history

| Build | Delivered |
|---|---|
| 0.1.0 | The platform: foundations, Phase 1 Own, Phase 2 Decide, Phase 3 Watch, app API, chat server |
| 0.1.1–0.1.5 | Chargebacks, commitments calendar, ops handbook drafts, three-menu admin, interactive tile dashboard, data-table list screens, targets |
| 0.1.6–0.1.9 | Signed Shareholders' Agreement (signature pad, stamp, countersignature, register), Data API + one-click Claude connection, settings split into sections, Power Platform sync, dashboard under Board |
| 0.2.0–0.2.3 | Shopify commerce (sign-to-claim, ladder verification, refund clawback), WooCommerce removed, commerce seam ops, beneficiary nomination, Legal/Targets/Governance to Board |
| 0.3.0 | Native forum: auto threads, chat archiving, thread-to-ballot conversion |
| 3.0.0 | V3: social layer — activity feed, directory, follows, DMs, notifications, @mentions |
| 3.1.0 | FanPress Chat brand + its own admin menu |
| 3.2.0 | Directory search/pagination, @mention autosuggest, cheers, role ties |
| 3.3.0–3.3.1 | WhatsApp-style chat: bubbles, unread badges, configurable colours, event-page embeds (side-by-side) |
| 3.4.0 | Sample data pack through the Data API; FanPress topics + chat colours API-writable |
| 3.5.0 | One-click "Load demo club" in wp-admin |
| 3.6.0 | Idempotent demo loader, "Remove demo data", FanPress Settings menu name |
| 3.7.0 | Ballot/match permalinks render fully (block-theme safe), "Create member pages" installer |
| 3.8.0 | Sequential numbered ballot URLs |
| 3.9.0 | Rich HTML/image ballot questions, answers with descriptions |
| 3.10.0.0 | The smaller-ones round: certificate QR, member merge tool, voting-record archive, referral leaderboard, chapters map, Live Q&A presenter, annual-report workspace, weekly-show flag, player honours, first-run welcome, draft-ballot state note, identity scan + GitHub Actions CI, render smoke tests (every member surface proven; fixed a decisions-page fatal) |
| 3.11.0.0 | Comms round: chat emails with opt-out, reply-quoting, pinned messages, per-chat mute; owner profiles with the activity percentage (voting/community/watching) and match-watch tracking |
| 3.12.0.0 | Identity round: six-field private identity record with PEP declaration, audited masked compliance lookup, profile picture + five-photo consent gallery, GDPR wiring |
| 3.12.0.1 | Fix: demo ballots pre-approved (no four-eyes hold on demo sites), kit ballot live to 31 Aug; member import seeds bios/socials/identity incl. a PEP=yes example |
| 3.12.1.0 | WP user screens link to the live owner profile (Users-list row action + Owner Profile panel with owner number, shares, activity, and a view button) |
| 3.12.1.1 | Fix: demo open ballot opened through the real lifecycle (electorate snapshot, quorum, audit, chat) so demo owners can vote; Load demo club heals dead ballots |
| 3.12.1.2 | Fix: admin screens for four screen-less stores — Share Register (Owners), Audit Log (FanPress Settings), Reports queue (FanPress), Gift Codes (Owners) |
| 3.12.1.3 | Fix: member pages self-install on version change (stamped, idempotent); profile-link fallback re-wires the Profile page by slug |
| 3.12.1.4 | Board menu gains the audited Identity Lookup (masked government ID) — P122 amended: board members may view identity records |
| 3.13.0.0 | Meeting video: in-platform Jitsi rooms on every meeting page (member + board, never matches), windowed one hour before to six after, audited joins, self-hostable domain setting |
| 3.14.0.0 | Setup menu + 8×8 JaaS (P124): dedicated top-level Setup menu (Overview status checklist, Commerce — Shopify, Streaming, Meeting Video, Push, Data API, Sync) with a note and "Where to get this" link on every field; meeting video signs RS256 8×8 JaaS room tokens (board/governance/admin moderate, owners join as guests, no 8×8 accounts needed for joiners) with the open meet.jit.si fallback kept |
| 3.15.0.0 | Documentation complete (P125, closes FO-314): developer guide + full API reference in docs/guides (auth models, error codes, rate limits, payload examples, webhooks, tokenised feeds) and a Setup → Developers page inside every install, generated from the plugin's own endpoint registry |
| 3.15.0.1 | Fix: the technical admin menu is named "FanPress Technical Setup" (was "Setup") — menu label, page headings, and guides updated |
| 3.15.1.0 | Question categories (P126): Board / Manager — pre-match / Manager — weekly / Captain on submit, lists, API, and the Live Q&A presenter. Profile content moderation (P127): admins can fix inappropriate bio/socials/photos from the WP user screen; identity, name, and contact details stay untouchable; all audited |
| 3.15.1.1 | Fix (P128): Board → Owner Management — search an owner, personal info (audited, ID masked) and profile-content moderation on one page; Share Register moved from Owners to the Board menu; these screens are board members + Owner-Admins only; board members can now moderate profile content |
| 3.15.1.2 | Fix (P129): FanPress branding across the admin — "Fan App Settings" → FanPress Settings, "Fan Club Technical Setup" → FanPress Technical Setup; labels/headings/notices/guides updated, slugs unchanged |
| 3.15.1.3 | Fix (P130): owner avatars use the uploaded photo (local grey placeholder, no Gravatar) everywhere WordPress asks for one; Share Register drills into a per-owner share record and each row links to WP account, web profile, and Owner Management |
| 3.15.1.4 | Fix (P131): selectable squad source — point the player-of-the-match / player-of-the-month features at an existing players table from another plugin (FanPress Settings → Club); the built-in Players list is hidden when an external one is chosen, so there is never a second squad |
| 3.15.1.5 | Full code scan: WPCS back to 0/0 across the plugin (four standards findings in recent screens corrected, no behaviour change); requirements + specification refreshed for question categories, Owner Management, owner avatars, and the selectable squad source |
| 3.15.1.6 | Review-driven fixes (P132): avatar placeholder no longer stripped by esc_url; 8×8 JaaS kid normalised to AppID/KeyID; Live Q&A presenter drops answered questions; empty-answer save no longer hides a question from the overdue sweep; private messages resolve the owner number instead of a raw user ID. Test harness models esc_url protocol stripping; 401 assertions |
| 3.15.1.7 | Squad-source selector (P133): "Players come from" now lists any players table with an admin screen (public or admin-only) so an external club plugin's Players type is connectable; excludes the platform's own content types and WP internals; built-in option rebranded "FanPress players" |
| 3.15.1.8 | Fixtures-source selector (P134): "Matches come from" points player-of-the-match voting at an existing fixtures table (same pattern as players); external fixtures open voting while published; built-in FanPress matches remain the default |
| 3.15.1.9 | Fix (P129 completed): the last two top-level menus rebranded — "Owners" → FanPress Owners, "Board" → FanPress Board; all five menus now carry the FanPress name, slugs unchanged |
| 3.16.2.0 | App branding (P137): new public `GET /config` (club name + brand pack); the app themes itself from the brand pack — full club palette, badge in the app bar, club font (best-effort runtime load), tagline, and the branded sign-in screen (badge/wordmark + name + tagline) before login — with dark mode following the device. No mobile-app settings menu required; launcher name/icon stay per-build values from the brand pack |
| 3.16.1.2 | Fix (P136): the app's JWT is honoured on production. `bearer_auth` now reads the Authorization header from `HTTP_AUTHORIZATION`, the Apache rewrite fallback `REDIRECT_HTTP_AUTHORIZATION`, and `getallheaders()` — many hosts strip it from the first, which silently ignored the token so sign-in worked but every authenticated call (e.g. `/me`) 401'd and the app bounced back to sign-in. Six new bearer-auth assertions cover all three sources plus the negative cases |
| 3.16.1.1 | App resilience (P136): every API call now has a 20s timeout and maps timeouts, unreachable-host and non-JSON responses to clear messages, so a stalled `/me` after sign-in no longer spins forever on "Signing in…"; a failed profile load drops the session back to a usable sign-in screen, and the login screen surfaces any error instead of silently bouncing |
| 3.16.1.0 | App sign-in under 2FA (P136): new `POST /auth/app-login` authenticates with a WordPress Application Password (validated by core, unaffected by interactive two-factor) and issues the same JWT pair, with the sign-in throttle applied; the app tries the normal password then transparently falls back to the application-password path on 401/403, and the login screen guides members to create one if their club enforces 2FA |
| 3.16.0.1 | Fix (P135): the website bot launcher no longer renders a bare "?" — it shows the configured avatar, else a local inline chat-bubble glyph (no third-party request); the chat header matches (avatar, else a grey placeholder) for consistency everywhere the bot appears |
| 3.16.0.0 | FanPress Bot (P135): a configurable help bot for web and app — answers members from a searchable FAQ knowledge base and, when it cannot, opens a support ticket in one tap. WhatsApp-style bubbles matching FanPress Chat; name/avatar/colour/background/greeting/on-off under FanPress Technical Setup → FanPress Bot. New FAQ + Support Ticket types (Technical Setup), member-scoped ticket threads with staff notification, floating launcher + [prx3_helpbot]/[prx3_tickets] shortcodes, REST under prx3/v1/help and prx3/v1/tickets |

## Phase 1 — Own

| Story | Status | Built in | Implementation | Gaps / notes |
|---|---|---|---|---|
| FO-101 Members-only areas | Done | 0.1.0 | `class-prx3-access.php` — redirect to join page, search/feed exclusion, teaser layer, instant loss on closure | |
| FO-102 Roles & permissions | Done | 0.1.0 | `class-prx3-roles.php` — six roles, capability matrix in file header, audited changes, session destroy on board removal | 2FA enforced via provider contract (`prx3_2fa_provider_active`); pair with a 2FA plugin at deploy |
| FO-103 Kill switches | Done | 0.1.0 | `class-prx3-config.php` — per-feature toggles, audit with reason, member notice; API routes honour switches | |
| FO-104 Identity as config | Done | 0.1.0; 3.10.0.0 | `helpers.php` (`prx3_club_name`), used across emails/certificates/API; historical identity frozen on certificates + CI-grade identity scan lives in the suite (test-identity.php); helpers default now sourced from config | |
| FO-105 Registration | Partial | 0.1.0 | `class-prx3-membership.php` — 18+ confirm, terms, email verification gate, duplicate email routing | Apple/Google sign-in needs a social-login companion plugin; app login is email/password until Firebase project exists |
| FO-106 Tiered share checkout | Done | 0.2.0–0.2.1 | **Shopify (P105)**: `class-prx3-shopify.php` — tier-per-variant cart permalinks, HMAC webhooks, ladder re-verification (mismatches held), sign-to-claim gate, refund clawback (WooCommerce removed entirely, P106) | Requires the Shopify store, two webhooks, and tier variant IDs (Settings → Shares & Checkout) |
| FO-107 Top-ups | Done | 0.1.0 | Ladder continues from held position; cap explained at max | |
| FO-108 Gifting (Gift Codes screen 3.12.1.2) | Done | 0.1.0 | `class-prx3-gifts.php` — codes, indefinite validity, recipient cap/age at redemption, failure preserves code | Giver's unredeemed-gift list is API-only (`PRX3_Gifts::unredeemed_for`), not yet on the account page |
| FO-109 Invoices | Operational | 0.2.0 | Shopify issues the order confirmation/receipt for every purchase; the platform's register holds the permanent ownership record | A branded PDF invoice app on the Shopify store covers T58 if formal invoices are required |
| FO-110 One person one account | Done | 0.1.0; 3.10.0.0 | Email-heuristic + payment-fingerprint flagging (`prx3_payment_identity` hook), audited + Member Tools → Merge duplicate accounts: register-recorded share move, SHA acceptance kept, owner role removed, audited | |
| FO-111 Share register | Done | 0.1.0; on-screen register 3.12.1.2 | `class-prx3-register.php` — append-only table, holding-after, consideration, CSV export, audited | |
| FO-112 Owner numbers | Done | 0.1.0 | Atomic sequence, never reused, on certificate/profile/API | |
| FO-113 Certificates | Done | 0.1.0; 3.10.0.0 | Instant issue + re-issue with history, print-to-PDF view, public verification endpoint with consent-gated name, surrendered state + QR code rendered on the certificate linking /verify-owner/CODE/ (remote QR image service) | |
| FO-114 Badges | Done | 0.1.0 | `class-prx3-badges.php` — contract (`prx3_award_badge`/`prx3_get_member_badges`), queue + hourly retry, founders cutoff, configurable milestone rules | Needs the club's badge plugin to implement the contract |
| FO-115 Onboarding | Done | 0.1.2–0.1.9; 3.10.0.0 | Journey state, 3-step email series stopping early, dismissible, starter-ballot prompt via dashboard + First-run welcome panel on the owners hub: club welcome video (setting), starter pointers, dismissible | |
| FO-116 Email foundations | Done | 0.1.0 | `class-prx3-comms.php` — one branded template from config, category prefs (email+push in one centre), governance always sends, prefs link | Campaign sending itself rides Brevo SMTP site-wide |
| FO-117 My data | Done | 0.1.0 | `class-prx3-privacy.php` — core exporter/eraser integration, self-serve closure with surrender + session destroy, retention sweeps | |
| FO-118 Exclusive content | Done | 0.1.0 | `prx3_exclusive` CPT + teaser meta + gating | |
| FO-119 Editorial workflow | Done | 0.1.0 | `class-prx3-editorial.php` — author≠approver enforcement at publish, manager gate on sensitive, audited approvals | |
| FO-120 Dashboard v1 | Done | 0.1.2–0.1.9 | `admin/class-prx3-dashboard.php` — owners vs 1,000 target, shares by holding, revenue from register, gifts, surrenders | |
| FO-121 Accept & sign the SHA | Done | 0.1.6–0.1.9 | `class-prx3-agreements.php` + `prx3-signature.js` — checkout/gift/re-accept all require tick + drawn signature (validated PNG), append-only acceptance log (version/at/IP/context/order/signed), version-bump re-accept banner, no lockout while outstanding | Agreement text itself awaits solicitor (operations handbook draft) |
| FO-122 Executed copy | Done | 0.1.6–0.1.9 | `/my-agreement/` — full page text + execution block: member signature/name/owner #/date, club stamp (`brand_club_stamp_id`), board countersignature (Legal settings), print-to-PDF, stale-version note, self-only access with board/admin override | |
| FO-123 Board signatures register | Done | 0.1.6–0.1.9 | Board Workspace → Owner Signatures — owner #, version (out-of-date flag), acceptance count/date/context, signature thumbnail, link to executed copy, `prx3_board` capability, personal-data warning; exporter includes acceptances, eraser removes signature image and retains the log | |
| FO-124 Outbound CRM sync | Done | 0.1.6–0.1.9 | `class-prx3-sync.php` — change hooks queue signed webhooks (HMAC-SHA256, 8-try retry, capped outbox, 5-min tick), paged `/sync/members` delta, `/sync/register` append-only feed, signatures excluded from payloads, admin health page, off until configured | Dataverse solution (columns/table/flows/connector) built by the club from `spec/power-platform-sync-design.md` |
| FO-125 Inbound matching rules | Done | 0.1.9 | `/sync/upsert` — ID → email → owner number exact matching, allow-listed enrichment fields (`prx3_sync_inbound_fields`), review queue for no-match/ambiguous/conflict with admin link-or-discard (audited), permanent linking, no outbound echo | |
| FO-126 Guarded automation API | Done | 0.1.0 | `class-prx3-data-api.php` — key-gated data routes (schema/content-list/content/members/settings), one-click provision/revoke + connection card + JSON profile, platform-types/prx3-meta/allow-list guard rails, money-path member imports, full audit | |
| FO-127 Data-rich work screens | Done | 0.1.3–0.1.5; menus 3.1.0.0 era, FanPress Settings 3.6.0, Technical Setup menu 3.14.0.0, Owner Management 3.15.1.1 | `class-prx3-admin-columns.php` (all 16 types audited, P103), interactive tile dashboard with configurable targets, five-menu structure (FanPress Owners, FanPress Chat, FanPress Board, FanPress Settings, FanPress Technical Setup) — the Technical Setup menu gathers all API/integration configuration with an Overview status checklist and annotated fields (note + "Where to get this" link on each). Board → Owner Management (P128) unifies owner personal-info lookup and profile-content moderation; the Share Register lives in the Board menu and drills into a per-owner share record (P128, P130) | |
| FO-128 Commerce seam ops | Done | 0.2.0–0.2.1 | `class-prx3-shopify.php` ops layer — Commerce Ops screen (held/unclaimed with release/reassign/remind/drop), 3/10-day chasing + 30-day refund-review flag, webhook-quiet alert, dashboard tile, seeded reconciliation commitment, monthly register safeguard email (`class-prx3-register.php`) | Refunds themselves are issued in Shopify by policy |
| FO-129 Beneficiary nomination | Done | 0.1.0 | Account-page nomination (audited), privacy export/erasure wiring, `death-and-transmission.md` runbook | Solicitor to confirm articles transmission clause |

## Phase 2 — Decide

| Story | Status | Built in | Implementation | Gaps / notes |
|---|---|---|---|---|
| FO-201 Author & schedule | Done | 0.1.0; ballot numbers 3.8.0, rich questions 3.9.0 | `class-prx3-ballots.php` + lifecycle — options/type/window, default 7 days, max-2 live with queue, hard lock once voting starts, second approval; sequential numbered URLs (/owners/ballot/N/, P117) | Annual voting calendar is the scheduled-ballot queue; no separate calendar entity |
| FO-202 Cast my votes | Done | 0.1.0 | Snapshot-weighted casting, unlimited revision to close, unique-key concurrency safety, REST + accessible web form | |
| FO-203 Secret until closed | Done | 0.1.0 | Tally access requires `prx3_view_tally`; members get secrecy notice; named records never surfaced | |
| FO-204 Eligibility snapshot | Done | 0.1.0 | Electorate + weights frozen at open; clear explanation for mid-ballot joiners | |
| FO-205 Quorum & re-run | Done | 0.1.0 | Active-owner denominator (P76), one automatic re-run, unresolved → board action, at-risk reminders to non-voters | |
| FO-206 Automated lifecycle | Done | 0.1.0 | 5-minute cron: open/close/publish, notifications each stage, pre-publish snapshot + audit | Weekly video wrap is editorial output; result pages link once the video is attached |
| FO-207 Constitutional | Done | 0.1.0 | 75% check at close, labelled in web/app UI | |
| FO-208 Ties | Done | 0.1.0 | Result withheld, board casting-vote flow with mandatory reasoning, published with trail | Escalation timer for overdue casting votes not built |
| FO-209 Voting record | Done | 0.1.0; 3.10.0.0 | Closed ballots with results via API + archive pages; aggregates only + [prx3_voting_record] searchable archive of finished ballots + installer page | |
| FO-210 Ideas pipeline | Done | 0.1.0 | Pending → moderation → support → 5% auto-draft ballot + staff/supporter notifications, milestone event | Declining to schedule the drafted ballot records a reason on the idea, not a separate published statement |
| FO-211 Idea lifecycle | Done | 0.1.0 | Six statuses, history, notifications, decline reason required | |
| FO-212 Questions | Done | 0.1.0; categories 3.15.1.0 | Submission, upvotes, monthly video selection + link, written answers, SLA flagging. Categories (P126): every question is put to Board, Manager — pre-match, Manager — weekly, or Captain (filterable list); owners pick on submit, staff can re-file, lists/app/API and the Live Q&A presenter filter by category | |
| FO-213 Meetings & RSVP | Done | 0.1.0 | RSVP toggle, per-member tokenised ICS feed (meetings + ballot closes), reminders | App shows site-timezone datetimes; device-local conversion at app build-out |
| FO-214 Live participation | Done | 0.1.0; 3.10.0.0 | Gated stream embed + meeting chat room via the shared chat service; questions module handles upvoting + Board → Live Q&A presenter: open questions ranked by upvotes, 20s auto-refresh | |
| FO-215 Meeting record | Done | 0.1.0 | Recording + action minutes fields, permanent archive, audited recording publication | 24h is an operational commitment; platform flags nothing yet |
| FO-216 AGM resolutions | Done | 0.1.0 | AGM-flagged meetings; resolutions as ballots with formal recorded outcomes; articles-dependence explicitly flagged | |
| FO-217 Financial publishing | Done | 0.1.0 | Monthly-summary document type, in-portal inline streaming viewer (no download route), missed-month flag, second approval | |
| FO-218 Decision register | Done | 0.1.0 | Auto entry on pass, statuses + dated updates, stall detection + board alerts, board releases | |
| FO-219 Annual report | Done | 0.1.0; 3.10.0.0 | `assemble_annual_report()` compiles ballots/decisions/growth from records + Board → Annual Report: drafts list + one-click structured draft into the vault (documents, dtype annual-report) | |
| FO-220 Forum & comments | Done | 0.1.0; native rebuild 0.3.0 | Native forum (see FO-230) gated, owner-only comments, [Board]/[Club] tags, reporting into queue | bbPress dependency superseded by P109 |
| FO-221 Moderation & sanctions | Done | 0.1.0 | Queue, warn/mute/expel ladder, reasons mandatory, expel = admin-only + surrender + session destroy, audited | Edit-with-note moderation action not built |
| FO-222 Chapters | Done | 0.1.0; 3.10.0.0 | 5+ founders, naming convention, approval, membership toggle, annual re-affirmation, de-recognition + [prx3_chapters] directory + OpenStreetMap/Leaflet map when chapters carry _prx3_lat/_prx3_lng | |
| FO-223 Referrals | Done | 0.1.0; 3.10.0.0 | Links, cookie attribution, credit on first purchase, milestone badges, zero price impact + [prx3_referrals]: personal link, opt-in public leaderboard (top ten), recognition-only | |
| FO-224 Board role & directory | Done | 0.1.0 | Role caps exactly per P79, votes only via own shares, directory shortcode with conflicts, tagged posts | |
| FO-225 Structured board actions | Done | 0.1.0 | Recommendations on ballots, casting votes, reserved/failed-quorum decisions with mandatory published reasoning → register | |
| FO-226 Board workspace | Done | 0.1.0 | Board-only CPTs (papers/threads/votes/vault/meetings), capability-walled incl. admins-except-break-glass (audited), open internal voting, chair casting vote, auto-minutes | |
| FO-227 Conflicts | Done | 0.1.0 | Public conflicts register on profile/directory, declare-or-confirm step, recusal lockout on papers/threads/votes, chair-applied recusal | |
| FO-228 Board meetings & observers | Done | 0.1.0; video 3.13.0.0; 8×8 JaaS 3.14.0.0 | In-platform video rooms on every meeting page (member meetings/AGMs for owners, board meetings inside the board wall): 8×8 JaaS with platform-signed RS256 room tokens when configured under Setup → Meeting Video (board/governance/admin moderate, owners join as guests, nobody needs an 8×8 account), open meet.jit.si fallback otherwise; unguessable per-meeting rooms, open one hour before start to six hours after, joins audited. Matches excluded by design — they stream via the Match Centre | Owner observation of board meetings: chair streams the room into a member meeting when observers are invited |
| FO-229 Vault & departures | Done | 0.1.0 | View-only rendering, per-view name+time watermark, chair-visible access log (audit), instant revoke + session destroy, records preserved | |

| FO-230 Native forum | Done | 0.3.0 | `class-prx3-forum.php` — gated topic CPT + comment replies + boards taxonomy, auto threads on ballot open and match publish (idempotent source keys), chat transcript archived on match end (anonymised, held/removed excluded), one-action thread→ballot conversion with provenance, REST routes for the app, admin columns, word-filter holds + mutes + kill switch | bbPress/BuddyPress dependency removed (T16 superseded by P109) |
| FO-231 Social layer | Done | 3.0.0 | `class-prx3-social.php` — activity feed merging topics/ballots/decisions/videos, member directory with one-way follows, private messages over the moderated chat transport (participant-guarded rooms, dual inboxes), capped notifications with unread counts and mark-read, @mentions by login/slug, four shortcodes plus REST `/activity`, `/notifications`, `/messages/{with}` | Native BuddyPress-parity build (P110); literal BuddyPress code reuse ruled out on GPL licensing |
| FO-232 FanPress Chat menu | Done | 3.1.0 | Top-level FanPress Chat admin menu (Overview with live counts + latest topics, Topics, Boards, Held Replies), forum post type moved out of Owners, FanPress Chat branding across admin labels and the member forum heading | Amends the three-menu rule (FO-127) to four menus by user decision P111 |
| FO-233 Community round-out | Done | 3.2.0 | Directory search + pagination (`PRX3_Social::directory`), @mention autosuggest (`assets/js/prx3-mentions.js` + owner-gated `/members/suggest`), activity cheers (`toggle_cheer`/`cheer_count`, nonce + owner gate), FanPress capabilities tied to platform roles via versioned self-heal | Remaining BuddyPress gaps by choice: email notifications, avatars/cover images, online presence, widgets/blocks |
| FO-234 WhatsApp-style chat | Done | 3.3.0–3.3.1 | Chat-list UI with category chips, last-message snippets, and unread badges (`prx3_thread_reads`, visible-only counting); bubble conversations (`render_thread_view`/`bubble_role`: mine right, theirs left, board highlighted + tagged); configurable colours in Settings → FanPress Chat (`chat_colors` with safe fallbacks); match/ballot chats embedded on their pages via `the_content` + `topic_for_source`; front-end send handler; app API unread counts + mark-read | |
| FO-130 Sample data pack | Done | 3.4.0–3.6.0 | `integrations/sample-data/` — 20 members (money-path grants), 32 content items across all 12 Data API types incl. FanPress chats, targets + chat colours via settings route, one-command `seed.sh` plus bundled one-click "Load demo club" / "Remove demo data" admin buttons (no key/terminal needed; loading idempotent — skips existing content, tops holdings up to pack amounts; removal deletes the exact demo footprint incl. auto-chats and register rows); `tests/test-sample-data.php` proves every payload through the real API handlers; `prx3_forum_topic` added to Data API allowed types; chat colours registered as known settings | |
| FO-131 Renders out of the box | Done | 3.7.0 | Ballot permalinks render the voting card/state (`PRX3_Shortcodes::single_content`), match permalinks render the Match Centre, both block-theme safe (queried-post keyed; chat embed fixed likewise); `PRX3_Pages` one-click installer creates all 17 member shortcode pages idempotently and wires join/account gate destinations | Root-caused from the live Perth Panthers site showing bare ballot pages |
| FO-235 Owner profile | Done | 3.11.0.0; avatars + moderation 3.15.1.x | `PRX3_Social::activity_score`/`record_match_watch`/`shortcode_profile` — activity ring (voting/community/watching), bio + socials + share-visibility choice, public card with follow, directory links, profile page in the installer; WP Users screens link to the live profile (3.12.1.0). Owner avatars come from the uploaded photo everywhere, with a local grey placeholder and no Gravatar (P130, 3.15.1.3). `PRX3_Social::moderate_profile` lets board/admins fix inappropriate bio/socials/photos (never identity or contact details), audited, surfaced in Board → Owner Management (P127–P128) | |
| FO-236 FanPress comms | Done | 3.11.0.0 | Chat emails via PRX3_Comms with profile opt-out, quote replies (comment_parent + excerpt), staff pins (audited, banner), per-chat mute (badge + email suppression) | |
| FO-237 Identity & photos | Done | 3.12.0.0–3.12.1.4; Owner Management 3.15.1.1 | `PRX3_Social::identity`/`identity_public`/`mask_gov_id`/`add_gallery_photo` — six-field private identity record (public slice: birth name + nationality), PEP yes/no, audited masked compliance lookup in Member Tools and Board → Owner Management (P128), profile + five-photo gallery via the media pipeline with social-use consent, GDPR export/erase wiring | Government ID stored as WordPress user meta — standard plugin practice; DB access equals record access |

## Phase 3 — Watch

| Story | Status | Built in | Implementation | Gaps / notes |
|---|---|---|---|---|
| FO-301 App sign-in | Partial | 0.1.0 | JWT access/refresh with rotation + revocation version, secure storage, 401-refresh flow | Apple/Google sign-in and enforced-2FA-in-app await Firebase/entitlement config |
| FO-302 Member parity | Partial | 0.1.2–0.1.9 | **API: complete** for every member capability; app: login, dashboard, ballots (voting), match centre (timeline+chat), TV library screens; ideas/questions/meetings/decisions are API-wired stubs | Per the B2 decision — remaining screens are structured build-out |
| FO-303 Notifications | Partial | 0.1.0 | Server: FCM fan-out, category prefs (one centre), deep-link payloads, token registration API | App-side FCM + universal links need the Firebase project and domain association files |
| FO-304 Shipping | Operational | 0.1.0 | Sentry dependency included; store accounts, monthly train, phased rollout are operational setup | Documented in the spec; nothing to code until store accounts exist |
| FO-305 Live match stream | Done | 0.1.0 | Cloudflare signed playback tokens (per-member, 4h), state machine (countdown/live/delayed/ended), gated web player, staff alert path via monitoring | App-side player embed pending a webview/player package choice |
| FO-306 Matchday chat | Done | 0.1.0 | Chat service: identity-tagged, word filter + hold, slow mode, reporting, delete/timeout/mute in-chat; polling transport + websocket relay (`services/chat-server`) persisting through the same API | |
| FO-307 Reporter console | Done | 0.1.0 | Idempotent `client_key` events (DB unique), retry queue (web localStorage; app mirrors pattern), visible corrections, push on key events, vetted reporter lists | Dedicated big-button console UI is the API + `prx3Reporter` JS; a styled console page is cosmetic build-out |
| FO-308 Away audio | Done | 0.1.0 | Audio URL per away match, gated page, background-capable native audio element | |
| FO-309 Stream sponsorship | Done | 0.1.0 | Staff-controlled ad slot URLs per match, served via API, no ad network | |
| FO-310 Replays in the hour | Done | 0.1.0 | Auto-publish 15-min retries after full-time, Cloudflare recording lookup, one-hour staff alert, push on publish | |
| FO-311 TV library | Done | 0.1.0 | Types, search, resume positions, teaser layer, signed/gated playback | |
| FO-312 Weekly show pipeline | Done | 0.1.0; 3.10.0.0 | Interviews attach to fixtures via video-type + match meta; sensitive gating applies + Standing weekly slot setting; missed-slot flag + staff notice when no episode in 8 days, self-clearing | |
| FO-313 Matchday health | Partial | 0.1.0 | Kill switches, delayed-stream member messaging, replay/stream failure alerts, Sentry hooks | UptimeRobot/status page are external services to configure; pre-kickoff checklist lives in the runbook |
| FO-314 Documentation | Done | 0.1.x–3.12.1.4; developer docs + API reference 3.15.0.0 | Fourteen guides in docs/guides: setup, admin screens, board, volunteers, match/ballot runbooks, owners, agreement, player voting, sync, data API, FanPress, Shopify, developer guide, and the full API reference — plus the in-install reference at Setup → Developers, generated from the plugin's own endpoint registry so docs and code can't drift | |
| FO-315 Full dashboard | Partial | 0.1.2–0.1.9 | Membership/revenue/ballot-health/community/moderation/stalled-decisions | Stream concurrents + episode completion need the analytics/Cloudflare data feeds |
| FO-316 The squad | Done | 0.1.0; 3.10.0.0; source select 3.15.1.4 | `class-prx3-players.php` — `prx3_player` CPT at `/squad/` (number, position, active flag, featured-image photo), staff-only editing, active-only poll options + Player pages render number, position, and POTM/month honours (block-theme safe). **Squad source is selectable (P131):** a club can point the player features at an existing players table from another plugin (FanPress Settings → Club → "Players come from"), and the built-in list is hidden so there is never a second squad | |
| FO-317 Player of the match live | Done | 0.1.0; match source 3.15.1.8 | Opens on match live, closes 30 min after `_prx3_ended_at`, one changeable vote per member, live tallies, roster validation, auto winner + push + player honours on ballot tick; REST GET/POST `/matches/{id}/potm`. **Fixtures source is selectable (P134):** with an external matches table the vote attaches to that plugin's fixtures and opens while the fixture is published | Web/app poll UI consumes the REST routes; native screen is scheduled app build-out |
| FO-318 Player of the month | Done | 0.1.0 | Last-7-days window, one changeable vote per member, per-month archive option, auto winner + push on daily tick; REST GET/POST `/potm-month` | Same UI note as FO-317 |
| FO-319 FanPress Bot | Done | 3.16.0.0 | `class-prx3-helpbot.php` — configurable help bot answering members from a searchable FAQ knowledge base (`prx3_faq`, token-overlap scoring with a confidence floor); when it cannot answer it opens a member-scoped support ticket (`prx3_ticket`) in one tap. Threaded replies with ownership enforcement, staff notification on new tickets, answered-on-staff-reply. WhatsApp-style widget matching FanPress Chat; name/avatar/colour/background/greeting/on-off under FanPress Technical Setup → FanPress Bot. Member-only floating launcher, `[prx3_helpbot]`/`[prx3_tickets]` shortcodes, REST `prx3/v1/help` (public config/ask) + `prx3/v1/tickets` (member list/create/reply) | Native app screen consumes the REST routes; scheduled in the app build-out |

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
- **Straight-to-production risk (T34)**: the automated money/vote-path suite (T35) now exists — `plugin/fan-ownership-platform/tests/` (`php tests/run-tests.php`, 172 assertions over the ladder, cap, register, ballot casting/weighting/secrecy, agreement signatures/versioning, and sync matching rules). Wire it into the reviewed-PR pipeline (T55) so it gates every deploy.

## Honest summary

Done 79 · Partial 6 · Operational 2 · Not built 0. The documentation
suite is complete (FO-314, 3.15.0.0), meeting video is fully
in-platform (FO-228: 8×8 JaaS with the meet.jit.si fallback, 3.14.0.0),
and CI runs the full suite, lint, JS checks, and WPCS on every pull
request. What remains is the Flutter app screen build-out (FO-302) and
the items waiting on external accounts (Firebase push, store delivery,
analytics feeds), consistent with the phasing decisions.
