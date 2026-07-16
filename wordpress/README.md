# 365 Community — WordPress theme + syndication plugin

Syndicate Pro + Community 365: a self-contained replacement for the current
365community.online stack (WP Automatic etc.). Two installable packages, built only on WordPress core — no other plugins
required:

| Package | Folder | What it does |
|---|---|---|
| **Syndicate Pro** (plugin, v2.0.0) | `plugins/syndicate-pro` | The whole back end: member feed records (RSS and no-RSS web scraping), the 5-minute rotation, importing with de-duplication and full-text scrape, Events/Podcasts/Videos content types, post/social templates, member profiles, social auto-sharing, category fallback images, admin dashboard and wp-admin widgets. |
| **Community 365** (theme, v1.0.0) | `themes/community365` | Presentation: card-based magazine design with a distinct layout per content type, member author pages, source badges, dark mode. |

The full requirements are in [`docs/full-specification.md`](docs/full-specification.md).

## Installing

1. Zip each folder (or use the delivered zips), then in wp-admin:
   - **Plugins → Add New Plugin → Upload Plugin** → `syndicate-pro.zip` → Activate.
   - **Appearance → Themes → Add New Theme → Upload Theme** → `community365.zip` → Activate.
2. Go to **Settings → Permalinks** and click *Save Changes* once (registers the
   `/events/`, `/podcasts/`, `/videos/` URLs).
3. Add a hosting cron job for a reliable 5-minute rotation:
   `*/5 * * * * curl -s https://365community.online/wp-cron.php?doing_wp_cron > /dev/null`
   and optionally `define( 'DISABLE_WP_CRON', true );` in `wp-config.php`.
4. Once verified, deactivate WP Automatic and the other legacy plugins. Existing
   posts stay untouched — and the importer matches new feed items against them
   **by title** as well as feed GUID, so your 6 years of existing content is
   never duplicated.

## How the syndication works

- **Feed records**: each member (Contributor role and above) manages their own
  feeds on their profile screen (**Users → Profile**) — any number of feeds,
  each with a type (Blog RSS / **Web page (no RSS)** / Podcast RSS / YouTube
  channel / Events feed) and its own target category(ies). Admins see and
  manage everything under **Syndication** in wp-admin.
- A cron tick runs **every 5 minutes** and rotates to the **next member**
  (round-robin), checking all of that member's feeds.
- **First fetch of a new feed backfills its full history** (with social
  posting suppressed); after that, up to 5 new items per feed per fetch
  (configurable). Admins can also trigger **Run historic** per feed or
  **Run all historic (no social posting)** from the dashboard at any time.
- Imports keep the **original title, image (sideloaded to the Media Library),
  full text, and publish date**, are authored by the member, and land in the
  feed record's categories. **YouTube Shorts are skipped.**
- **Full text** toggle per feed: when a feed only carries summaries, the
  importer fetches the actual article page and extracts the complete body
  (used only when clearly more complete than the feed's own content).
- **Web page (no RSS)** sources: point a record at any listing-page URL and
  the importer discovers new article links itself, then scrapes each new
  article's title, date, `og:image`, and full body.
- Every syndicated item ends with a link to the **original source** and a note
  that it is **republished with the member's permission**; `rel="canonical"`
  points at the original.
- Duplicates are impossible: items are matched by feed GUID *and* by title
  (legacy posts get the GUID stamped on for fast future checks).
- **Featured image fallback chain**: item image → first image in the content
  → the **category's image** (set under Posts → Categories; downloaded once
  and reused) → none.
- If a feed fails 5 fetches in a row, the **site admin gets an email**; other
  feeds are unaffected.

## Templates

**Syndication → Templates** holds two editable templates per content type
(blog post / event / podcast episode / video):

- **Post body template** — how the imported post itself is built (HTML
  allowed; default `{content}` imports the original text unchanged).
- **Social post template** — the announcement wording for that type.

Placeholders: `{content}` `{title}` `{author}` `{excerpt}` `{link}`
`{source_name}` `{source_url}` `{date}` `{hashtags}`. Post-body placeholders
are filled at import time from the feed item; social placeholders at share
time from the created post.

## wp-admin Dashboard widgets

Admins see three widgets on the standard WordPress Dashboard: **Top posters
this month** (top 10 members by published items across all four types),
**Failing feeds** (consecutive failures, worst first), and **Unverified
members** (never logged in and never updated their profile — tracking starts
at plugin activation).

## Social auto-sharing

When new content is published (imported or manual), the plugin announces it on
the community's own accounts — **LinkedIn, Bluesky, Mastodon, X** — as:

```
New Post: {title} by {@handle or WordPress name}
{one-sentence excerpt}
{link}
{#category hashtags}
```

…with the **featured image attached**, using a different template per content
type (New Post / New Event / New Episode / New Video — editable under
**Syndication → Social sharing**, per-network toggles per type). Members are
credited by their @handle when their profile links provide one. Shares are
queued with retries; each post is shared exactly once per network.

Connect accounts under **Syndication → Social sharing** (each has a
"Send test post" button):

- **Mastodon** — instance URL + access token (Preferences → Development).
- **Bluesky** — handle + app password (Settings → App passwords).
- **X/Twitter** — developer app keys from developer.x.com (free tier has low
  monthly posting caps; heavy volume may need a paid tier).
- **LinkedIn** — organisation URN + OAuth token with `w_organization_social`
  (tokens expire and need renewing).

## Content types & layouts

- **Blog posts** — standard posts; source badge on cards, attribution box below.
- **Events** (`/events/`) — manual entry via **Events → Add New** (start/end,
  location, registration URL) or from a member's events feed; the archive lists
  **upcoming events first**, past ones below.
- **Podcasts** (`/podcasts/`) — audio streams from the member's host (built-in
  player layout); duration shown.
- **Videos** (`/videos/`) — privacy-friendly `youtube-nocookie.com` embeds with
  the YouTube thumbnail as featured image.

## Member author pages

Members manage everything from **Users → Profile**: feed records; profile photo
URL (replaces Gravatar site-wide); cover image; tagline; bio; links (website,
blog, LinkedIn, X, Bluesky, GitHub, YouTube, Mastodon); and toggles for which
sections show on their public author page (blogs / podcasts / videos / events /
links / bio).

## Theme options

**Appearance → Customize → Community 365 Options**: accent colour, hero
heading/intro, show/hide hero, show/hide source badges, footer credit text.
Dark mode follows the visitor's OS.

## For developers

- Feed records: `{prefix}synpro_feeds` table (user, type, feed_url, categories,
  active, backfilled, full_content, diagnostics), managed via the `Synpro_Feeds`
  class; schema auto-upgrades via `dbDelta` and `synpro_feeds_db_version`.
- Imported content meta: `_synpro_guid`, `_synpro_feed_id`, `_synpro_source_url`,
  `_synpro_source_name`; plus `_synpro_audio_url`/`_synpro_duration` (podcasts),
  `_synpro_video_id` (videos), `_synpro_event_*` (events); `_synpro_shared_<network>`
  marks completed social shares.
- Term meta: `synpro_category_image` (URL) and `synpro_category_image_id`
  (cached attachment) power the category fallback image.
- Options: `synpro_syndicator_settings`, `synpro_templates`,
  `synpro_social_settings`, `synpro_share_queue`, `synpro_rotation_pointer`.
- Filters: `synpro_attribution_html` (attribution wording),
  `synpro_alert_threshold` (failure alert threshold).
- Any theme can declare `add_theme_support( 'c365-attribution' )` to take over
  rendering the attribution box.
- A WordPress-stub smoke-test harness covering load, activation, rotation,
  imports, templates, scraping, social sharing, and widgets lives in the
  development scratchpad and runs on plain PHP — no WordPress install needed.
