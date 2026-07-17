# 365 Community — Deployment Runbook

A step-by-step guide to installing, configuring, testing, and going live
with **Syndicate Pro** (plugin), **Community 365** (theme), and the
**365 Community** Android app. Follow it top to bottom for a new install;
each section is self-contained for later reference.

> **Golden rule:** do the whole of Part 1–4 on a **staging copy** of
> 365community.online first. Nothing here has been run against your real
> content yet — staging is where you find the surprises.

---

## Part 0 — Before you start

You'll need:

- Admin access to a **staging** WordPress site (a clone of the live site,
  ideally with a copy of the real database so de-duplication is tested
  against your ~6 years of posts).
- The two zips: `syndicate-pro.zip` and `community365.zip`.
- Ability to add a **hosting cron job** (or a wp-cron alternative).
- For email at volume: an **SMTP service** (your host's, or SendGrid /
  Mailgun / Brevo / Amazon SES) and access to your domain's DNS (SPF/DKIM).
- For the app: a **Google Play developer account** ($25 one-off) and a
  **Firebase project** (free).

Take a full **database + files backup** of staging before you begin.

---

## Part 1 — Install the plugin and theme

1. **Plugins → Add New → Upload Plugin** → `syndicate-pro.zip` → *Install*
   → *Activate*. Activation creates the feed + subscriber tables, the three
   member pages (Submit Content, My Account, Write a Post), registers the
   Events/Podcasts/Videos content types, and schedules the cron jobs.
2. **Appearance → Themes → Add New → Upload Theme** → `community365.zip` →
   *Activate*.
3. **Settings → Permalinks → Save Changes** (once, no changes needed) — this
   flushes rewrite rules so `/events/`, `/podcasts/`, `/videos/` work.
4. Confirm there are **no PHP errors** (check the site and, if you can,
   `wp-content/debug.log` with `WP_DEBUG` on). If activation fatals, note the
   exact error + PHP version before anything else.

### Reliable cron (required for the 5-minute rotation)

WordPress's built-in cron only fires on page visits. Add a real cron job:

```
*/5 * * * * curl -s "https://STAGING-URL/wp-cron.php?doing_wp_cron" >/dev/null 2>&1
```

and, in `wp-config.php`:

```php
define( 'DISABLE_WP_CRON', true );
```

---

## Part 2 — Configure the back end (Syndicate Pro)

All under the **Syndicate Pro** admin menu.

1. **Settings tab** — review the rotation interval (default 5 min), import
   options, attribution/canonical/redirect toggles, content-pruning
   thresholds (**consider leaving pruning OFF for the first few months** so
   view counts accumulate), and the **Paid content** prices + featured
   category + your **PayPal email**.
2. **Templates tab** — optional per-type post/social templates.
3. **Social sharing tab** — connect LinkedIn / Bluesky / Mastodon / X and use
   each *Send test post*. Credentials are entered here only, never in chat.
4. **Emails tab** — welcome email, weekly digest (subject/intro/HTML
   template), and **SMTP** (below). Website-subscriber counts show here.
5. **Mobile app tab** — REST API endpoints + **Firebase push** (Part 5).

### SMTP (email deliverability)

On the **Emails tab → Email delivery (SMTP)**: tick *Use SMTP*, enter your
service's host / port / encryption / username / password and a **From
address on your own domain**. Then set **SPF** and **DKIM** DNS records for
that domain per your SMTP provider's instructions — without them, digest
mail to hundreds of addresses will land in spam regardless of SMTP. Send
yourself a welcome/digest test and confirm it arrives in the inbox.

### Members and feeds

- Set new-member default role appropriately: publishing (feeds, Submit
  Content, Write a Post) needs **Contributor or above**. To let people
  self-register, **Settings → General → Anyone can register** + default role
  Contributor.
- Add a test member, give them a blog RSS feed on their profile (or via the
  **Submit Content** page), and run **Run historic** — verify posts import,
  de-duplicate against existing content, and are attributed correctly.

---

## Part 3 — Configure the theme (Community 365)

**Appearance → Customize:**

1. **Branding** — upload the website logo and (optional) sponsor logo + label
   + link; set the six palette colours and the font/size. Check contrast in
   both light and dark.
2. **Home page** — assign each of the 8 slots a component (news / YouTube /
   popular / podcast / events / post blocks) and set its filters.
3. **Community 365 Options** — hero, source badges, footer text, and the
   **cookie banner** (enable, text, and link it to your privacy-policy page).
4. **Post sidebar** — advert, three social buttons, Buy Me a Coffee, calendar.
5. Add the three member pages and any content pages to your **menus**
   (Appearance → Menus). Drop `[events_calendar]` on any page that needs the
   standalone calendar.

Then walk the site on a phone and a tablet: home, an article, an event, a
podcast, a video, an author page, the login screen, and the three member
pages.

---

## Part 4 — Staging acceptance checklist

Tick every box on staging before touching production:

- [ ] Plugin + theme activate with no PHP errors.
- [ ] `/events/`, `/podcasts/`, `/videos/` resolve.
- [ ] A member feed imports on the 5-minute tick; **no duplicates** of
      existing posts; attribution + canonical present; YouTube Shorts skipped.
- [ ] **Run historic** backfills without social-posting or push.
- [ ] Category fallback image used when a post has no image.
- [ ] A social **test post** succeeds on each connected network.
- [ ] Welcome email + a **digest test** arrive **in the inbox** (SMTP + SPF/
      DKIM working).
- [ ] Website subscribe → **confirmation email** arrives → confirming adds
      them → unsubscribe link works.
- [ ] Paid post: badge over image + disclosure banner; featured post enters
      the featured category and the PayPal link is correct.
- [ ] Submit Content, Write-a-Post (→ pending → admin approval), My Account
      all work for a Contributor test user.
- [ ] Cookie banner shows once and stays dismissed.
- [ ] Redirect-to-original and view counting behave; pruning left off for now.
- [ ] Mobile app (Part 5) lists all four types, signs in, filters by
      category, and reads offline.

---

## Part 5 — The Android app

The app lives in `mobile/community365_app` (Flutter). See its own README for
the full detail; the deployment-critical steps:

1. **Bootstrap** (one-time, on a dev machine with Flutter 3.19+):
   ```bash
   cd mobile/community365_app
   flutter create --org online.community365 --project-name community365 .
   flutter pub get
   ```
   Then apply `android_manifest_reference.xml` into the generated manifest and
   set `minSdkVersion 26` + `applicationId "online.community365.app"`.
2. **Firebase** — create a Firebase project; add an Android app with package
   `online.community365.app`; download `google-services.json` into
   `android/app/`; run `flutterfire configure` to generate
   `lib/firebase_options.dart`; pass those options to
   `Firebase.initializeApp()` in `main.dart`.
3. **Push on the WordPress side** — in **Syndicate Pro → Mobile app**, enable
   push, paste the Firebase **Project ID** and a **service-account JSON**
   (Firebase Console → Project settings → Service accounts → Generate new
   private key), save, and hit **Send test notification**.
4. **Build + test**: `flutter run` on a device. Verify the four tabs, sign-in
   with an Application Password (Users → Profile → Application Passwords),
   category filtering, offline reading, and a live push.
5. **Play Store**: `flutter build appbundle`, sign it, and upload to a Play
   Console internal-testing track first, then production.

---

## Part 6 — Go live (production)

1. Re-take a production backup.
2. Install the plugin + theme on production exactly as Part 1; re-save
   permalinks; add the production cron job.
3. Re-enter the production Social, SMTP, PayPal, and Firebase credentials
   (they don't transfer from staging).
4. **First fetch backfills each feed's full history** with social + push
   suppressed — expect a burst of imports; let it settle.
5. Only once you're happy: **deactivate WP Automatic / AIPublish and the
   other legacy plugins.** Existing posts are untouched and de-duplicated
   against.
6. Watch the wp-admin **Dashboard widgets** (top posters, failing feeds,
   unverified members) and the site's error log for the first few days.
7. Consider enabling **content pruning** only after a few months, once view
   counts are meaningful.

---

## Part 7 — Rollback

- **Theme problem** → switch back to your previous theme; the plugin keeps
  running.
- **Plugin problem** → deactivate Syndicate Pro. Imported posts, events,
  podcasts, videos, and media **remain** (they're your content). Re-activate
  when fixed; the feed/subscriber tables and settings persist.
- **Bad import run** → imported items are normal posts: bin them from the
  Posts screen; the GUID/title de-dup stops re-import.
- **Full uninstall** removes options, the feed + subscriber tables, and
  per-user syndication meta, but **keeps** imported content and media.

---

## Appendix — What lives where

| Concern | Location |
|---|---|
| Rotation, imports, de-dup, scrape | `Synpro_Fetcher`, `Synpro_Source_*`, `Synpro_Scraper` |
| Feed records table | `{prefix}synpro_feeds` (`Synpro_Feeds`) |
| Subscribers table (double opt-in) | `{prefix}synpro_subscribers` (`Synpro_Subscribers`) |
| Social sharing | `Synpro_Social` (option `synpro_social_settings`) |
| Emails + SMTP | `Synpro_Emails` (options `synpro_email_settings`, `synpro_smtp_settings`) |
| Paid content | `Synpro_Paid` (post meta `_synpro_paid*`) |
| Push (FCM) | `Synpro_Push` (option `synpro_push_settings`) |
| REST API | `Synpro_Api` (`synpro/v1`) |
| Member pages | `Synpro_Pages` (`[synpro_submit]` / `[synpro_account]` / `[synpro_write]`) |
| Settings + all admin tabs | `Synpro_Settings` |
| Theme branding / palette | `community365_brand_css()` + `inc/customizer.php` |
| Home slots | `inc/home-slots.php` |
