# FanPress Chat — Club Guide

The club community — forum, activity feed, follows, private messages,
notifications, and @mentions — is branded **FanPress Chat** and has its
own top-level admin menu.

The platform's own forum (P109/FO-230) — no forum plugin needed.

## The shape

- **Topics** live under Owners → Forum in the admin and at
  `/owners/forum/` for members (owner gate applies, teasers for
  visitors). Replies are comments on the topic.
- **Boards**: General, Match Days, Club Business & Ballots, Ideas &
  Suggestions — seeded automatically; add more under Owners → Forum →
  Boards.
- Members browse and post via the `[prx3_forum]` shortcode page or the
  app (the API mirrors the web).

## What happens automatically

- **A ballot opens** → a "Discussion: …" thread appears in Club
  Business & Ballots, linking to the vote. Debate is public, the
  ballot stays secret.
- **A match is published** → a "Match day: …" thread appears in Match
  Days, linked from the Match Centre.
- **The match ends** → the live chat transcript is archived into the
  match-day thread as a single reply (held/removed messages excluded,
  erased members shown as "Former member"), so the night's
  conversation is never lost.

Every automated thread is created exactly once, however many times the
event fires.

## Threads become ballots

When a discussion clearly deserves a vote, governance staff open the
topic in the admin (or use the one-click action) and **convert it to a
draft ballot**: the ballot carries the thread's text and a link back
to the discussion; the thread shows "Converted". The normal rules
still apply before it opens — options added, second approval,
scheduling, quorum.

## House rules (enforced in code)

- Owner gate on reading and posting; the **forum kill switch** stops
  posting instantly site-wide and in the app.
- The **word filter** (Settings → API & Integrations → chat word
  filter) holds matching topics and replies for moderation.
- **Muted members** cannot post topics or replies anywhere.
- Everything notable lands in the audit log.

## For app developers

`prx3/v1` member-JWT routes: `GET/POST /forum/topics`
(`?board=&page=&per_page=`), `GET/POST /forum/topics/{id}/replies`.
Held content returns `held: true` so the app can explain moderation.

## The social layer (V3)

The forum carries a native social layer with the features members know
from BuddyPress-style communities — built into the platform, no plugin.

- **Activity feed** — `[prx3_activity]` merges new topics, opening
  ballots, published decisions, and new videos, newest first. Members
  can filter it to only the people they follow.
- **Member directory & follows** — `[prx3_members]` lists owners with
  owner number and badges, with a follow/unfollow button. Follows are
  one-way (like Twitter, not a friend-request handshake).
- **Private messages** — `[prx3_messages]` runs owner-to-owner DMs over
  the *same moderated chat transport* as match chat: the word filter,
  mutes, and the chat kill switch all apply. Only the two participants
  can read a conversation; each side gets an inbox of threads.
- **Notifications** — `[prx3_notifications]` shows replies to your
  topics, @mentions, and incoming DMs. The store keeps the newest fifty
  per member; opening the screen marks everything read.
- **@mentions** — write `@login` (or the member's profile slug) in a
  topic or reply and they're notified. You're never notified about
  mentioning yourself; unknown handles are ignored.

App routes (member JWT): `GET /activity` (`?following=1`),
`GET /notifications` (marks read), `GET/POST /messages/{user_id}`.

Groups: use **chapters** — they already provide membership, pages, and
gating, so the social layer doesn't duplicate them.

## The FanPress Chat admin menu (V3.1)

FanPress Chat has its own top-level menu in wp-admin, between Owners
and Board:

- **Overview** — live counts (topics, replies, automated threads,
  threads converted to ballots) and the ten latest topics.
- **Topics** — the forum topic list (the same table columns as every
  other work screen: board, replies, origin, ballot).
- **Boards** — manage the boards taxonomy.
- **Held Replies** — the moderation queue for word-filter holds.

Capabilities are respected per item: content staff see Overview and
Topics, taxonomy managers see Boards, comment moderators see Held
Replies. The forum kill switch, word filter, and mutes stay in
Settings, where the rest of the platform switches live.

## Community round-out (V3.2)

- **Directory search** — the owner directory now has a search box
  (name, login, profile slug, or owner number) and pages of 24.
- **@mention suggestions** — typing `@` in a topic or message box
  suggests matching owners; picking one inserts the handle. Typed
  handles keep working with JavaScript off.
- **Cheers** — every activity-feed item has a cheer button (🎉 with a
  count). One cheer per member, click again to withdraw.

### Who can run FanPress (WordPress roles)

FanPress staff duties are WordPress capabilities carried by the
platform roles, so permissions are managed in one place (Users →
change role):

| Role | FanPress access |
|---|---|
| Fan Owner | Member-facing screens only (forum, feed, directory, messages, notifications) — everything behind the owner gate |
| Moderator | FanPress menu: Overview, Topics (including others' topics), Held Replies |
| Content Editor | The above plus Boards (taxonomy management) |
| Owner-Admin | Full FanPress admin |
| Administrator | Everything |

Existing installs pick the capabilities up automatically on upgrade —
the plugin re-verifies roles whenever the version changes.

## The WhatsApp look (V3.3)

FanPress now presents as a messenger, not a classic forum:

- **Chat list** — every thread is a chat row: name, last message
  (sender + snippet), time, and a green unread badge, sorted by newest
  activity. Category chips (the boards) filter the list.
- **Conversations as bubbles** — your messages sit on the right in
  your colour; everyone else's sit on the left. Board members' bubbles
  use the board colour and carry a small "Board" tag.
- **Unread counts** — the badge counts messages you haven't seen and
  clears when you open the chat (or when the app fetches replies).
  Held (word-filtered) messages never show and never count.
- **Match & ballot chats on their own pages** — publishing a match or
  opening a ballot activates its chat (as before), and the whole
  conversation now also renders on the match page and the ballot page
  for owners — beside the video / ballot card on wide screens,
  stacked underneath on phones.
- **Colours** — Settings → FanPress Chat sets three hex colours: my
  bubbles, other owners' bubbles, and board members' bubbles.
  Defaults: WhatsApp green / white / soft gold. Invalid values fall
  back to the defaults.
