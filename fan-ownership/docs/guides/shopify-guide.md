# Shopify Share Sales — Club Guide

How share sales run through Shopify — the platform's only checkout —
while it stays the system of record (decisions P105/P106).

## The design in one paragraph

Shares are products in your Shopify store — one variant per ladder
tier, each priced at that tier. The platform sends buyers to a cart
containing exactly their next tiers, Shopify takes the money, and its
webhook tells the platform. The platform re-checks the price against
the ladder, then grants the shares — immediately if the buyer has
already signed the Shareholders' Agreement, otherwise the purchase
waits until they sign on the platform (the signature cannot be drawn
inside Shopify's checkout, so signing stays here, exactly as the
agreement rules require).

## Setup

1. **In Shopify** create a "Club Share" product with one variant per
   tier, priced from your ladder (tier 1 £50, tier 2 £62.50, … — check
   Settings → Shares & Checkout for your configured ladder). Note each
   variant ID.
2. **In Shopify admin → Settings → Notifications → Webhooks** add two
   webhooks, both pointing at
   `https://your-site/wp-json/prx3/v1/shopify/webhook`:
   `Order payment` (orders/paid) and `Refund create`. Copy the signing
   secret Shopify shows.
3. **On the platform** Settings → Shares & Checkout:
   - *Shopify store domain* — e.g. `club.myshopify.com`
   - *Shopify webhook signing secret* — from step 2
   - *Shopify variant IDs per tier* — comma-separated, tier 1 first

The admin notice on the plugins screen reminds you until all three
Shopify fields are set.

## How buying works

- The member's account page "Buy another share" button opens a Shopify
  cart pre-loaded with their next tier(s) — they always pay the
  published ladder.
- When the webhook arrives the platform re-verifies the paid amount
  against the ladder for that buyer. An underpaid or overpaid order is
  **held for review** (audited, never granted) — the defence against
  someone buying a tier-1 variant when they're on tier 7.
- **Signed members** (current agreement version) get their shares
  instantly: cap and age checks, owner number, register entry, the
  same as ever.
- **Everyone else** — including brand-new buyers with no account — gets
  an email: register/sign in with the same email address and sign the
  agreement; the shares are granted the moment they sign (or on next
  login once signed). The account page shows "You have N shares
  waiting" until then.
- **Gifts**: add a line item property `gift = 1` (and optionally
  `recipient_email`) via your storefront's gift option — the platform
  issues a gift code instead of granting the buyer, redeemable exactly
  as before (signature required at redemption).

## Refunds and chargebacks

A Shopify refund fires the second webhook: granted shares are
surrendered back to the club on the register (reason: chargeback),
unredeemed gift codes from that order are voided, redeemed ones are
surrendered from the redeemer, and unclaimed pending purchases are
cancelled. Idempotent — Shopify's retries do no harm.

## Operating the seam (Commerce Ops)

Settings → **Commerce Ops** is the daily surface (P107):

- **Webhook health** — the last webhook received; staff are emailed if
  the store is configured but nothing has arrived for 7+ days.
- **Held orders** (price mismatch or cap): review, then either
  **Release** (grants through the money path) or refund in Shopify —
  the refund webhook clears the hold automatically. Held orders are
  never granted without a human decision.
- **Unclaimed purchases**: chased automatically at 3 and 10 days; at
  30 days the row is flagged **refund review due** — the working
  policy is refund at 30 days unclaimed (solicitor to confirm the
  terms wording). **Resend invite** and **Reassign** (the buyer used a
  different email than their platform account) are one click.
- The Club Dashboard carries a commerce tile (held + unclaimed) so the
  queue is never invisible, and the **monthly commerce reconciliation**
  (Shopify payouts vs the share register) is a seeded commitment with
  an accountable owner.

## Troubleshooting

- **Webhook rejected (401)** — the signing secret on the platform
  doesn't match the one Shopify shows for the webhook.
- **Order shows "held" in the audit log** — price mismatch or the cap;
  review the audit entry, then resolve manually (Data API or a manual
  grant) if legitimate.
- **Buyer says "where are my shares?"** — they haven't signed yet:
  point them to sign in with their purchase email and sign the
  agreement; the claim is automatic.
