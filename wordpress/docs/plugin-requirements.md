# 365 Community Syndicator — Requirements Specification

**Product:** 365 Community Syndicator (WordPress plugin)
**Site:** https://365community.online
**Version covered:** 1.0.0
**Status:** Implemented
**Last updated:** 16 July 2026

---

## 1. Purpose and background

365community.online is a community site that republishes ("syndicates") content
created by its members — blog posts, podcast episodes, YouTube videos, and
events. This was previously done with a collection of third-party plugins
(WP Automatic for blog aggregation, a separate events plugin, etc.).

The 365 Community Syndicator replaces all of them with a single plugin that:

1. Automatically republishes each member's content from their own feeds,
   credited to that member, with a link back to the original and a note that it
   is shared with the member's permission.
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
- **G4** — Safe to run unattended: no duplicate posts, no unbounded imports, no
  fatal errors when a feed is down.

### Non-goals (v1)

- Front-end (non-wp-admin) profile editing UI.
- Importing historical/back-catalogue content beyond the configured per-fetch cap.
- Two-way sync (changes made on the community site are never pushed back to the
  source blog).
- Automatic member on-boarding/registration flows.

---

## 2. Actors

| Actor | Description |
|---|---|
| **Member** | A registered website user who creates content elsewhere (blog, podcast, YouTube) and has agreed to have it republished. Can edit their own profile. |
| **Administrator** | Site admin. Configures site-wide syndication settings, can edit any member's fields, can trigger manual fetches. |
| **Visitor** | Anonymous reader of the public site. |
| **Scheduler** | WP-Cron (ideally driven by a real system cron job) that fires the rotation. |

---

## 3. Functional requirements

### 3.1 Member feed configuration

- **FR-1.1** Each member SHALL have the following fields on their own profile
  screen (Users → Profile), editable by the member and by administrators:
  - Blog RSS/Atom feed URL.
  - Target WordPress category for their blog posts (dropdown of existing
    categories; falls back to the site default category when unset).
  - Podcast RSS feed URL.
  - YouTube **channel ID** (the `UC…` identifier; the plugin derives the feed
    URL `https://www.youtube.com/feeds/videos.xml?channel_id=<ID>` itself).
  - Events feed URL (optional).
- **FR-1.2** All URL fields SHALL be sanitised as URLs; the YouTube channel ID
  SHALL be restricted to `[A-Za-z0-9_-]`.
- **FR-1.3** A member with at least one feed field populated is "in the
  rotation"; clearing all feed fields removes them from the rotation.

### 3.2 Rotation scheduler

- **FR-2.1** A recurring cron event SHALL fire every 5 minutes by default.
- **FR-2.2** Each tick SHALL process exactly **one** member: the next member
  (by ascending user ID) after the previously processed one, wrapping to the
  first member after the last (round-robin). The pointer SHALL persist across
  ticks and plugin restarts.
- **FR-2.3** When a member is processed, ALL of their configured feeds (blog,
  podcast, YouTube, events) SHALL be checked in that same tick.
- **FR-2.4** The interval SHALL be admin-configurable: 5 min / 15 min / hourly /
  twice daily / daily. Changing it SHALL reschedule the event automatically.
- **FR-2.5** The schedule SHALL be created on plugin activation and removed on
  deactivation.
- **FR-2.6** Feed HTTP responses SHALL be cached for less than the shortest
  rotation interval (implemented: 4 minutes) so a 5-minute rotation always sees
  fresh feed content.

### 3.3 Importing items

- **FR-3.1** For each feed, up to N newest items SHALL be considered per fetch
  (N is admin-configurable, 1–50, default 10).
- **FR-3.2 (de-duplication)** An item SHALL be imported only once. Identity is
  the feed item GUID (falling back to the item permalink), stored as post meta
  `_c365_guid` and checked across all four content types regardless of post
  status. Re-fetching, re-activating, or overlapping feeds SHALL never create
  duplicates.
- **FR-3.3** An imported item SHALL preserve the original:
  - **Title** (tags stripped).
  - **Body text** — full item content, sanitised through `wp_kses_post`
    (script/iframe and other disallowed markup removed).
  - **Image** — see FR-3.6.
  - **Publish date** — original item date used as the WordPress post date
    (admin-toggleable; when off, the import time is used).
- **FR-3.4** The imported post's **author** SHALL be the member whose feed it
  came from, so it appears on their author page and archives.
- **FR-3.5** Target type and placement per feed:
  | Feed | Created as | Placement |
  |---|---|---|
  | Blog RSS | standard `post` | Member's chosen category; optionally also mapped from the feed item's own categories (created on demand, admin-toggleable) |
  | Podcast RSS | `c365_podcast` | `/podcasts/` archive |
  | YouTube channel | `c365_video` | `/videos/` archive |
  | Events feed | `c365_event` | `/events/` archive |
- **FR-3.6 (featured image)** When enabled (default on), the plugin SHALL
  sideload an image into the Media Library and set it as the featured image,
  chosen in this order: YouTube video thumbnail → feed enclosure / media
  image or thumbnail → first `<img>` in the item content. Import SHALL succeed
  even when no image is found or the sideload fails.
- **FR-3.7 (type-specific metadata)**
  - Podcast episodes: audio enclosure URL (`_c365_audio_url`, audio/* MIME
    types only) and duration (`_c365_duration`) when present.
  - Videos: YouTube video ID (`_c365_video_id`), extracted from the feed GUID
    (`yt:video:<id>`) or the permalink's `v=` parameter.
- **FR-3.8** Imported content status SHALL be admin-configurable: Published
  (default) / Draft / Pending review / Private.
- **FR-3.9** Items with an empty title, or with no GUID and no permalink,
  SHALL be skipped.

### 3.4 Attribution and SEO

- **FR-4.1** Every syndicated item SHALL display, below its content:
  - a link to the **original source** article/episode/video, and
  - a note that the content is **republished with the permission of the
    member** (by display name).
- **FR-4.2** The attribution SHALL be appended by the plugin on single views by
  default. When the active theme declares `add_theme_support( 'c365-attribution' )`,
  the plugin SHALL NOT append it (the theme renders it instead) — attribution
  is therefore theme-independent but never duplicated.
- **FR-4.3** The wording SHALL be customisable by developers via the
  `c365_attribution_html` filter.
- **FR-4.4** For syndicated items, `rel="canonical"` SHALL point at the
  original source URL (admin-toggleable, default on) so search engines credit
  the original author.
- **FR-4.5** Source metadata SHALL be stored on every imported post:
  `_c365_source_url` (original item URL) and `_c365_source_name` (source
  feed/site title), available to themes for badges and attribution.

### 3.5 Content types

- **FR-5.1** The plugin SHALL register three public custom post types, each
  with its own archive and REST support, so each gets a distinct layout in the
  theme:
  - **Events** (`c365_event`, `/events/`) — extra fields: start date/time, end
    date/time, location, registration/info URL.
  - **Podcast episodes** (`c365_podcast`, `/podcasts/`) — extra fields: audio
    file URL, duration.
  - **Videos** (`c365_video`, `/videos/`) — extra field: YouTube video ID.
- **FR-5.2** All three types SHALL be creatable and editable **manually** in
  wp-admin with meta boxes for their extra fields — an event added on the site
  SHALL be publicly visible as soon as it is published, with no cron
  involvement.
- **FR-5.3** Content types live in the plugin (not the theme) so content
  survives a theme switch.

### 3.6 Member author pages and profiles

- **FR-6.1** Each member SHALL be able to edit, on their own profile screen:
  - Tagline (short line under their name).
  - Profile photo URL — SHALL replace their Gravatar everywhere avatars are
    shown on the site.
  - Cover image URL for their author-page banner.
  - Bio (WordPress's built-in Biographical Info).
  - Links: Website, Blog, LinkedIn, X/Twitter, Bluesky, GitHub, YouTube,
    Mastodon.
- **FR-6.2** Each member SHALL control which sections appear on their public
  author page via toggles (all default ON): blog posts, podcast episodes,
  videos, events, links, bio.
- **FR-6.3** Toggle state SHALL be exposed to themes via
  `C365_Profile::section_enabled()`, and the link list via
  `C365_Profile::link_fields()`.
- **FR-6.4** Members SHALL only be able to edit their own profile;
  administrators can edit anyone's (standard `edit_user` capability checks).

### 3.7 Administration

- **FR-7.1** A top-level **Syndication** admin page (capability
  `manage_options`) SHALL show:
  - Rotation status: time to next tick and number of members in rotation.
  - A member table: name (linking to their profile), which feeds they have
    configured, last-checked time, last result (items imported / errors), and a
    marker showing who is next in the rotation.
  - A **Fetch now** button per member and a **Fetch all members now** button.
  - All site-wide settings (interval, imported status, max items, featured
    images, category mapping, original dates, attribution, canonical).
- **FR-7.2** Manual fetch actions SHALL be nonce-protected and restricted to
  administrators, and SHALL report how many items were imported.
- **FR-7.3** Per-member fetch outcomes (timestamp + human-readable result,
  including up to the first three error messages) SHALL be recorded and shown
  in the member table.

### 3.8 Lifecycle

- **FR-8.1** Activation: register content types, flush rewrite rules, schedule
  the rotation.
- **FR-8.2** Deactivation: clear the schedule, flush rewrite rules. No content
  is touched.
- **FR-8.3** Uninstall: delete plugin options, the rotation pointer, and
  per-member feed/fetch meta. **Imported posts, podcasts, videos, events, and
  their media SHALL be kept** — they are the site's content.

---

## 4. Non-functional requirements

- **NFR-1 (dependencies)** WordPress core only (SimplePie via `fetch_feed()`,
  WP-Cron, Settings/Users APIs). No Composer packages, no other plugins, no
  external services beyond the members' own feeds.
- **NFR-2 (compatibility)** WordPress ≥ 6.0, PHP ≥ 7.4. Works with any theme
  (attribution falls back to a plugin-rendered box); pairs with the
  Community 365 theme for the full per-type layouts.
- **NFR-3 (performance)** One member per tick bounds each cron run to a handful
  of HTTP requests; per-feed item cap bounds insert volume; duplicate check is
  a single indexed meta lookup per item.
- **NFR-4 (resilience)** A failing feed SHALL never abort the run or affect the
  member's other feeds; errors are captured per feed and surfaced in the admin
  table. A member being deleted simply drops them from the rotation.
- **NFR-5 (security)** All output escaped; imported HTML sanitised with
  `wp_kses_post`; nonces on every form and action; capability checks
  (`manage_options` for settings/fetches, `edit_user` for profiles,
  `edit_post` for meta boxes); YouTube embeds use `youtube-nocookie.com`
  (theme side).
- **NFR-6 (i18n)** All strings translatable, text domain `c365-syndicator`.
- **NFR-7 (scheduling caveat)** WP-Cron fires on page visits. For a guaranteed
  5-minute cadence the host SHALL run a real cron job hitting `wp-cron.php`
  every 5 minutes (documented in the README).

---

## 5. Data model

### Post meta (imported content)

| Key | On | Meaning |
|---|---|---|
| `_c365_guid` | all imported | Feed item GUID — de-duplication key |
| `_c365_source_url` | all imported | URL of the original item |
| `_c365_source_name` | all imported | Title of the source feed/site |
| `_c365_audio_url`, `_c365_duration` | podcasts | Audio enclosure, episode length |
| `_c365_video_id` | videos | YouTube video ID |
| `_c365_event_start`, `_c365_event_end`, `_c365_event_location`, `_c365_event_url` | events | Event details |

### User meta (members)

| Key | Meaning |
|---|---|
| `c365_blog_feed`, `c365_blog_category` | Blog feed + target category |
| `c365_podcast_feed` | Podcast feed |
| `c365_youtube_channel` | YouTube channel ID |
| `c365_events_feed` | Events feed |
| `c365_tagline`, `c365_avatar_url`, `c365_cover_url` | Author-page presentation |
| `c365_link_*` (website, blog, linkedin, twitter, bluesky, github, youtube, mastodon) | Profile links |
| `c365_show_*` (blogs, podcasts, videos, events, links, bio) | Author-page section toggles |
| `c365_last_fetch`, `c365_last_result` | Fetch diagnostics |

### Options

| Key | Meaning |
|---|---|
| `c365_syndicator_settings` | All site-wide settings (see FR-7.1) |
| `c365_rotation_pointer` | User ID last processed by the rotation |

---

## 6. Acceptance criteria (summary)

1. Two members with blog feeds: tick 1 imports only member A's new items;
   tick 2 only member B's; tick 3 wraps back to A.
2. Running "Fetch now" twice in a row for the same member creates no
   duplicates.
3. A new post on a member's blog appears on the site with identical title,
   featured image, and body text, in that member's chosen category, authored
   by their account, ending with the original-source link and the permission
   note, and its canonical URL is the original article.
4. A new video on a configured YouTube channel appears under `/videos/` with
   the video embedded and its thumbnail as the featured image.
5. A new podcast episode appears under `/podcasts/` with a working audio
   player and duration.
6. An event created in wp-admin is publicly visible immediately with its
   date/location/registration details.
7. A member unticks "Show my videos" → the Videos section disappears from
   their author page; their photo URL replaces their Gravatar site-wide.
8. A member's feed being offline shows an error in the Syndication table but
   other members and other feeds continue to import normally.
9. Deactivating and deleting the plugin removes settings and feed
   configuration but leaves all imported content published.
