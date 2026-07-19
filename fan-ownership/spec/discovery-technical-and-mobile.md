# Discovery — Technical/Process & Mobile App

Answers captured with Mark, 2026-07. 50 technical/process + 100 mobile-app
decisions. This is the requirements baseline for the mobile app build and
the operational setup; it feeds an Azure DevOps **Mobile App** epic.

Guiding principle stated repeatedly by Mark: **the app must replicate the
web member experience — full feature parity.**

---

## Part 1 — Technical & Process (50)

### Environments, deployment, release
- **Hosting/staging:** production-only today; **stand up staging soon** so the pipeline + UAT work.
- **Release flow:** dev → staging → prod, with a smoke-test checklist.
- **CI:** GitHub Actions builds/tests and **auto-deploys to staging**; production is a manual promote.
- **Rollback:** keep the previous plugin ZIP to re-install (instant revert; data untouched).
- **Plugin updates:** Mark chose **full auto-update** — ⚠ reconcile with "board approval for governance changes"; recommend **auto-update fix builds only**, hold feature/governance releases.
- **Release sign-off:** Mark alone (fixes on green tests; **UAT for big releases**).
- **Backlog source of truth:** **Azure DevOps** (repo spec mirrors).
- **Cadence:** continuous / no fixed sprints.
- **Build numbering:** keep four-part Major.Minor.Release.Fix.

### Security & data
- **2FA:** mandatory for staff + board.
- **Secrets:** stored via the FanPress Technical Setup screens (WP DB).
- **Owner sign-in:** email + Apple + Google + **Microsoft**.
- **Sessions:** long-lived for members; shorter for staff.
- **Data residency:** UK/EU — **hard requirement** (equity + KYC/PEP).
- **Backups:** host-managed, daily.
- **Audit log:** keep indefinitely.
- **KYC retention:** keep while legally required, then erase.
- **KYC/AML:** manual / self-declared for now (no verification provider yet).
- **Data Controller/DPO:** the club entity (named).
- **WAF/Cloudflare front:** not wanted for now.
- **Analytics:** privacy-first / cookieless.

### Testing & quality
- **Accessibility:** build to WCAG 2.1 AA; no paid external audit.
- **UAT:** manual sign-off for big releases only.
- **E2E browser tests (Playwright):** **yes — add** (join/buy/vote/chat).
- **Load/perf test:** skip for now (peak ~200–1,000 concurrent).
- **Error/crash tracking:** yes — **Sentry** for plugin + app.

### Ops & governance
- **Uptime monitoring:** not yet; **public status page:** yes.
- **Day-to-day ops:** volunteers being recruited.
- **Incident runbook + member comms plan:** full runbook wanted.
- **Support:** matchday priority, best-effort otherwise.
- **Governance changes (ballots/shares/register):** **formal board approval**.

### Integrations (readiness)
| Integration | Status |
|---|---|
| Shopify | Store exists, **not configured** (webhooks/variant IDs to do) |
| Cloudflare Stream | **Not provisioned** |
| 8×8 JaaS | Club will create the account |
| Firebase (push) | **Does not exist — create** |
| Power Platform / Dataverse | Environment **exists** |
| Hockey Club plugin | Inspect the site DB at config time for the real players/matches post types (selector reads registered types live) |
| Email | **Site mail only** — ⚠ deliverability risk; recommend Brevo + verified domain (SPF/DKIM/DMARC) |
| External CRM | None — platform is the system of record |

### Legal & launch
- **Securities law:** reviewed & **cleared**.
- **Shareholders' Agreement text:** **not started** — legal drafting needed (plugin machinery is ready).
- **Streaming rights:** cleared.
- **Companies House filings (SH01 etc.):** owner undecided — **needs guidance**.
- **Domain:** perthpanthersihc.com is permanent.
- **Roles:** some assigned, gaps remain.
- **Budget:** keep to free/low-cost tiers; upgrade only when needed.
- **Deadline:** none fixed.
- **Stated next priority:** **legal & content** (SHA text, privacy policy, Companies House process).

---

## Part 2 — Mobile App (100)

### Scope & platform
- **White-label from the start** — any club themes itself; **per-club branded apps from one codebase** (own store listing, icon, name); member lands on their club **automatically from login**.
- **Flutter**, **iOS + Android together**, **phone + tablet**, **min iOS 15 / Android 8**.
- **Dev accounts:** Apple + Google both **in hand**.
- **First release scope:** **full parity before launch**; **beta open to everyone**; **full store release**; **timeline: ASAP**. ⚠ full parity is a large build — expect a longer first release; recommend an internal MVP milestone even if public launch waits for parity.
- **Design:** I design UI/flows from the brand pack; club reviews. **Theme dynamically from the brand pack** (colours/badge/fonts via API). **Dark mode** (follow system). **WCAG 2.1 AA**. English now, **i18n-ready**.

### Auth & onboarding
- Sign-in: **all four** (email, Apple, Google, Microsoft).
- **Biometric** quick-unlock (optional). **Magic-link** recovery.
- **Registration identical in app and web** (full parity incl. identity/KYC).
- Pre-auth: limited preview + "become an owner" path, then gate.
- Onboarding: brief tour + guided first week. **Single account** (one club per user for now).

### Money (store-compliant)
- **Share purchase: web-only via Shopify**, app links out. Same for **gifting**.
- **Shop/merch:** browse in-app, checkout on web. **Ticketing:** show + discount codes, link out.

### Feature parity (all in-app)
- **Vote:** full voting (cast/change/results/receipt), rich ballot content, open + closing push; live turnout **staff/board only**.
- **Chat (FanPress):** full parity, **real-time websocket**, push for mentions/DMs/replies, **image** sharing.
- **Watch:** full native Cloudflare player; **away audio with background playback**; live minute-by-minute + **goal push**; live match chat beside the stream; **POTM/POTM-month** voting; **VOD library + resume**; **offline replay downloads at launch** (⚠ DRM/licensing); **Chromecast/AirPlay + PiP**; adaptive + manual quality.
- **Reporter console:** **big-button mobile-first** tool for volunteers.
- **Meetings:** join **8×8** video in-app; RSVP + device calendar; **full board workspace** on mobile; Live Q&A submit + upvote.
- **Profile:** full view/edit; camera+gallery profile photo; 5-photo social gallery with consent; full owner stats; **member directory (no follow)**; certificate view + **share**.
- **Community/content:** Ideas & Questions (full), **financial transparency (view-only)**, decision register, document vault (gated view), chapters list + map, **club comms inbox + push**, badges **showcase only**, **referral flow** (invite link + leaderboard).
- **Light admin:** moderators handle held replies/reports/moderation in-app.
- **Match Centre:** **deep integration** — pull fixtures/scores/line-ups from the Hockey Club plugin.

### Notifications
- Categories default-on: **ballots, matches, meetings + club comms** (chat push = mentions/DMs/replies, respecting mute).
- **Rich + deep-link** to the exact screen; **app-icon badge counts**; quiet hours offered.

### Platform features
- **Home-screen widgets**, **iOS Live Activities / Dynamic Island** (live scores), **wearable app (Watch/Wear OS) at launch**.
- Share-out: result, fixture, news, shop items, **refer a friend**.

### Offline & performance
- Offline: **certificate, share holding, club config/brand**, + read-cache of recent content; actions queue and sync.
- Cold start **< ~3s**; graceful degrade + retry on poor signal; adaptive + manual video quality.

### Security & telemetry (app)
- Tokens in **secure keychain/keystore**; **certificate pinning**; **Sentry** crash reporting; **no ATT prompt** (cookieless analytics).

### Lifecycle & delivery
- **Force-update:** soft prompt + hard gate below a min version.
- **Remote feature flags: no** (features fixed per release).
- **Universal/app links** open the exact screen.
- **Versioned, backward-compatible API** (app updates independent of plugin).
- **Automated app CI/CD** (Fastlane/Codemagic → TestFlight/Play).
- **In-app help + feedback** channel.
- **Store:** privacy label I draft / club approves; **age rating 17+**; listings I draft / club approves; **device QA matrix** incl. VoiceOver/TalkBack.

---

## Cross-cutting flags to resolve
1. **"Full parity before launch" + "ASAP"** — these pull against each other; agree an MVP cut-line for an internal/beta milestone.
2. **"Full auto-update" vs "board approval for governance changes"** — recommend auto-update fix builds only.
3. **Legal/content is the stated next priority** but the **app timeline is ASAP** — run legal + integrations in parallel with app kickoff.
4. **Integrations mostly unprovisioned** (Shopify config, Cloudflare, Firebase, 8×8, email domain) — these gate several app features; sequence early.
5. **Ambitious v1 surface** (wearables, Live Activities, widgets, offline downloads, full board workspace) — likely phase some past first release.
