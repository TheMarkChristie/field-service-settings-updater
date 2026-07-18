# Shareholders' Agreement — Club Guide

How the platform supplies, executes, and evidences the Shareholders'
Agreement (stories FO-121 to FO-123; decisions P97/P98).

## One-time setup

1. **Publish the agreement.** Paste the solicitor-settled text into a
   WordPress page and publish it. Do not open the share checkout on the
   unsettled draft.
2. **Point the platform at it.** Settings → Legal:
   - *Shareholders' Agreement page ID* — the page from step 1.
   - *Shareholders' Agreement version* — start at `1.0`.
   - *Terms of Membership page ID* — the terms page, listed alongside
     the agreement in every member's documents.
3. **Set the countersignatory.** Settings → Legal:
   - *Board signatory name* and *role* (e.g. "Director").
   - *Board signatory signature image* — a scan or photo of their
     signature; a transparent PNG on white looks best. The board should
     minute a resolution authorising this signature being applied
     automatically to each acceptance.
4. **Upload the club stamp.** Settings → Brand Pack → *Club stamp*.
   It is placed, slightly rotated, over the execution block of every
   executed copy.

## What members experience

- **Buying shares** — checkout requires (a) ticking "I have read and
  agree…", with the agreement one click away, and (b) drawing a
  signature in the signature box (finger, stylus, or mouse; a Clear
  button lets them redo it). Checkout will not complete without both.
- **Redeeming a gift** — the same tick and signature are required on
  the redemption form before the shares are granted.
- **Afterwards** — the account page's "My documents" section links the
  agreement, the terms, and *View / print my executed copy*.

## The executed copy

Every accepting owner has a personalised copy at `/my-agreement/`:
the full agreement text plus an execution block with their signature,
name, owner number, and acceptance date on one side, and the board
signatory's countersignature "for and on behalf of" the club on the
other, with the club stamp over the block. A print button produces the
member's PDF copy. If the member's accepted version is older than the
published version, the copy says so and points them to re-accept.

Members can open only their own copy. Board members and administrators
can open any member's copy from the signatures register.

## Issuing a new version

1. Update the agreement page text.
2. Settings → Legal → bump the *version* (e.g. `1.0` → `1.1`).
3. Every owner now sees a banner on their account asking them to read,
   re-accept, and re-sign. Each re-acceptance is recorded as a new
   entry — the original acceptance is never overwritten.
4. Owners keep their shares and votes while re-acceptance is
   outstanding; the platform chases, it does not lock out. The board
   signatures register flags who is out of date.

Material adverse changes should go to a member ballot first (agreement
clause 7).

## The board signatures register

Board → **Owner Signatures** (board access only) lists every
accepting owner: owner number, name and email, accepted version (with
an out-of-date flag), how many acceptances they have recorded, the most
recent date and context, their signature, and a link to their executed
copy.

Treat it as personal data: board eyes only, no exporting or sharing
outside the board.

## The evidence trail

Each acceptance stores: agreement version, date and time, IP address,
context (checkout, gift redemption, or re-acceptance), the order
reference where there is one, and whether it was signed. The log is
append-only. The signature image is kept once per member (their most
recent signature). Every acceptance is also written to the audit log.

## Data protection

- A member's data export includes their full acceptance history and
  whether a signature image is held.
- Erasure (or account closure) deletes the signature image; the
  acceptance log itself is retained with the share register as the
  contractual legal minimum, and the privacy notice should say so.

## Troubleshooting

- **/my-agreement/ gives a 404** — rewrite rules need refreshing:
  visit Settings → Permalinks and save, or deactivate/reactivate the
  plugin. (The plugin flushes automatically on upgrade; this is the
  manual fallback.)
- **Checkout doesn't show the checkbox or signature box** — the cart
  must contain the share product, and the agreement page must be
  published with its ID set in Settings → Legal.
- **The signature box won't draw** — the member's browser has
  JavaScript disabled; the form says so and will not submit without a
  signature.
- **The countersignature or stamp is missing from executed copies** —
  the images have not been uploaded, or the signatory name is blank, in
  Settings → Legal / Brand Pack. Copies render without them and pick
  them up on the next view once configured.
