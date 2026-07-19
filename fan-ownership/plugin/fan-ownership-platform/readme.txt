=== Fan Ownership Platform ===
Contributors: markchristie
Tags: fan ownership, membership, voting, sports club, streaming
Requires at least: 6.4
Tested up to: 6.7
Requires PHP: 8.1
Stable tag: 3.12.0.1
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
  ideas, questions, meetings, financial transparency, the decision
  register, FanPress Chat (the native forum and social layer with
  automated threads, thread-to-ballot conversion, activity feed,
  follows, private messages, and notifications), plus a private
  board workspace
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

= 3.12.0.1 =
* Fix: the demo pack's ballots now arrive with a recorded second
  approval, so editing and re-publishing a seeded ballot no longer
  sticks at Pending on a single-admin demo site; the open kit ballot
  stays live until 31 August. The member import (Data API + demo
  loader) now seeds the new profile fields - bios, socials, and the
  private identity record including a PEP=yes compliance example
  (Morag Sinclair) - all with obviously fake DEMO- IDs.

= 3.12.0.0 =
* Identity record: profiles gain a private Identity section - full
  birth name, nationality, country of residence, date of birth,
  government registration ID, and a politically-exposed-person
  declaration. Only birth name and nationality appear on the owner
  card; everything else is visible to the member and Owner-Admins
  only, via an audited Identity Lookup in Member Tools with the ID
  masked to its last four characters. Owners can upload a profile
  picture and up to five photos of themselves (sixth refused) with a
  consent checkbox for club social-media use; photos show on the
  owner card. Identity and photos are included in GDPR export and
  wiped by the eraser; identity edits and compliance views are
  audited.

= 3.11.0.0 =
* Owner profiles: [prx3_profile] (installer adds the page) shows name,
  owner number, owner since, bio, social links, badges, a
  private-by-default share count, and an activity percentage - the
  average of voting (ballots voted of ballots held), community
  (FanPress posts in 90 days), and watching (matches watched or
  listened, tracked once per match). Directory names link to
  profiles; other owners see the public card with a Follow button.
* FanPress comms round: replies, @mentions, and private messages now
  email you through the club rails (one-toggle opt-out on your
  profile; the bell always works); quote any message with Reply;
  staff can pin a message to the top of a chat; mute any chat to
  silence its badge and emails.

= 3.10.0.0 =
* The completeness round (first four-part build number). Certificates
  carry a scannable QR to their public verification page. New Member
  Tools screen merges duplicate accounts through the register. New
  member pages: Voting Record (searchable archive of finished
  ballots), Bring a Fellow Fan (referral link + opt-in leaderboard),
  and Owner Chapters (directory + OpenStreetMap map when chapters
  have coordinates). Board menu gains Live Q&A (questions ranked by
  upvotes, auto-refreshing) and Annual Report (structured drafts into
  the vault). Weekly show slot setting with a missed-week staff flag.
  Player pages show number, position, and POTM honours. New owners
  get a dismissible welcome panel with the club welcome video. Draft
  ballots now say so on their page instead of showing nothing. Render
  smoke tests now prove every member surface outputs real content
  (and caught a decision-register page crash, fixed); an identity
  scan keeps club names out of code; GitHub Actions CI runs the full
  suite on every change.

= 3.9.0 =
* Rich ballot questions: the question is the ballot's post content -
  full editor with HTML, images, and embedded video - rendered on the
  ballot page and now also inside the voting card on the ballots list.
* Answers with descriptions: each option is an answer plus an optional
  longer description ("Answer | why this option" in the ballot editor),
  shown beneath the choice on the voting card and exposed to the app
  API as option_descriptions. The demo kit ballot ships with worked
  examples.

= 3.8.0 =
* Ballot URLs are now sequential numbers, not titles:
  /owners/ballot/17/. Each ballot takes the next number on first save
  (immutable; shown as "Ballot #N" on the voting card and in a new
  admin list column). Existing ballots are renumbered oldest-first
  automatically on upgrade, and previously shared title links keep
  working via WordPress old-slug redirects.

= 3.7.0 =
* Fresh installs now render out of the box. Ballot pages carry their
  full experience for owners - live voting card (options, weighted
  cast button, change-vote, secrecy note) while open, scheduled and
  closed states otherwise - and match pages carry the Match Centre,
  each with their FanPress chat beside them. Both are block-theme
  safe (the loop checks that could leave pages empty are gone). New
  "Create member pages" button on the Fan App Settings landing builds
  every member shortcode page - join, account, owners hub, ballots,
  ideas, questions, meetings, decisions, videos, match centre,
  FanPress Chat, activity, owners, messages, notifications, board,
  gift redemption - publishes them, and wires the join and account
  destinations. Idempotent: existing pages are never touched.

= 3.6.0 =
* Demo club lifecycle: loading is now idempotent - re-running skips
  content that already exists and tops member holdings up to the pack
  amounts instead of granting again, so double clicks or earlier
  partial runs can never duplicate data or hit the share cap. New
  "Remove demo data" button deletes exactly the demo footprint (pack
  posts, their auto-created match/ballot chats, the twenty demo
  members, and their register rows) with confirmation; load after
  remove rebuilds the full club. The result notice now reports skips.
* The Settings top-level admin menu is renamed "Fan App Settings".

= 3.5.0 =
* One-click demo club: the sample data pack now ships inside the
  plugin. Settings > API & Integrations > "Load demo club" loads the
  20 owners, squad, fixtures, open ballot, ideas, questions, AGM,
  decisions, videos, documents, chapters, and FanPress chats through
  the same Data API code paths - no key, terminal, or network access
  needed. Owner-Admins only, audited, with a result notice and a
  duplicate warning on re-run.

= 3.4.0 =
* Sample data pack: integrations/sample-data loads a complete demo
  club through the platform's own Data API - 20 owners with shares
  via the money path, the squad, fixtures, an open ballot, ideas,
  questions, the AGM, decisions, videos, vault documents, an
  exclusive, chapters, and FanPress chats - with a one-command
  seeder and every payload proven against the real API handlers in
  the test suite. FanPress topics are now a Data API content type,
  and the FanPress bubble colours are settable through the settings
  route.

= 3.3.1 =
* On wide screens the embedded event chat now sits beside the match
  video and beside the ballot card (sticky, with its own scroll)
  instead of below them; it still stacks underneath on phones.

= 3.3.0 =
* FanPress Chat now looks and feels like WhatsApp: the forum page is a
  chat list split by category chips with last-message snippets and
  unread badges; conversations render as bubbles - yours on the right,
  everyone else's on the left, board members highlighted in their own
  colour with a Board tag - with a compose box at the bottom. Match
  and ballot chats still activate automatically with the event and now
  embed directly on the match and ballot pages for owners. Settings >
  FanPress Chat adds three colour settings: my bubbles, other owners'
  bubbles, and board members' bubbles. Unread badges count visible
  messages only and clear on opening (web) or fetching (app API).

= 3.2.0 =
* FanPress Chat community round-out: the owner directory gains search
  (name, login, slug, or owner number) and pagination; typing @ in a
  topic or message box suggests matching owners (degrades to plain
  typed handles without JavaScript); activity-feed items can be
  cheered, one cheer per member with live counts. FanPress staff
  duties now tie into WordPress roles - Moderators manage topics and
  the held-replies queue, Content Editors and Owner-Admins also
  manage boards - granted automatically to existing installs through
  the versioned role self-heal.

= 3.1.0 =
* The community layer is now branded FanPress Chat with its own
  top-level admin menu between Owners and Board: an Overview with
  live counts (topics, replies, automated threads, ballot
  conversions) and the latest topics, plus Topics, Boards, and the
  Held Replies moderation queue. The forum has moved out of the
  Owners menu; the member-facing forum heading now reads FanPress
  Chat.

= 3.0.0 =
* V3 release. Native social layer with the community features members
  know from BuddyPress, built on our own forum and chat: activity feed
  (topics, opening ballots, decisions, videos - filterable to people
  you follow), member directory with follow/unfollow, private messages
  over the moderated chat transport (word filter, mutes, and kill
  switch apply; only the two participants can read a conversation),
  notifications for replies, @mentions, and messages (capped at fifty,
  unread counts, mark-read on view), and @mentions by login or profile
  slug. Shortcodes prx3_activity, prx3_members, prx3_messages,
  prx3_notifications plus app API routes /activity, /notifications,
  and /messages/{user}. Full codebase check: WordPress coding
  standards clean across all 48 files, 172 automated assertions
  passing.

= 0.3.0 =
* Native owners forum - no bbPress/BuddyPress needed: gated topics
  with comment replies and boards; ballots and matches auto-create
  their discussion threads (idempotent); match chat transcripts are
  archived into match-day threads at full time; governance staff
  convert threads into draft ballots in one action with provenance
  both ways; word-filter holds, mutes, kill switch, audit, admin
  columns, and app API routes throughout.

= 0.2.3 =
* Legal, Targets, and Governance settings moved from the Settings
  menu to the Board menu - board members see them read-only (secrets
  masked), Owner-Admins edit as before. Settings keeps Features,
  Club, Brand Pack, Ticketing, Shares & Checkout, and API &
  Integrations.

= 0.2.2 =
* Commerce operations: new Commerce Ops screen (held orders with
  audited Release, unclaimed purchases with resend/reassign/drop),
  automatic chasing at 3 and 10 days with a 30-day refund-review
  flag, webhook-quiet staff alerts, a dashboard attention tile, a
  seeded monthly commerce reconciliation commitment, and an automatic
  monthly register safeguard email.
* Members can nominate a beneficiary on the account page (P30), with
  export/erasure wiring and a documented transmission procedure; new
  expulsion procedure checklist; Legal settings warn before agreement
  version bumps; long-lived Data API connections flagged.

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
