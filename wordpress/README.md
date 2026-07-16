# 365 Community — WordPress theme + syndication plugin

A self-contained replacement for the current 365community.online stack (WP Automatic
etc.). Two installable packages, built only on WordPress core — no other plugins
required:

| Package | Folder | What it does |
|---|---|---|
| **Community 365** (theme) | `themes/community365` | Card-based magazine theme with dedicated layouts for blog posts, events, podcasts, and videos, plus member author pages. |
| **365 Community Syndicator** (plugin) | `plugins/c365-syndicator` | Rotates through your members every 5 minutes, checks their feeds, and auto-creates content credited to them with a link to the original source. |

## Installing

1. Zip each folder (or use the zips attached to the delivery), then in wp-admin:
   - **Plugins → Add New Plugin → Upload Plugin** → `c365-syndicator.zip` → Activate.
   - **Appearance → Themes → Add New Theme → Upload Theme** → `community365.zip` → Activate.
2. Deactivate WP Automatic and any other aggregation plugins once you're happy —
   nothing here depends on them. Existing WP Automatic posts stay untouched; only
   items imported by this plugin carry the new source metadata, so old posts simply
   won't show a source badge.
3. Go to **Settings → Permalinks** and click *Save Changes* once (refreshes the
   `/events/`, `/podcasts/`, `/videos/` URLs).

## How the syndication works

- Each **website user** gets feed fields on their own profile screen
  (**Users → Profile**): blog RSS URL + target category, podcast RSS URL,
  YouTube **channel ID**, and an optional events feed.
- A WP-Cron event runs **every 5 minutes** and rotates to the **next user** with
  feeds configured (round-robin), then checks all of that user's feeds.
- New items are imported with the **same title, image, and text**, assigned to
  that user as the post author, into their chosen category (blog posts), or into
  the Events / Podcasts / Videos content types.
- Every imported item ends with a **link to the original source** and a note that
  it is **republished with the permission of the author**. `rel="canonical"`
  points at the original article so Google credits the source (toggleable).
- Duplicates are skipped by feed GUID, so re-fetching never double-posts.

Admin dashboard: **Syndication** in the wp-admin menu — rotation status, who's
next, per-member "Fetch now" buttons, and site-wide settings (interval, imported
post status, max items per fetch, featured images, original dates, attribution,
canonical).

> **Reliable 5-minute ticks:** WP-Cron only fires when the site gets a visit. Add
> a real cron job in your hosting panel:
> `*/5 * * * * curl -s https://365community.online/wp-cron.php?doing_wp_cron > /dev/null`
> and optionally set `define( 'DISABLE_WP_CRON', true );` in `wp-config.php`.

## Content types & layouts

- **Blog posts** — standard posts; theme shows a source badge on cards and an
  attribution box under the article.
- **Events** (`/events/`) — manual entry via **Events → Add New** (start/end,
  location, registration URL) or imported from a member's events feed. Published
  events appear immediately with an event-details layout.
- **Podcasts** (`/podcasts/`) — imported from podcast RSS (audio enclosure +
  duration) with a built-in audio player layout; can also be added manually.
- **Videos** (`/videos/`) — imported from `https://www.youtube.com/feeds/videos.xml?channel_id=…`
  with a privacy-friendly `youtube-nocookie.com` embed layout and the YouTube
  thumbnail as featured image.

## Member author pages

Members manage everything themselves from **Users → Profile**:

- Profile photo URL (replaces Gravatar site-wide), cover image URL, tagline, bio.
- Links: website, blog, LinkedIn, X/Twitter, Bluesky, GitHub, YouTube, Mastodon.
- Section toggles for their public author page: blog posts, podcasts, videos,
  events, links, bio.

The author page (`/author/username/`) shows their cover, photo, tagline, bio,
link chips, then each enabled section as a card grid.

## Theme options

**Appearance → Customize → Community 365 Options**: accent colour, hero
heading/intro, show/hide hero, show/hide source badges, footer credit text.
Dark mode follows the visitor's OS preference automatically.

## For developers

- Imported content is stamped with post meta: `_c365_guid` (dedup),
  `_c365_source_url`, `_c365_source_name`; podcasts add `_c365_audio_url` /
  `_c365_duration`; videos add `_c365_video_id`; events use `_c365_event_start`,
  `_c365_event_end`, `_c365_event_location`, `_c365_event_url`.
- Reword the permission note with the `c365_attribution_html` filter.
- Any theme can declare `add_theme_support( 'c365-attribution' )` to take over
  rendering the attribution box (the plugin then stops appending its own).
