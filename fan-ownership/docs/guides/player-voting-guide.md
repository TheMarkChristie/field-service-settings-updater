# Player Voting — Club Guide

How the squad, live player-of-the-match voting, and player of the month
work (stories FO-316 to FO-318; decisions P94 to P96).

## The one rule to remember

Player polls are **engagement polls, not governance ballots**: one vote
per member regardless of shareholding, results visible live, no quorum,
nothing in the decision register. Governance ballots stay share-
weighted and secret. The two never mix.

## Managing the squad

Admin → Players. Each player has a name, squad number, position (free
text — works for any sport), photo (featured image), and an **active**
flag. Public profiles live under `/squad/`.

- Only **active** players appear as voting options.
- Retiring a player (unticking active) removes them from current and
  future polls without touching past results or honours.
- Roster editing is staff-only.

## Player of the match

The poll runs itself off the Match Centre:

1. **Opens** the moment the match is set live.
2. **During the match** members vote from the match screen beside the
   stream; they can change their vote any time while the poll is open,
   and the live standings are visible.
3. **Closes** 30 minutes after the match is ended in the Match Centre —
   the buzzer-to-close window lets the full game weigh in. (Ending the
   match on time matters: the timestamp also drives the replay
   pipeline.)
4. **On close** the winner is crowned automatically on the next
   scheduler tick: recorded on the match, added to the player's
   honours, and announced by push notification (members' notification
   preferences are respected).

No staff action is needed beyond running the Match Centre as normal.

## Player of the month

- Opens automatically for the **last seven days of each calendar
  month**, across the active squad.
- One changeable vote per member, live standings visible.
- At month end the winner is archived against that month, added to the
  player's honours, and announced by push.
- Past monthly winners remain as a permanent archive.

## For app and web developers

REST namespace `prx3/v1`, member JWT required to vote:

| Endpoint | Method | Purpose |
|---|---|---|
| `/matches/{id}/potm` | GET | Poll status (open/closed), options, live tallies, the member's current vote |
| `/matches/{id}/potm` | POST `{player_id}` | Cast or change the member's vote |
| `/potm-month` | GET | This month's poll status, options, tallies, and the winners archive |
| `/potm-month` | POST `{player_id}` | Cast or change the member's monthly vote |

Votes outside the window, for inactive players, or from non-members are
refused with a descriptive error. Voting twice replaces the previous
vote — it never double-counts.

## Troubleshooting

- **The poll never opened** — the match was not set live in the Match
  Centre.
- **The poll never closed / no winner announced** — the match was
  never marked ended; end it and the poll closes 30 minutes later on
  the next scheduler tick.
- **A player is missing from the options** — they are not marked
  active, or the roster change came after the poll opened.
- **A member says their vote "didn't count"** — voting again replaces
  the earlier vote by design; the tally shows one vote per member.
