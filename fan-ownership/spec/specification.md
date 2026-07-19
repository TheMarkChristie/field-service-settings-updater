# Perth Panthers Fan Ownership Platform — Process & Functional Specification

**Version:** 1.1 (draft for sign-off)
**Date:** 18 July 2026
**Status:** All 139 scoping decisions captured (89 process, 75 technical) — see `decisions-log.md` for the full question-by-question record. §10 consolidates the round-2 decisions; board-member decisions (P78–P81) are in §6.5, and the private board workspace (P82–P89) in §6.5a.
**Scope note:** the club retains its existing WordPress theme — this project delivers **the plugin and the native apps only**; plugin UI inherits the theme's look (T53–T54).
**Reference model:** Caledonian Braves FC (Boardroom voting app, Match Centre, Brave TV, worldwide ownership community), including lessons from their Wefunder campaign's public Q&A.

---

## 1. Vision

Perth Panthers will be run by its fans. Anyone in the world can buy shares in the club, and every share is a vote. Owners watch every home game live, follow away games with live commentary, get inside the dressing room through weekly content, question the board, propose ideas, read the accounts, and make the decisions that shape the club — up to and including its own name.

**Season-one success (P52):** 1,000 owners, the team moved into its own stadium, and an established club identity.

The platform is one WordPress site with gated owner areas, plus native iOS and Android apps, built in-house.

---

## 2. The offer: shares and membership

### 2.1 Shares (P1–P5, P46)

- The club is a **private limited company**; owners buy **real equity shares**, one-off purchases owned outright.
- **Tiered price ladder**, published from day one: share 1 costs **£50**, and each subsequent share a member buys costs **25% more** than the last.

| Share # | Price | Cumulative |
|---|---|---|
| 1 | £50.00 | £50.00 |
| 2 | £62.50 | £112.50 |
| 3 | £78.13 | £190.63 |
| 4 | £97.66 | £288.28 |
| 5 | £122.07 | £410.35 |
| 6 | £152.59 | £562.94 |
| 7 | £190.73 | £753.67 |
| 8 | £238.42 | £992.09 |
| 9 | £298.02 | £1,290.11 |
| 10 | £372.53 | £1,662.64 |

- **Cap: 10 shares per member.** 1 share = 1 vote on every ballot; 10 shares = 10 votes. Extra shares carry votes and ticket discounts but no other privileges.
- **Eligibility:** anyone worldwide (P4). One person = one account, enforced by verified email + payment-identity matching, with duplicates flagged for admin review (P32).

### 2.2 Buying, gifting, leaving (P25–P31, P49)

- **Payment:** card, Apple Pay, Google Pay via the club's **Shopify store** (P105/P106): one variant per ladder tier, the platform routes each buyer to a cart containing exactly their next tiers, and independently re-verifies the paid amount against the ladder when the paid-order webhook arrives (mismatches held, never granted). Shares are granted only once the buyer holds a signed current Shareholders' Agreement — instantly for signed members, on signing for everyone else (sign-to-claim). Each purchase paid in full — no instalments. Top-ups towards the cap allowed any time.
- **Gifting:** buy-as-gift at checkout issues a redemption code by email; the recipient redeems at sign-up and shares register to them (cap enforced at redemption).
- **Refunds:** none (⚠ subject to legal review — see §12).
- **Transfers:** surrender back to the club only; no secondary market. On death, shares pass to next of kin / nominated beneficiary. Leavers surrender shares without payout; personal data erased per GDPR while the share register retains its legal minimum.
- **Future share releases (P51):** any new issue must first pass a member ballot with terms published — the anti-dilution lesson from the Braves' Wefunder backlash.

### 2.3 Perks (P46–P48, P50)

All perks are **digital and delivered instantly** on payment (the Braves' fulfilment delays were their single biggest owner complaint):

- Personalised **owner certificate** (auto-generated PDF: name, owner number, shares, date), stored in the account, emailed, re-issued on top-ups.
- **Digital Founders badge**, auto-issued via the club's existing badge plugin (integration details pending — open item).
- **Merch discount** in the club shop and **priority access** to cup tickets/events.
- **Ticket discounts:** 5% per share on matchday tickets; 10% per share on season tickets — **10 shares = a free season ticket**.
- **Owner chapters:** owners anywhere can register an official chapter (admin-approved), listed in a directory/map with their own forum space and a watch-party toolkit.

---

## 3. The Boardroom: governance and voting

### 3.1 What fans decide (P6–P8)

Fans vote on effectively everything: budget allocation, club identity (name, badge, motto), match kits, football strategy including recruitment, commercial and matchday matters, and club decisions including hire & fire. Only **legal & regulatory matters** are reserved to the board. Votes on fan-scope matters are **binding**; reserved matters are advisory.

- **Hire & fire (P45)** works through **confidence/no-confidence votes** (triggering a managed process) and votes on **board-vetted candidate shortlists**; employment-law execution stays with the club.
- **Budget (P23–P24):** fans ratify the season budget annually and vote on allocating surplus/new money. Any unbudgeted spend over **£5,000** goes to ballot.

### 3.2 Ballot rules (P9–P13, P41 + earlier scoping)

| Rule | Standard ballot | Constitutional ballot (name, badge, colours, relocation) |
|---|---|---|
| Majority | Simple majority of votes cast | **75% supermajority** |
| Quorum | 25% of members | 25% of members |
| Window | 7 days | 7 days (14 recommended at author's discretion) |
| Secrecy | Secret until closed; admins may view running tally | Same |
| Failed quorum | Re-run once for 7 days with full comms push; if failed again, board decides and publishes reasoning | Same |

- **No proxies** — every member votes personally. Weighting: one vote per share held at ballot open.
- **Ballot presentation (P117–P118):** every ballot takes a sequential number on first save and its address is that number (`/owners/ballot/17/` — the question never leaks into a shared URL; old links redirect). The question itself is full rich content (HTML, images, embedded video), rendered on the ballot page and inside the voting card; each option is an answer plus an optional longer description shown beneath it, and the app receives both (`options` + `option_descriptions`).
- **No elected fan directors** — direct democracy only (P14).

### 3.3 Ideas pipeline (P15–P16)

Any owner submits an idea → staff pre-moderate (legality, abuse, duplicates only) → published ideas gather member support → at **5% of members supporting**, a **draft ballot is auto-created** and staff legality-check and schedule it. Idea statuses: New → Under review → Planned / Delivered / Declined.

### 3.4 Questions to the board (P17)

Members submit questions year-round; staff moderate; members upvote. The most-supported questions are answered in a **monthly Q&A video**; the rest get written answers.

### 3.5 Meetings (P18–P20)

**Quarterly online member meetings + online AGM**, produced in StreamYard and streamed into the gated portal, with portal live chat and question upvoting; the most-upvoted questions are answered live. Members RSVP in the portal; meetings appear in the members' calendar feed.

---

## 4. Content: Match Centre and Panthers TV

### 4.1 Commitments (P33–P36)

- **Every home game streamed live worldwide** (⚠ subject to league broadcast rules).
- **Every away game: live audio commentary.**
- **Weekly Panthers TV episode** (training, features) plus **post-match interviews with players and management after every game**.
- **Paywall line:** teasers/trailers public everywhere; full streams, replays, episodes, meetings, and financials owners-only.

### 4.2 Match Centre features (T40–T42)

Live stream player + **live match chat** + **minute-by-minute updates** (goal/card/sub/HT/FT) entered by **volunteer owner match reporters** through a big-button mobile console, which also fires push notifications.

### 4.3 Financial transparency (P21–P22, T23)

Monthly one-page income/spend summary published as portal content + full annual accounts. Full category detail; individual salaries shown only as an aggregate wage bill. **In-portal viewing only — no downloads.**

---

## 5. Community (P37–P40)

- **Comments** on ballots, ideas, videos, and news, **plus FanPress Chat — the platform's own native forum and social layer** (P109–P113): WhatsApp-style chat list with category chips and unread badges, bubble conversations (yours right, others left, board members in a configurable board colour with a Board tag), automated match/ballot chats embedded beside the event on its own page, activity feed with cheers, follows, searchable member directory, private messages, notifications, and @mentions with autosuggest — all behind the owner gate with one identity. Bubble colours (mine / other owners / board) are configurable under Settings → FanPress Chat. FanPress also carries **chat emails** (opt-out per member), **reply-quoting**, **staff-pinned messages**, and **per-chat mute** (P121).
- **Owner profiles (P121–P122):** every owner has a profile — bio, socials, badges, owner-since, a private-by-default share count, and an **activity percentage** (average of ballots voted ÷ held, FanPress posts in 90 days, and matches watched/listened ÷ held). Behind it sits the **private identity record**: full birth name, nationality, residence, date of birth, government registration ID, and a politically-exposed-person declaration — only birth name and nationality are public; Owner-Admins reach the rest through an audited, masked compliance lookup. Owners add a profile picture and up to five photos of themselves with explicit consent for the club's social channels; identity and photos ride the GDPR export/eraser.
- **Code of conduct** with graduated sanctions: warn → mute (temporary loss of comment/submission rights) → expel (shares surrendered). Voting rights removed only at expulsion.
- **Comms channels:** app push (Firebase), email (Brevo — weekly digest, ballot notices, receipts), public social media as the teaser layer. No SMS.
- **Onboarding (P40):** guided first week — welcome video from the manager, Boardroom tour, a live "starter ballot" to cast a first vote, and a welcome email series.

---

## 6. Technical architecture

### 6.1 Core stack (T1–T8)

| Layer | Decision |
|---|---|
| Platform | WordPress + custom fan-ownership plugin; one site with public teaser layer and gated owner areas |
| Hosting | Managed WordPress host (Kinsta/WP Engine class), UK/EU region |
| Commerce | **Shopify** (P105/P106): tier-per-variant products, HMAC webhooks (orders/paid, refunds/create), ladder re-verification, sign-to-claim grants, gift line properties → gift codes, refund clawback. WordPress remains the system of record for ownership |
| Apps | **Flutter**, iOS + Android from day one, online-only, full member feature parity; admin stays in WP admin |
| App API | Custom REST namespace on the plugin; JWT + refresh tokens in secure storage |
| Login | Email/password + Sign in with Apple/Google; 2FA required for staff, optional for members |
| Store rules | Share purchases via web checkout; apps link out (no in-app purchase, no store commission) |

### 6.2 Streaming & media (T9–T13, T43)

- Production: **OBS/Streamlabs** encoder at the ground; audio-only events for away commentary; StreamYard for meetings.
- Delivery: **OPEN ITEM** — OBS ingest/delivery target undecided. Recommendation: Cloudflare Stream Live; alternatives: self-hosted Owncast/nginx-rtmp or private streaming SaaS.
- Protection: domain-locked players behind the member gate (accepting screen-record risk).
- VOD: self-hosted MP4s in S3-compatible UK/EU object storage (e.g. Cloudflare R2) with signed, member-gated CDN URLs.

### 6.3 Voting engine (T17–T19)

- Votes stored as **full named records** (member, choice, weight, timestamp); secrecy is enforced at the display layer until close. Admins can view the running tally.
- **Fully automated lifecycle:** scheduled open/close, quorum check against the 25% threshold, automatic results publication, automatic single re-run on failed quorum, push + email at every stage.
- Idea support threshold (5%) auto-creates a draft ballot for staff approval.
- Pre-ballot-close database snapshots (T31).

### 6.4 Supporting services (T14–T16, T21–T24, T41, T44)

Firebase FCM push · Brevo email · FanPress Chat, the native forum + social layer (P109–P113) · self-hosted websocket chat service (match + meeting chat, portal identities, in-house moderation) · members-only ICS calendar feed + add-to-calendar buttons · in-portal document viewer (no downloads) · auto-PDF certificate generation + automatic badge issue · scoped member search across all accessible content.

### 6.5 Club-side tooling (T25–T28, T42, P78–P81)

- **Five platform roles:** Owner-Admin, Content Editor, Governance Officer, Moderator, and **Board Member**.
- **Board Member role (P78–P81):** full owner access plus running tallies on open ballots, decision-register editing, financial drafts before publish, and moderation-queue visibility — but no content publishing and no member/checkout admin. Board members vote in fan ballots **only via shares they personally own** (same 10-share cap); the role adds no ballot votes. A board directory page (photo, bio, responsibilities) and a "Board" badge and a distinct configurable bubble colour on their FanPress Chat and live-chat posts make it clear when the club is speaking. Formal board acts are structured platform flows with a permanent trail: recorded casting-vote actions on ties (P59), formal recommendations attached to ballots (P62), and reserved-matter or failed-quorum decisions (P7, P13) logged in the decision register with published reasoning.
- **Draft → review → publish** workflow on everything member-facing; a second person approves every ballot.
- **Engagement dashboard:** membership & revenue (tracking the 1,000-owner target), ballot health vs quorum, content performance, community health.
- Match-reporter console for volunteer minute-by-minute reporting.
- Analytics: privacy-first self-hosted (Matomo/Plausible) + Firebase app analytics.

### 6.5a The private board workspace (P82–P89)

A board-only area inside the platform — the full boardroom moves in:

- **Contents:** agenda packs and board papers per meeting, formal internal board votes, private discussion threads, and a confidential document vault (contracts, legal advice, personnel records).
- **Internal votes (P83):** one vote per director, open within the board (directors see each other's votes, as company-law accountability expects), chair holds a casting vote; outcomes are auto-minuted.
- **Disclosure (P84):** the board releases business to the owners' decision register selectively — nothing publishes automatically.
- **Conflicts (P85):** a standing conflicts register per director, public on the board directory, plus a declare-and-recuse step on every board vote; a recused director loses access to that item's papers and ballot.
- **Meetings (P86, P123, P124):** board meetings run fully in-platform. Built: every meeting page (member, AGM, board — never matches) carries its own video room, open one hour before the start to six hours after, joins audited. Rooms run on **8×8 JaaS** with platform-signed RS256 tokens — board/governance/admin moderate, owners join as guests, nobody needs an 8×8 account; configured under the top-level **FanPress Technical Setup** menu (App ID, API key ID, private key, each field annotated with where to get it), with an open meet.jit.si fallback when unconfigured.
- **Advisors (P87):** a **Board Observer** role for the company secretary, lawyer, or auditor — invited per meeting or item, read-only, never voting, access auto-expiring.
- **Vault security (P88):** documents render in-platform only (no downloads, same rule as member financials), every view watermarked with the director's name and timestamp, and a full access log visible to the chair.
- **Departure (P89):** access revoked the instant the role is removed; the director's votes, declarations, and contributions remain permanently on record; their ordinary owner account and shares are untouched.

Delivery: the board workspace lands as a **Phase 2 extension** (with the Boardroom governance build), with the in-platform video element allowed to slip to Phase 3 alongside the other real-time work if needed.

### 6.6 Non-functional requirements (P43–P44, T29–T36, T46)

- **Accessibility: WCAG 2.1 AA** across portal and apps, including accessible voting and captions on produced video.
- **Languages:** English only at launch, built translation-ready.
- **Rebrand-proof:** club name, crest, colours, domain all configuration — a passed 75% identity ballot can be applied across site, emails, certificates, and app theming in hours.
- **GDPR:** UK/EU data residency; self-serve export; automated erasure honouring the share register's legal minimum; consent & preference centre synced to Brevo/FCM; retention automation. Named club privacy officer (P42).
- **Security:** independent audit of plugin + API before launch; WAF, rate limiting, bot protection on registration/voting; dependency monitoring.
- **Resilience:** daily backups (30-day retention) + pre-ballot snapshots + monthly off-host copies; 99.9% uptime target with independent monitoring, a member status page, and pre-kickoff health checks.
- **Deploys:** straight to production per T34 (⚠ risk-flagged; revisit before binding ballots go live).

---

## 7. Delivery plan (T45, T48–T50)

Built **in-house with Claude Code** in this repository. Budget: **bootstrap, < £200/month run-rate** (hosting, Shopify plan + fees, Brevo, storage/CDN, StreamYard, stream delivery, Apple/Google developer accounts). External spend only if the security audit is commissioned.

| Phase | Scope | Target |
|---|---|---|
| **1 — Own** | Public site + gated portal, registration, Shopify share checkout (tier variants, gifting, sign-to-claim), certificate + Founders badge, onboarding journey, first content areas | Live 8–12 weeks from spec sign-off; founding-owner beta before public launch |
| **2 — Decide** | The Boardroom: ballots (weighted, secret, automated), ideas pipeline, questions + monthly Q&A, meetings + RSVP + StreamYard embeds, financial publishing, FanPress Chat, chapters | Follows Phase 1 |
| **3 — Watch** | Flutter apps (both stores), Match Centre (live stream, chat, minute-by-minute), away audio, VOD library, push notifications | Follows Phase 2 |

Testing: automated coverage on money and vote paths + the founding-owner beta group (T35).

---

## 8. Open items

1. ~~Stream delivery (T13)~~ **CLOSED:** Cloudflare Stream Live — OBS → RTMP ingest → signed playback in the gated player; recordings auto-publish to the replay library within the hour of full-time.
2. ~~Badge plugin (T20)~~ **CLOSED:** custom in-house plugin, integrated via a defined contract (`award_badge` action + `get_member_badges` function); confirm hook names at build kickoff.
3. **Migration inventory (T73):** confirm at build kickoff what carries over — supporter mailing list (→ Brevo, with consent review), existing WP users, badge plugin data. Working assumption: clean slate.

## 9. Risk flags requiring action before launch

1. **Securities law (critical):** selling equity online worldwide is regulated in most jurisdictions — the Braves ran their raise through Wefunder (a FINRA-member portal) for this reason. Obtain legal advice before the share checkout goes live; Phase 1's checkout build should be adaptable if a regulated route is required.
2. **No-refund policy** vs UK/EU 14-day online cooling-off rights — same legal review.
3. **Hire & fire ballots** — process wording needs employment-law sign-off.
4. **Streaming rights** — confirm league/association rules before promising all home games live.
5. **Straight-to-production deploys** — acceptable now; introduce at least a staging check before binding ballots and live payments.

---

## 10. Round-2 decisions (P53–P77, T51–T75) — consolidated

### 10.1 Membership refinements

- **18+ only** to buy shares (P53); Shopify's fraud analysis is the only identity/AML layer (P54), with the platform's ladder re-verification as the second control.
- **Owner numbers sequential by join order** (P55); **Founder = bought before public launch day** (P56).
- Referrals earn **recognition only** — badges, leaderboard, weekly-show shout-outs; the price ladder is never discounted (P73). **Milestone badges** for real participation (voted in 10 ballots, attended every quarterly, idea reached ballot) via the badge plugin (P74).

### 10.2 Ballot mechanics refinements

- **Max 2 ballots live at once**, with a published schedule of upcoming votes (P57); Governance Officer authors, guided by a **published annual voting calendar** — budget ratification, kit vote, season objectives (P58).
- **Ties: board casting vote** (P59). **Votes changeable until close** (P60). **Eligibility snapshot at ballot open** — mid-ballot share purchases vote from the next ballot (P61).
- **Open campaigning** under the code of conduct; club neutral unless the board formally recommends (P62).
- **Results:** instant automated reveal + weekly video wrap (P63). Every passed ballot enters a **public decision register** (decision, owner, status: planned/in progress/done/blocked) — the accountability backbone (P64).
- **Meta-governance:** changing quorum, majorities, windows, or fan-decision scope itself requires a 75% constitutional ballot (P75). **Quorum denominator = owners active in the last 12 months**; dormant owners keep shares but don't inflate the bar (P76).

### 10.3 Meetings, crisis, and content operations

- Meetings: **recording in portal within 24h + action minutes** feeding the decision register (P65). **AGM statutory resolutions run in-platform** as the formal shareholder record, subject to the articles permitting electronic voting (P66).
- **Crisis protocol:** owners hear first, within 24h of the club knowing, via push+email, with an owners' briefing stream inside 7 days (P67).
- **Talent on camera:** media clause in player/staff contracts + personal veto + manager sign-off on dressing-room footage (P68). Editor publishes routine content solo; manager gates sensitive material; the two-person review flow is reserved for ballots and financial posts (P70).
- **Commercial:** club sponsors visible on the website, and **advert slots in match streams are sold** as a revenue line (P69).
- **Chapters:** light charter — 5+ owners, "Panthers — [City]" naming, named lead, annual re-affirmation, de-recognisable for breaches (P71). **Volunteers** (reporters/mods): 3+ months in good standing + supervised trial, rights revocable (P72).
- **Annual "State of the Panthers" owners' report**: ballots and outcomes, decision register, finances vs budget, growth vs target, content stats (P77).

### 10.4 Technical refinements

- **Repo & scope:** platform gets its **own dedicated repository**; this repo keeps the spec only (T51). One club domain (T52). Existing theme retained; **plugin UI inherits the theme** and ships only structural CSS (T53–T54).
- **Engineering:** local dev + reviewed PRs with automated tests as the deploy gate (T55); Sentry (EU) error tracking across plugin + apps (T56); UptimeRobot-class monitoring + hosted member status page (T57); **admin kill-switch per major feature** — voting, chat, checkout, streams, forum (T74); **four-part build numbers** from 4.0.0.0 — Major.Minor.Release.Fix, each release's number agreed with the club before shipping (P119); **full documentation suite** — admin guide, matchday runbook, developer docs, API reference, volunteer handbooks (T75).
- **Commerce & compliance:** full sequential PDF invoicing (T58); the platform's member/share data **is the statutory register of members** with one-click export for filings (T59); certificates carry a **QR to a public verification page** — name shown only with the owner's consent (T60).
- **Streaming:** **Cloudflare Stream Live** confirmed (T62); replays auto-publish within the hour (T70); player supports Chromecast/AirPlay, quality selection, live DVR rewind, speed + captions (T69).
- **Matchday tooling:** chat moderation = word filter with auto-hold, slow mode/rate limits, member reporting, in-chat mod actions (T63); reporter console gets a **local retry queue** so a goal logged in a dead spot posts when signal returns (T64).
- **Apps:** category-level push preferences with quorum reminders default-on (T65); universal links open the exact screen in app or web (T66); iOS 15 / Android 8 floor (T67); club-owned store accounts, monthly release train, phased rollouts (T68).
- **Quality:** accessibility verified by automated checks (axe-core in CI + Flutter linting) (T71); one branded master email template driven by theme config (T72).

---

*Companion document: `decisions-log.md` — the complete Q&A record behind every decision above.*
