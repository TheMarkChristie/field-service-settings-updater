# 365 Community — Android app (Flutter)

The mobile reader for [365community.online](https://365community.online).
It talks to the **Syndicate Pro** plugin's REST API (`synpro/v1`) and shows
the community's **blogs, events, podcasts, and videos**, with offline
reading, category preferences, and push notifications for new content.

Built with **Flutter (Dart)**, minimum **Android 8.0 (API 26)**, distributed
via the **Google Play Store**.

## What it does

- **Four content tabs** — Blogs, Events, Podcasts, Videos (bottom navigation).
- **Sign in** with a WordPress username + **Application Password**. Signed-in
  members see the categories they follow and can download them; anonymous
  users see the **last 5 days of everything**.
- **Category preferences** — pick your topics (synced to your WordPress
  profile via the API); the feed and downloads follow them.
- **Offline reading** — the app keeps the latest *N* items per section
  (configurable in Settings: 10/25/50/100/200) in a local SQLite cache and
  falls back to it with no signal.
- **Native reader** — posts render natively (title, image, HTML body).
  Podcasts and videos **open externally** (podcast app / YouTube); event
  pages show date, location, and a tickets button.
- **Paid content** carries the same "Paid content" badge and disclosure as
  the website.
- **Push notifications** via Firebase Cloud Messaging when new content lands
  in the categories you follow.
- **Black + orange** brand matching the site; light/dark follows the device.

## Project layout

```
lib/
  config.dart                 API base URL, content types, offline defaults
  theme.dart                  black/orange Material 3 light + dark themes
  main.dart                   startup: load services, init Firebase, run app
  app.dart                    MaterialApp + theme wiring
  models/post.dart            unified content model (+ JSON & cache mapping)
  services/
    auth_service.dart         username + app password in secure storage
    api_client.dart           synpro/v1 client (feed, categories, preferences)
    feed_repository.dart      API + cache bridge (offline fallback)
    cache_service.dart        SQLite offline store, trimmed to the limit
    settings_service.dart     theme mode + offline limit (shared_preferences)
    push_service.dart         Firebase Cloud Messaging topics
  state/feed_controller.dart  per-tab pagination/refresh/error state
  screens/
    home_screen.dart          four tabs + account/categories/settings
    feed_tab.dart             list, pull-to-refresh, infinite scroll
    article_screen.dart       native reader + type-specific extras
    login_screen.dart         sign in / account
    categories_screen.dart    category preferences
    settings_screen.dart      theme, offline size, clear cache
```

The `lib/` app, `pubspec.yaml`, and `assets/` are committed. The generated
platform folders (`android/`, `ios/`) are **not** — regenerate them locally
(below), which keeps the repo clean and avoids committing fragile,
version-specific Gradle.

## First-time setup

1. **Install Flutter** (3.19+) and run once inside this folder to generate the
   platform scaffolding:
   ```bash
   flutter create --org online.community365 --project-name community365 .
   flutter pub get
   ```
2. **Android config** — apply `android_manifest_reference.xml` into
   `android/app/src/main/AndroidManifest.xml` (INTERNET + notification
   permissions, app label, `url_launcher` queries), and set the minimum SDK
   in `android/app/build.gradle`:
   ```gradle
   defaultConfig {
       applicationId "online.community365.app"
       minSdkVersion 26
       targetSdkVersion flutter.targetSdkVersion
   }
   ```
3. **Firebase (push)** — create a Firebase project, add an Android app with
   package `online.community365.app`, download `google-services.json` into
   `android/app/`, and run `flutterfire configure` to generate
   `lib/firebase_options.dart`. Until this is done the app runs fine but push
   is disabled (it fails open — see `push_service.dart`). Then pass the
   generated options to `Firebase.initializeApp()` in `main.dart`.
4. **Run**: `flutter run`.

## API contract (Syndicate Pro `synpro/v1`)

- `GET /feed?type=post|event|podcast|video&page=&per_page=` — a page of items.
  Signed-in (Basic auth with the application password) → the member's
  categories; anonymous → last 5 days. Returns `items[]`, `page`,
  `total_pages`, `logged_in`.
- `GET /categories` — id / name / count.
- `GET`/`POST /preferences` — the member's selected category IDs.

Each item: `id, type, title, excerpt, content, date, link, source_url,
image, author, paid, categories[]`, plus type extras (`audio_url`,
`duration`, `video_id`, `event_start/end/location/url`).

## Notes

- Authentication uses WordPress **Application Passwords** over HTTPS Basic
  auth — no plugin changes needed. A friendlier username+password (JWT) login
  can be added later if desired.
- Podcasts and videos deliberately open in the device's dedicated apps rather
  than an in-app player.
- App icons: drop source art in `assets/` and generate launcher icons with
  `flutter_launcher_icons` (add to dev_dependencies when you have the art).
