# Webmaster Setup Guide

Zero to running club, in order.

## 1. Install
1. WordPress 6.4+, PHP 8.1+. Upload the plugin ZIP (Plugins → Add New
   → Upload) and activate. Roles, tables, cron, and member pages
   install themselves (and re-verify on every upgrade).
2. Settings → Permalinks → Save once (registers /brand-pack/,
   /my-agreement/, /verify-owner/).

## 2. Configure (Fan App Settings)
Work down the sections: **Club** (name, sport, currency, welcome video
URL, weekly show day) · **Brand Pack** (colours, badge, fonts — drives
the whole front end) · **Ticketing** · **Shares & Checkout** (Shopify
domain, webhook secret, tier variant IDs, ladder pricing, join/account
page IDs — wired automatically if the installer created them) ·
**FanPress Chat** (bubble colours) · **API & Integrations** (Cloudflare
Stream, Brevo rides site mail, chat word filter, Data API / Claude
connection, Power Platform sync).
Board-level sections (Legal, Targets, Governance) live in the Board menu.

## 3. Companions & services
- Shopify store with two webhooks (orders/paid, refunds/create).
- A 2FA plugin (staff/board enrolment is enforced via contract).
- The club badge plugin (badges queue until connected).
- Cloudflare Stream for match video; Firebase for app push (later).

## 4. People
Users → assign roles: Owner-Admin (day-to-day admin), Governance
Officer, Content Editor, Moderator, Board Member. Fan Owner is granted
automatically by the money path. Staff/board need 2FA enrolment.

## 5. Verify
Load the demo club (API & Integrations) on a staging site: check the
register, the live kit ballot, FanPress, profiles, then Remove demo
data. The audit log records everything you just did.
