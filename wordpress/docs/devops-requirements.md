# 365 Community — Product Backlog (Azure DevOps ready)

Business-facing user stories and acceptance criteria for the whole 365
Community platform: the **Syndicate Pro** plugin, the **Community 365**
theme, and the **365 Community** Android app. Written system-agnostically
in the standard *As a … I want … so that …* form with testable acceptance
criteria, ready for Azure DevOps refinement and import.

- **Companion CSV for import:** [`devops-backlog.csv`](devops-backlog.csv)
  (Epic → Feature → User Story hierarchy via the Title 1/2/3 columns).
- **As-built reference:** [`full-specification.md`](full-specification.md).
- **Deploy/test:** [`deployment-runbook.md`](deployment-runbook.md).

## Conventions

- **Hierarchy:** Epic → Feature → User Story. IDs `US-nnn` are stable
  handles for traceability.
- **Estimate template** per story: *Design / Build / Develop / Test*
  (indicative days, to be confirmed at refinement).
- **Roles:** *Visitor* (anonymous), *Member* (Contributor+ WordPress user),
  *Site Admin*, *App User* (anonymous or signed-in on the mobile app).
- Acceptance criteria are testable and describe *what*, not *how*. The
  technical design is in the as-built specification.

---

## Epic 1 — Content Syndication Engine

Automatically republishing community members' content onto the site.

### Feature 1.1 — Member feed records

**US-001 — Manage my content sources**
*As a* Member, *I want* to register any number of content sources (blog
RSS, a web page with no RSS, podcast RSS, a YouTube channel, or an events
feed), each mapped to one or more categories, *so that* my content is
pulled onto the community site automatically.
**Acceptance criteria**
- A member can add, edit, pause, and delete feed records from their profile
  and from the front-end Submit Content page.
- Each record has a type, a URL/identifier, target category(ies), and a
  full-text toggle.
- A YouTube record accepts a channel ID (UC…) or channel URL; an invalid
  channel identifier is rejected with a clear message.
- A "web page (no RSS)" record accepts a listing-page URL.
- Only members with publishing rights (Contributor and above) can manage
  feeds.
*Estimate: 1 / 3 / 4 / 2*

**US-002 — Round-robin fetch every 5 minutes**
*As a* Site Admin, *I want* the system to check one member's feeds every 5
minutes in rotation, *so that* new content appears promptly without
overloading the server or any single source.
**Acceptance criteria**
- A scheduled task runs every 5 minutes and advances to the next member.
- The rotation pointer persists and wraps around all eligible members.
- The interval is configurable; changing it reschedules the task.
- The schedule self-heals if the scheduled event is lost.
*Estimate: 1 / 2 / 3 / 2*

### Feature 1.2 — Importing and de-duplication

**US-003 — Import new items faithfully**
*As a* Member, *I want* new items imported with their original title,
image, full text, and publish date under my name, *so that* my work is
represented accurately and credited to me.
**Acceptance criteria**
- Imported items keep the original title, sideloaded featured image, body,
  and date, and are authored by the member.
- Each item ends with a link to the original source and a "republished with
  permission" note; a canonical link points to the original.
- Items land in the feed record's target category(ies).
- Up to 5 new items are imported per feed per run (configurable).
- YouTube Shorts are skipped.
*Estimate: 2 / 4 / 5 / 3*

**US-004 — Never duplicate existing content**
*As a* Site Admin, *I want* imported items matched against existing content
by unique identifier and by title, *so that* six years of existing posts
are never duplicated.
**Acceptance criteria**
- An item already present (by feed GUID or by matching title) is not
  re-imported.
- A legacy post matched by title has the feed GUID stamped onto it for fast
  future checks.
- De-duplication considers trashed items so removed imports don't return.
*Estimate: 1 / 3 / 4 / 3*

**US-005 — Backfill history on first fetch**
*As a* Member, *I want* my full back-catalogue imported the first time a
feed is checked, *so that* my existing content is available immediately.
**Acceptance criteria**
- The first fetch of a new feed imports its full available history,
  uncapped by the per-run limit.
- Social sharing and push notifications are suppressed during backfill.
- A "Run all historic (no social posting)" and per-feed "Run historic"
  action is available to admins on demand.
*Estimate: 1 / 3 / 3 / 2*

### Feature 1.3 — Source handling and resilience

**US-006 — Full-text and no-RSS scraping**
*As a* Member whose feed only carries summaries or has no feed at all,
*I want* the system to fetch the full article from my site, *so that* the
complete post is republished.
**Acceptance criteria**
- With the full-text toggle on, the importer fetches the article page and
  extracts the complete body when clearly more complete than the feed.
- A no-RSS source discovers new article links from a listing page and
  scrapes each new article's title, date, image, and body.
- Outbound fetches of member-supplied URLs are blocked from reaching
  private, loopback, or internal network addresses.
*Estimate: 2 / 4 / 5 / 3*

**US-007 — Category fallback image**
*As a* Site Admin, *I want* a category's image used when an imported post
has none, *so that* every card has a picture.
**Acceptance criteria**
- Featured image resolves in order: item image → first content image →
  category image → none.
- A category image is set per category and downloaded once, then reused.
*Estimate: 1 / 1 / 2 / 1*

**US-008 — Alert on failing feeds**
*As a* Site Admin, *I want* an email after a feed fails several times in a
row, *so that* I can fix broken sources.
**Acceptance criteria**
- After 5 consecutive failures for a feed, the admin email address is
  notified, with the feed and last error.
- Other feeds are unaffected by one failing feed.
- Failing feeds are listed on the admin dashboard, worst first.
*Estimate: 1 / 2 / 2 / 1*

---

## Epic 2 — Content Types & Presentation

Distinct handling and layouts for blogs, events, podcasts, and videos.

**US-009 — Dedicated content types**
*As a* Visitor, *I want* events, podcasts, and videos presented as their
own content types with appropriate layouts, *so that* each reads the way it
should.
**Acceptance criteria**
- Events, podcasts, and videos have their own archives (`/events/`,
  `/podcasts/`, `/videos/`).
- Blog layout shows the full article; video shows title, embed, and
  description; podcast shows a player and notes; event shows name, date,
  location, organiser, and Tickets / Call-for-speakers / Website actions.
- The events archive lists upcoming events first, past events after.
- Video embeds are privacy-friendly; podcast episodes show duration.
*Estimate: 3 / 5 / 6 / 4*

**US-010 — Configurable home page**
*As a* Site Admin, *I want* an 8-slot home page where each slot is a chosen
component with its own filters, *so that* I control the front page without
code.
**Acceptance criteria**
- Each of 8 slots can be assigned a component: news block, YouTube slider,
  popular posts, podcast slider, events calendar, or post blocks.
- Every component supports category include/exclude, a date window, author
  include/exclude, and item counts.
- Sliders play media in place; empty items are skipped.
- The home page is fully responsive.
*Estimate: 4 / 8 / 10 / 5*

**US-011 — Single-post sidebar**
*As a* Visitor, *I want* a sticky sidebar on posts with an events calendar,
an advert, social buttons, and a Buy Me a Coffee link, *so that* I can
explore and support the community.
**Acceptance criteria**
- The sidebar shows a monthly events calendar with event days linked and
  month navigation.
- The advert (image+link or HTML) is configurable and marked as sponsored.
- Three social buttons and a coffee link are configurable; the whole
  sidebar has one master toggle.
*Estimate: 2 / 3 / 4 / 2*

---

## Epic 3 — Member Experience & Contribution

Front-end pages so members never need wp-admin.

**US-012 — Submit a content source (front end)**
*As a* Member, *I want* a front-end page to add a content source by
choosing a type, categories, and a URL, *so that* I can contribute without
using the admin area.
**Acceptance criteria**
- Logged-out visitors see a styled prompt to log in or register.
- The URL question's label, placeholder, and help change with the selected
  type.
- Submitting adds a feed record and lists the member's existing sources with
  a status indicator.
*Estimate: 2 / 3 / 4 / 2*

**US-013 — Write a post on the site with approval**
*As a* Member without a blog, *I want* to write a post on the site that goes
for admin approval, *so that* I can contribute original content.
**Acceptance criteria**
- A front-end editor captures title, rich-text body, categories, and an
  optional featured image (images only).
- Submissions are saved as pending; the admin is emailed a review link.
- Empty or whitespace-only submissions are rejected.
- On approval the post behaves like any other (author page, stats, social)
  with no source attribution or redirect.
*Estimate: 2 / 4 / 4 / 3*

**US-014 — Manage my profile and author page (front end)**
*As a* Member, *I want* to manage my display name, bio, photos, links, and
which sections show on my author page, *so that* I control my public
presence.
**Acceptance criteria**
- A front-end My Account page edits the same profile fields as the admin
  screen and saves through the same validated path.
- The member can toggle blog/podcast/video/event/links/bio sections and set
  their digest preference.
- The profile photo replaces the default avatar site-wide.
*Estimate: 2 / 3 / 4 / 2*

**US-015 — Branded sign-in, registration, and password reset**
*As a* Visitor, *I want* the login, registration, and password screens
styled to the site, *so that* joining feels part of the brand.
**Acceptance criteria**
- The login/registration/lost-password screens use the site's colours and
  logo.
- Button and field colours stay readable under any configured palette.
- Self-registration can be enabled with a default publishing role.
*Estimate: 1 / 2 / 2 / 1*

**US-016 — Member stats on profile**
*As a* Member, *I want* to see my content's view stats, *so that* I know how
my posts perform.
**Acceptance criteria**
- Views are counted per post (all-time and per month).
- The profile shows views this month, all-time, published count, and top 3
  most-read posts.
*Estimate: 1 / 2 / 3 / 2*

---

## Epic 4 — Monetisation (Paid Content)

Selling sponsored placement transparently.

**US-017 — Mark a post as paid at a tier**
*As a* Site Admin, *I want* to mark a post as paid at a standard or featured
tier with configurable prices, *so that* I can monetise placement.
**Acceptance criteria**
- A post can be flagged paid as Standard (default £25) or Featured (default
  £100); prices are configurable.
- A Featured post joins the designated featured category and leaves it
  automatically after a configurable number of days (default 7), returning
  to the normal cycle; the paid status remains.
- Downgrading Featured to Standard removes the featured placement
  immediately.
*Estimate: 2 / 4 / 4 / 3*

**US-018 — Always disclose paid content**
*As a* Visitor, *I want* paid content clearly labelled, *so that* I know
when placement was paid for.
**Acceptance criteria**
- Every paid post shows a "Paid content" badge over its image wherever the
  image appears, and a disclosure banner when opened.
- The disclosure cannot be removed by configuration and persists after the
  featured window ends.
- The badge and banner appear in the mobile app too.
*Estimate: 1 / 2 / 3 / 2*

**US-019 — Take payment via PayPal**
*As a* Site Admin, *I want* a ready-made PayPal payment link per paid post
and a way to record receipt, *so that* I can invoice and track sponsors.
**Acceptance criteria**
- With a PayPal business email configured, each paid post shows a payment
  link for the correct amount in the site currency, tagged with the post ID.
- A "payment received" control records the date; the posts list shows tier,
  featured-until, and an outstanding-payment indicator.
*Estimate: 1 / 3 / 3 / 2*

---

## Epic 5 — Community Communications

Social sharing and email.

**US-020 — Auto-share new content to social accounts**
*As a* Site Admin, *I want* new content announced on the community's
LinkedIn, Bluesky, Mastodon, and X accounts, *so that* it reaches our
followers automatically.
**Acceptance criteria**
- On publish (not backfill), an announcement is queued per enabled network:
  "New Post: {title} by {@handle}", an excerpt, the link, category hashtags,
  and the featured image.
- Templates are editable per content type and per network; each post is
  shared exactly once per network with retries.
- Members are credited by @handle when their profile links provide one.
- Credentials are entered in the admin only and stored securely.
*Estimate: 3 / 5 / 6 / 4*

**US-021 — Welcome email on first post**
*As a* Member, *I want* a welcome email when my first content goes live,
*so that* I feel acknowledged and learn how the site works.
**Acceptance criteria**
- Sent once, ever, when a member's first item is published.
- Subject and body are editable with placeholders; it can be disabled.
*Estimate: 1 / 1 / 2 / 1*

**US-022 — Weekly digest**
*As a* Subscriber, *I want* a weekly digest of the best new content, *so
that* I keep up without visiting daily.
**Acceptance criteria**
- Weekly, the digest contains the top 4 blogs, the top video, the top
  podcast, and the newest event, never repeating previously sent items.
- Recipients are members who haven't opted out plus confirmed website
  subscribers; website subscribers get a one-click unsubscribe.
- A custom HTML template is supported; quiet weeks are skipped.
*Estimate: 2 / 4 / 4 / 3*

**US-023 — Website subscribe with double opt-in**
*As a* Visitor, *I want* to subscribe to the digest on the website and
confirm by email, *so that* only I can add my address.
**Acceptance criteria**
- A subscribe form (spam-protected, rate-limited) sends a confirmation
  email; only confirmed addresses receive the digest.
- The form response never reveals whether an address is already subscribed.
- Admins see confirmed vs pending counts.
*Estimate: 1 / 3 / 3 / 2*

**US-024 — Reliable email delivery (SMTP)**
*As a* Site Admin, *I want* to send email through an authenticated SMTP
service, *so that* digests reach inboxes rather than spam.
**Acceptance criteria**
- SMTP host, port, encryption, auth, and from-address are configurable; the
  password is stored securely and never displayed.
- When enabled, all site email is sent via SMTP; when disabled, default mail
  is used.
*Estimate: 1 / 2 / 2 / 2*

---

## Epic 6 — Mobile App (Android)

A Flutter reader over the site's API.

**US-025 — Browse four content types**
*As an* App User, *I want* to browse blogs, events, podcasts, and videos in
the app, *so that* I can read the community on my phone.
**Acceptance criteria**
- Four tabs list each content type with pull-to-refresh and infinite scroll.
- Each item shows image, type, date, title, excerpt, and author; paid items
  show the disclosure.
- The app targets Android 8.0 and up.
*Estimate: 3 / 6 / 8 / 4*

**US-026 — Sign in and follow categories**
*As an* App User, *I want* to sign in and choose the categories I follow,
*so that* my feed and downloads match my interests.
**Acceptance criteria**
- Sign-in uses a WordPress username + application password over a secure
  connection; credentials are stored in the device secure store.
- Signed-in users see their followed categories; anonymous users see the
  last 5 days of everything.
- Category preferences are saved to the member's profile via the API.
*Estimate: 2 / 4 / 5 / 3*

**US-027 — Read offline**
*As an* App User, *I want* to keep the latest items for offline reading with
a size I choose, *so that* I can read with no signal.
**Acceptance criteria**
- The latest N items per section (member-chosen: 10/25/50/100/200) are kept
  locally and shown when offline.
- Lowering the limit trims stored items; a control clears offline content.
*Estimate: 2 / 4 / 4 / 3*

**US-028 — Native reader and external media**
*As an* App User, *I want* posts to read natively and media to open in the
right app, *so that* reading is fast and playback uses the best player.
**Acceptance criteria**
- Post title, image, and body render natively; source links open the
  original.
- Podcasts and videos open in the device's browser/YouTube/podcast app.
- Events show date, location, and a tickets action.
*Estimate: 2 / 4 / 5 / 3*

**US-029 — Search and save in the app**
*As an* App User, *I want* to search all content and save items, *so that* I
can find and keep what matters.
**Acceptance criteria**
- Search queries all four types and merges results newest-first.
- Any item can be saved to a local list that survives clearing the offline
  cache.
*Estimate: 2 / 3 / 4 / 2*

**US-030 — Push notifications for new content**
*As an* App User, *I want* a notification when new content lands in the
categories I follow, *so that* I don't miss it.
**Acceptance criteria**
- New published content triggers a push to a general topic and the post's
  category topics; historic imports never notify.
- Members following specific categories are notified for those; the general
  topic can be turned off.
- The admin configures push securely and can send a test notification.
*Estimate: 2 / 4 / 4 / 3*

---

## Epic 7 — Branding, Search & Site Chrome

Making the site owner's own.

**US-031 — Full branding control**
*As a* Site Admin, *I want* to set logos, a six-colour palette, and fonts,
*so that* the site matches our brand without code.
**Acceptance criteria**
- Website logo and header sponsor (label, logo, link) are uploadable.
- Six roles are configurable: primary, secondary, tertiary, hyperlink,
  hyperlink-visited, background; other colours (surfaces, borders, body
  text, button text) derive from these and stay readable/accessible.
- A font family and base size are selectable; defaults meet contrast
  standards.
*Estimate: 2 / 4 / 5 / 3*

**US-032 — Site search across all content types**
*As a* Visitor, *I want* to search the site and find blogs, events,
podcasts, and videos, *so that* I can locate content quickly.
**Acceptance criteria**
- A header search control reveals a search field; results render as cards
  with pagination.
- Search covers all four content types, not just blog posts.
- The standalone events calendar is available as a shortcode for any page.
*Estimate: 1 / 3 / 3 / 2*

---

## Epic 8 — Operations, Compliance & Lifecycle

Running and governing the platform.

**US-033 — Admin dashboard widgets**
*As a* Site Admin, *I want* at-a-glance widgets for top posters, failing
feeds, and unverified members, *so that* I can manage the community.
**Acceptance criteria**
- Widgets show top 10 posters this month, failing feeds worst-first, and
  members who've never logged in or updated their profile.
*Estimate: 1 / 2 / 3 / 1*

**US-034 — Redirect syndicated posts to the original**
*As a* Site Admin, *I want* visitors on a syndicated blog post redirected to
the author's site after the view is counted, *so that* authors get their
traffic.
**Acceptance criteria**
- Syndicated blog posts redirect to the original; the view is counted first;
  editors, previews, and an override are exempt; the behaviour is
  toggleable.
*Estimate: 1 / 1 / 2 / 2*

**US-035 — Auto-prune stale low-interest content**
*As a* Site Admin, *I want* imported content older than a threshold with few
views moved to the bin, *so that* the site stays fresh.
**Acceptance criteria**
- Daily, imported content older than the configured years with fewer than
  the configured views is binned (default 3 years / 50 views), capped per
  run, recoverable, and never touching manual content; the whole feature is
  toggleable.
*Estimate: 1 / 2 / 2 / 2*

**US-036 — Cookie/consent banner**
*As a* Site Admin, *I want* a dismissible cookie banner linking to our
policy, *so that* we meet consent expectations.
**Acceptance criteria**
- A configurable banner (enable, text, policy-page link) shows once and is
  remembered per device; it is accessible.
*Estimate: 1 / 1 / 2 / 1*

**US-037 — Clean install, upgrade, and uninstall**
*As a* Site Admin, *I want* the platform to install, upgrade, and uninstall
cleanly, *so that* operations are safe.
**Acceptance criteria**
- Activation creates tables, pages, content types, and schedules with no
  errors; schema upgrades run automatically and preserve data.
- Uninstall removes settings, tables, and per-user syndication data but
  keeps imported content and media.
- Secrets are stored so they don't load on every request and are never
  exposed via the settings API.
*Estimate: 1 / 3 / 3 / 3*

**US-038 — Deployment and staging verification**
*As a* Site Admin, *I want* a documented install/config/go-live and rollback
procedure with a staging checklist, *so that* I can launch confidently.
**Acceptance criteria**
- A runbook covers install, configuration, SMTP/DNS, the app and push
  bootstrap, a staging acceptance checklist, go-live, and rollback.
- The platform is verified on a staging copy of the live content before
  production.
*Estimate: 1 / 1 / 1 / 3*

---

## Notes for refinement

- Estimates are indicative (design/build/develop/test days) for planning and
  should be confirmed during refinement.
- Stories US-013 (write-a-post) and US-020 (social) are the largest and may
  each be split further (e.g. per network) if the team prefers finer slices.
- Non-functional requirements — responsiveness, WCAG AA contrast,
  performance (no external fonts/scripts), and security (nonces, capability
  checks, SSRF guard, secret storage) — apply across all stories and are
  captured in the as-built specification's non-functional section.
