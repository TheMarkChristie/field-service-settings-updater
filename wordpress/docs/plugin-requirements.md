# 365 Community Syndicator — Requirements Specification

> **Superseded:** the authoritative document is now
> [`full-specification.md`](full-specification.md) (v1.2), which covers the
> plugin AND the theme and adds the social auto-sharing requirement (§2.9).
> This file is kept for history and is accurate up to v1.1.

**Product:** 365 Community Syndicator (WordPress plugin)
**Site:** https://365community.online
**Document version:** 1.1 — incorporates the owner's 25 scoping decisions of 16 July 2026 (see Appendix A)
**Code status:** v1.0.0 implemented; items tagged **[CHANGE]** or **[NEW]** are approved requirements not yet built. Untagged requirements are implemented and confirmed.
**Last updated:** 16 July 2026

---

## 1. Purpose and background

365community.online is a community site that republishes ("syndicates") content
created by its members — blog posts, podcast episodes, YouTube videos, and
events. This was previously done with a collection of third-party plugins
(WP Automatic for blog aggregation, a separate events plugin, etc.). The site
already holds roughly **six years of previously imported content** that the new
plugin must not duplicate.

The 365 Community Syndicator replaces all of them with a single plugin that:

1. Automatically republishes each member's content from their own feeds,
   credited to that member, with a link back to the original and a note that it
   is republished with the member's permission.
2. Provides the site's community content types (Events, Podcasts, Videos), each
   rendered with its own layout.
3. Lets members manage their own public author page — their information,
   images, links, and which content sections are displayed.

### Goals

- **G1** — Zero third-party plugin dependencies: built entirely on WordPress core.
- **G2** — Members' original work is always credited and linked; the community
  site must never look like it is claiming authorship.
- **G3** — Members self-serve: feeds and author-page presentation are managed by
  the members themselves, not by a site admin.
- **G4** — Safe to run unattended: no duplicate posts (including against the six
  years of pre-existing content), no unbounded imports, no fatal errors when a
  feed is down.

### Non-goals (v1)

- Front-end (non-wp-admin) profile editing UI — wp-admin profile confirmed (Q18).
- Re-syncing when a source post is edited — imported copies are kept as-is (Q8).
- Removing local copies when items disappear from a feed (Q9).
- Two-way sync back to the source blog.
- Automatic member on-boarding/registration flows.

---

## 2. Actors

| Actor | Description |
|---|---|
| **Member** | A registered user with the **Contributor role or above** (Q4) who creates content elsewhere and has agreed to have it republished. Can edit their own profile and feeds. |
| **Administrator** | Configures site-wide settings and feed records, can edit any member's fields, triggers manual fetches, receives failure alerts. |
| **Visitor** | Anonymous reader of the public site. |
| **Scheduler** | WP-Cron, driven by a real server cron job every 5 minutes (confirmed available, Q17). |

---

## 3. Functional requirements

### 3.1 Feed records **[CHANGE — replaces per-user feed fields]**

Per Q6, feeds are first-class records in their **own database table**, not user
profile fields, so one member can have several feeds of the same or different
types, each mapped to its own category or categories.

- **FR-1.1** The plugin SHALL store feed sources in a dedicated table
  (`{prefix}c365_feeds`), one row per feed, with at minimum:
  | Column | Meaning |
  |---|---|
  | `id` | Primary key |
  | `user_id` | The member the feed belongs to (post author for imports) |
  | `type` | `blog` \| `podcast` \| `youtube` \| `event` |
  | `feed_url` | Feed URL, or the YouTube **channel ID** for `youtube` rows |
  | `categories` | One or more WordPress category IDs for imported items |
  | `active` | Enable/disable without deleting |
  | `last_fetch`, `last_result`, `fail_count` | Diagnostics (see FR-7) |
  | `backfilled` | Whether the one-time historic import has run (see FR-3.10) |
- **FR-1.2** A member SHALL be able to have **multiple feed records**, including
  several of the same type — e.g. two blog feeds posting into two different
  categories.
- **FR-1.3** Members (Contributor+) SHALL be able to add, edit, and remove
  **their own** feed records from their profile screen; administrators SHALL be
  able to manage everyone's, including from the Syndication admin page.
- **FR-1.4** Validation: URLs sanitised as URLs; YouTube channel IDs restricted
  to `[A-Za-z0-9_-]`; `type` restricted to the four known values; at least one
  category required for `blog` feeds (others may default to none).
- **FR-1.5** Only users with the Contributor role or above SHALL have feed
  records fetched or author-page profile fields available **[CHANGE]** (Q4).
  Feed records belonging to a user who loses that role are skipped, not deleted.

### 3.2 Rotation scheduler

- **FR-2.1** A recurring cron event SHALL fire every 5 minutes by default.
  A real server cron job drives it in production (Q17); the README documents
  the crontab line.
- **FR-2.2** Each tick SHALL process exactly **one member** (Q1): the next
  member (by ascending user ID) after the previously processed one, wrapping
  after the last (round-robin). The pointer persists across ticks and restarts.
- **FR-2.3** When a member is processed, **all of their active feed records**
  SHALL be checked in that tick.
- **FR-2.4** The interval SHALL remain admin-configurable (5 min / 15 min /
  hourly / twice daily / daily); changing it reschedules automatically.
- **FR-2.5** The schedule is created on activation and cleared on deactivation.
- **FR-2.6** Feed HTTP responses SHALL be cached for less than the shortest
  rotation interval (4 minutes) so a 5-minute rotation sees fresh content.

### 3.3 Importing items

- **FR-3.1** Ongoing fetches SHALL import at most **5 new items per feed per
  fetch** **[CHANGE]** (Q16; admin-configurable 1–50). The one-time backfill
  (FR-3.10) is exempt from this cap.
- **FR-3.2 (de-duplication)** An item SHALL be imported only once. Identity
  checks, in order **[CHANGE]** (Q14):
  1. Feed item **GUID** (fallback: item permalink), stored as `_c365_guid`,
     checked across all four content types and all post statuses.
  2. **Title match** against existing content of the target post type — this
     protects the ~6 years of pre-WP-Automatic-era posts that have no
     `_c365_guid` meta. Comparison is case-insensitive on the normalised title.
  On a title match, the existing post SHALL be stamped with the item's
  `_c365_guid` so future fetches use the fast GUID path.
- **FR-3.3** An imported item SHALL preserve the original:
  - **Title** (tags stripped).
  - **Body text** — full item content (Q21), sanitised through `wp_kses_post`.
  - **Image** — downloaded to the Media Library and set as featured image (Q5).
  - **Publish date** — original item date used as the post date
    (admin-toggleable).
- **FR-3.4** The imported post's **author** SHALL be the member who owns the
  feed record, so it appears on their author page and archives.
- **FR-3.5** Target type and placement per feed record:
  | Feed type | Created as | Placement |
  |---|---|---|
  | `blog` | standard `post` | The **feed record's categories** [CHANGE] (Q6) |
  | `podcast` | `c365_podcast` | `/podcasts/` archive (+ feed record's categories if set) |
  | `youtube` | `c365_video` | `/videos/` archive (+ feed record's categories if set) |
  | `event` | `c365_event` | `/events/` archive (+ feed record's categories if set) |
- **FR-3.6 (featured image)** When enabled (default on), sideload an image into
  the Media Library and set it as featured, chosen in order: YouTube video
  thumbnail → feed enclosure / media image or thumbnail → first `<img>` in the
  content. Import succeeds even when no image is found or the sideload fails.
- **FR-3.7 (type-specific metadata)**
  - Podcast episodes: audio enclosure URL (`_c365_audio_url`, audio/* only —
    **streamed from the member's host, never downloaded**, Q11) and duration.
  - Videos: YouTube video ID (`_c365_video_id`) from the feed GUID
    (`yt:video:<id>`) or the permalink's `v=` parameter.
- **FR-3.8** Imported content SHALL be **published immediately** (Q2; status
  remains admin-configurable: Publish / Draft / Pending / Private).
- **FR-3.9** Items with an empty title, or with no GUID and no permalink, are
  skipped. **YouTube Shorts SHALL be skipped** **[NEW]** (Q10) — detected via a
  `/shorts/` item URL, with a per-video oEmbed/URL check as fallback where the
  feed link is ambiguous.
- **FR-3.10 (historic backfill)** **[NEW]** (Q14) The **first** fetch of a
  newly added feed record SHALL import **everything the feed exposes** (not
  capped at 5), relying on FR-3.2's GUID + title matching to skip the years of
  content already on the site. The record is then marked `backfilled` and
  subsequent fetches use the normal cap. Note: a feed only exposes what the
  source publishes (typically 10–50 items); deeper history would need the
  source to enlarge their feed.
- **FR-3.11 (updates/deletions at source)** Imported copies are **kept as-is**
  when the source item is later edited (Q8) or disappears from the feed (Q9).
  Manual takedown = trash the post in wp-admin.

### 3.4 Attribution and SEO

- **FR-4.1** Every syndicated item SHALL display, below its content, a link to
  the **original source** and the note that the content is **“republished here
  with the permission of <member display name>”** (wording confirmed, Q20).
- **FR-4.2** The plugin appends this on single views by default; a theme
  declaring `add_theme_support( 'c365-attribution' )` renders it instead —
  never duplicated, never missing.
- **FR-4.3** Wording customisable by developers via the `c365_attribution_html`
  filter.
- **FR-4.4** `rel="canonical"` on syndicated items SHALL point at the original
  source URL (confirmed, Q3; admin-toggleable).
- **FR-4.5** Source metadata stored on every imported post: `_c365_source_url`,
  `_c365_source_name`.

### 3.5 Content types

- **FR-5.1** Three public custom post types, each with its own archive, REST
  support, and distinct theme layout:
  - **Events** (`c365_event`, `/events/`) — start/end date-time, location,
    registration URL.
  - **Podcast episodes** (`c365_podcast`, `/podcasts/`) — audio URL, duration.
  - **Videos** (`c365_video`, `/videos/`) — YouTube video ID.
- **FR-5.2** All three creatable and editable manually in wp-admin with meta
  boxes; a manually added event is publicly visible the moment it is published
  (events flow confirmed as manual + optional feed, Q12).
- **FR-5.3** Content types live in the plugin so content survives theme switches.
- **FR-5.4 (events ordering)** **[NEW]** (Q13) The `/events/` archive SHALL
  list **upcoming events first, soonest first**, with past events below in a
  separate, de-emphasised section. Past events remain reachable by direct link.
- **FR-5.5** Comments on imported content follow the site's Discussion
  settings (Q7) — the plugin does not force them open or closed.

### 3.6 Member author pages and profiles

- **FR-6.1** Each member (Contributor+) SHALL edit on their own wp-admin
  profile screen (Q18): tagline; profile photo **URL** (replaces their Gravatar
  site-wide) and cover image **URL** (URL fields confirmed, Q19); bio; links —
  Website, Blog, LinkedIn, X/Twitter, Bluesky, GitHub, YouTube, Mastodon; and
  their feed records (FR-1.3).
- **FR-6.2** Members control which sections appear on their author page via
  toggles, all defaulting ON: blog posts, podcast episodes, videos, events,
  links, bio. Section **order is fixed** (Blogs → Podcasts → Videos → Events;
  confirmed, Q22).
- **FR-6.3** Toggle state and the link list are exposed to themes via
  `C365_Profile::section_enabled()` / `C365_Profile::link_fields()`.
- **FR-6.4** Members can only edit their own profile; administrators anyone's
  (standard `edit_user` capability checks).

### 3.7 Administration and monitoring

- **FR-7.1** A top-level **Syndication** admin page (`manage_options`) SHALL
  show: rotation status (next tick, member count, who's next); a feed-record
  table (member, type, URL, categories, active, last checked, last result,
  consecutive-failure count **[CHANGE]**); per-member and per-feed **Fetch
  now** buttons; **Fetch all** ; and all site-wide settings.
- **FR-7.2** Manual fetches are nonce-protected, admin-only, and report the
  number of items imported.
- **FR-7.3** Fetch outcomes recorded per feed record (timestamp + result).
- **FR-7.4 (failure alerts)** **[NEW]** (Q15) When a feed record fails
  **5 consecutive fetches**, the plugin SHALL email the site administrator
  (member name, feed URL, last error), then not re-alert until the feed
  succeeds once and fails 5 more times. The threshold is filterable.

### 3.8 Lifecycle

- **FR-8.1** Activation: create/upgrade the feeds table **[CHANGE]**, register
  content types, flush rewrite rules, schedule the rotation.
- **FR-8.2** Deactivation: clear the schedule, flush rewrite rules; nothing
  else touched.
- **FR-8.3** Uninstall: delete plugin options, the rotation pointer, the feeds
  table, and per-member profile/fetch meta. **Imported posts, podcasts, videos,
  events, and their media are kept.**
- **FR-8.4 (migration)** **[NEW]** On upgrade from v1.0.0, existing per-user
  feed meta (`c365_blog_feed` etc.) SHALL be migrated into feed-record rows
  automatically, preserving each member's category choice.

---

## 4. Non-functional requirements

- **NFR-1 (dependencies)** WordPress core only (SimplePie via `fetch_feed()`,
  WP-Cron, Settings/Users APIs, `dbDelta` for the feeds table). No Composer
  packages, no other plugins, no external services beyond members' feeds.
- **NFR-2 (compatibility)** WordPress ≥ 6.0, PHP ≥ 7.4. Works with any theme;
  pairs with the Community 365 theme for the full per-type layouts. (Theme
  note per Q24: default accent colour to be **orange**.)
- **NFR-3 (performance)** One member per tick bounds each cron run; the 5-item
  cap bounds ongoing insert volume; GUID dedup is one indexed meta lookup per
  item, with the title check as a secondary indexed query. The one-time
  backfill of a feed is the only unbounded operation and runs once per record.
- **NFR-4 (resilience)** A failing feed never aborts the run or affects the
  member's other feeds; errors are captured per record, surfaced in admin, and
  escalate to email per FR-7.4. Deleting a member drops their feeds from the
  rotation.
- **NFR-5 (security)** All output escaped; imported HTML sanitised with
  `wp_kses_post`; nonces on every form/action; capability checks
  (`manage_options` for settings/fetches, `edit_user` for profiles, `edit_post`
  for meta boxes); members can only manage their own feed records; table
  queries via `$wpdb->prepare`. YouTube embeds use `youtube-nocookie.com`
  (theme side).
- **NFR-6 (i18n)** All strings translatable, text domain `c365-syndicator`.
- **NFR-7 (scheduling)** Production runs a real 5-minute server cron job
  hitting `wp-cron.php` (confirmed available, Q17); README documents the
  crontab line and the optional `DISABLE_WP_CRON` constant.

---

## 5. Data model

### Feeds table `{prefix}c365_feeds` **[CHANGE]**

See FR-1.1. Replaces the v1.0.0 per-user meta keys `c365_blog_feed`,
`c365_blog_category`, `c365_podcast_feed`, `c365_youtube_channel`,
`c365_events_feed` (migrated per FR-8.4).

### Post meta (imported content)

| Key | On | Meaning |
|---|---|---|
| `_c365_guid` | all imported (and stamped onto title-matched legacy posts) | Feed item GUID — de-duplication key |
| `_c365_feed_id` | all imported **[NEW]** | Feed record that produced the post |
| `_c365_source_url` | all imported | URL of the original item |
| `_c365_source_name` | all imported | Title of the source feed/site |
| `_c365_audio_url`, `_c365_duration` | podcasts | Streamed audio enclosure, episode length |
| `_c365_video_id` | videos | YouTube video ID |
| `_c365_event_start`, `_c365_event_end`, `_c365_event_location`, `_c365_event_url` | events | Event details |

### User meta (members)

| Key | Meaning |
|---|---|
| `c365_tagline`, `c365_avatar_url`, `c365_cover_url` | Author-page presentation (URL fields, Q19) |
| `c365_link_*` (website, blog, linkedin, twitter, bluesky, github, youtube, mastodon) | Profile links |
| `c365_show_*` (blogs, podcasts, videos, events, links, bio) | Author-page section toggles |
| `c365_last_fetch`, `c365_last_result` | Member-level fetch diagnostics |

### Options

| Key | Meaning |
|---|---|
| `c365_syndicator_settings` | Site-wide settings (interval, status, cap=5, images, dates, attribution, canonical, alert threshold) |
| `c365_rotation_pointer` | User ID last processed by the rotation |
| `c365_feeds_db_version` | Feeds table schema version **[NEW]** |

---

## 6. Acceptance criteria

1. Two members with blog feeds: tick 1 imports only member A's new items;
   tick 2 only member B's; tick 3 wraps back to A.
2. Running "Fetch now" twice in a row for the same feed creates no duplicates.
3. **Legacy protection:** adding a feed whose posts already exist on the site
   (imported years ago by WP Automatic, no `_c365_guid`) creates **zero**
   duplicates — existing posts are matched by title and stamped with the GUID.
4. **Backfill:** the first fetch of a new feed imports every item the feed
   exposes; the second fetch imports at most 5 new items.
5. A member with two blog feed records mapped to two categories gets each
   feed's posts in the right category, both credited to their account.
6. A new post on a member's blog appears with identical title, featured image,
   and full text, authored by their account, ending with the source link and
   the "republished with permission" note, with canonical pointing at the
   original.
7. A regular upload on a configured YouTube channel appears under `/videos/`
   with an embed and thumbnail; **a Short does not**.
8. A new podcast episode appears under `/podcasts/` with a player streaming
   from the member's host (no local audio file created).
9. An event created in wp-admin is publicly visible immediately; `/events/`
   shows upcoming events (soonest first) above past ones.
10. Only Contributor-and-above users can configure feeds; a Subscriber sees no
    syndication fields.
11. After a feed fails 5 consecutive fetches, the admin receives one email;
    other feeds keep importing normally.
12. A member unticks "Show my videos" → that section disappears from their
    author page; their photo URL replaces their Gravatar site-wide.
13. Upgrading from v1.0.0 migrates existing profile-field feeds into feed
    records with categories intact.
14. Deactivating and deleting the plugin removes settings, the feeds table,
    and feed configuration, but leaves all imported content published.

---

## Appendix A — Decision log (owner Q&A, 16 July 2026)

| # | Question | Decision |
|---|---|---|
| Q1 | Members checked per 5-min tick | One member per tick (round-robin) |
| Q2 | Imported content status | Publish immediately |
| Q3 | rel=canonical target | The original article |
| Q4 | Who can syndicate | **Contributor role and above** |
| Q5 | Featured images | Download to Media Library |
| Q6 | Categorisation | **Separate feed-records table**: each record = member + type + feed URL + category(ies); one member may have many feeds mapped to different categories |
| Q7 | Comments on imports | Follow site default |
| Q8 | Source post edited later | Keep imported copy as-is |
| Q9 | Item vanishes from feed | Keep the local copy |
| Q10 | YouTube Shorts | **Skip Shorts** |
| Q11 | Podcast audio | Stream from the member's host |
| Q12 | Events flow | Manual entry + optional per-member events feed |
| Q13 | Past events | **Upcoming first (soonest first), past shown below** |
| Q14 | First-fetch history | **Import all historic items; de-duplicate against ~6 years of existing content by title as well as GUID** |
| Q15 | Feed-failure alerts | **Email admin after repeated consecutive failures** |
| Q16 | Ongoing per-fetch cap | **5 items per feed** |
| Q17 | Real server cron | Yes — owner will add a 5-minute cron job |
| Q18 | Profile editing UI | wp-admin profile screen |
| Q19 | Profile/cover images | URL fields |
| Q20 | Attribution wording | “Original source: <link>. This content is republished here with the permission of <member>.” |
| Q21 | Article body | Full text |
| Q22 | Author-page sections | Fixed order, member toggles |
| Q23 | Dark mode (theme) | Follow visitor's OS |
| Q24 | Accent colour (theme) | **Orange** (default; adjustable in Customizer) |
| Q25 | Next step | **Update this requirements doc only; code changes await approval** |
