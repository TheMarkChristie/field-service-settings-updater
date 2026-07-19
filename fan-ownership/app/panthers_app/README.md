# FanPress App (panthers_app)

The white-label fan-ownership mobile app (Flutter) — Boardroom voting,
Match Centre, FanPress Chat, profiles, and more. It consumes the
WordPress plugin's `prx3/v1` REST API and themes itself per club from
`/me` (brand pack), so one codebase serves any club.

> Status: skeleton (login, dashboard, ballots, match, videos wired to
> the API). Full parity with the web member experience is the target —
> see `../../spec/discovery-technical-and-mobile.md`.

## Prerequisites

- Flutter (stable) with Dart ≥ 3.3 — https://docs.flutter.dev/get-started/install
- Chrome for web; an Android/iOS toolchain for device builds
- `flutter doctor` clean for your target platforms

## Run

```bash
flutter pub get

# Web (quickest to see it):
flutter run -d chrome --dart-define=PRX3_API=https://your-site/wp-json/prx3/v1

# Android / iOS device or emulator:
flutter run --dart-define=PRX3_API=https://your-site/wp-json/prx3/v1
```

`PRX3_API` points the app at a live plugin install. Without it, it
defaults to a placeholder host and the login call won't resolve.

## Build

```bash
flutter build web        # static bundle in build/web
flutter build apk        # Android
flutter build ipa        # iOS (needs a Mac + signing)
```

## Building with Claude (Dart MCP bridge)

This project ships a `.mcp.json` registering the Dart/Flutter MCP server
(`dart mcp-server`). Open the project in Claude Code and it can drive a
running app — hot reload, read runtime errors, inspect the widget tree,
run tests — instead of guessing from source. Start the app first
(`flutter run`), then let Claude connect via the `dart` MCP server.
Requires a Dart SDK with the built-in MCP server.

## Layout

```
lib/
  main.dart            App shell, per-club theming from /me
  api/prx3_api.dart    prx3/v1 client: JWT auth + refresh, typed calls
  screens/             login, dashboard, ballots, match, videos
```

## Notes

- **Payments:** share/gift purchase is web-only (Shopify); the app links
  out, to stay App Store / Play compliant.
- **White-label:** per-club branded builds from this one codebase;
  colours/badge/fonts come from the club's brand pack via the API.
- **Push/crash:** `firebase_messaging` + `sentry_flutter` are declared;
  wire the Firebase config and Sentry DSN per environment before use.
