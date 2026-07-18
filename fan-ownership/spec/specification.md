# Perth Panthers Fan Ownership Platform — Process & Functional Specification

**Version:** 1.0 (draft for sign-off)
**Date:** 18 July 2026
**Status:** All 102 scoping decisions captured (52 process, 50 technical) — see `decisions-log.md` for the full question-by-question record.
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

- **Payment:** card, Apple Pay, Google Pay via WooCommerce + Stripe. Each purchase paid in full — no instalments. Top-ups towards the cap allowed any time.
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

- **Comments** on ballots, ideas, videos, and news, **plus a full member forum** (bbPress), all behind the owner gate with one identity.
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
| Commerce | WooCommerce + Stripe (cards, Apple Pay, Google Pay); share tiers as products; gift codes |
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

Firebase FCM push · Brevo email · bbPress forum · self-hosted websocket chat service (match + meeting chat, portal identities, in-house moderation) · members-only ICS calendar feed + add-to-calendar buttons · in-portal document viewer (no downloads) · auto-PDF certificate generation + automatic badge issue · scoped member search across all accessible content.

### 6.5 Club-side tooling (T25–T28, T42)

- **Four staff roles:** Owner-Admin, Content Editor, Governance Officer, Moderator.
- **Draft → review → publish** workflow on everything member-facing; a second person approves every ballot.
- **Engagement dashboard:** membership & revenue (tracking the 1,000-owner target), ballot health vs quorum, content performance, community health.
- Match-reporter console for volunteer minute-by-minute reporting.
- Analytics: privacy-first self-hosted (Matomo/Plausible) + Firebase app analytics.

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

Built **in-house with Claude Code** in this repository. Budget: **bootstrap, < £200/month run-rate** (hosting, Stripe fees, Brevo, storage/CDN, StreamYard, stream delivery, Apple/Google developer accounts). External spend only if the security audit is commissioned.

| Phase | Scope | Target |
|---|---|---|
| **1 — Own** | Public site + gated portal, registration, WooCommerce share checkout (tiers, gifting), certificate + Founders badge, onboarding journey, first content areas | Live 8–12 weeks from spec sign-off; founding-owner beta before public launch |
| **2 — Decide** | The Boardroom: ballots (weighted, secret, automated), ideas pipeline, questions + monthly Q&A, meetings + RSVP + StreamYard embeds, financial publishing, forum, chapters | Follows Phase 1 |
| **3 — Watch** | Flutter apps (both stores), Match Centre (live stream, chat, minute-by-minute), away audio, VOD library, push notifications | Follows Phase 2 |

Testing: automated coverage on money and vote paths + the founding-owner beta group (T35).

---

## 8. Open items

1. **Stream delivery server (T13):** decide the OBS ingest/delivery target before Phase 3 (recommendation: Cloudflare Stream Live).
2. **Badge plugin (T20):** name + hooks of the existing custom badge distribution plugin, needed to wire Founders-badge auto-issue into checkout.

## 9. Risk flags requiring action before launch

1. **Securities law (critical):** selling equity online worldwide is regulated in most jurisdictions — the Braves ran their raise through Wefunder (a FINRA-member portal) for this reason. Obtain legal advice before the share checkout goes live; Phase 1's checkout build should be adaptable if a regulated route is required.
2. **No-refund policy** vs UK/EU 14-day online cooling-off rights — same legal review.
3. **Hire & fire ballots** — process wording needs employment-law sign-off.
4. **Streaming rights** — confirm league/association rules before promising all home games live.
5. **Straight-to-production deploys** — acceptable now; introduce at least a staging check before binding ballots and live payments.

---

*Companion document: `decisions-log.md` — the complete Q&A record behind every decision above.*
