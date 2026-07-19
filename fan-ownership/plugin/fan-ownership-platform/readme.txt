=== Fan Ownership Platform ===
Contributors: markchristie
Tags: fan ownership, membership, voting, sports club, streaming
Requires at least: 6.4
Tested up to: 6.7
Requires PHP: 8.1
Stable tag: 0.2.1
License: MIT

The fan-owned club platform: shares, weighted secret ballots, signed
shareholders' agreements, match streaming, and the companion app API.

== Description ==

Turns a WordPress site into a fan-owned sports club platform, generic
for any club and any sport:

* Tiered share ladder sold through Shopify, with gifting and a
  statutory share register with certificates and owner numbers
* Signed Shareholders' Agreement at purchase: drawn signature, club
  stamp, board countersignature, executed copies, and a board-only
  signatures register
* Share-weighted secret ballots with electorate snapshots, quorum,
  ideas, questions, meetings, financial transparency, and the decision
  register, plus a private board workspace
* Match Centre with sport presets, live streams, chat, live player-of-
  the-match and player-of-the-month voting, and replays
* Uploadable brand pack with a printable supplier pack at /brand-pack/
* REST + JWT API for the companion apps, and bidirectional Power
  Platform (Dataverse) sync with matching rules
* Feature kill switches, audit log, GDPR export/erasure, commitments
  calendar

Commerce: shares sell through Shopify (webhooks, ladder verification,
sign-to-claim); a 2FA plugin and the
club badge plugin integrate through documented contracts.

== Installation ==

1. Upload the ZIP via Plugins > Add New > Upload Plugin, then activate.
2. Visit Settings > Permalinks and click Save (registers the
   /brand-pack/ and /my-agreement/ endpoints).
3. Work through Fan Ownership > Settings: club identity and sport,
   brand pack, legal pages and signatory, the Shopify store connection,
   governance numbers, and integrations.

== Changelog ==

= 0.2.1 =
* WooCommerce removed entirely: one cart, one code path. The Woo
  checkout module, dispute handler, provider switch, and Woo settings
  fields are gone; Shopify handles all commerce and refund clawback;
  receipts come from Shopify; email-verification and app checkout
  links now point at the Shopify cart.

= 0.2.0 =
* Shopify replaces WooCommerce as the default commerce provider
  (switchable). Tier variants, HMAC-verified orders/paid and
  refunds/create webhooks, ladder price re-verification with held
  mismatches, cart permalinks for the buyer's exact next tiers,
  sign-to-claim flow preserving the Shareholders' Agreement
  signature rules, gift line properties, and refund clawback
  (surrender + gift-code voiding). All grants still travel the money
  path. 22 new test assertions (132 total).

= 0.1.10 =
* The Board menu now gathers everything the board deals with:
  Workspace, Dashboard, board meetings/papers/threads/votes, the
  vault, the Decision Register, the Commitments calendar (moved from
  Owners; read-only for board members, edited by governance staff),
  and Owner Signatures.

= 0.1.9 =
* Fix: the Club Dashboard was unreachable for administrators after
  moving under Board - the Board menu and Dashboard submenu now hang
  on the shared tally-view capability while all board content stays
  behind the board-only wall; dashboard scripts load again and every
  tile is clickable (active owners, gifts, and surrenders gained
  destinations). Member dashboard tiles now link to the account page
  and jump to open ballots.

= 0.1.8 =
* Ready-made Claude connection under Settings > API & Integrations:
  one click provisions a key and produces a paste-ready connection
  card plus a downloadable JSON connection profile; revoke with one
  click when done. New GET data/content-list read route so automation
  can inspect existing data before writing.

= 0.1.7 =
* Settings split into submenus: Features (kill switches), Club, Brand
  Pack, Legal, Ticketing, Shares & Checkout, Targets, Governance, and
  API & Integrations - each with its own page, save, and tools (the
  register export sits with Shares, the ticketing export with
  Ticketing, the printable pack with Brand Pack).
* The Club Dashboard moved from Owners to the Board menu; Owners now
  opens on a content overview.

= 0.1.6 =
* New key-gated Data API (off by default) for trusted automation:
  insert/update platform content with meta and terms, import members
  with share grants through the money path (cap, age gate, register),
  update allow-listed settings, and a schema discovery route. Every
  write audited; core post types and non-platform meta refused.

= 0.1.5 =
* Post-type audit completed: data columns extended to meetings
  (RSVPs, AGM flag), documents (type), chapters (city, lead, members,
  status), board votes (outcome, votes cast), and board papers
  (released to owners or board only). Content-only board types stay
  plain by design.

= 0.1.4 =
* Every content list screen now shows its data as table columns:
  ballots (state, type, turnout vs quorum, closes - sortable), ideas
  (support vs auto-ballot threshold), questions (answered or
  awaiting), meetings (start - sortable), videos (type, teaser),
  behind-the-scenes (teaser), decisions (decided date, delivery
  status), matches (kick-off - sortable, opponent and venue, live
  status, player of the match), players (number - sortable, position,
  active, POTM wins).

= 0.1.3 =
* New Settings > Targets section: owner target and season financial
  target. The dashboard owner meter follows the owner target, and the
  revenue tile gains its own progress meter when a financial target is
  set.

= 0.1.2 =
* Interactive tile dashboards: the Club Dashboard is now a live tile
  grid - owner hero number with a target meter, cumulative-shares
  sparkline, members-by-holding mini chart with hover/keyboard
  tooltips, per-ballot quorum meters with closing countdowns, and
  click-through operations tiles (moderation, ideas, questions,
  stalled decisions, commitments, CRM sync), refreshing every minute
  with a table view for accessibility.
* The member dashboard shortcode leads with tiles: my shares, voting
  power, open ballots, and ticket discounts.
* Plugin author corrected to Mark Christie.

= 0.1.1 =
* Admin menus regrouped into three places: Owners (dashboard, ballots,
  ideas, questions, meetings, video, documents, behind the scenes,
  decision register, chapters, match centre, players, commitments),
  Board (workspace, board records, owner signatures), and Settings
  (all configuration plus CRM sync).
* Self-healing install: roles/capabilities, tables, and cron are
  verified on admin load, so the configurable menus appear even when
  the activation hook did not re-run after an upgrade.

= 0.1.0 =
* First packaged build: all three phases (Own, Decide, Watch), signed
  agreements, player voting, Power Platform sync, full WordPress
  coding-standards pass, and the money/vote-path test suite.
