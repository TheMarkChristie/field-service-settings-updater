# Phase 1 — Own

A supporter anywhere in the world becomes a paying owner: registration,
tiered share purchase, certificate and badge, onboarding, and the first
exclusive content, on a platform with the right roles, gating, and safety
switches from day one.

Epics: A. Platform foundations · B. Join and buy · C. Recognition ·
D. Onboarding, comms and account · E. Content foundations

---

## Epic A — Platform foundations

### FO-101 Members-only areas
As a visitor, I want clearly signposted owner-only areas that I cannot open without an account, so that I understand what ownership unlocks and how to join.
Traceability: P36, T4. Estimate: Design 0.5 / Build 0.5 / Develop 1.5 / Test 1

Acceptance criteria:
1. A visitor opening any owner-only page or list is redirected to a join page explaining ownership, with a login option.
2. Owner-only material never appears in public search results, feeds, or excerpts.
3. Public teaser content (trailers, clips, news) remains fully visible without an account.
4. A logged-in owner reaches all owner-only areas without further prompts.
5. Expelled or closed accounts lose access immediately.

### FO-102 Roles and permissions
As the club, I want six defined roles — Owner, Owner-Admin, Content Editor, Governance Officer, Moderator, Board Member — so that every person has exactly the access their duties need.
Traceability: T25, P79. Estimate: Design 1 / Build 0.5 / Develop 2 / Test 1.5

Acceptance criteria:
1. Each role grants only the capabilities listed in the specification; a documented permissions matrix is the test oracle.
2. Staff and board accounts cannot be created or elevated except by an Owner-Admin.
3. Two-factor authentication is enforced for all staff and board roles and available to owners (T7).
4. Role changes take effect on the user's next request and are recorded in an audit log.
5. Removing a role revokes its access instantly without deleting the person's owner account.

### FO-103 Feature kill switches
As an administrator, I want an on/off switch for every major feature with a member-facing notice, so that a misbehaving feature can be disabled in seconds without a deployment.
Traceability: T74, T34. Estimate: Design 0.5 / Build 0.5 / Develop 1.5 / Test 1

Acceptance criteria:
1. Registration, checkout, voting, forum, chat, streams, and meetings each have an independent switch.
2. Switching a feature off replaces it with a "temporarily unavailable" notice within one minute, site-wide and in any connected client.
3. Switch changes are restricted to Owner-Admins and recorded with who, when, and an optional reason.
4. Switching a feature back on restores it without data loss.

### FO-104 Club identity as configuration
As the club, I want the club name, crest, colours, and domain held as configuration used everywhere, so that a successful identity ballot can be applied in hours rather than rebuilt.
Traceability: T46, P41, T52, T54. Estimate: Design 1 / Build 1 / Develop 2 / Test 1

Acceptance criteria:
1. Changing the configured name, crest, or colours updates the portal, emails, certificates, and all generated documents without code changes.
2. No member-facing surface contains a hard-coded club name or brand asset; an automated check enforces this.
3. Portal screens inherit the site theme's typography and colours (T54).
4. Historical documents (past certificates, invoices, minutes) retain the identity that was current when they were issued.

---

## Epic B — Join and buy

### FO-105 Register as a prospective owner
As a supporter anywhere in the world, I want to create an account with my email verified, so that I can buy shares and join the ownership community.
Traceability: P4, P53, P32, T5. Estimate: Design 1 / Build 0.5 / Develop 2 / Test 1.5

Acceptance criteria:
1. Registration requires name, email, password, confirmation of being 18 or over, and acceptance of terms; under-18 declarations are refused with an explanation.
2. The account activates only after the email address is verified.
3. Sign-in with Apple and Google is offered alongside email and password.
4. A registration attempt with an email already in use is declined with a route to sign in or recover the password.
5. Registration works correctly from any country, and all times shown are in the member's local time zone.

### FO-106 Buy shares on the published price ladder
As a registered supporter, I want to buy between 1 and 10 shares at the published tiered prices in a single paid-in-full checkout, so that I become an owner with voting power immediately.
Traceability: P2, P3, P5, P26, P46, T8. Estimate: Design 1.5 / Build 1 / Develop 3 / Test 2

Acceptance criteria:
1. The price ladder (share 1 at £50, each subsequent share 25% higher) is displayed before payment, and the charged amount always matches the published ladder for the buyer's current holding.
2. Card, Apple Pay, and Google Pay are accepted (P25); payment is taken in full at purchase.
3. No route exists to exceed 10 shares per member, including by combining purchases, gifts, and top-ups.
4. On successful payment the member's share count and voting power update immediately and a confirmation with receipt is sent.
5. A failed or abandoned payment leaves the member's holding unchanged.
6. The terms presented at checkout state the no-refund policy (P27) and that shares are non-transferable except back to the club (P28).

### FO-107 Top up my holding
As an owner, I want to buy further shares at any time up to the cap, so that I can deepen my stake as my commitment grows.
Traceability: P29, P46. Estimate: Design 0.5 / Build 0.5 / Develop 1 / Test 1

Acceptance criteria:
1. The price of each additional share continues from the owner's position on the ladder, not from the bottom.
2. An owner at 10 shares sees the cap explained instead of a purchase option.
3. Top-ups update voting power, ticket discount entitlement, and the certificate (FO-113) immediately.

### FO-108 Give shares as a gift
As a supporter, I want to buy shares as a gift that the recipient redeems into their own account, so that ownership can be given for birthdays and Christmas.
Traceability: P49, P32. Estimate: Design 1 / Build 0.5 / Develop 2.5 / Test 1.5

Acceptance criteria:
1. A gift purchase issues a redemption code to the giver by email, with a message option for the recipient.
2. The recipient redeems by creating or signing into their own account; shares, votes, and owner number register to the recipient, not the giver.
3. Redemption enforces the recipient's 10-share cap and 18+ requirement; a redemption that would breach either is declined with an explanation and no loss of the gift.
4. Unredeemed gifts remain valid indefinitely and are visible to the giver as unredeemed.

### FO-109 Invoices and receipts
As an owner, I want a numbered invoice for every purchase kept in my account, so that my records are complete.
Traceability: T58. Estimate: Design 0.5 / Build 1 / Develop 1 / Test 0.5

Acceptance criteria:
1. Every completed purchase generates a sequentially numbered invoice showing the club's details, the shares bought, and the tier prices paid.
2. Invoices are emailed and permanently available in the member's account.
3. Invoice numbering has no gaps or duplicates, verified under concurrent purchases.

### FO-110 One person, one account
As the club, I want duplicate accounts detected and flagged, so that no individual can exceed 10 shares or 10 votes through multiple identities.
Traceability: P32, P54. Estimate: Design 1 / Build 0.5 / Develop 2 / Test 1

Acceptance criteria:
1. Accounts sharing payment identity signals or near-identical registration details are flagged for admin review, not blocked automatically.
2. An admin can merge or close confirmed duplicates, with the outcome and reasoning recorded.
3. Closing a duplicate follows the surrender rules (P31): shares return to the club and personal data is handled per the erasure flow.

### FO-111 The statutory share register
As the club, I want the platform's member and share data to serve as the register of members with a one-click export, so that filings and audits need no separate record-keeping.
Traceability: T59, P30, P31. Estimate: Design 1 / Build 0.5 / Develop 2 / Test 1

Acceptance criteria:
1. The register records, for every current and former member: name, contact details, shares held, and the date and consideration of every acquisition and surrender.
2. An authorised administrator can export the full register in an accountant-usable format at any time.
3. Surrenders (leaving, expulsion, death transfers to next of kin) are recorded as register events with dates and reasons.
4. Register history is immutable: corrections are recorded as new entries, never edits.

---

## Epic C — Recognition

### FO-112 Owner numbers
As an owner, I want a permanent owner number allocated in join order, so that my place in the club's story is recognised.
Traceability: P55. Estimate: Design 0.5 / Build 0 / Develop 0.5 / Test 0.5

Acceptance criteria:
1. Numbers are allocated sequentially at first completed purchase, with no gaps, duplicates, or races under concurrent purchases.
2. An owner's number never changes and is never reused after surrender.
3. The number appears on the owner's profile, certificate, and invoices.

### FO-113 Owner certificate
As an owner, I want a personalised certificate generated instantly on purchase and verifiable by anyone, so that my ownership is tangible and provable.
Traceability: P47, P50, T24, T60. Estimate: Design 1 / Build 0.5 / Develop 2.5 / Test 1

Acceptance criteria:
1. Within one minute of a completed purchase, a certificate showing name, owner number, shares held, and date is available in the member's account and emailed.
2. Top-ups re-issue the certificate with the new holding; superseded versions remain viewable.
3. Each certificate carries a verification code and scannable link resolving to a public page confirming owner number, share count, and membership date; the owner's name appears only with their consent.
4. Verification of a revoked or surrendered holding states that clearly.

### FO-114 Founders and milestone badges
As an owner, I want the Founders badge and future milestone badges issued automatically to my account, so that my part in the story is recognised without waiting.
Traceability: P56, P47, P74, T61, T24. Estimate: Design 1 / Build 0.5 / Develop 1.5 / Test 1

Acceptance criteria:
1. Every owner whose first purchase completes before the configured public launch moment receives the Founders badge automatically; purchases after it do not.
2. Badge issuing and display integrate with the club's existing badge system through a defined contract; badge outcomes are visible on the owner's profile.
3. Milestone badge rules (for example: voted in 10 ballots, attended every quarterly meeting) are configurable without code changes, and award automatically when earned.
4. Badge issue failures are queued and retried, never silently lost.

---

## Epic D — Onboarding, comms and account

### FO-115 Guided first week
As a new owner, I want a guided first-week journey — welcome video, tour, a starter ballot, and a short email series — so that I engage from day one rather than drifting.
Traceability: P40. Estimate: Design 1.5 / Build 1 / Develop 2 / Test 1

Acceptance criteria:
1. Immediately after first purchase the owner sees a welcome experience including a video from the club and a tour of what ownership includes.
2. A standing starter ballot invites a first vote within the first session (its subject configured by the club; its result advisory).
3. A welcome email series spans the first week and stops early if the owner completes the actions it prompts.
4. The journey can be revisited or dismissed at any time and never blocks access to anything.

### FO-116 Email foundations
As the club, I want all email sent through one branded master template with per-category preferences, so that communication is consistent, deliverable, and respectful of choice.
Traceability: T15, T72, T65, P39. Estimate: Design 1 / Build 1 / Develop 1.5 / Test 1

Acceptance criteria:
1. Every platform email (receipts, governance notices, digests, onboarding) uses the single branded template, drawing identity from configuration (FO-104).
2. Members manage preferences by category — governance, match, content, meetings, news — and choices apply to email and push consistently.
3. Governance-critical notices (ballot open/close, AGM) are sent regardless of marketing preferences, and this is stated in the preference centre.
4. Every marketing-category email carries a working unsubscribe that updates preferences immediately.

### FO-117 My account, my data
As an owner, I want to manage my profile, export my data, and close my account, so that my privacy rights are honoured without needing to ask anyone.
Traceability: T29, P31, P42. Estimate: Design 1 / Build 0.5 / Develop 2.5 / Test 1.5

Acceptance criteria:
1. An owner can view and edit profile details and see their shares, votes cast (their own), badges, invoices, and consent history in one place.
2. A self-serve export delivers everything held about the member in a portable format within 24 hours.
3. Account closure explains the surrender consequences (P31), requires explicit confirmation, then revokes access immediately, returns shares to the club, and erases or anonymises personal data — while the share register and named ballot records retain their legal minimum.
4. Retention automation removes data past the configured policy without manual work (T29).

---

## Epic E — Content foundations

### FO-118 Exclusive content areas
As an owner, I want behind-the-scenes posts and early content in the owner area from launch day, so that ownership feels alive before the full video platform arrives.
Traceability: P35, P36, FO-101. Estimate: Design 0.5 / Build 1 / Develop 1 / Test 0.5

Acceptance criteria:
1. The club can publish owner-only posts with images and embedded video, organised by type.
2. Each owner-only item can carry a public teaser that is visible without an account and links to the join page.
3. Owner-only items are reachable within two clicks of the owner home page.

### FO-119 Editorial workflow
As the club, I want routine content published by the editor alone, ballots and financial posts to require a second approver, and sensitive footage gated by the manager, so that speed and safety each apply where they belong.
Traceability: T26, P70, P68. Estimate: Design 1 / Build 1 / Develop 2 / Test 1

Acceptance criteria:
1. Content Editors can draft, schedule, and publish routine content without further approval.
2. Ballots and financial posts cannot be published by their author alone; a second authorised person must approve, and the approval is recorded.
3. Items marked as sensitive footage cannot publish without manager sign-off, recorded with who and when.
4. Scheduled items publish at their scheduled time without manual intervention.

### FO-120 Club dashboard, first edition
As the club, I want a dashboard of membership and revenue against the season-one target, so that the team sees progress at a glance.
Traceability: T28, P52, T27. Estimate: Design 1 / Build 0.5 / Develop 2 / Test 1

Acceptance criteria:
1. The dashboard shows current owners, shares sold by tier, revenue, gifts outstanding, and surrenders, against the 1,000-owner target.
2. Figures reconcile exactly with the share register and payment records.
3. Analytics collection is privacy-first, first-party, and disclosed in the privacy notice (T27).
4. Dashboard access is restricted to staff and board roles.
