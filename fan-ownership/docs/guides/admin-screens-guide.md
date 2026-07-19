# Admin Screens — Club Guide

Where everything lives in wp-admin (V3.12.1.4). Four menus: **Owners**,
**FanPress Chat**, **Board**, **Fan App Settings**.

## Owners
- **Share Register** — the statutory list on screen: every holder with
  owner number, current holding, last event, profile link, and the
  holders/shares-in-issue totals, plus the latest 50 register events
  (grants, redemptions, surrenders, merges, consideration). CSV export
  stays under Fan App Settings → Shares & Checkout.
- **Gift Codes** — every code: shares, buyer, status (outstanding /
  redeemed / voided), redeemer.
- All member content types as data tables.

## FanPress Chat
- **Overview, Topics, Boards, Held Replies** — plus **Reports**: member
  reports (reporter, item, reason) for moderators.

## Board
- Workspace, Dashboard, Decision Register, Commitments, Owner
  Signatures, Live Q&A presenter, Annual Report, Legal/Targets/
  Governance (read-only for board), and **Identity Lookup** — the full
  identity record (birth name, nationality, residence, DOB, PEP) with
  the government ID always masked to its last four. Every view is
  audited.

## Fan App Settings
- Feature switches, Member pages (self-install on upgrade; button for
  manual re-runs), settings sections, Commerce Ops, API & Integrations
  (Claude connection + demo club load/remove), **Member Tools** (merge
  duplicate accounts through the register; the same audited Identity
  Lookup), and the **Audit Log** — latest 100 entries, filterable by
  event key (`ballot_open`, `identity_viewed`, `data_api_member`, …).

## Compliance notes
- Identity records: only full birth name and nationality ever appear
  member-facing. Residence, DOB, government ID, and the PEP flag are
  member + admin/board only; the ID is never shown in full on screen.
- Every identity view, identity edit, merge, pin, demo load/remove,
  and money/vote event lands in the Audit Log permanently.
