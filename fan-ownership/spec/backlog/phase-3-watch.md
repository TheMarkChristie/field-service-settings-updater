# Phase 3 — Watch

Owners watch the club from anywhere: native apps on both stores, live home
match streams with chat and minute-by-minute updates, away audio, replays
within the hour, and the weekly show — with matchday operations to keep it
all up when it matters.

Epics: L. Native apps · M. Match Centre · N. Video library · O. Matchday operations · P. Player engagement

---

## Epic L — Native apps

### FO-301 Sign in on the apps
As an owner, I want to sign into the iOS or Android app once and stay signed in securely, so that the club is one tap away.
Traceability: T2, T5, T6, T7, T67. Estimate: Design 1 / Build 1 / Develop 3 / Test 2

Acceptance criteria:
1. Owners sign in with email and password or Apple and Google sign-in; sessions persist securely across app restarts until signed out or revoked.
2. Members with two-factor enabled complete it in-app; staff and board accounts require it.
3. Revoking a session (password change, account closure, role removal) ends app access at the next request.
4. The apps support iOS 15 and Android 8 upwards.

### FO-302 Everything a member can do
As an owner, I want every member capability — voting, ideas, questions, forum, meetings, documents, profile — working natively in the app, so that the app is the club, not a brochure.
Traceability: T39, P44. Estimate: Design 2.5 / Build 2 / Develop 8 / Test 4

Acceptance criteria:
1. Every member-facing capability from Phases 1 and 2 is available and fully functional in the apps; club administration is not.
2. Behaviour matches the web in every governed detail: eligibility snapshots, secrecy rules, quorum displays, view-only documents.
3. The apps meet the platform accessibility commitment: screen-reader labels, dynamic type, and contrast (T71 checks in CI).
4. Share purchases open the web checkout outside the app; ownership state reflects a completed purchase on return (T33).
5. The apps function online-only, showing a clear connectivity state when offline (T38).

### FO-303 Notifications that respect me
As an owner, I want push notifications by category with quorum reminders on by default, each opening the exact screen it announces, so that I hear what matters and land where I need to be.
Traceability: T14, T65, T66. Estimate: Design 1 / Build 1 / Develop 3 / Test 2

Acceptance criteria:
1. Push categories match the email categories (FO-116) and are managed in one preference centre applying to both.
2. Ballot-close quorum reminders default on; every category can be turned off.
3. Tapping a notification opens the exact ballot, stream, meeting, or thread; the same link shared to a friend opens the app if installed, otherwise the website.
4. Notifications for owner-only material never reveal restricted content in the notification itself beyond title-level information.

### FO-304 Shipping the apps
As the club, I want both apps released from club-owned store accounts on a monthly train with phased rollouts, so that releases are routine and the club can never lose its own apps.
Traceability: T68, T2. Estimate: Design 0.5 / Build 2 / Develop 1 / Test 1

Acceptance criteria:
1. Both store listings are owned by club-controlled accounts; no personal account holds the apps.
2. Releases follow a monthly train with a documented hotfix path; rollouts are phased with the ability to halt.
3. Store listing branding is driven by the identity configuration (FO-104) and can be resubmitted within days of an identity change.
4. Crash and error reporting from both apps reaches the monitoring stack (T56) with symbolicated reports.

---

## Epic M — Match Centre

### FO-305 Watch the match live
As an owner anywhere in the world, I want every home game streamed live inside the member gate, so that distance never costs me a minute.
Traceability: P33, T9, T12, T62. Estimate: Design 1.5 / Build 2 / Develop 3 / Test 2

Acceptance criteria:
1. A live home match stream plays inside the owner area on web and apps; the player works nowhere outside the member gate.
2. The stream goes live before kick-off with a countdown state beforehand and a clear status if production is delayed.
3. Playback adapts to connection quality with manual quality selection; live rewind is available during the stream (T69).
4. Chromecast and AirPlay work from the apps (T69).
5. A stream failure shows recovery status to owners and alerts staff immediately (FO-313).

### FO-306 Matchday chat
As an owner, I want live chat alongside the stream under proper moderation, so that matchday has a global pub without the ugliness.
Traceability: T40, T41, T63, P38. Estimate: Design 1.5 / Build 1.5 / Develop 4 / Test 2

Acceptance criteria:
1. Owners chat in real time beside the stream on web and apps under their member identity; board and staff identities are marked.
2. Moderation tools work live from any device: word filter with held messages, slow mode and rate limits, member reporting, and delete, timeout, and mute in-chat.
3. Muted or sanctioned members (FO-221) are restricted in chat consistently with their sanction.
4. Chat scales to the full membership online at once without degrading the stream.
5. This chat service also powers meeting chat (FO-214) and FanPress private messages (FO-231) with one codebase and identical moderation.
6. The match's FanPress chat thread (auto-created on publish, FO-230) renders beside the stream on the match page (FO-234); at full time the live chat transcript is archived into it.

### FO-307 Minute-by-minute, from the ground
As a volunteer reporter, I want a big-button console that queues events when my signal dies, so that a goal is never lost to a dead spot.
Traceability: T42, T64, P72. Estimate: Design 1.5 / Build 1 / Develop 3 / Test 2

Acceptance criteria:
1. Approved volunteers (P72 vetting) can record goal, card, substitution, half-time, and full-time events with score, minute, and player where relevant, from a phone-friendly console.
2. Events post to the Match Centre timeline and push notifications (respecting category preferences) within seconds when connected.
3. Events recorded without signal queue locally and post in order when connectivity returns; nothing is lost or duplicated.
4. A staff user can correct or remove an erroneous event, with the correction visible rather than silent.
5. Away matches use the same console, giving remote owners the away timeline alongside audio (FO-308).

### FO-308 Away days by ear
As an owner, I want live audio commentary from every away game, so that away days belong to the whole community.
Traceability: P34, T11. Estimate: Design 0.5 / Build 1 / Develop 1.5 / Test 1

Acceptance criteria:
1. Live audio commentary plays inside the member gate on web and apps for every away fixture, alongside the minute-by-minute timeline.
2. Audio continues in the background on mobile while the device is locked or the owner uses other apps.
3. The audio pipeline reuses the live streaming infrastructure; a failure shows status and alerts staff as for video.

### FO-309 Stream sponsorship
As the club, I want sold advert slots and sponsor idents delivered cleanly around live streams, so that matchday coverage earns its keep.
Traceability: P69. Estimate: Design 1 / Build 0.5 / Develop 2 / Test 1

Acceptance criteria:
1. The club can schedule sponsor idents and advert slots around a stream: pre-start, half-time, and configured breaks.
2. Advert scheduling is staff-controlled per stream; no third-party ad network injects content into the member area.
3. Sponsor content is clearly distinguishable from club content.

---

## Epic N — Video library

### FO-310 Replays within the hour
As an owner, I want the full match replay available within an hour of full-time automatically, so that a missed match is only ever a delayed one.
Traceability: T70, T10, T43. Estimate: Design 0.5 / Build 1 / Develop 1.5 / Test 1

Acceptance criteria:
1. The live recording publishes automatically to the replay library within one hour of stream end, without manual steps.
2. Replays live in the owner-gated library with the same player features as live (quality, speed, captions where available, casting).
3. Edited or highlight versions can be added later alongside the automatic replay.
4. A failed auto-publish alerts staff rather than failing silently.

### FO-311 The Panthers TV library
As an owner, I want all club video — episodes, interviews, replays — organised, searchable, and resumable, so that the club's story is always at hand.
Traceability: P35, T10, T43, T44, T69. Estimate: Design 1.5 / Build 1 / Develop 3 / Test 1.5

Acceptance criteria:
1. Video is organised by type (match replay, training, interview, behind the scenes, weekly show) and browsable and searchable within the member gate (FO-118 content merges into this library).
2. Playback resumes where the owner left off across web and apps.
3. Self-hosted video is delivered efficiently worldwide via member-gated links that do not work when shared outside (T43, T12).
4. Public teaser clips can be attached to any item for the marketing layer (P36).

### FO-312 The weekly show pipeline
As a Content Editor, I want a scheduled weekly episode slot with post-match interview publishing after every game, so that the content promise runs on rails.
Traceability: P35, P70. Estimate: Design 0.5 / Build 0.5 / Develop 1 / Test 0.5

Acceptance criteria:
1. The weekly episode has a standing scheduled slot; a missed slot is flagged to staff.
2. Post-match interviews attach to their fixture and appear in both the fixture context and the library.
3. Sensitive-footage gating (FO-119) applies to all of it.

---

## Epic O — Matchday operations

### FO-313 Matchday health
As the club, I want independent monitoring, a member status page, and pre-kickoff checks, so that a 2:55pm failure is caught before 3pm.
Traceability: T36, T57, T13/T62. Estimate: Design 1 / Build 1.5 / Develop 1.5 / Test 1

Acceptance criteria:
1. Portal, checkout, member API, chat, and stream endpoints are independently monitored with instant staff alerts on failure.
2. A public status page shows current platform and matchday-service health; members are pointed to it from error states.
3. A pre-kickoff checklist verifies stream ingest, chat, and Match Centre health before every home game, with results logged.
4. Sentry-class error tracking covers the plugin and both apps with alerting thresholds.

### FO-314 The documentation set
*(Completed 3.15.0.0 (P125): fourteen guides in docs/guides — the developer guide and full API reference closed the set — plus an in-install API reference at Setup → Developers generated from the plugin's endpoint registry. Definition of done for every story has included updating affected documents since the trace discipline began.)*
As the club, I want the full documentation suite live and current — admin guide, matchday runbook, developer docs, API reference, volunteer handbooks — so that no part of running the club lives only in someone's head.
Traceability: T75. Estimate: Design 1 / Build 0 / Develop 3 / Test 0.5

Acceptance criteria:
1. Each of the five documents exists, is current with the shipped platform, and names an owner for upkeep.
2. The matchday runbook covers the failure playbook: who does what when the stream, chat, or console fails mid-match.
3. Volunteer handbooks cover the reporter console and moderation tools at the depth a new volunteer needs.
4. Definition of done for every future story includes updating affected documents.

### FO-315 Full engagement picture
As the club, I want content and community performance added to the dashboard, so that the whole club — money, votes, content, community — reads from one page.
Traceability: T28, T27. Estimate: Design 1 / Build 0.5 / Develop 2 / Test 1

Acceptance criteria:
1. The dashboard adds stream concurrents and peaks, replay and episode viewing, forum and chapter activity, ideas and questions throughput, and moderation queue depth to the Phase 1 membership and revenue view.
2. Ballot health remains prominent: live turnout against quorum for every open ballot.
3. All figures respect the privacy-first analytics commitment (T27).

---

## Epic P — Player engagement

### FO-316 The squad
As the club, I want a maintained roster of players with number, position, and an active flag, so that player voting and match coverage always work from the current squad whatever the sport.
Traceability: P96, P90, T46. Estimate: Design 0.5 / Build 0.5 / Develop 1 / Test 0.5

Acceptance criteria:
1. Staff can add, edit, retire, and reinstate players with name, squad number, position, photo, and an active flag; positions are free text so any sport's roles fit.
2. Only active players appear as voting options; retiring a player removes them from future polls without touching past results.
3. Each player has a public profile page listing their details and any player-of-the-match and player-of-the-month honours.
4. Roster management requires a staff role; members cannot alter the squad.

Test script:
1. Add a player with number, position, and photo — expect them on the squad listing and in the next poll's options.
2. Retire the player — expect them gone from open and future polls while past wins remain on their profile.
3. Reinstate them — expect them back in the next poll.
4. Attempt to edit the roster as a member — expect refusal.

### FO-317 Player of the match, voted live
As an owner watching the match, I want to vote for my player of the match while the game is on and see the live standing, so that the award is the fans' and the wait for the result is part of matchday.
Traceability: P94, P96, T14. Estimate: Design 1 / Build 1 / Develop 2.5 / Test 1.5

Acceptance criteria:
1. The vote opens automatically when the match goes live and closes 30 minutes after the match ends; outside that window voting is refused with the poll's status shown.
2. Every member gets exactly one vote regardless of shareholding — this is an engagement poll, deliberately distinct from share-weighted governance ballots — and may change it while the poll is open.
3. Live tallies are visible to voters while the poll runs, alongside the stream on web and apps.
4. Only the active squad (FO-316) can receive votes; an invalid selection is refused.
5. When the poll closes, the winner is declared automatically, announced by push notification (respecting notification preferences), recorded on the match, and added to the player's honours.
6. Non-members can see that the vote exists but cannot cast one.

Test script:
1. Before the match is live, attempt to vote — expect refusal with status.
2. Set the match live, vote as a member, then vote again for a different player — expect one counted vote reflecting the change and live tallies updating.
3. Vote for a retired or invalid player — expect refusal.
4. Cast votes from two member accounts with different shareholdings — expect each to count exactly once.
5. End the match; within the 30-minute window vote again — expect it accepted; after the window — expect refusal.
6. After close, confirm the winner is declared without staff action, the push notification is sent, and the win appears on the player's profile and the match record.
7. Attempt to vote signed out — expect a sign-in route, not a counted vote.

### FO-318 Player of the month
As an owner, I want to vote for our player of the month at the end of each month and see the club announce the winner, so that sustained form gets the fans' recognition, not just single nights.
Traceability: P95, P96, T14. Estimate: Design 0.5 / Build 0.5 / Develop 2 / Test 1

Acceptance criteria:
1. The month's poll opens automatically for the final seven days of each calendar month across the active squad and closes when the month ends.
2. One vote per member, changeable while the poll is open, with live standings visible to voters.
3. The winner is declared automatically at month end, announced by push notification, archived against that month, and added to the player's honours.
4. Past monthly winners remain visible as an honours archive.
5. Outside the voting window, the poll shows when voting next opens.

Test script:
1. During the last seven days of a month, vote as a member and change the vote — expect one counted, changeable vote and live standings.
2. Before the window, attempt to vote — expect refusal with the opening date shown.
3. Roll the month over — expect the winner archived for that month, announced by push, and shown on the player's profile.
4. Check the archive — expect previous months' winners listed and unchanged by roster edits.
