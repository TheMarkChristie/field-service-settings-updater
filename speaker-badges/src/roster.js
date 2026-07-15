// Task D: single-event roster issue, bulk multi-year import, and 5-year milestones.

import { parseCsv, toCsv, personKey } from "./lib.js";
import { mintAward, upsertEvent, listAwards } from "./badges.js";
import { dispatchBadgeEmail } from "./email.js";

const ISSUABLE_ROLES = ["speaker", "volunteer", "organiser"]; // milestone5 is derived, not rostered

function normaliseRole(role) {
  const r = String(role || "").trim().toLowerCase();
  return ISSUABLE_ROLES.includes(r) ? r : null;
}

/**
 * Issue a single event's roster.
 * body: { name, year, month?, orgId?, orgName?, csv, sendEmail }
 * CSV header: name,role,email (optional month,orgId,orgName).
 */
export async function issueRoster(env, origin, body, ctx) {
  if (!body.name || !body.year) throw new Error("Event name and year are required");
  const rows = parseCsv(body.csv || "");
  if (rows.length === 0) throw new Error("No roster rows found");

  const event = await upsertEvent(env, {
    name: body.name, year: body.year, month: body.month,
    orgId: body.orgId, orgName: body.orgName,
  });

  const issued = [];
  const rejected = [];
  for (const row of rows) {
    const role = normaliseRole(row.role);
    if (!role) { rejected.push({ reason: "invalid or missing role", row }); continue; }
    if (!row.name) { rejected.push({ reason: "missing name", row }); continue; }
    const person = {
      name: row.name, email: row.email || "", role,
      month: row.month || undefined, orgId: row.orgId || undefined, orgName: row.orgName || undefined,
    };
    const result = await mintAward(env, origin, event, person);
    issued.push(summarise(result));
    if (body.sendEmail && result.award.email) dispatchBadgeEmail(env, origin, result.award, ctx);
  }

  return {
    eventId: event.eventId,
    issued,
    rejected,
    csv: exportCsv(issued, false),
  };
}

/**
 * Bulk multi-year import.
 * body: { csv, sendEmail, orgId?, orgName?, month? (defaults) }
 * CSV header: event,year,name,role,email (optional month,orgId,orgName).
 * Idempotent per (eventId, email). Rows without an email are rejected & reported.
 * Runs milestone recompute at the end.
 */
export async function bulkImport(env, origin, body, ctx) {
  const rows = parseCsv(body.csv || "");
  if (rows.length === 0) throw new Error("No import rows found");

  // Group rows by (event, year).
  const groupsMap = new Map();
  const rejected = [];
  for (const row of rows) {
    if (!row.event || !row.year) { rejected.push({ reason: "missing event or year", row }); continue; }
    if (!row.email || !row.email.trim()) { rejected.push({ reason: "missing email (required for bulk import)", row }); continue; }
    const role = normaliseRole(row.role);
    if (!role) { rejected.push({ reason: "invalid or missing role", row }); continue; }
    if (!row.name) { rejected.push({ reason: "missing name", row }); continue; }
    const key = `${row.event}||${row.year}`;
    if (!groupsMap.has(key)) groupsMap.set(key, { event: row.event, year: row.year, rows: [] });
    groupsMap.get(key).rows.push({ ...row, role });
  }

  const groups = [];
  let total = 0;
  for (const g of groupsMap.values()) {
    const event = await upsertEvent(env, {
      name: g.event, year: g.year,
      month: body.month, orgId: body.orgId, orgName: body.orgName,
    });
    const issued = [];
    for (const row of g.rows) {
      const person = {
        name: row.name, email: row.email, role: row.role,
        month: row.month || undefined, orgId: row.orgId || undefined, orgName: row.orgName || undefined,
      };
      const result = await mintAward(env, origin, event, person);
      issued.push(summarise(result));
      total++;
      if (body.sendEmail) dispatchBadgeEmail(env, origin, result.award, ctx);
    }
    groups.push({ key: `${g.event} ${g.year}`, eventId: event.eventId, issued });
  }

  // Auto-issue milestones after a bulk import.
  const { milestones } = await recomputeMilestones(env, origin, ctx, body.sendEmail);

  return {
    total,
    groups,
    rejected,
    milestones,
    csv: exportCsv(groups.flatMap((g) => g.issued), true),
  };
}

/**
 * Derive & issue 5-year speaker milestones. A person qualifies when they hold a
 * badge of ANY issuable role in ≥ 5 distinct years. Issues one milestone5 for
 * their latest such year; skips if they already have one.
 */
export async function recomputeMilestones(env, origin, ctx, sendEmail = false) {
  const awards = await listAwards(env);
  const byPerson = new Map();
  for (const a of awards) {
    const k = personKey(a.email);
    if (!k) continue;
    if (!byPerson.has(k)) byPerson.set(k, { name: a.name, email: a.email, years: new Set(), hasMilestone: false, latest: null });
    const p = byPerson.get(k);
    if (a.role === "milestone5") { p.hasMilestone = true; continue; }
    p.years.add(String(a.year));
    if (!p.latest || Number(a.year) > Number(p.latest.year)) p.latest = a;
    if (a.name) p.name = a.name;
  }

  const milestones = [];
  for (const p of byPerson.values()) {
    if (p.hasMilestone) continue;
    if (p.years.size < 5) continue;
    const latest = p.latest;
    // Ensure the milestone5 event exists (reuse the latest event's org details).
    const event = await upsertEvent(env, {
      name: latest.eventName, year: latest.year,
      month: latest.month, orgId: latest.orgId, orgName: latest.orgName,
    });
    const result = await mintAward(env, origin, event, {
      name: p.name, email: p.email, role: "milestone5", year: latest.year,
    });
    milestones.push(summarise(result));
    if (sendEmail) dispatchBadgeEmail(env, origin, result.award, ctx);
  }
  return { milestones };
}

/** Send the badge email to every award issued but not yet emailed. */
export async function emailUnsent(env, origin, ctx) {
  const awards = await listAwards(env);
  let sent = 0;
  for (const a of awards) {
    if (a.email && !a.emailedAt) { dispatchBadgeEmail(env, origin, a, ctx); sent++; }
  }
  return { sent };
}

function summarise(result) {
  const a = result.award;
  return {
    token: a.token, name: a.name, role: a.role, email: a.email,
    eventId: a.eventId, eventName: a.eventName, year: a.year,
    certId: a.certId, badgeUrl: result.badgeUrl, linkedInUrl: result.linkedInUrl,
    isNew: result.isNew,
  };
}

function exportCsv(issued, includeEventYear) {
  const cols = includeEventYear
    ? ["event", "year", "name", "role", "email", "certId", "badgeUrl", "linkedInUrl"]
    : ["name", "role", "email", "certId", "badgeUrl", "linkedInUrl"];
  const records = issued.map((a) => ({
    event: a.eventName, year: a.year, name: a.name, role: a.role, email: a.email,
    certId: a.certId, badgeUrl: a.badgeUrl, linkedInUrl: a.linkedInUrl,
  }));
  return toCsv(cols, records);
}
