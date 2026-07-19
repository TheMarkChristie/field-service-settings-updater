# Phase 2 — Decide

The Boardroom: owners vote on the club's future, propose ideas, question the
board, meet online, read the accounts, and hold the club to its decisions —
plus the full board layer, from the Board Member role to the private board
workspace.

Epics: F. Ballots and voting · G. Ideas and questions · H. Meetings ·
I. Transparency and accountability · J. Community · K. The board

---

## Epic F — Ballots and voting

### FO-201 Author and schedule ballots
As a Governance Officer, I want to author ballots with options, type, and voting window, within a published schedule that allows at most two live at once, so that voting stays an event rather than a chore.
Traceability: P57, P58, P11, T26. Estimate: Design 1.5 / Build 1 / Develop 2.5 / Test 1.5

Acceptance criteria:
1. A ballot has a title, description, two or more options, a type (standard or constitutional), and an open and close time; the default window is 7 days.
2. No more than two ballots can be open simultaneously; further ballots queue, and the queue with expected dates is visible to owners as the voting schedule.
3. Publishing a ballot requires a second approver (FO-119); the annual voting calendar (budget, kit, objectives) is visible to owners all year.
4. Once any vote has been cast, a ballot's options and close rules cannot be edited; corrections require withdrawing the ballot, which is recorded and announced.

Rich questions and described answers (P118, added at V3.9): the
question is the post content — full editor, HTML, images, video —
shown on the ballot page and in the voting card; each option is an
answer plus an optional longer description underneath, exposed to the
app as option_descriptions.

Numbered addresses (P117, added at V3.8): every ballot takes the next
sequential number on first save (Ballot #N, shown on the card and the
admin list), and its URL is /owners/ballot/N/ — the title never
appears in the address, so a link can be shared without leaking the
question. Existing ballots are renumbered oldest-first on upgrade,
with old title URLs redirecting.

### FO-202 Cast my votes
As an owner, I want to cast my ballot with the voting power of my shares and change it any time before close, so that my stake counts and I can respond to debate.
Traceability: P5, P60, P12. Estimate: Design 1 / Build 0.5 / Develop 2.5 / Test 2

Acceptance criteria:
1. A ballot presents its options with the owner's voting power stated; submitting records all the owner's votes on the chosen option.
2. The owner can change their choice any number of times before close; only the final choice counts, exactly once.
3. An owner cannot vote twice, vote after close, or vote on behalf of anyone else (P12).
4. Voting is fully operable by keyboard and assistive technology, and confirmation of the recorded vote is announced to the voter.
5. Vote recording is exact under concurrent load: the tally always equals the sum of recorded final choices.

### FO-203 Secret until closed
As an owner, I want tallies hidden from members until the ballot closes while club administrators can monitor the running tally, so that voting is free of bandwagon pressure.
Traceability: Scoping (secret ballots), T17, P80. Estimate: Design 0.5 / Build 0.5 / Develop 1.5 / Test 1

Acceptance criteria:
1. Before close, no member-facing surface reveals tallies, percentages, or momentum; members see participation confirmation only.
2. Governance Officers, Owner-Admins, and Board Members can view the running tally, clearly marked as private.
3. Individual choices are stored as named records but exposed to no member-facing surface at any time.
4. The moment a ballot closes, results become visible to all owners without manual action (FO-206).

### FO-204 Eligibility snapshot
As the club, I want each ballot's electorate fixed at the moment it opens, so that a contested vote cannot be swung by buying shares mid-ballot.
Traceability: P61. Estimate: Design 0.5 / Build 0 / Develop 1.5 / Test 1.5

Acceptance criteria:
1. Only members who held shares when the ballot opened may vote on it, with the voting power they held at that moment.
2. Shares bought after open neither add votes to that ballot nor appear in its quorum denominator.
3. A member joining mid-ballot sees a clear explanation and the next ballots they can vote in.

### FO-205 Quorum and the re-run rule
As the club, I want quorum tracked against active owners with one automatic re-run on failure, so that decisions are legitimate without being permanently blocked by apathy.
Traceability: P10, P13, P76. Estimate: Design 1 / Build 0.5 / Develop 2 / Test 1.5

Acceptance criteria:
1. Quorum is 25% of owners active in the 12 months before ballot open; the denominator is recorded on the ballot at open.
2. A ballot closing below quorum automatically re-opens once for 7 days, announced to all eligible voters with a reminder to those who have not voted.
3. A re-run failing quorum again closes as unresolved and is flagged for a board decision, whose outcome and reasoning enter the decision register (FO-218).
4. Progress toward quorum is visible to staff and board throughout (FO-203), and a pre-close reminder goes to non-voters when quorum is at risk.

### FO-206 Automated lifecycle and results
As an owner, I want ballots to open, close, and reveal results automatically with notifications at every stage, so that governance runs like clockwork.
Traceability: T18, P63, P9. Estimate: Design 1 / Build 0.5 / Develop 2.5 / Test 2

Acceptance criteria:
1. Ballots open and close at their scheduled times without manual action; each stage (open, closing soon, closed, result) notifies eligible owners by their chosen channels.
2. At close, the result — option totals, percentages, turnout, and whether quorum was met — publishes instantly to all owners.
3. A standard ballot passes on a simple majority of votes cast; the outcome statement names the winning option or records that the proposal fell.
4. A snapshot of the ballot data is preserved at close for audit before results publish (T31).
5. Results are also covered in the weekly video wrap (editorial, not platform-enforced) with the platform linking result to coverage.

### FO-207 Constitutional ballots
As the club, I want identity-defining votes to require a 75% supermajority, so that the club's soul is harder to change than its kit.
Traceability: P41, P75. Estimate: Design 0.5 / Build 0 / Develop 1 / Test 1

Acceptance criteria:
1. A ballot marked constitutional passes only when at least 75% of votes cast back the winning option and quorum is met.
2. Constitutional ballots are visibly labelled before and during voting, including what happens if the threshold is not reached.
3. Changes to the governance rules themselves (quorum, majorities, windows, scope) can only be made via a constitutional ballot.

### FO-208 Tied results
As the club, I want a tied ballot resolved by a recorded board casting vote, so that ties end in a decision with a visible trail.
Traceability: P59, P81. Estimate: Design 0.5 / Build 0 / Develop 1 / Test 0.5

Acceptance criteria:
1. A tie triggers a board casting-vote action; the result is withheld until the casting vote is recorded.
2. The published result shows the tie, the casting vote, and the board's stated reasoning, and the decision register entry carries all three.
3. The casting vote must be recorded within a configured period; overdue casting votes are escalated to the chair.

### FO-209 The voting record
As an owner, I want every past ballot and result permanently browsable, so that the club's democratic history is on the record.
Traceability: P63, P77. Estimate: Design 0.5 / Build 0.5 / Develop 1 / Test 0.5

Acceptance criteria:
1. Owners can browse and search all closed ballots with results, turnout, thresholds, and dates.
2. Individual members' choices are never shown; aggregates only.
3. Each result links to its decision register entry where implementation is tracked.

---

## Epic G — Ideas and questions

### FO-210 Propose an idea
As an owner, I want to propose an idea that other owners can support, with strong support automatically starting the ballot process, so that good ideas cannot be quietly ignored.
Traceability: P15, P16, T19. Estimate: Design 1.5 / Build 1 / Develop 2.5 / Test 1.5

Acceptance criteria:
1. Any owner can submit an idea with a title and rationale; it becomes visible to other owners only after staff moderation for legality, abuse, and duplication (with a stated reason if declined, and duplicates linked to the original).
2. Owners can support an idea once; support counts are visible; support can be withdrawn.
3. When support reaches 5% of active owners, a draft ballot is created automatically and staff are notified; the proposer and supporters are told their idea has reached the threshold.
4. Staff may only legality-check and schedule the auto-drafted ballot; declining to schedule requires a published reason.

### FO-211 Idea lifecycle
As an owner, I want to see every idea's status from new to delivered or declined, so that the fate of proposals is transparent.
Traceability: P15, P64. Estimate: Design 0.5 / Build 0.5 / Develop 1 / Test 0.5

Acceptance criteria:
1. Ideas carry a status — new, under review, ballot scheduled, planned, delivered, declined — visible to all owners with a status history.
2. Status changes notify the proposer and supporters.
3. Declined ideas show a reason.

### FO-212 Question the club
As an owner, I want to submit questions and upvote others', with the most-supported answered on video monthly and the rest in writing, so that the club answers to its owners.
Traceability: P17. Estimate: Design 1 / Build 1 / Develop 2 / Test 1

Acceptance criteria:
1. Owners submit questions; staff moderation applies as for ideas; published questions are upvotable by other owners.
2. Each month's most-supported questions are marked as selected for the video answer, linked to the published video.
3. Every accepted question receives a written answer within a stated target; unanswered questions past target are flagged to staff.
4. Askers are notified when their question is answered; questions and answers remain browsable.

---

## Epic H — Meetings

### FO-213 Meetings and RSVP
As an owner, I want quarterly meetings and the AGM listed with local times, RSVP, and calendar feeds, so that I never miss one from any time zone.
Traceability: P18, P19, T22. Estimate: Design 1 / Build 1 / Develop 2 / Test 1

Acceptance criteria:
1. Meetings show date and time in the owner's local time zone, agenda, and how to join; owners can RSVP and withdraw.
2. Owners can subscribe to a members-only calendar feed of meetings, matches, and ballot deadlines, and add any single event to their calendar.
3. Reminders go to RSVP'd owners ahead of start via their chosen channels.

### FO-214 Attend live with a voice
As an owner, I want to watch the meeting live in the portal, chat, and upvote questions, with the most-upvoted answered live, so that remote attendance is participation rather than spectating.
Traceability: P19, P20, T21. Estimate: Design 1.5 / Build 1 / Develop 3 / Test 2

Acceptance criteria:
1. The live meeting stream plays inside the owner area only, with live chat alongside under the standard moderation tools (FO-306 shares this service).
2. Owners submit and upvote questions before and during the meeting; the presenter view ranks questions by support in real time.
3. Questions answered live are marked answered; unanswered ones roll into the written-answer flow (FO-212).
4. If the stream fails, the meeting page shows status and recovery information rather than an error.

### FO-215 The meeting record
As an owner, I want the recording and action minutes available within 24 hours, so that absence never means exclusion.
Traceability: P65. Estimate: Design 0.5 / Build 0.5 / Develop 1 / Test 0.5

Acceptance criteria:
1. The recording is available in the owner area within 24 hours of meeting end.
2. Written action minutes are published alongside, and any decisions feed the decision register (FO-218).
3. Past meetings, recordings, and minutes remain permanently browsable.

### FO-216 AGM resolutions
As the club, I want statutory AGM resolutions run in the platform as the formal shareholder record, so that legal governance and the ownership platform are one system.
Traceability: P66, P8. Estimate: Design 1 / Build 0.5 / Develop 1.5 / Test 1.5

Acceptance criteria:
1. Resolutions run as ballots during the AGM window, labelled as formal shareholder resolutions, using the voting, secrecy, and eligibility rules of Epic F.
2. The recorded outcome — including proposer, text, votes for and against, and turnout — is exportable as the formal minute of the resolution.
3. The mechanism is configurable to match what the articles of association permit; if the articles require a different process, the platform records the externally taken outcome instead (flagged assumption for legal review).

---

## Epic I — Transparency and accountability

### FO-217 Financial publishing
As an owner, I want monthly financial summaries and annual accounts viewable in the portal, so that I can see how my club spends its money.
Traceability: P21, P22, T23. Estimate: Design 1 / Build 1 / Develop 1.5 / Test 1

Acceptance criteria:
1. A monthly income and spend summary by category is published in the owner area; salaries appear only as an aggregate wage bill.
2. Annual accounts are viewable in the portal; financial documents cannot be downloaded (view-only rule).
3. Financial posts require second-person approval before publishing (FO-119).
4. A missed monthly publication is flagged to staff and board.

### FO-218 The decision register
As an owner, I want every passed ballot and board decision tracked in a public register from decision to done, so that "binding" visibly means something.
Traceability: P64, P81, P24. Estimate: Design 1.5 / Build 1 / Develop 2 / Test 1

Acceptance criteria:
1. Every passed ballot enters the register automatically with decision, date, and an accountable owner; board-released decisions (P84) enter when released.
2. Each entry carries a status — planned, in progress, done, blocked — with dated updates; owners can browse and filter the register.
3. Entries stalled beyond a configured period without update are flagged visibly on the register and to the board.
4. Spending decisions above the £5,000 gate (P24) reference their authorising ballot in the register.

### FO-219 The annual owners' report
As the club, I want an annual State of the Panthers report assembled from the platform's own records, so that the ownership story is told with evidence.
Traceability: P77, P52. Estimate: Design 1 / Build 0.5 / Develop 1.5 / Test 0.5

Acceptance criteria:
1. The platform assembles a draft annual report: every ballot and outcome, decision register status, membership growth against target, financial summaries, and content and participation statistics.
2. Staff can edit and then publish the report to the owner area as a permanent annual record.

---

## Epic J — Community

### FO-220 Forum and comments
As an owner, I want a members' forum and comments on ballots, ideas, videos, and news under one identity, so that owners can debate in the club's own home.
Traceability: P37, T16 (superseded by P109 — built natively, no bbPress). Estimate: Design 1 / Build 1.5 / Develop 2 / Test 1
Delivered as: **FanPress Chat** (FO-230/FO-234) — the board identifier in AC 2 is the board bubble colour + Board tag.

Acceptance criteria:
1. Owners can create forum topics and reply, and comment on club content, with one profile and display name throughout.
2. Board Members' posts carry a visible board identifier (P80); staff posts are identified similarly.
3. Owner-only rules apply: no forum or comment content is visible without an account.
4. Members can report any post; reports feed the moderation queue (FO-221).

### FO-221 Moderation and sanctions
As a Moderator, I want a queue, tools, and the graduated sanctions ladder, so that the code of conduct is enforced consistently and proportionately.
Traceability: P16, P38, P72. Estimate: Design 1 / Build 1 / Develop 2 / Test 1

Acceptance criteria:
1. Reported and pre-moderated items arrive in one queue with context; moderators can approve, edit-with-note, remove, warn, mute for a period, or escalate.
2. Sanctions follow the ladder — warning, temporary loss of posting and submission rights, expulsion — with each step recorded; expulsion (which surrenders shares) requires Owner-Admin confirmation and follows P31.
3. A muted owner retains voting and viewing rights; only expression rights are suspended, and the member is told what, why, and until when.
4. All moderation actions are logged and visible to the board's oversight view (P79).

### FO-222 Owner chapters
As an owner abroad, I want to found or join an official chapter under the light charter, so that Panthers owners find each other everywhere.
Traceability: P48, P71. Estimate: Design 1 / Build 1 / Develop 2 / Test 1

Acceptance criteria:
1. Five or more owners can apply to found a chapter with the standard naming convention and a named lead; staff approve or decline with reason.
2. Approved chapters appear in a directory and map, with a chapter space in the forum; owners can join or leave freely.
3. Chapters re-affirm annually; lapsed or de-recognised chapters are archived, with the club able to de-recognise for charter breaches (recorded).

### FO-223 Referral recognition
As an owner, I want my successful referrals recognised with badges and a leaderboard, so that growing the community is celebrated without discounting the ladder.
Traceability: P73. Estimate: Design 0.5 / Build 0.5 / Develop 1.5 / Test 0.5

Acceptance criteria:
1. Each owner has a personal referral link; a new owner whose first purchase follows it credits the referrer.
2. Referral counts award milestone badges (FO-114) and appear on an opt-in leaderboard.
3. No referral mechanism alters any price on the published ladder.

---

## Epic K — The board

### FO-224 Board Member role and directory
As an owner, I want to know who the board are and see them act under a visible board identity, so that I always know when the club is speaking.
Traceability: P78, P79, P80. Estimate: Design 1 / Build 0.5 / Develop 2 / Test 1

Acceptance criteria:
1. The Board Member role grants full owner access plus running tallies, decision register editing, financial drafts before publish, and moderation queue visibility — and nothing of content publishing or member and checkout administration.
2. Board members vote in fan ballots only through shares they personally own, under the same 10-share cap; the role adds no voting power.
3. A board directory shows each director's photo, biography, responsibilities, and conflicts register (FO-227).
4. Board members' forum and chat posts carry the board identifier automatically.

### FO-225 Structured board actions
As the club, I want every formal board act — casting votes, recommendations, reserved-matter and failed-quorum decisions — recorded through structured flows, so that board power always leaves a trail.
Traceability: P81, P7, P13, P59, P62. Estimate: Design 1.5 / Build 1 / Develop 2.5 / Test 1.5

Acceptance criteria:
1. A board recommendation can be formally attached to a ballot and is shown to voters as the board's position, distinct from the ballot text.
2. Casting votes (FO-208) and reserved-matter or failed-quorum decisions are recorded through dedicated flows capturing the decision, the directors involved, and published reasoning.
3. Every structured board action feeds the decision register when released under the board's disclosure control (P84).

### FO-226 The board workspace
As a Board Member, I want a private workspace with agenda packs, papers, discussion threads, and formal internal votes, so that the whole boardroom runs in one governed place.
Traceability: P82, P83. Estimate: Design 2 / Build 1.5 / Develop 4 / Test 2

Acceptance criteria:
1. The workspace is invisible and inaccessible to every role except Board Member, Board Observer (FO-228), and no others — including Owner-Admins, except a single audited break-glass path.
2. Board meetings have agendas with papers attached; papers are distributed in the workspace rather than by email.
3. Internal resolutions are voted one vote per director, votes visible to fellow directors, with the chair holding a casting vote; outcomes are minuted automatically with the vote breakdown.
4. Private discussion threads support matters not yet ready for owners; nothing in the workspace publishes anywhere without an explicit release action (P84).

### FO-227 Conflicts of interest
As the club, I want a standing public conflicts register and per-item recusal that locks a conflicted director out of the papers and the vote, so that conflicts are managed structurally rather than on trust.
Traceability: P85. Estimate: Design 1 / Build 0.5 / Develop 2 / Test 1.5

Acceptance criteria:
1. Each director maintains declared interests, visible to owners on the board directory.
2. Every internal vote includes a declare-or-confirm-none step before the director may vote.
3. A recused director cannot open that item's papers, join its threads, or vote on it; the recusal is minuted.
4. Recusal can be applied by the chair as well as self-declared.

### FO-228 Board meetings and observers
As a Board Member, I want board meetings held by video inside the workspace with per-item observer access for advisors, so that even the meeting itself is in the governed environment.
Traceability: P86, P87. Estimate: Design 1.5 / Build 1 / Develop 3.5 / Test 2

Acceptance criteria:
1. Directors join board meetings by video within the workspace; the meeting is listed with agenda and papers alongside.
2. A Board Observer (secretary, lawyer, auditor) can be invited per meeting or per item: read access to the relevant papers and threads, no vote, access expiring automatically at the configured point.
3. A recused director (FO-227) is excluded from the meeting segment covering their conflicted item.
4. Whether a meeting is recorded is a per-meeting chair decision, recorded either way.

### FO-229 Vault security and departures
As the chair, I want vault documents view-only and watermarked with full access logging, and departing directors cut off instantly with their record preserved, so that the most sensitive layer is the best governed.
Traceability: P88, P89, P42. Estimate: Design 1 / Build 0.5 / Develop 2.5 / Test 1.5

Acceptance criteria:
1. Vault documents render in the workspace only; there is no download route; every view is watermarked with the viewing director's name and timestamp.
2. The chair can see a complete access log of who viewed what and when.
3. Removing a director's role revokes workspace access immediately, terminating any active session's access.
4. A departed director's votes, declarations, and contributions remain permanently in the record; their ordinary owner account and shareholding are unaffected.

### FO-230 Our own forum, wired into the club
*(Now branded **FanPress Chat** (P111) and presented WhatsApp-style — chat list, bubbles, unread badges — per FO-234. The wiring below is unchanged.)*
As the club, I want our own forum — no third-party forum plugin — where club events open their own threads, match chat is archived into match-day threads, and a strong thread can become a ballot, so that the conversation and the governance live in one system.
Traceability: P109, T16 (superseded), P37, P38. Estimate: Design 1.5 / Build 1.5 / Develop 4 / Test 2

Acceptance criteria:
1. Owners browse boards, start topics, and reply behind the owner gate on web and app; the forum kill switch, word-filter holds, and mute sanctions apply to every post and reply.
2. Every ballot that opens and every match that is published automatically gets its own discussion thread, exactly once, in the right board, linking back to the source.
3. When a match ends, the live chat transcript (excluding held/removed messages) is archived into the match-day thread, with erased members anonymised.
4. Governance staff can convert any topic into a draft ballot in one action; the ballot records its origin thread and the thread records its ballot, conversion is idempotent, and the normal second-approval and lifecycle rules still apply before it opens.
5. Topic list screens show board, replies, origin (member or automated), and conversion status.

Test script:
1. Post a topic and a reply as an owner; attempt both as a non-owner, a muted member, and with the forum switched off — expect refusal in each case; post content hitting the word filter — expect it held for moderation.
2. Open a ballot and publish a match — expect one discussion thread each, in the right boards; re-fire both events — expect no duplicates.
3. End a match with chat messages (including one removed and one from an erased account) — expect a transcript reply on the match thread without the removed message and with the erased member anonymised.
4. Convert a topic to a ballot — expect a draft ballot with provenance both ways; convert again — expect the same ballot returned; attempt to convert a non-topic — expect refusal.
5. Check the Forum list screen shows board, reply count, origin, and converted status.
### FO-231 A social layer with BuddyPress-style features
*(Extended by FO-233 — directory search + pagination, @mention autosuggest, activity cheers — and included in the FanPress Chat brand, P111.)*
As an owner, I want the community features members expect from a social network — an activity feed, a member directory with follows, private messages, notifications, and @mentions — built natively on our own forum and chat, so that we never depend on a third-party community plugin.
Traceability: P110, P109. Estimate: Design 1 / Build 1 / Develop 3 / Test 1.5

Acceptance criteria:
1. An owner-gated activity feed merges new forum topics, opening ballots, published decisions, and new videos, newest first, and can be filtered to only the members someone follows.
2. The member directory lists owners with owner number and badges, and lets a member follow or unfollow anyone but themselves; follows are one-way (a follow, not a friendship contract).
3. Private messages run between exactly two owners over the same moderated chat transport as public chat (word filter, mutes, and kill switch all apply); only the two participants can ever read a conversation, and each side gets a conversation inbox.
4. Notifications are generated for replies to your topic, @mentions in topics and replies, and incoming private messages; the store is capped at fifty per member, shows unread counts, and marks read on viewing.
5. @mentions resolve by login or profile slug, never notify the author about themselves, and ignore unknown handles.
6. Everything is available as shortcodes for the site and as authenticated REST endpoints for the app.

Test script:
1. Publish a topic, open a ballot, publish a decision and a video — expect all four in the activity feed, newest first; follow one author and filter the feed — expect only their items.
2. Follow a member from the directory, then unfollow — expect the follow state to toggle; attempt to follow yourself — expect refusal.
3. Send a private message — expect it delivered through the chat rails, a notification for the recipient, and the thread listed in both inboxes; attempt to read another pair's conversation — expect refusal; send a message hitting the word filter — expect it held exactly as in public chat.
4. Reply to someone's topic mentioning a third member — expect a reply notification for the author and a mention notification for the third member, and none for yourself; pile in more than fifty notifications — expect the store capped at the newest fifty.
5. Open the notifications screen — expect unread badges to clear; call the activity, notifications, and messages REST endpoints as an owner and as a non-owner — expect data for the owner and refusal otherwise.

### FO-232 FanPress Chat has its own home
As club staff, I want the community — branded FanPress Chat — to have its own top-level admin menu with an overview, topics, boards, and the moderation queue in one place, so that running the conversation never means hunting through the Owners content menu.
Traceability: P111, P109, P110. Estimate: Design 0.5 / Build 0.5 / Develop 1 / Test 0.5

Acceptance criteria:
1. A FanPress Chat top-level admin menu sits between Owners and Board, holding an Overview, Topics (the forum post type list), Boards (the taxonomy), and Held Replies (the moderation queue) — the forum no longer appears under Owners.
2. The Overview shows live community counts (topics, replies, automated threads, threads converted to ballots), the ten latest topics with reply counts, and points staff at the member-facing shortcodes and moderation controls.
3. The FanPress Chat name is used consistently: admin menu, post-type labels, and the member-facing forum heading.
4. Menu items respect WordPress capabilities: overview and topics for content staff, boards for taxonomy managers, held replies for comment moderators.

Test script:
1. As an admin, confirm the FanPress Chat menu appears with Overview, Topics, Boards, and Held Replies, and that the forum is gone from the Owners menu.
2. Create topics (one by hand, one automated, one converted to a ballot) and replies — expect the overview tiles to count each correctly and the latest-topics list to link to them.
3. View the member forum page — expect the FanPress Chat heading.
4. Sign in as a user without moderate_comments — expect Held Replies hidden while Overview and Topics remain.

### FO-233 FanPress Chat rounds out the community
As an owner, I want the community to feel finished — a searchable member directory, @mention suggestions while I type, and the ability to cheer things in the activity feed — and as the club I want FanPress staff duties tied to WordPress roles, so that members engage easily and staff permissions stay manageable in one place.
Traceability: P112, P110, P111. Estimate: Design 0.5 / Build 1 / Develop 2 / Test 1

Acceptance criteria:
1. The member directory has a search box (matching name, login, profile slug, and owner number) and pagination (24 per page with page links); the follow buttons work on every page.
2. Typing @ in a forum or private-message box suggests up to eight matching owners (by login, slug, or display-name prefix) from an owner-gated endpoint; picking one inserts the @handle. Plain typed handles keep working without JavaScript.
3. Members can cheer any activity-feed item; cheers toggle per member, the count shows on the feed, and cheering requires the owner gate and a nonce.
4. FanPress staff duties are WordPress capabilities tied to platform roles: Moderators can manage topics and the held-replies queue, Content Editors and Owner-Admins additionally manage boards; existing installs gain the capabilities through the versioned role self-heal.
5. Everything sits behind the owner gate: directory, suggest endpoint, cheers, feed, forum, and messages all refuse non-owners.

Test script:
1. Search the directory for a name and an owner number — expect matches; browse past page one — expect the remainder and working follow buttons; search gibberish — expect a clean empty state.
2. Type @ plus three letters in a topic reply and a private message — expect suggestions; pick one — expect the handle inserted; disable JavaScript and type a handle by hand — expect the mention still notifies.
3. Cheer a feed item as two different members — expect the count to read 2; cheer again as one — expect it withdrawn and the count to read 1; attempt to cheer logged out — expect refusal.
4. Sign in as a Moderator — expect Topics and Held Replies in the FanPress menu but not Boards; as a Content Editor — expect Boards too; upgrade an existing install — expect the same without touching roles by hand.
5. Call the suggest endpoint as a non-owner — expect a 403.

### FO-234 FanPress Chat feels like WhatsApp
As an owner, I want FanPress to look and feel like a WhatsApp group — a list of chats split by category with unread badges, and conversations as bubbles with mine on the right and everyone else's on the left — with match and ballot chats appearing on the match and ballot pages, and the club able to set my colour, other owners' colour, and a board-member colour, so that chatting feels instantly familiar.
Traceability: P113, P109, P111. Estimate: Design 1 / Build 1.5 / Develop 3 / Test 1.5

Acceptance criteria:
1. The forum page is a chat list: rows show the chat name, the last message (sender and snippet), the time of the latest activity, and a WhatsApp-style unread badge; rows sort by newest activity and filter by category chips (the boards).
2. Opening a chat shows the conversation as bubbles: the viewer's own messages right-aligned in "my" colour, other owners left-aligned in "theirs" colour, and board members' messages left-aligned in the board colour with a Board tag; a compose box sits at the bottom and opening the chat clears its unread badge.
3. Unread counts track visible messages only — held (word-filtered) messages never appear in a conversation and never inflate a badge; new messages bring the badge back.
4. Creating a match or opening a ballot activates its chat automatically (existing automation), and that chat is embedded on the match page and the ballot page for owners, so the conversation lives where the event lives.
5. Settings → FanPress Chat holds three colour settings — my bubble, other owners' bubbles, board members' bubbles — with sensible defaults and invalid values falling back safely; the colours apply everywhere the conversation renders, including embeds.
6. All existing rails hold: owner gate, forum kill switch, mutes, word-filter holds, @mention suggestions, and the app API (which now reports per-chat unread counts and marks chats read when fetched).

Test script:
1. Open the forum page — expect a chat list with category chips, last-message snippets, times, and unread badges; filter by a category — expect only its chats.
2. Open a chat with two other participants (one a board member) — expect your messages right in your colour, the owner's left in the standard colour, and the board member's left in the board colour with a Board tag.
3. Have someone post while you're away — expect the badge to count it; open the chat — expect the badge cleared; have a message tripped by the word filter — expect it absent from the thread and the badge unchanged.
4. Publish a match and open a ballot — expect their chats on the match and ballot pages for owners (and absent for non-owners); post from the embed — expect it in the same thread everywhere.
5. Change the three colours in Settings → FanPress Chat — expect every conversation to re-colour; enter an invalid value — expect the default used.
6. Fetch topics from the app API as a member — expect an unread count per chat that clears after fetching replies.

### FO-235 The owner profile
As an owner, I want a profile page — my details, bio and social links, owner since, my shares (visible to others only if I choose), badges, and an activity percentage — so that the community can see who I am and how engaged I am.
Traceability: P121, P110. Estimate: Design 0.5 / Build 0.5 / Develop 2 / Test 1

Acceptance criteria:
1. My profile shows name, owner number, owner-since date, bio, social links (X/Instagram/Facebook/Bluesky), badges, share count with a public/private choice (private by default), and my activity percentage with its three components.
2. Activity % is the average of voting (ballots voted ÷ ballots held), community (FanPress posts in the last 90 days, ten posts = 100%), and watching (matches watched or listened ÷ matches held, recorded once per match when I open its page).
3. Other owners see the public card (no email, no edit form, shares only if I opted in) with a Follow button; directory names link to profiles; non-owners are gated.
4. I can edit bio and socials, toggle share visibility, and switch chat emails on/off from my profile.

Test script:
1. Vote in the only ballot, post once, watch the only match — expect voting 100 / community 10 / watching 100 and the average overall; a dormant owner scores 0.
2. Save a bio and socials — expect them public to owners; leave shares private — expect no share tile on the public view; opt in — expect it shown.
3. Open another owner's profile — expect the public card and a working follow; open as a non-owner — expect the join gate.

### FO-236 FanPress comms round
As an owner, I want chat emails, reply-quoting, pinned messages, and per-chat mute, so that FanPress keeps me informed without drowning me.
Traceability: P121, FO-234. Estimate: Design 0.5 / Build 0.5 / Develop 2 / Test 1

Acceptance criteria:
1. Replies to my topics, @mentions, and private messages email me through the club rails as well as the bell; one toggle on my profile switches chat emails off; the bell keeps working regardless.
2. Any message can be quoted: Reply on a bubble quotes it above my message (stored as the comment parent, shown as an excerpt with the author's name).
3. Moderators/staff can pin one message per chat; it shows in a banner at the top; pinning again unpins; pins are audited.
4. Muting a chat hides its unread badge and stops its emails; unmuting restores both; mute is per member per chat.

Test script:
1. Trigger a reply notification — expect one email; switch emails off — expect bell only; mute the chat — expect no email even with emails on.
2. Quote a message — expect the excerpt above the reply in the thread.
3. Pin a message as staff — expect the banner; pin again — expect it cleared; attempt as a plain owner — expect refusal.
4. Mute then unmute a chat — expect the unread badge to vanish and return.
