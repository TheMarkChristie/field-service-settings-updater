# Perth Panthers Fan Ownership Platform — Delivery Backlog

Build-ready backlogs for all three phases, authored from `../specification.md`
(v1.1, 139 decisions). Every story carries traceability references to the
decision log (P/T numbers). Stories and acceptance criteria are
system-agnostic: they state what the business needs; the technical how lives
in the specification and the solution design produced at build time.

## Phases

| Phase | Theme | Backlog | Stories | Outcome |
|-------|-------|---------|---------|---------|
| 1 | Own | `phase-1-own.md` | FO-101 to FO-130 | A supporter anywhere in the world becomes a paying owner and feels it: shares, certificate, badge, onboarding, first exclusive content, a signed Shareholders' Agreement, and a CRM that always knows the members |
| 2 | Decide | `phase-2-decide.md` | FO-201 to FO-234 | Owners run the club: ballots, ideas, questions, meetings, financials, decision register, FanPress Chat (the forum + social layer), chapters, and the full board layer |
| 3 | Watch | `phase-3-watch.md` | FO-301 to FO-318 | Owners watch the club from anywhere: native apps, live match streams, chat, minute-by-minute, replays, the weekly show, and live player-of-the-match voting |

## Sequencing and dependencies

- Phase 1 is the foundation: roles, gating, configuration, checkout, and the
  member record. Everything else assumes it.
- Phase 2 depends on Phase 1 accounts and roles. The board workspace
  (FO-226 to FO-229) is a Phase 2 extension and may trail the member-facing
  Boardroom by a sprint. In-platform board video (FO-228) may slip to Phase 3
  alongside the other real-time work.
- Phase 3 depends on the Phase 1/2 capability APIs. The reporter console
  (FO-307) and live chat (FO-306) are also used by Phase 2 meetings, so their
  server-side elements should be designed once, in Phase 2, and reused.
- Cross-phase constraints that apply to every story: WCAG 2.1 AA (P44),
  UK/EU data residency (T30), rebrand-proof configuration (T46), feature kill
  switches (T74), and the paywall line (P36).

## Estimating convention

Each story carries the standard estimate template — Design / Build / Develop /
Test — in days. Estimates are refinement inputs, not commitments; re-estimate
at sprint planning.

## Definition of done (all stories)

1. Acceptance criteria demonstrably met, with automated coverage on money and
   vote paths (T35).
2. Accessibility checks pass in CI (T71).
3. Feature is behind its kill switch where applicable (T74).
4. Documentation updated (admin guide / matchday runbook / developer docs)
   per T75.
5. Deployed via reviewed pull request with tests green (T55).
