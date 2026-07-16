# 365 Community — Full System Specification

**Site:** https://365community.online
**Packages:** 365 Community Syndicator (plugin) + Community 365 (theme)
**Document version:** 1.1 — incorporates the owner's 25 scoping decisions of 16 July 2026 (Appendix A)
**Code status:** v1.0.0 of both packages is built and delivered. Requirements tagged **[CHANGE]** or **[NEW]** are approved but not yet implemented (owner chose "update the spec only" pending approval — Q25). Untagged requirements are implemented.
**Last updated:** 16 July 2026

---

## Part 1 — System overview

### 1.1 Background

365community.online is a community site that republishes ("syndicates") content
created by its members — blog posts, podcast episodes, YouTube videos, and
events. Historically this ran on a collection of third-party plugins
(WP Automatic for blog aggregation, a separate events plugin, and others). The
site already holds roughly **six years of previously imported content** that
the new system must not duplicate.

### 1.2 What is being delivered

| Package | Replaces | Responsibility |
|---|---|---|
| **365 Community Syndicator** (plugin) | WP Automatic, events plugin, podcast/video import plugins | All content automation and data: feed records, the 5-minute rotation, importing, de-duplication, attribution, canonical SEO, the Events/Podcasts/Videos content types, member profile fields, admin dashboard, failure alerts. |
| **Community 365** (theme) | Current theme | All presentation: card-grid magazine design, a distinct layout per content type, member author pages, source badges, dark mode, Customizer options. |

Content and data live in the **plugin** so nothing is lost on a theme switch;
the theme is presentation only. Either package works without the other (the
plugin renders a fallback attribution box under any theme; the theme renders
sensibly without the plugin), but they are designed as a pair.

### 1.3 Goals

- **G1** — Zero third-party plugin dependencies: WordPress core only.
- **G2** — Members' original work is always credited and linked; the site must
  never appear to claim authorship.
- **G3** — Members self-serve: feeds, profile, and author-page presentation are
  managed by members themselves.
- **G4** — Safe unattended operation: no duplicates (including against the six
  years of legacy content), bounded imports, graceful behaviour when feeds fail.

### 1.4 Non-goals (v1.x)

- Front-end (non-wp-admin) profile editing UI (Q18).
- Re-syncing imported copies when the source post is edited (Q8) or removed
  from the feed (Q9).
- Two-way sync back to source blogs.
- Automatic member registration/on-boarding flows.
- Podcast audio hosting (audio streams from the member's host — Q11).

### 1.5 Actors

| Actor | Description |
|---|---|
| **Member** | Registered user with the **Contributor role or above** (Q4) who creates content elsewhere and has agreed to republication. Manages their own feeds and profile. |
| **Administrator** | Configures site-wide settings, manages any member's feeds/fields, triggers manual fetches, receives failure alerts. |
| **Visitor** | Anonymous reader. |
| **Scheduler** | WP-Cron, driven by a real server cron job every 5 minutes (confirmed available — Q17). |

---

## Part 2 — Plugin specification (365 Community Syndicator)

### 2.1 Feed records **[CHANGE — replaces per-user feed fields]**

Per Q6, feed sources are first-class records in their **own database table**,
so one member can have several feeds — including several of the same type —
each mapped to its own category or categories.

- **FR-1.1** Feed sources SHALL be stored in a dedicated table
  (`{prefix}c365_feeds`), one row per feed:

  | Column | Type | Meaning |
  |---|---|---|
  | `id` | BIGINT PK | Primary key |
  | `user_id` | BIGINT | Owning member; becomes post author of imports |
  | `type` | VARCHAR | `blog` \| `podcast` \| `youtube` \| `event` |
  | `feed_url` | TEXT | Feed URL; for `youtube`, the **channel ID** (`UC…`) |
  | `categories` | TEXT | Comma-separated WordPress category IDs for imports |
  | `active` | TINYINT | Enable/disable without deleting |
  | `backfilled` | TINYINT | Whether the one-time historic import ran (FR-3.10) |
  | `last_fetch` | DATETIME | Diagnostics |
  | `last_result` | TEXT | Human-readable outcome of the last fetch |
  | `fail_count` | INT | Consecutive failures (drives FR-7.4 alerts) |
  | `created_at` | DATETIME | Row creation |

- **FR-1.2** A member MAY have **multiple feed records**, e.g. two blog feeds
  posting into two different categories.
- **FR-1.3** Members (Contributor+) SHALL add/edit/remove **their own** feed
  records from their profile screen; administrators manage everyone's,
  including from the Syndication admin page.
- **FR-1.4** Validation: URLs sanitised as URLs; YouTube channel IDs restricted
  to `[A-Za-z0-9_-]`; `type` restricted to the four values; at least one
  category required for `blog` feeds.
- **FR-1.5** Only Contributor-and-above users have feed records fetched or
  profile syndication fields shown **[CHANGE]** (Q4). Records owned by a user
  who loses the role are skipped, not deleted.

### 2.2 Rotation scheduler

- **FR-2.1** A recurring cron event fires **every 5 minutes** by default;
  production is driven by a real server cron job (Q17, see §5.2).
- **FR-2.2** Each tick processes exactly **one member** (Q1): the next member
  by ascending user ID after the previously processed one, wrapping after the
  last (round-robin). The pointer persists in an option across ticks/restarts.
- **FR-2.3** A processed member has **all of their active feed records**
  checked in that tick.
- **FR-2.4** Interval admin-configurable: 5 min / 15 min / hourly / twice daily
  / daily; changing it reschedules automatically.
- **FR-2.5** Schedule created on activation; cleared on deactivation.
- **FR-2.6** Feed HTTP responses cached < shortest interval (4 minutes) so a
  5-minute rotation always sees fresh content.

### 2.3 Importing items

- **FR-3.1** Ongoing fetches import at most **5 new items per feed per fetch**
  **[CHANGE]** (Q16; admin-configurable 1–50). The one-time backfill (FR-3.10)
  is exempt.
- **FR-3.2 (de-duplication)** An item is imported only once. Identity checks in
  order **[CHANGE]** (Q14):
  1. Feed item **GUID** (fallback: item permalink), stored as post meta
     `_c365_guid`, checked across all four content types and all statuses.
  2. **Title match** against existing content of the target post type —
     case-insensitive on the normalised (whitespace-collapsed, entity-decoded)
     title. This protects the ~6 years of legacy posts that carry no
     `_c365_guid`.
  On a title match, the existing post is stamped with the item's `_c365_guid`
  so future fetches take the fast GUID path.
- **FR-3.3** Imported items preserve the original:
  - **Title** (tags stripped).
  - **Body** — full item content (Q21), sanitised via `wp_kses_post`.
  - **Image** — downloaded to the Media Library, set as featured image (Q5).
  - **Publish date** — original item date used as post date (toggleable).
- **FR-3.4** Post author = the member owning the feed record.
- **FR-3.5** Target type and placement per feed record:

  | Feed type | Created as | Placement |
  |---|---|---|
  | `blog` | `post` | The **feed record's categories** [CHANGE] (Q6) |
  | `podcast` | `c365_podcast` | `/podcasts/` (+ record's categories if set) |
  | `youtube` | `c365_video` | `/videos/` (+ record's categories if set) |
  | `event` | `c365_event` | `/events/` (+ record's categories if set) |

- **FR-3.6 (featured image)** When enabled (default on): sideload an image and
  set as featured, chosen in order — YouTube video thumbnail → enclosure /
  media:content image or thumbnail → first `<img>` in the content. Import
  succeeds even if no image is found or the sideload fails.
- **FR-3.7 (type-specific metadata)**
  - Podcasts: audio enclosure URL (`_c365_audio_url`, `audio/*` MIME only,
    **streamed from the member's host, never downloaded** — Q11) and duration
    (`_c365_duration`).
  - Videos: YouTube video ID (`_c365_video_id`) from the feed GUID
    (`yt:video:<id>`) or the permalink's `v=` parameter.
- **FR-3.8** Imported content is **published immediately** (Q2); status remains
  admin-configurable (Publish / Draft / Pending / Private).
- **FR-3.9** Skipped items: empty title; no GUID and no permalink; **YouTube
  Shorts** **[NEW]** (Q10) — detected via `/shorts/` item URLs, with a
  per-video URL check (`https://www.youtube.com/shorts/<id>` resolving without
  redirect) as fallback where the feed link is ambiguous.
- **FR-3.10 (historic backfill)** **[NEW]** (Q14) The **first** fetch of a new
  feed record imports **everything the feed exposes** (uncapped), relying on
  FR-3.2 GUID + title matching to skip content already on the site. The record
  is then marked `backfilled`; later fetches use the normal cap.
  *Limitation:* a feed only exposes what the source publishes (typically
  10–50 items); deeper history requires the source to enlarge their feed.
- **FR-3.11 (updates/deletions at source)** Imported copies are kept as-is when
  the source item is later edited (Q8) or disappears from the feed (Q9).
  Takedown = trash the post in wp-admin.

### 2.4 Attribution and SEO

- **FR-4.1** Every syndicated item displays, below its content, a link to the
  **original source** and the note: *“Original source: <link>. This content is
  republished here with the permission of <member display name>.”* (Q20).
- **FR-4.2** The plugin appends this on single views by default; a theme
  declaring `add_theme_support( 'c365-attribution' )` renders it instead —
  never duplicated, never missing.
- **FR-4.3** Wording customisable via the `c365_attribution_html` filter.
- **FR-4.4** `rel="canonical"` on syndicated items points at the original
  source URL (Q3; admin-toggleable).
- **FR-4.5** Source metadata stored on every import: `_c365_source_url`,
  `_c365_source_name`, `_c365_feed_id` **[NEW]**.

### 2.5 Content types

- **FR-5.1** Three public custom post types, each with its own archive, REST
  support, and distinct theme layout:
  - **Events** — `c365_event`, `/events/`; fields: start/end date-time,
    location, registration URL.
  - **Podcast episodes** — `c365_podcast`, `/podcasts/`; fields: audio URL,
    duration.
  - **Videos** — `c365_video`, `/videos/`; field: YouTube video ID.
- **FR-5.2** All three creatable/editable manually in wp-admin via meta boxes;
  a manually added event is publicly visible the moment it is published
  (events = manual + optional per-member feed — Q12).
- **FR-5.3** Content types are registered by the plugin so content survives
  theme switches.
- **FR-5.4 (events ordering)** **[NEW]** (Q13) `/events/` lists **upcoming
  events first, soonest first**, with past events below in a de-emphasised
  section; past events stay reachable by direct link.
- **FR-5.5** Comments on imports follow the site's Discussion settings (Q7).

### 2.6 Member profiles and author-page data

- **FR-6.1** Each member (Contributor+) edits on their own wp-admin profile
  screen (Q18):
  - Tagline.
  - Profile photo **URL** — replaces their Gravatar site-wide — and cover
    image **URL** (URL fields confirmed — Q19).
  - Bio (WordPress Biographical Info).
  - Links: Website, Blog, LinkedIn, X/Twitter, Bluesky, GitHub, YouTube,
    Mastodon.
  - Their feed records (FR-1.3).
- **FR-6.2** Author-page section toggles, all defaulting ON: blog posts,
  podcast episodes, videos, events, links, bio. Section **order is fixed**:
  Blogs → Podcasts → Videos → Events (Q22).
- **FR-6.3** Exposed to themes: `C365_Profile::section_enabled( $user_id, $key )`
  and `C365_Profile::link_fields()`.
- **FR-6.4** Members edit only their own profile; admins anyone's (standard
  `edit_user` checks).

### 2.7 Administration and monitoring

- **FR-7.1** Top-level **Syndication** admin page (`manage_options`):
  - Rotation status: next tick countdown, member count, who's next.
  - Feed-record table **[CHANGE]**: member, type, URL, categories, active,
    last checked, last result, consecutive-failure count.
  - **Fetch now** per member and per feed record; **Fetch all**.
  - All site-wide settings (interval, status, cap, images, category import,
    original dates, attribution, canonical, alert threshold).
- **FR-7.2** Manual fetches: nonce-protected, admin-only, report import counts.
- **FR-7.3** Outcomes recorded per feed record (timestamp + result).
- **FR-7.4 (failure alerts)** **[NEW]** (Q15) After **5 consecutive failed
  fetches** of a feed record, email the site administrator (member name, feed
  URL, last error). No re-alert until the feed succeeds once and fails 5 more
  times. Threshold filterable.

### 2.8 Lifecycle

- **FR-8.1** Activation: create/upgrade the feeds table via `dbDelta`
  **[CHANGE]**, register content types, flush rewrites, schedule rotation.
- **FR-8.2** Deactivation: clear schedule, flush rewrites; nothing else.
- **FR-8.3** Uninstall: delete options, rotation pointer, feeds table, and
  member profile/fetch meta. **All imported content and media are kept.**
- **FR-8.4 (migration)** **[NEW]** On upgrade from v1.0.0, existing per-user
  feed meta (`c365_blog_feed`, `c365_blog_category`, `c365_podcast_feed`,
  `c365_youtube_channel`, `c365_events_feed`) is migrated into feed-record
  rows automatically, preserving category choices, then removed.

### 2.9 Plugin file layout (v1.0.0, for orientation)

```
c365-syndicator/
├── c365-syndicator.php            # bootstrap, activation/deactivation
├── uninstall.php
└── includes/
    ├── class-c365-settings.php    # options, admin menu, dashboard
    ├── class-c365-types.php       # Events/Podcasts/Videos CPTs + meta boxes
    ├── class-c365-profile.php     # member profile fields + avatar override
    ├── class-c365-fetcher.php     # rotation, fetching, importing
    └── class-c365-frontend.php    # attribution, canonical, source helpers
```
v1.1 adds `class-c365-feeds.php` (feeds table CRUD + migration) and moves feed
configuration out of `class-c365-profile.php`.

---

## Part 3 — Theme specification (Community 365)

### 3.1 General

- **TH-1.1** Classic PHP theme, no build step, no external requests: system
  font stack, no CDNs, no Google Fonts, no analytics. Only external embeds are
  YouTube players via `youtube-nocookie.com`.
- **TH-1.2** Fully responsive (mobile nav toggle at ≤ 782 px); dark mode
  follows the visitor's OS via `prefers-color-scheme` (Q23) — no toggle UI.
- **TH-1.3** Design tokens as CSS custom properties (`--c365-*`); accent
  colour injected from the Customizer. Default accent: **orange** **[CHANGE]**
  (Q24; exact hex to be sampled from the current site once network access or a
  screenshot is provided — placeholder `#f97316` until then).
- **TH-1.4** Declares `add_theme_support( 'c365-attribution' )` and renders
  the attribution itself (via `C365_Frontend::attribution_html()` when the
  plugin is active, with equivalent fallback markup when not).
- **TH-1.5** Accessibility: skip link, `aria-expanded` nav toggle, escapable
  mobile menu, visible focus styles, semantic landmarks, screen-reader text
  helpers.
- **TH-1.6** i18n: all strings translatable, text domain `community365`.

### 3.2 Global layout

- **TH-2.1** Sticky translucent header: custom logo + site title, primary menu,
  mobile hamburger.
- **TH-2.2** Footer: up to three widget areas, footer menu, credit line
  (Customizer-overridable; default includes “Syndicated posts remain the
  property of their original authors.”).
- **TH-2.3** Home page: optional hero (heading + intro from Customizer,
  falling back to site title/tagline) above a responsive card grid with
  numbered pagination.

### 3.3 Cards (grids on home/archives/search/author pages)

- **TH-3.1** One card component adapts per content type:
  - Blog post: category chip, thumbnail, title, excerpt, date, author link,
    **source badge** linking to the original (hidden if not syndicated).
  - Event: orange **Event** badge, 📅 start date and 📍 location lines.
  - Podcast: purple **Podcast** badge, play overlay on the artwork.
  - Video: red **Video** badge, play overlay on the thumbnail.
- **TH-3.2** Source badges are Customizer-toggleable site-wide.

### 3.4 Single layouts (one per content type — the "different layout each" requirement)

- **TH-4.1 Blog post** (`single.php`): article card with title, meta (date,
  author, source badge, categories), featured image, full content, attribution
  box, tag list, previous/next navigation, comments per Discussion settings.
- **TH-4.2 Event** (`single-c365_event.php`): Event badge, title, **event
  details panel** (starts, ends, location, "Register / more info" button),
  image, description, "Added by <member>", attribution box.
- **TH-4.3 Podcast** (`single-c365_podcast.php`): Podcast badge, title, meta
  incl. duration, **player panel** (episode artwork + HTML5 `<audio>` streaming
  the source-hosted file), show notes, attribution box.
- **TH-4.4 Video** (`single-c365_video.php`): Video badge, title, meta,
  **responsive 16:9 `youtube-nocookie.com` embed**, description, attribution box.

### 3.5 Author pages

- **TH-5.1** `/author/<name>/` renders: cover image banner (gradient fallback),
  circular profile photo, display name, tagline, bio, link chips — each
  governed by the member's toggles (FR-6.2).
- **TH-5.2** Content sections in fixed order (Q22): Blog posts, Podcast
  episodes, Videos, Events — each a card grid of the member's latest six items;
  sections the member disabled, or with no content, are omitted.
- **TH-5.3** The member's photo URL overrides their Gravatar everywhere
  (implemented plugin-side, FR-6.1).

### 3.6 Other templates

- **TH-6.1** Archives (`archive.php`) for categories/tags/dates/CPT archives:
  page header with archive title/description + card grid. The `/events/`
  archive gains upcoming/past split ordering per FR-5.4 **[NEW]**.
- **TH-6.2** Search results, 404 with search form, static pages, comments —
  all styled to the same card/article system.

### 3.7 Customizer options ("Community 365 Options")

| Setting | Default |
|---|---|
| Accent colour | Orange **[CHANGE]** (was indigo) |
| Hero heading / intro text | Site title / tagline |
| Show hero on home page | On |
| Show source badges | On |
| Footer credit text | Built-in copyright + rights line |

---

## Part 4 — Data model (consolidated)

### 4.1 Feeds table `{prefix}c365_feeds` **[CHANGE]**

See FR-1.1. Replaces the v1.0.0 per-user meta keys (`c365_blog_feed`,
`c365_blog_category`, `c365_podcast_feed`, `c365_youtube_channel`,
`c365_events_feed`), which are migrated per FR-8.4.

### 4.2 Post meta (imported content)

| Key | On | Meaning |
|---|---|---|
| `_c365_guid` | all imported; stamped onto title-matched legacy posts | De-duplication key |
| `_c365_feed_id` **[NEW]** | all imported | Feed record that produced the post |
| `_c365_source_url` | all imported | URL of the original item |
| `_c365_source_name` | all imported | Source feed/site title |
| `_c365_audio_url`, `_c365_duration` | podcasts | Streamed enclosure, length |
| `_c365_video_id` | videos | YouTube video ID |
| `_c365_event_start`, `_c365_event_end`, `_c365_event_location`, `_c365_event_url` | events | Event details |

### 4.3 User meta (members)

| Key | Meaning |
|---|---|
| `c365_tagline`, `c365_avatar_url`, `c365_cover_url` | Author-page presentation (URL fields — Q19) |
| `c365_link_website`, `c365_link_blog`, `c365_link_linkedin`, `c365_link_twitter`, `c365_link_bluesky`, `c365_link_github`, `c365_link_youtube`, `c365_link_mastodon` | Profile links |
| `c365_show_blogs`, `c365_show_podcasts`, `c365_show_videos`, `c365_show_events`, `c365_show_links`, `c365_show_bio` | Author-page toggles (default on) |
| `c365_last_fetch`, `c365_last_result` | Member-level diagnostics |

### 4.4 Options

| Key | Meaning |
|---|---|
| `c365_syndicator_settings` | interval, post status, fetch cap (**5**), featured images, feed-category import, original dates, attribution, canonical, alert threshold |
| `c365_rotation_pointer` | User ID last processed |
| `c365_feeds_db_version` **[NEW]** | Feeds table schema version |

### 4.5 Theme mods (Customizer)

`c365_accent_color`, `c365_hero_heading`, `c365_hero_text`, `c365_show_hero`,
`c365_show_source_badges`, `c365_footer_text`.

---

## Part 5 — Non-functional requirements and operations

### 5.1 Non-functional

- **NFR-1 (dependencies)** WordPress core only: SimplePie via `fetch_feed()`,
  WP-Cron, Settings/Users APIs, `dbDelta`. No Composer packages, no other
  plugins, no external services beyond members' feeds.
- **NFR-2 (compatibility)** WordPress ≥ 6.0, PHP ≥ 7.4. Plugin works under any
  theme; theme works without the plugin.
- **NFR-3 (performance)** One member per tick bounds each cron run; 5-item cap
  bounds ongoing inserts; GUID dedup is one indexed meta lookup per item with
  the title check as a secondary indexed query. Backfill is the only uncapped
  operation and runs once per feed record.
- **NFR-4 (resilience)** A failing feed never aborts the run or affects the
  member's other feeds; failures recorded per record, surfaced in admin, and
  escalated to email (FR-7.4). Member deletion drops their feeds from rotation.
- **NFR-5 (security)** All output escaped; imported HTML sanitised with
  `wp_kses_post`; nonces on every form/action; capability checks
  (`manage_options` settings/fetches, `edit_user` profiles, `edit_post` meta
  boxes); members manage only their own feed records; all feeds-table queries
  through `$wpdb->prepare`; YouTube embeds via `youtube-nocookie.com`.
- **NFR-6 (i18n)** Text domains `c365-syndicator` and `community365`; all
  strings translatable.

### 5.2 Operations / deployment

1. Upload and activate `c365-syndicator.zip` (Plugins → Add New → Upload),
   then `community365.zip` (Appearance → Themes → Add New → Upload).
2. Settings → Permalinks → Save once (registers `/events/`, `/podcasts/`,
   `/videos/`).
3. Hosting panel cron job (confirmed available — Q17):
   `*/5 * * * * curl -s https://365community.online/wp-cron.php?doing_wp_cron > /dev/null`
   Optionally `define( 'DISABLE_WP_CRON', true );` in `wp-config.php`.
4. Add feed records for each member; first fetch of each backfills history
   (FR-3.10) with legacy dedup active (FR-3.2).
5. Verify against the acceptance criteria (Part 6), then deactivate
   WP Automatic and the other legacy plugins. Legacy posts remain untouched.

---

## Part 6 — Acceptance criteria

1. Two members with blog feeds: tick 1 imports only member A's new items,
   tick 2 only member B's, tick 3 wraps back to A.
2. Running "Fetch now" twice in a row for the same feed creates no duplicates.
3. **Legacy protection:** adding a feed whose posts already exist on the site
   (imported years ago, no `_c365_guid`) creates **zero** duplicates; matched
   posts are stamped with the GUID.
4. **Backfill:** a new feed's first fetch imports everything the feed exposes;
   its second fetch imports at most 5 new items.
5. A member with two blog feed records mapped to two categories gets each
   feed's posts in the right category, both credited to their account.
6. A new source blog post appears with identical title, featured image, and
   full text, authored by the member, ending with the source link and the
   "republished with permission" note, canonical pointing at the original.
7. A regular YouTube upload appears under `/videos/` with an embed and
   thumbnail; **a Short does not**.
8. A new podcast episode appears under `/podcasts/` with a player streaming
   from the member's host; no audio file is created locally.
9. An event created in wp-admin is publicly visible immediately; `/events/`
   shows upcoming events soonest-first above past ones.
10. Only Contributor+ users can configure feeds; a Subscriber sees no
    syndication fields and their feeds (if any) are never fetched.
11. After a feed fails 5 consecutive fetches the admin receives exactly one
    email; other feeds keep importing.
12. A member unticks "Show my videos" → the section disappears from their
    author page; their photo URL replaces their Gravatar site-wide.
13. Upgrading from v1.0.0 migrates profile-field feeds into feed records with
    categories intact.
14. Deactivating + deleting the plugin removes settings, the feeds table, and
    feed configuration, but leaves all imported content published; switching
    themes leaves all content and archives intact.
15. Theme: blog posts, events, podcasts, and videos each render with their own
    distinct single layout; dark mode follows the OS; the accent colour changes
    site-wide from the Customizer.

---

## Appendix A — Decision log (owner Q&A, 16 July 2026)

| # | Question | Decision |
|---|---|---|
| Q1 | Members checked per 5-min tick | One member per tick (round-robin) |
| Q2 | Imported content status | Publish immediately |
| Q3 | rel=canonical target | The original article |
| Q4 | Who can syndicate | **Contributor role and above** |
| Q5 | Featured images | Download to Media Library |
| Q6 | Categorisation | **Separate feed-records table**: member + type + feed URL + category(ies); one member may have many feeds mapped to different categories |
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
| Q24 | Accent colour (theme) | **Orange** (exact hex to be matched to the current site) |
| Q25 | Next step | Update the spec only; code changes await approval |

## Appendix B — Delta summary: v1.0.0 (built) → v1.1 (approved, not yet built)

| Area | v1.0.0 (delivered) | v1.1 (this spec) |
|---|---|---|
| Feed storage | Five fields on the user profile | Dedicated `c365_feeds` table; many feeds per member, categories per feed; auto-migration |
| Who syndicates | Any user with feeds set | Contributor role and above |
| De-duplication | Feed GUID only | GUID **+ title match** against legacy content, with GUID back-stamping |
| First fetch | Capped like any fetch (10) | Full backfill of everything the feed exposes |
| Ongoing cap | 10 items/feed | **5** items/feed |
| YouTube Shorts | Imported | Skipped |
| Events archive | Reverse-chronological | Upcoming first, past below |
| Failure handling | Shown in admin table | + admin email after 5 consecutive failures |
| Theme accent | Indigo `#4f46e5` | Orange (hex TBC from current site) |
