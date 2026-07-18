# Power Platform Sync — Club Guide

How data moves between the platform and Dataverse (stories FO-124 and
FO-125; decisions P99 to P102). The Dataverse build sheet is
`spec/power-platform-sync-design.md`; the maker-friendly connector is
`integrations/power-platform/prx3-sync-connector.swagger.json`.

## The two rules that keep it safe

1. **Field-level ownership.** The platform owns what it mints — shares,
   votes, acceptances, owner numbers. Dataverse owns CRM enrichment —
   phone, address, marketing consents, notes. Neither side can
   overwrite the other's territory: inbound writes outside the
   enrichment allow-list are rejected and reported.
2. **No guessing on identity.** Matching is exact — cross-reference ID,
   then email, then owner number. Anything that matches nothing,
   matches twice, or contradicts an existing link goes to a human, not
   an algorithm.

## Setup

1. Build the Dataverse side from the design sheet (Contact columns,
   the Share Register Event table, the three flows, the connector).
2. Settings → Integrations on the platform:
   - *Power Platform sync enabled* — `1`.
   - *Sync API key* — generate a long random value; put the same value
     in the flows' `prx3_SyncApiKey` environment variable.
   - *Outbound webhook URL* — the `PRX3SyncInboundWebhook` flow's HTTP
     trigger URL.
   - *Webhook signing secret* — another long random value, mirrored in
     `prx3_SyncWebhookSecret`.
3. Initial load: run the `PRX3SyncDeltaPull` flow once with no
   modified-since value to pull every member, then let the schedule and
   webhooks take over.

Sync is completely off until the key is set and the toggle is on.

## What flows where

**Platform → Dataverse** (within ~5 minutes of the change):
registration and profile edits, share grants and surrenders, agreement
acceptances (version, current flag, and count — the signature images
never leave the platform), badges, and engagement counts. The share
register also streams as an append-only feed the CRM resumes from the
last row it received. Every webhook is signed (HMAC-SHA256 in
`X-Prx3-Signature`); deliveries retry up to 8 times before dropping
with an audit entry.

**Dataverse → platform**: phone, mobile, address, marketing consents,
and notes, applied to the matched member and shown as enrichment.
A successful match links the two records permanently. Inbound updates
never trigger an outbound echo, so there are no loops.

## The review queue

Settings → **CRM Sync** shows sync health (outbound queue depth)
and the matching review queue. Records land there when they matched no
member, matched ambiguously, or conflicted with an existing link. For
each one: check the payload, then either *Link & apply* (enter the
member's user ID or email) or *Discard*. Both are audited. Nothing in
the queue has touched any member record yet.

## Troubleshooting

- **Nothing arriving in Dataverse** — check the toggle and API key are
  set, the webhook URL is the flow's current trigger URL, and the CRM
  Sync page's queue depth (a growing queue means deliveries are
  failing; the audit log records drops).
- **Flow reports signature verification failures** — the webhook
  secrets on the two sides don't match.
- **Everything lands in the review queue** — the CRM is not sending
  email or owner number for unlinked records; after the first linked
  pass, matching is by ID and this stops.
- **A field "won't sync" into the platform** — it isn't on the
  enrichment allow-list; that's ownership protection, not a fault
  (developers can extend the list with the `prx3_sync_inbound_fields`
  filter).
- **Missed a window of changes** (outage, expired trigger URL) — the
  hourly delta pull self-heals; or run it manually with a
  modified-since just before the outage.
