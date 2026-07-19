# Platform Guides

Working guides for running the fan ownership platform (the T75
documentation suite, delivered feature by feature — FO-314). Each guide
covers setup, day-to-day operation, and troubleshooting for one area.

| Guide | Audience | Covers |
|---|---|---|
| `shareholders-agreement-guide.md` | Club admins, board | Publishing the agreement, signatures, executed copies, the club stamp and countersignature, version bumps, the board signatures register, data protection |
| `player-voting-guide.md` | Club staff, app developers | Squad management, live player-of-the-match voting, player of the month, announcements, API endpoints |
| `power-platform-sync-guide.md` | Club admins, Power Platform makers | Dataverse sync setup, what flows in each direction, field ownership, matching rules, the review queue |
| `data-api-guide.md` | Club admins, automation | The key-gated write API: inserting content, importing members through the money path, updating settings, guard rails |
| `forum-guide.md` | Club staff, members | FanPress Chat (the native forum and social layer): boards, automated threads, chat archiving, thread-to-ballot conversion, activity feed, follows, private messages, notifications, @mentions, house rules |
| `shopify-guide.md` | Club admins | Selling shares through Shopify: tier variants, webhooks, ladder verification, the sign-to-claim flow, refund clawback |
| `admin-screens-guide.md` | Club admins, board | Every wp-admin screen across the four menus: Share Register, Audit Log, Reports, Gift Codes, Member Tools, Identity Lookup, compliance notes |
| `webmaster-setup-guide.md` | Webmasters | Zero-to-running: install, configuration order, companion services, roles, verification |
| `board-members-guide.md` | Board members | The Board menu end to end, plus the rules that bind board conduct |
| `volunteers-guide.md` | Volunteers | Rotas, moderation, reporting, content help, chapter leads |
| `match-ballot-managers-guide.md` | Match & ballot managers | The matchday runbook and the ballot runbook, draft to declared result |
| `owners-guide.md` | Owners | The member experience: voting, FanPress, profile, ownership, match centre |
| `developer-guide.md` | Developers | Architecture, module map, conventions (self-heal, kill switches, audit, idempotency), hooks/filters, capabilities, post types, tests/CI/versioning |
| `api-reference.md` | App & integration developers | Every endpoint under `prx3/v1`: auth models (JWT, nonce, per-integration keys), error codes, rate limits, payload examples, webhooks, tokenised feeds |

The documentation suite (FO-314) is complete — the same API reference
ships inside every install at **FanPress Technical Setup → Developers**, generated from
the plugin's own endpoint registry.

> Fresh install? **FanPress Settings → Create member pages** builds the
> whole member site (join, account, hub, ballots, FanPress Chat, match
> centre, and more) in one click, and event permalinks render their
> full experience on any theme.
