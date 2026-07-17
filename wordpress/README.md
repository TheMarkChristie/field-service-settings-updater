# 365 Community — WordPress theme + syndication plugin

Syndicate Pro + Community 365: a self-contained replacement for the current
365community.online stack (WP Automatic etc.). Two installable packages, built only on WordPress core — no other plugins
required:

| Package | Folder | What it does |
|---|---|---|
| **Syndicate Pro** (plugin, v2.9.0) | `plugins/syndicate-pro` | The whole back end: member feed records (RSS and no-RSS web scraping), the 5-minute rotation, importing with de-duplication and full-text scrape, Events/Podcasts/Videos content types, post/social templates, member profiles, front-end Submit Content / My Account / Write-a-Post pages, paid/sponsored posts with PayPal, social auto-sharing, category fallback images, website digest subscribers with double opt-in, SMTP delivery, mobile-app REST API + Firebase push, admin dashboard and wp-admin widgets. |
| **Community 365** (theme, v2.6.0) | `themes/community365` | Presentation: black/orange/white magazine design (fully rebrandable: six-colour palette, fonts, logo + sponsor uploads), an 8-slot configurable home page, distinct layouts per content type, single-post sidebar, member author pages, paid-content badges, digest subscribe form, branded login, cookie/consent banner, `[events_calendar]` shortcode, fully responsive. |
| **365 Community** (Android app) | `../mobile/community365_app` | Flutter reader: four content tabs, Application-Password sign-in, category preferences, configurable offline cache, native reader, external media, Firebase push, black/orange brand. |

The full requirements are in [`docs/full-specification.md`](docs/full-specification.md),
and the step-by-step install/config/go-live guide is in
[`docs/deployment-runbook.md`](docs/deployment-runbook.md).

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

## Member emails and stats

- **Welcome email** — sent once when a member's first content goes live;
  editable subject/body under **Syndication → Emails** (placeholders
  `{name} {title} {link} {profile_url} {site_name}`).
- **Weekly digest** — every week: the **top 4 blog posts, the top YouTube
  video, the top podcast episode, and the newest event** — and nothing that
  was ever in an earlier digest (sent items are remembered). Recipients are
  all members who haven't opted out on their profile **plus everyone who
  subscribed on the website** (the `[synpro_subscribe]` form — the theme
  shows it in the Join us strip; every email to a website subscriber carries
  a one-click unsubscribe link). You can supply your **own HTML template**
  under Syndication → Emails (placeholders `{site_name} {intro} {items}
  {unsubscribe} {link}`), or leave it empty for the built-in design. Weeks
  with no unsent content skip silently.
- **View stats** — the plugin counts post views (all-time + per month) and
  members see a "Your content stats" panel on their profile screen: views
  this month, all-time, published items, top 3 most-read posts.

## Lifecycle automation

- **Content pruning** — daily, imported content older than **3 years with
  fewer than 50 views** goes to the bin (thresholds + toggle in Settings;
  100/day cap; manual content never touched; 30-day recovery). Consider
  leaving this OFF for a few months after install so view counts can
  accumulate first.
- **Redirect to original** — visitors opening a syndicated blog post are
  302-redirected to the author's site (view counted first; editors,
  previews, and `?noredirect=1` exempt; toggleable).

## The home page (theme)

The home page is an **8-slot component system** — assign each slot a
component in **Customize → Home Slot N**, every slot carrying the universal
filters (category include/exclude, date window: today / this week excl.
today / last week / this month, author include/exclude, item counts):

1. **News main block** — 3 feature cards left, Popular/Recent tabbed large
   feature centre, title list right (side columns have their own category
   and count).
2. **YouTube slider** — large in-place player with a numbered thumbnail
   playlist; clicking a card plays it in place.
3. **Popular posts** — card grid by views (last 30 days default), content
   types selectable, no view counts shown.
4. **Podcast slider** — newest episode auto-featured with a NEW badge and
   inline audio player; episode cards swap into the player.
5. **Events calendar** — monthly grid plus every scheduled event with
   Tickets / Speak / Website chips.
6. **Post blocks** — featured-image blog slider over three configurable
   blog columns.

Around the slots: the #category ticker, Join us social bars (editable
follower counts), Buy Me a Coffee, and a header sponsor slot. Single posts
get a sticky right sidebar (events calendar, configurable advert, three
social buttons, Buy Me a Coffee). The whole theme is responsive for mobile
and tablet (collapsing grids, swipe sliders, 44px touch targets) and ships
accessibility basics (focus outlines, reduced-motion support, semantic
markup).

## Member pages and login

Activation creates two front-end pages (add them to your menu):

- **Submit Content** (`/submit-content/`, the `[synpro_submit]` shortcode) —
  members must be **logged in** (visitors get a styled log in / create
  account card). They pick a **post type, category(ies), and a URL** — and
  the URL question changes with the type: blog → RSS feed URL, no-RSS blog
  → listing page URL, podcast → podcast RSS URL, YouTube → channel ID or
  URL, events → events feed URL. Submitting adds the source to their feed
  records, backfills its history on the next rotation, and imports new
  items automatically from then on. Their existing sources are listed
  below the form with a live status chip.
- **Write a Post** (`/write-a-post/`, the `[synpro_write]` shortcode) —
  for members **without a blog of their own**: a logged-in writing screen
  (rich text editor, category chips, optional featured-image upload) whose
  submissions go in as **Pending** for a **site admin to approve** from the
  normal Posts screen — the admin also gets an email with a review link.
  Once approved, the post behaves like any other (author page, social
  share, stats), but with **no source attribution or redirect** since it
  was written here. The member sees their on-site posts listed with
  Published / Awaiting approval status, and the Submit Content page
  cross-links here ("No blog? Write your post right here").
- **My Account** (`/my-account/`, the `[synpro_account]` shortcode) — a
  branded front-end profile: display name, bio, and the full set of
  syndication/author-page fields from the wp-admin profile screen (feeds,
  photos, links, section toggles, digest opt-out) — same fields, same
  save logic, no wp-admin needed.
- **Login / registration / lost password** — the theme restyles the
  WordPress login screen in the site's black/orange design with your logo.
  To let new members self-register, enable *Settings → General → Anyone
  can register* and set the default role to **Contributor** (submitting
  content requires Contributor or above).

## Paid (sponsored) posts

Any blog post can be sold as paid-for content, at two tiers (prices
editable under **Syndicate Pro → Settings → Paid content**; defaults
**£25** standard / **£100** featured):

- Tick **"This is paid-for content"** in the post editor's *Paid content*
  box and pick the tier. **Featured** posts are placed into your
  designated featured category (chosen in Settings — point a home-page
  slot at that category for their placement) and **drop out of it
  automatically after 7 days** (configurable), returning to the normal
  content cycle. The paid flag — and its disclosure — stays for good.
- **Disclosure is enforced by the plugin, not the theme**: every paid
  post shows a **"Paid content" badge over its featured image** wherever
  the image appears (cards, sliders, archives) and a **disclosure banner
  at the top of the post** when opened. A filter can reword the banner
  but cannot remove it, and the plugin injects fallback styling on any
  theme that doesn't provide its own.
- **PayPal payments**: add your PayPal email in Settings and each paid
  post's editor box shows a ready-made **PayPal payment link** for the
  right amount in GBP, tagged with the post ID so it's traceable in your
  PayPal activity — send it to the sponsor, then tick **"Payment
  received"** (date recorded). The Posts list gains a *Paid* column
  showing tier, featured-until date, and an **"awaiting payment"**
  warning until you tick it.

## Mobile app API (Android)

The plugin ships the back end for the Android app as a REST API under
`/wp-json/synpro/v1/` (the app itself is a separate project):

- `GET /feed` — blog posts for the app, paginated. **Logged-in users**
  (authenticate with a core WordPress **Application Password**) receive
  only the categories they've selected; **anonymous requests** receive
  everything, but only the **last 5 days**. Each item carries title,
  excerpt, full content, date, link, original source URL, image, author,
  and categories.
- `GET /categories` — the category list for the app's picker.
- `GET/POST /preferences` — read/save the logged-in user's selected
  category IDs (stored per user; the same selection applies on every
  device they sign in on).

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

**Appearance → Customize → Branding — logos, colours, fonts** gathers the
whole brand in one place:

- **Logos** — upload the **website logo** (shown in the header and on the
  login screen) and the **header sponsor**: an editable label (default
  "Sponsored by"), the sponsor's **logo upload**, and an optional
  **click-through link** (plus an advanced free-form HTML override).
  Leave the sponsor logo empty to hide the slot.
- **Six palette colours** — **primary** (buttons, highlights, badges,
  ticker), **secondary** (tags, hover flourishes, kind labels),
  **tertiary** (muted text: dates, bylines, help text), **hyperlink**,
  **hyperlink clicked** (visited — applied in article bodies and
  comments), and **background**. The theme derives everything else:
  card surfaces, borders, and body text follow the background (pick a
  light background and text goes dark automatically), and text on
  primary-coloured elements auto-flips between white and black so
  buttons stay readable whatever you pick. Shipped defaults all pass
  WCAG AA contrast.
- **Fonts** — a font choice (six bundled system stacks: sans, Helvetica,
  Verdana, Trebuchet, Georgia, Palatino — no external font downloads)
  and a **base font size** (14–20px); headings scale with it.

**Community 365 Options** keeps the rest: hero heading/intro, show/hide
hero, show/hide source badges, footer credit text.

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
- Website digest subscribers: `{prefix}synpro_subscribers` table (email +
  unsubscribe token), managed via `Synpro_Subscribers`; already-sent digest
  content IDs live in the `synpro_digest_sent` option; the app's per-user
  category picks in `synpro_app_cats` user meta.
- REST API: namespace `synpro/v1` (`/feed`, `/categories`, `/preferences`),
  authenticated with core Application Passwords.
- Options: `synpro_syndicator_settings`, `synpro_templates`,
  `synpro_social_settings`, `synpro_share_queue`, `synpro_rotation_pointer`,
  `synpro_digest_sent`.
- Filters: `synpro_attribution_html` (attribution wording),
  `synpro_alert_threshold` (failure alert threshold).
- Any theme can declare `add_theme_support( 'c365-attribution' )` to take over
  rendering the attribution box.
- A WordPress-stub smoke-test harness covering load, activation, rotation,
  imports, templates, scraping, social sharing, and widgets lives in the
  development scratchpad and runs on plain PHP — no WordPress install needed.
