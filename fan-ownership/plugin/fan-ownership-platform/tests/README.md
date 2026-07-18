# Money & Vote Path Tests (T35)

Automated coverage of the paths where a bug costs money, votes, or
legal evidence — the agreed gate before real payments or binding
ballots run. Dependency-free: a WordPress shim layer in `bootstrap.php`
(options, user/post meta, users, hooks, a fake `wpdb` with the ballot
unique-key behaviour) means the suite needs nothing but PHP.

Run:

    php tests/run-tests.php

Exit code 0 = all assertions pass; failures print per assertion and
exit 1 (CI-ready — wire into the reviewed-PR pipeline per T55).

| File | Covers |
|---|---|
| `test-share-ladder.php` | Tiered pricing (£50 +25%/tier, penny rounding), ladder totals continuing from the held position, split-purchase parity, config-driven repricing |
| `test-shares-cap.php` | 18+ gate, invalid counts, the 10-share cap across purchase and gift combined, sequential never-reused owner numbers, register records for every movement, surrender |
| `test-ballot-voting.php` | Kill switch, electorate snapshot eligibility and weighting (not live holdings), revision = exactly one counted vote, weighted tallies and member turnout, secret-ballot tally capability, closed-ballot refusal |
| `test-agreements.php` | Signature PNG validation (magic bytes, size floor/ceiling, base64), versioned append-only acceptance log, version-bump staleness and re-acceptance, sync event firing |
| `test-sync-matching.php` | Matching rules in order (ID, email case-insensitive, owner number), permanent linking, enrichment allow-list rejecting ownership fields, link-conflict and no-match review queueing with zero writes, no auto-create, API-key auth and disabled-state refusal |

The suite already caught one real defect (a stray empty first entry in
the acceptance log) — add a test here whenever a money/vote bug is
found, before fixing it.

`tests/` is excluded from WPCS: the shims redefine WordPress core
functions by design.
