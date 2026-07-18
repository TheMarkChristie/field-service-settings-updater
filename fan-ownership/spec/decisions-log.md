# Perth Panthers Fan Ownership — Specification Decisions Log

Reference model: Caledonian Braves (Boardroom voting app, Match Centre live
streams, Brave TV behind-the-scenes content, worldwide ownership community).

## Process decisions

| # | Question | Decision |
|---|----------|----------|
| P1 | Legal structure | Private limited company — fans buy real equity shares |
| P2 | What a member buys | One-off equity shares, owned outright |
| P3 | Share price | £50 per share |
| P4 | Eligibility | Anyone worldwide |
| P5 | Voting power | 1 share = 1 vote, capped at 10 shares per member |
| P6 | Fan-voted matters | Budget allocation; club identity (name, badge, motto); match kits; football decisions incl. recruitment strategy; commercial & matchday; club decisions incl. hire & fire |
| P7 | Reserved to board | Legal & regulatory matters only |
| P8 | Vote force | Binding within fan-decision scope; reserved matters advisory |
| P9 | Majority | Simple majority of votes cast |
| P10 | Quorum | 25% of members must vote for a ballot to be valid |
| P11 | Ballot window | 7 days default |
| P12 | Proxy voting | None — members vote personally |
| P13 | Failed quorum | Re-run once for 7 days with full comms push; if quorum fails again, board decides and publishes reasoning |
| P14 | Representation | Direct democracy only — no elected fan directors |
| P15 | Idea → ballot pipeline | Any member proposes; ideas reaching a support threshold (~5% of members) automatically become formal ballots; club moderates for legality only |
| P16 | Moderation | Club staff pre-moderate ideas/questions before they are visible to members |
| P17 | Q&A process | Monthly Q&A video answering most-supported questions + written answers for the rest |
| P18 | Meeting cadence | Quarterly member meetings + AGM |
| P19 | Meeting format | Fully online |
| P20 | Remote participation | Live moderated chat; most-upvoted questions answered live |
| P21 | Financial reporting | Monthly one-page income/spend summary + full annual accounts |
| P22 | Financial depth | Full category detail; individual salaries confidential (aggregate wage bill only) |
| P23 | Budget control | Annual budget ratification vote + allocation ballots for surplus/new money |
| P24 | Spend gate | Unbudgeted spend over £5,000 requires a fan ballot |
| P25 | Payments | Card online + Apple Pay / Google Pay |
| P26 | Instalments | None — each share purchase paid in full |
| P27 | Refunds | No refunds on share purchases |
| P28 | Share transfers | Surrender back to club only; no secondary market |
| P29 | Top-ups | Buy further shares any time up to the 10 cap |
| P30 | Death | Shares pass to next of kin / nominated beneficiary |
| P31 | Leaving | Surrender shares, no payout; account closed, data deleted per GDPR |
| P32 | Identity | One person one account via verified email + payment identity; duplicates flagged |
| P33 | Match streams | ALL home games streamed live worldwide (subject to league rules) |
| P34 | Away cover | Live audio commentary from every away game |
| P35 | BTS content | Weekly "Panthers TV" episode + extras; post-match interviews after every game |
| P36 | Paywall | Teasers/trailers public; full streams, episodes, meetings, financials owners-only |
| P37 | Community | Comments on club content AND a full member forum |
| P38 | Conduct | Published code of conduct; warn → mute → expel (expulsion = shares surrendered) |
| P39 | Comms channels | App push notifications, email, public social media (no SMS) |
| P40 | Onboarding | Guided first-week journey: welcome video, Boardroom tour, starter ballot, email series |
| P41 | Constitutional votes | 75% supermajority for identity matters (name, badge, colours, relocation) |
| P42 | Privacy owner | Named club officer handles GDPR (SARs, breaches, retention) |
| P43 | Languages | English only at launch, built translation-ready |
| P44 | Accessibility | WCAG 2.1 AA commitment |
| P45 | Hire & fire | Confidence/no-confidence votes trigger managed process; fans vote on vetted shortlists; employment-law execution stays with club |
| P46 | Tickets & pricing | Owners buy tickets at 5% discount per share (matchday) and 10% per share (season ticket → 10 shares = free season ticket). TIERED SHARE PRICING: share 1 = £50, each subsequent share +25% (share 10 ≈ £373; full 10-share holding ≈ £1,663) |
| P47 | Perks | Digital owner certificate, merch discount, priority access, Digital Founders badge (existing badge distribution plugin to be integrated) |
| P48 | Global fans | Recognised owner chapters per city/country with watch-party toolkit |
| P49 | Gifting | Gift shares at checkout; recipient activates own account |
| P50 | Perk fulfilment | Digital-only perks, delivered instantly on payment (lesson from Braves' fulfilment complaints) |
| P51 | New share rounds | Any new share issue must pass a member ballot with terms published first (anti-dilution lesson from Braves Wefunder backlash) |
| P52 | Season-one success | 1,000 owners; team into its own stadium; established identity |

### Earlier scoping answers (pre-questionnaire)

- Membership: self-registration; paid; up to 10 shares; shares confer votes only (no other perks per extra share)
- Ballots: secret until closed (admins can see running tally)
- Video delivery: both embeds (YouTube/Vimeo/live) and self-hosted files
- Branding: built for Perth Panthers, but club name configurable — the name itself may change by fan vote

## Technical decisions

| # | Question | Decision |
|---|----------|----------|
| T1 | Core platform | WordPress + custom fan-ownership plugin |
| T2 | Mobile | Native iOS + Android apps from day one (consume plugin REST API) |
| T3 | Hosting | Managed WordPress host (Kinsta/WP Engine class) |
| T4 | Architecture | One site: public teaser layer + gated owner areas |
| T5 | Login | Email + password, plus Sign in with Apple/Google |
| T6 | App auth | JWT/refresh tokens issued by plugin REST namespace |
| T7 | 2FA | Required for staff, optional for members |
| T8 | Payments | WooCommerce + Stripe (cards, Apple Pay, Google Pay); share tiers as products |
| T9 | Live production | OBS/Streamlabs encoder feed |
| T10 | VOD | Self-hosted MP4s |
| T11 | Away audio | Audio-only live stream through same pipeline |
| T12 | Stream protection | Domain-locked player behind member gate |
| T13 | Stream delivery server | **OPEN** — OBS ingest target undecided (recommendation: Cloudflare Stream Live; alternatives: self-hosted Owncast/nginx-rtmp, private SaaS) |
| T14 | Push | Firebase FCM direct (both platforms) |
| T15 | Email | Brevo (transactional + marketing) |
| T16 | Forum | bbPress inside WordPress, same login/gate |
| T17 | Vote storage | Full named records; secrecy enforced at display layer until close |
| T18 | Ballot lifecycle | Fully automated: open/close, quorum check, results publish, auto re-run, push+email at each stage |
| T19 | Idea threshold | 5% support auto-creates draft ballot; staff legality-check then schedule |
| T20 | Badges | **OPEN** — existing custom badge distribution plugin; need name/hooks for Founders badge integration |
| T21 | Meetings | StreamYard production → gated portal embed + portal chat with question upvoting |
| T22 | Calendar | Members-only ICS feed + add-to-calendar buttons |
| T23 | Documents | In-portal viewing ONLY — no downloads (financials leak resistance) |
| T24 | Certificate | Auto-PDF generated on purchase + automatic badge issue; re-issued on top-ups |
| T25 | Staff roles | Owner-Admin, Content Editor, Governance Officer, Moderator |
| T26 | Workflow | Draft → review → publish for all member-facing content, esp. ballots |
| T27 | Analytics | Privacy-first self-hosted (Matomo/Plausible) + Firebase app analytics |
| T28 | Club dashboard | Membership & revenue; ballot health vs quorum; content performance; community health |
| T29 | GDPR tooling | Self-serve data export; automated erasure flow; consent & preference centre; retention automation |
| T30 | Data residency | UK/EU only for core data; US services only where unavoidable (Apple/Google/Stripe) |
| T31 | Backups | Host daily backups (30-day) + automatic pre-ballot-close snapshots + monthly off-host copies |
| T32 | Security | Independent audit of plugin/API before launch + WP hardening (WAF, rate limits, bot protection) + dependency monitoring |
| T33 | Store commissions | Share purchases via web checkout; apps link out (no IAP, no 15–30% cut) |
| T34 | Deploys | Straight to production (RISK FLAG: revisit once binding ballots are live) |
| T35 | Testing | Automated tests on money/vote paths + founding-owner beta programme |
| T36 | Uptime | 99.9% target, independent monitoring, member status page, pre-kickoff health checks |
| T37 | App stack | Flutter (single codebase, both stores) |
| T38 | Offline | Online-only apps |
| T39 | Parity | Full member feature parity app/web; club admin web-only |
| T40 | Match Centre | Live stream + live chat + minute-by-minute score (no lineups/league table at launch) |
| T41 | Chat | Self-hosted websocket service (Node/Soketi class), portal identities, in-house moderation |
| T42 | MBM reporting | Volunteer owner match reporters using a big-button mobile console |
| T43 | Media storage | S3-compatible UK/EU object storage + signed member-gated CDN (e.g. Cloudflare R2) |
| T44 | Search | Scoped member search across all accessible content, access rules enforced |
| T45 | Phasing | Phase 1 own (join/shares/certificate/badge/content) → Phase 2 decide (Boardroom) → Phase 3 watch (apps + Match Centre) |
| T46 | Rebrand-proofing | Fully themeable: name, crest, colours, domain as configuration; identity ballot applied in hours |
| T47 | Gifting & chapters | Gift redemption codes via WooCommerce; chapter directory + map + chapter forum spaces |
| T48 | Timeline | Phase 1 live 8–12 weeks from spec sign-off, founding-owner beta before public launch |
| T49 | Builders | Built in-house with Claude Code in this repo; external security audit only |
| T50 | Budget | Bootstrap: < £200/month run-rate, no capital budget |

## Process decisions — round 2 (P53–P77)

| # | Question | Decision |
|---|----------|----------|
| P53 | Minimum age | 18+ only |
| P54 | KYC/AML | Stripe Radar checks only (max spend ~£1,663 caps risk) |
| P55 | Owner numbers | Sequential by join order |
| P56 | Founder status | Everyone who buys before public launch day |
| P57 | Ballot cadence | Maximum 2 live ballots at once + published schedule of upcoming votes |
| P58 | Ballot authorship | Governance Officer + published annual voting calendar (budget, kit, objectives) |
| P59 | Ties | Board casting vote |
| P60 | Vote changing | Allowed until close; final choice counts |
| P61 | Mid-ballot joiners | Eligibility snapshot at ballot open (shares/members as at open) |
| P62 | Campaigning | Open debate under code of conduct; club neutral unless board formally recommends |
| P63 | Results | Instant automated reveal (portal/push/email) + weekly video wrap |
| P64 | Accountability | Public decision register: every passed ballot tracked (owner, status, updates) |
| P65 | Meeting records | Recording in portal within 24h + written action minutes feeding decision register |
| P66 | AGM resolutions | Run in-platform as formal shareholder record (subject to articles permitting e-voting) |
| P67 | Crisis comms | Owners hear first, within 24h of club knowing; briefing stream within 7 days |
| P68 | Talent consent | Media clause in player/staff contracts + personal veto + manager sign-off on dressing-room footage |
| P69 | Sponsors | Club sponsors visible on website; advert slots in streams sold as revenue |
| P70 | Content sign-off | Editor publishes routine solo; manager gates sensitive footage; review flow reserved for ballots/financials |
| P71 | Chapters | Light charter: 5+ owners, naming convention, named lead, annual re-affirmation, de-recognisable |
| P72 | Volunteers | 3+ months good standing + supervised trial; rights revocable |
| P73 | Referrals | Recognition only (badges, leaderboard, shout-outs) — share price ladder untouched |
| P74 | Gamification | Meaningful milestone badges via badge plugin; no points/leaderboard noise |
| P75 | Meta-governance | Changing quorum/majorities/scope requires 75% constitutional ballot |
| P76 | Quorum denominator | Owners active in last 12 months; dormant owners keep shares, don't inflate quorum |
| P77 | Annual report | Yearly "State of the Panthers" owners' report in portal |

## Process decisions — round 3: board members (P78–P81)

| # | Question | Decision |
|---|----------|----------|
| P78 | Board ballot votes | Only via shares they personally own (same 10-share cap); the board role carries no extra ballot votes |
| P79 | Board platform access | Dedicated **Board Member** role: full owner access + running tallies on open ballots + decision-register editing + financial drafts before publish + moderation queue visibility. NOT content publishing or member/checkout admin |
| P80 | Board visibility | Board directory page (photo, bio, responsibilities); "Board" badge on forum/chat posts; casting votes and recommendations recorded in the decision register |
| P81 | Board formal actions | Structured flows: recorded casting-vote action on ties; formal recommendations attached to ballots; reserved-matter and failed-quorum decisions logged in the decision register with published reasoning |

## Process decisions — round 4: private board workspace (P82–P89)

| # | Question | Decision |
|---|----------|----------|
| P82 | Workspace contents | All four: board papers & agendas; internal board votes; private discussion threads; confidential document vault |
| P83 | Internal board votes | Open within the board (one vote per director, visible to fellow directors), chair carries casting vote, outcomes auto-minuted |
| P84 | Disclosure to owners | Board releases selectively — nothing publishes to the decision register until the board pushes it out |
| P85 | Conflicts of interest | Standing conflicts register per director (public on board directory) + declare-and-recuse per board vote; recused directors locked out of that item's papers and vote |
| P86 | Board meetings | Fully in-platform — board video calls run through the platform (small-group video conferencing, distinct from the StreamYard broadcast stack; implementation note: embed a WebRTC service, e.g. self-hosted Jitsi, fits bootstrap budget + UK/EU residency) |
| P87 | Advisor access | Board Observer role: invited per meeting/item, read-only, never votes, access auto-expires (company secretary, lawyer, auditor) |
| P88 | Vault security | View-only in-platform (no downloads), per-view watermark with director name + timestamp, full access log visible to the chair |
| P89 | Director departure | Access revoked instantly on role removal; votes/declarations/contributions preserved permanently; ordinary owner account and shares unaffected |

## Decisions — round 5: generic product, ticketing, brand pack (P90–P93)

| # | Question | Decision |
|---|----------|----------|
| P90 | Sport | The club is an **ice hockey** team, but the platform is **generic for any club**: a sport setting with presets (ice hockey, football, rugby, basketball, generic — filterable via `prx3_sports`) drives Match Centre event types, period labels, start-of-play language, and push notification titles |
| P91 | Ticketing provider | **Fanbase**. Per-owner discount codes reflect share entitlement (rotated when holdings change), shown in account + app, CSV export for upload to Fanbase, and a `prx3_ticketing_entitlement` hook ready for direct API sync |
| P92 | Brand pack | All brand assets are **uploadable via the Media Library**: badge, inverted badge, monochrome badges (dark/light), social media badge, SVG badge, wordmark, favicon, app icon, email header, and brand font file — plus primary/secondary/third colours with **print specs (CMYK/Pantone)**, primary + secondary font names, tagline/motto, legal/short names, abbreviation, founded year, social handles, brand contact, and usage notes. Drives site CSS variables + @font-face, favicon, email template, certificates, the app theme via `/me`, and a **printable supplier brand pack at /brand-pack/** (print-to-PDF: cover, logo suite on light/dark with downloads, colour table, type specimen, naming rules, usage, contact) |
| P93 | Prefix | Platform prefix is `prx3` (PROXIMO 3 standard), replacing the earlier `fop` |

## Decisions — round 6: engagement voting & legal documents (P94–P98)

| # | Topic | Decision |
|---|---|---|
| P94 | Player of the Match | **Live in-stream vote**: opens when the match goes live, stays open until 30 minutes after the final buzzer, live tallies shown. **One vote per member** (deliberately NOT share-weighted — engagement polls are a different instrument from governance ballots: no quorum, results visible live, no decision-register entry). Winner crowned automatically on the ballot tick, career POTM wins tracked on the player profile, push notification on the result |
| P95 | Player of the Month | Members vote in the **last 7 days of each month** across the active roster; winner archived per month and announced by push. Same one-member-one-vote rule as P94 |
| P96 | Player profiles | `prx3_player` CPT (squad number, position, active flag) under `/squad/` powers both polls and the Match Centre; roster is club-maintained, sport-agnostic |
| P97 | Shareholders' Agreement | A versioned **Shareholders' Agreement with morality/conduct clauses** is a condition of becoming a shareholder: ticked acceptance required at share checkout AND gift redemption; every acceptance recorded permanently (version, timestamp, IP, context, order). New versions banner existing owners for re-acceptance. Draft with clauses covering violence/discrimination, criminal offences, disrepute, democracy manipulation, access misuse, and brand misuse is in the operations handbook — **solicitor must settle it before checkout opens** |
| P98 | Executed copies | Every acceptance is **signed**: the member draws their signature (finger/stylus/mouse) at checkout, gift redemption, and re-acceptance — mandatory, validated PNG. A personalised **executed copy** at `/my-agreement/` shows the full agreement text plus an execution block: member signature, name, owner number, date, the **club stamp** (uploadable brand asset), and the configured **board signatory's countersignature** (name, role, signature image — Legal settings). Print-to-PDF for the member's personal copy. All owner signatures and the acceptance history live in a **board-only "Owner Signatures" register** inside the Board Workspace (personal data, board eyes only) |

## Decisions — round 7: Power Platform sync (P99–P102)

Defaults recorded on Mark's direction to sync "back and forth with API
and matching rules"; the selectable-answer round could not be delivered,
so the recommended options were applied — revisit any of these on request.

| # | Topic | Decision |
|---|---|---|
| P99 | Sync scope | **Members + shares + engagement**: contact identity, owner number, shareholding, agreement status (facts only — signatures never leave the platform), badges, and engagement counts; the share register streams as an append-only feed. Cases/meetings/commitments excluded for now |
| P100 | Conflict rule | **Field-level ownership**: the platform owns what it mints (shares, votes, acceptances, owner numbers — legally authoritative); Dataverse owns CRM enrichment (phone, address, marketing consents, notes). Inbound writes are allow-listed (`prx3_sync_inbound_fields`); nothing inbound can touch ownership data |
| P101 | Mechanism | **REST + signed webhooks + custom connector**: plugin REST API (`/sync/members` paged delta, `/sync/register` append-only feed, `/sync/upsert`, `/sync/review`) authenticated by `X-Prx3-Api-Key`; outbound events queued and delivered to a Power Automate HTTP trigger with HMAC-SHA256 `X-Prx3-Signature`, 8-try retry, capped outbox; hourly delta-pull flow as the safety net; Swagger 2.0 custom connector packaged for makers |
| P102 | Matching rules | **Cross-reference ID → verified email → owner number**, exact matches only. No fuzzy matching, no auto-create, no auto-merge: no-match, ambiguous, and link-conflict records queue for human review in Fan Ownership → CRM Sync, resolved by link-and-apply or discard, both audited. Dataverse upserts by `prx3_WPUserFK` alternate key so sync can never create duplicates |

## Technical decisions — round 2 (T51–T75)

| # | Question | Decision |
|---|----------|----------|
| T51 | Repo | Own dedicated repository (this repo holds spec only) |
| T52 | Domain | One club domain; portal under it; domain held as config |
| T53 | Theme | Existing theme retained — **project scope is plugin + apps only** |
| T54 | Plugin UI | Inherit theme typography/colours; ship only structural CSS |
| T55 | Code flow | Local dev + reviewed PRs; automated tests as the deploy gate |
| T56 | Errors | Sentry free tier (PHP plugin + Flutter apps), EU region |
| T57 | Monitoring | UptimeRobot-class checks (portal/checkout/API/streams) + hosted member status page |
| T58 | Invoicing | Full sequential PDF invoicing plugin on WooCommerce |
| T59 | Share register | Platform data is the statutory register of members + one-click export for filings |
| T60 | Certificate verify | QR + public verification page (name shown only with owner consent) |
| T61 | Badge plugin | Custom in-house plugin; integration contract: `award_badge` action + `get_member_badges` function |
| T62 | Stream delivery | **Cloudflare Stream Live** (closes T13): OBS→RTMP→signed playback; recordings to R2 as replays |
| T63 | Chat moderation | Word filter + auto-hold; slow mode + rate limits; member reporting; in-chat mod actions |
| T64 | Reporter console | Local retry queue — events post when signal returns (narrow exception to online-only) |
| T65 | Push prefs | Category toggles (ballots/match/content/meetings/news); quorum reminders default on |
| T66 | Deep links | Universal links: push + shared URLs open exact screen in app, else website |
| T67 | Min OS | iOS 15 / Android 8 |
| T68 | Releases | Club-owned Apple/Google accounts; monthly release train + hotfix path; phased rollouts |
| T69 | Player | Chromecast + AirPlay; quality selection; live DVR rewind; playback speed + captions |
| T70 | Replay SLA | Auto-publish within the hour of full-time (edited versions may follow) |
| T71 | A11y verification | Automated only: axe-core in CI + Flutter accessibility linting |
| T72 | Email design | One branded responsive master template, colours from theme config (rebrand-proof) |
| T73 | Data migration | No preference given — ASSUMPTION: clean slate; confirm mailing list / WP users / badge data at build kickoff |
| T74 | Kill switches | Admin on/off toggle per major feature (voting, chat, checkout, streams, forum) with member-facing notice |
| T75 | Documentation | Full suite: admin guide, matchday runbook, developer docs, API reference, volunteer handbooks |

## Open items

1. ~~T13 — Stream delivery server~~ **CLOSED (T62)**: Cloudflare Stream Live.
2. ~~T20 — Badge plugin~~ **CLOSED (T61)**: custom in-house plugin; integrate via defined contract (`award_badge` action + `get_member_badges` function). Hook names to be confirmed against the plugin's code at build kickoff.
3. **T73 — Migration inventory**: confirm at build kickoff what exists to migrate (mailing list → Brevo, current WP users, badge plugin data).

## Risk flags (recorded, not resolved)

- **Securities law**: selling equity shares online to a worldwide audience is a regulated activity in most jurisdictions (the Braves used Wefunder, a FINRA-member funding portal, for exactly this reason). Legal advice required before the share checkout goes live; the platform build should not assume direct card-purchase of equity is lawful everywhere.
- **No-refund policy (P27)**: UK/EU online consumer purchases normally carry a 14-day cooling-off right; whether equity is exempt needs the same legal review.
- **Hire & fire ballots (P45)**: confidence-vote design mitigates but does not remove employment-law exposure; process wording needs legal sign-off.
- **Streaming rights (P33)**: live match streaming is subject to league/association broadcast rules — confirm before promising "all home games live".
- **Straight-to-production deploys (T34)**: acceptable pre-launch; revisit once binding ballots and payments are live (a bad deploy mid-ballot is a governance incident).
- **Braves lessons baked in**: instant digital perk fulfilment (P50), fan-approved future share rounds with published terms (P51), published tiered price ladder (P46), gifting at checkout (P49), direct member votes rather than proxied voting rights.
