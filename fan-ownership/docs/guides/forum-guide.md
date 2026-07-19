# Owners Forum — Club Guide

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
