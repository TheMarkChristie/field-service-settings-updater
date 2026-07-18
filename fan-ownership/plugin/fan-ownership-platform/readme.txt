=== Fan Ownership Platform ===
Contributors: perthpanthers
Tags: fan ownership, membership, voting, sports club, streaming
Requires at least: 6.4
Tested up to: 6.7
Requires PHP: 8.1
Stable tag: 0.1.1
License: MIT

The fan-owned club platform: shares, weighted secret ballots, signed
shareholders' agreements, match streaming, and the companion app API.

== Description ==

Turns a WordPress site into a fan-owned sports club platform, generic
for any club and any sport:

* Tiered share ladder with WooCommerce checkout, gifting, and a
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

Companion plugins: WooCommerce (checkout) and a Stripe gateway are
required for share sales; a PDF invoice plugin, a 2FA plugin, and the
club badge plugin integrate through documented contracts.

== Installation ==

1. Upload the ZIP via Plugins > Add New > Upload Plugin, then activate.
2. Visit Settings > Permalinks and click Save (registers the
   /brand-pack/ and /my-agreement/ endpoints).
3. Work through Fan Ownership > Settings: club identity and sport,
   brand pack, legal pages and signatory, share product, governance
   numbers, and integrations.

== Changelog ==

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
