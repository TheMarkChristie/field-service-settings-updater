# Power Platform Sync — Solution Design (PROXIMO 3)

Design for the Dataverse side of the bidirectional sync with the fan
ownership platform (stories FO-124/FO-125; decisions P99–P102). The
plugin side is implemented in `class-prx3-sync.php`; this document is
the build sheet for the Power Platform solution.

## 1. Summary

Dataverse holds the CRM view of every owner: a Contact enriched with
ownership data (owner number, shares, agreement status, engagement) and
a child table streaming the append-only share register. Sync is
near-real-time outbound (signed webhooks into a flow) with a scheduled
delta pull as the safety net, and inbound upserts push CRM-owned
enrichment (phone, address, marketing consents, notes) back to the
platform under strict matching rules. Field-level ownership applies
throughout: the platform owns what it mints (shares, votes,
acceptances, owner numbers); Dataverse owns enrichment. Ambiguous
matches go to a human review queue on the platform — no fuzzy matching,
no auto-merge, no auto-create.

## 2. Out of the Box

- **Contact** table carries the person; no custom member table.
- **Alternate keys** give idempotent upserts from flows.
- **Duplicate detection rules** (email against Contact) guard manual
  entry on the Dataverse side, mirroring the platform's matching rules.
- **Power Automate** HTTP trigger + Dataverse triggers; no plugins or
  custom code needed in Dataverse.

## 3. Customisations Required

### Configuration

- Contact columns (below), one custom table, alternate keys, duplicate
  detection rule, three cloud flows, two environment variables, one
  security role extension, all in the single PROXIMO 3 solution
  (unmanaged dev/test, managed UAT/Live; version from the 82000
  baseline, never 1.0 before go-live).

### Custom Development

- None on the Dataverse side. (The custom development is the plugin's
  sync module, already built.)

## 4. Detailed Architecture

### 4.1 Contact extension columns (all `prx3_`)

| Display name | Schema name | Type | Notes |
|---|---|---|---|
| WP User FK | `prx3_WPUserFK` | Whole number | The platform user id. **Alternate key** — the cross-reference used for idempotent upserts |
| Owner Number | `prx3_OwnerNumber` | Whole number | Platform-owned; read-only on forms |
| Shares Held | `prx3_SharesHeld` | Whole number | Platform-owned; read-only |
| Is Owner | `prx3_IsOwner` | Yes/No | Platform-owned |
| Agreement Version | `prx3_AgreementVersion` | Single line of text | Platform-owned |
| Agreement Current | `prx3_AgreementCurrent` | Yes/No | Out-of-date flags drive a re-acceptance chase view |
| Agreement Acceptances | `prx3_AgreementAcceptances` | Whole number | Count only; the signatures stay on the platform (board-only) |
| Ballots Voted | `prx3_BallotsVoted` | Whole number | Engagement |
| Badges | `prx3_Badges` | Multiple lines of text | Comma list from the platform |
| Owner Since | `prx3_OwnerSinceOn` | Date only | From registration |
| Last Sync On | `prx3_LastSyncOn` | Date and time | Stamped by every flow write |

CRM-owned enrichment uses standard Contact columns (telephone1,
mobilephone, address1_*, marketing consent fields) — those flow back to
the platform, which stores them as `prx3_crm_*` meta and treats
Dataverse as their master.

### 4.2 Custom table: Share Register Event

Table `prx3_ShareRegisterEvent` (organization-owned; audit on;
no SharePoint; no offline). Append-only mirror of the platform's
statutory register feed.

| Display name | Schema name | Type | Notes |
|---|---|---|---|
| Register Row FK | `prx3_RegisterRowFK` | Whole number | Platform register row id. **Alternate key** — makes the feed idempotent |
| Contact | `prx3_ContactID` | Lookup (Contact) | Cascade: restrict delete |
| Event Code | `prx3_EventCode` | Choice | grant / surrender |
| Shares | `prx3_Shares` | Whole number | |
| Holding After | `prx3_HoldingAfter` | Whole number | |
| Source | `prx3_Source` | Single line of text | purchase / gift / chargeback / left … |
| Consideration | `prx3_Consideration` | Currency | |
| Occurred On | `prx3_OccurredOn` | Date and time | Platform `recorded_at` |

ERD: Contact 1—N `prx3_ShareRegisterEvent` (via `prx3_ContactID`).

### 4.3 Matching and duplicate rules

- Flows upsert Contact by the `prx3_WPUserFK` alternate key —
  deterministic, no duplicates possible from sync.
- A duplicate detection rule on Contact email addresses catches manual
  entry; the platform's inbound matching (ID → email → owner number,
  ambiguous to review) is the authoritative mirror of the same policy.
- Dataverse never creates platform members and the platform never
  creates Contacts directly — creation flows one way per system of
  record, linking happens through the upsert exchange.

### 4.4 Cloud flows (PascalCase, error-handled scopes, secrets in environment variables)

| Flow | Trigger | Behaviour |
|---|---|---|
| `PRX3SyncInboundWebhook` | HTTP request | Verify `X-Prx3-Signature` (HMAC-SHA256 with `prx3_SyncWebhookSecret`); upsert Contact by `prx3_WPUserFK`; stamp `prx3_LastSyncOn`; Try/Catch scopes with failure rows to a sync-log table or Teams alert |
| `PRX3SyncDeltaPull` | Recurrence (hourly) | GET `/wp-json/prx3/v1/sync/members?modified_since=@{lastRun}` (paged) and GET `/sync/register?since_id=@{lastRowId}`; upsert Contacts and Share Register Events by their alternate keys; safety net for missed webhooks |
| `PRX3SyncOutboundContact` | Dataverse: Contact modified (filtered to CRM-owned columns) | POST `/sync/upsert` with `dataverse_id` (Contact GUID), email, owner number, and the enrichment fields; on `status=review` responses, post to the sync channel for a human to resolve in the platform's review queue |

Environment variables: `prx3_SyncApiBaseUrl`, `prx3_SyncApiKey`
(secret), `prx3_SyncWebhookSecret` (secret). Flows send the key as
`X-Prx3-Api-Key`.

The packaged custom connector
(`integrations/power-platform/prx3-sync-connector.swagger.json`) wraps
the four API actions so makers use typed actions instead of raw HTTP.

### 4.5 Security model

- Extend the account/contact-facing role: read on Contact ownership
  columns for staff; `prx3_ShareRegisterEvent` read-only for everyone
  except the sync service (create via flow connection only); no delete
  for base users anywhere.
- The flows run under a dedicated service connection, not a named
  user's.
- Agreement signatures deliberately do not sync — they remain on the
  platform behind board-only access.

## 5. End-to-end processes

- **Member changes on the platform** → webhook within 5 minutes (queue
  tick) → `PRX3SyncInboundWebhook` upserts the Contact; hourly
  `PRX3SyncDeltaPull` closes any gaps.
- **CRM enrichment changes in Dataverse** → `PRX3SyncOutboundContact`
  posts the upsert → platform matches (ID → email → owner number) and
  writes only allow-listed fields; ambiguity lands in the platform's
  review queue (Fan Ownership → CRM Sync) for a human decision.
- **Reporting**: owner counts, share ladder revenue, agreement
  re-acceptance chase lists, and engagement segments all read from
  Contact + Share Register Event in Dataverse without touching the
  platform.

## 6. Next steps

1. Create the solution, columns, table, keys, and flows per this sheet.
2. Import the custom connector definition and bind the environment
   variables.
3. Point Settings → Integrations on the platform at the flow's HTTP URL
   and generate the API key + webhook secret.
4. Run `PRX3SyncDeltaPull` once with no `modified_since` for the
   initial full load, then enable the webhook path.
