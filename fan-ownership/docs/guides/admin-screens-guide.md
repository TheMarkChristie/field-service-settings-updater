# Admin Screens — Club Guide

Where everything lives in wp-admin (V3.15.0.1). Five menus: **Owners**,
**FanPress Chat**, **Board**, **Fan App Settings**, **Fan Club Technical Setup**.

## Owners
- **Gift Codes** — every code: shares, buyer, status (outstanding /
  redeemed / voided), redeemer.
- All member content types as data tables. (The Share Register moved
  to the Board menu — board members and Owner-Admins only.)

## FanPress Chat
- **Overview, Topics, Boards, Held Replies** — plus **Reports**: member
  reports (reporter, item, reason) for moderators.

## Board
- Workspace, Dashboard, Decision Register, Commitments, Owner
  Signatures, Live Q&A presenter, Annual Report, Legal/Targets/
  Governance (read-only for board).
- **Owner Management** — board members and Owner-Admins only: search
  for any owner, see their personal information on one page (identity
  with the government ID always masked, email, shares — every view
  audited), and fix inappropriate profile content right there —
  edit/clear the bio, remove social links, the profile picture, or
  individual gallery photos. Name, address, ID, PEP, and contact
  details are read-only by design.
- **Share Register** — the statutory list on screen (moved here from
  Owners; board members and Owner-Admins only): every holder with
  owner number, current holding, last event, profile link, and the
  holders/shares-in-issue totals, plus the latest 50 register events.
  CSV export stays under Fan App Settings → Shares & Checkout.
- **Identity Lookup** — the quick ID-number lookup; the government ID
  is always masked to its last four. Every view is audited.

## Fan App Settings
- Feature switches, Member pages (self-install on upgrade; button for
  manual re-runs), the club-facing settings sections (Club, Brand Pack,
  Ticketing, FanPress Chat), Commerce Ops, **Member Tools** (merge
  duplicate accounts through the register; the same audited Identity
  Lookup), and the **Audit Log** — latest 100 entries, filterable by
  event key (`ballot_open`, `identity_viewed`, `data_api_member`, …).

## Fan Club Technical Setup
Everything technical, in one place, with a note and a "Where to get
this" link on every field:
- **Overview** — status checklist: green dot when an integration is
  configured, grey when it still needs keys, linking to each page.
- **Commerce — Shopify** — store domain, webhook secret, tier variant
  IDs, ladder pricing, join/account page IDs.
- **Streaming — Cloudflare** — Cloudflare Stream for match video.
- **Meetings — 8×8 JaaS** — App ID, API key ID, and private key from
  jaas.8x8.vc; the platform signs each joiner's room token (board/
  governance/admin moderate, owners join as guests, no 8×8 accounts
  needed). Blank = open meet.jit.si fallback.
- **Push & App** — Firebase Cloud Messaging key for app push.
- **Data API & Claude** — connection panel, provisioned keys, demo
  club load/remove.
- **Power Platform** — Dataverse sync key and toggles.
- **Developers** — the API reference that ships with the install:
  every endpoint with methods/auth/purpose, extension hooks,
  capabilities, and error codes (matches `docs/guides/api-reference.md`).

## Compliance notes
- Identity records: only full birth name and nationality ever appear
  member-facing. Residence, DOB, government ID, and the PEP flag are
  member + admin/board only; the ID is never shown in full on screen.
- Profile moderation (Users → edit user): Owner-Admins can fix
  inappropriate profile content — edit/clear the bio, remove social
  links, the profile picture, or individual gallery photos. The
  identity record (name, address, ID, PEP) and contact details are
  never editable there; every moderation change is audited.
- Every identity view, identity edit, merge, pin, demo load/remove,
  and money/vote event lands in the Audit Log permanently.
