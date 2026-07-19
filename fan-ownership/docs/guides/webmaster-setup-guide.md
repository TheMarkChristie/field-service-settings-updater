# Webmaster Setup Guide

Zero to running club, in order.

## 1. Install
1. WordPress 6.4+, PHP 8.1+. Upload the plugin ZIP (Plugins → Add New
   → Upload) and activate. Roles, tables, cron, and member pages
   install themselves (and re-verify on every upgrade).
2. Settings → Permalinks → Save once (registers /brand-pack/,
   /my-agreement/, /verify-owner/).

## 2. Configure (FanPress Settings)
Work down the sections: **Club** (name, sport, currency, welcome video
URL, weekly show day) · **Brand Pack** (colours, badge, fonts — drives
the whole front end) · **Ticketing** · **FanPress Chat** (bubble
colours, chat word filter).
Board-level sections (Legal, Targets, Governance) live in the Board menu.

## 3. Technical setup (the FanPress Technical Setup menu)
Everything that needs an API key or an outside account lives under the
top-level **FanPress Technical Setup** menu. Every field carries a plain-English note and a
"Where to get this" link to the exact console page. The **Overview**
page is a status checklist — green when an integration is configured,
grey when it still needs keys.

- **Commerce — Shopify**: store domain, webhook secret, tier variant
  IDs, ladder pricing, join/account page IDs (wired automatically if
  the installer created the pages). Add two webhooks in Shopify
  (orders/paid, refunds/create).
- **Streaming — Cloudflare**: account ID and API token for Cloudflare
  Stream (match video).
- **Meetings — 8×8 JaaS**: meeting video rooms. Create a free account
  at jaas.8x8.vc, copy the **App ID** (vpaas-magic-cookie-…), generate
  an **API key pair**, then paste the **API key ID** and the **private
  key** (PEM). The platform signs a room token per joiner: board,
  governance, and admins moderate; owners join as guests; **nobody else
  needs an 8×8 account**. Leave blank to fall back to the open
  meet.jit.si.
- **Push & App**: Firebase Cloud Messaging server key for app push.
- **Data API & Claude**: the connection panel, provisioned keys, and
  the demo club load/remove.
- **Power Platform**: sync key and toggles for the Dataverse flow.

## 4. Companions & services
- Shopify store with two webhooks (orders/paid, refunds/create).
- A 2FA plugin (staff/board enrolment is enforced via contract).
- The club badge plugin (badges queue until connected).
- Cloudflare Stream for match video; Firebase for app push (later);
  8×8 JaaS for meeting video.

## 5. People
Users → assign roles: Owner-Admin (day-to-day admin), Governance
Officer, Content Editor, Moderator, Board Member. Fan Owner is granted
automatically by the money path. Staff/board need 2FA enrolment.

## 6. Verify
Load the demo club (FanPress Technical Setup → Data API & Claude) on a staging site: check
the register, the live kit ballot, FanPress, profiles, then Remove demo
data. The audit log records everything you just did.
