// Badge domain logic: roles, deterministic cert IDs, the LinkedIn Add-to-Profile
// URL builder, and KV read/write helpers for awards and events.

import { slug, newToken, personKey, todayISO } from "./lib.js";

/** The four badge roles and their human labels / LinkedIn certification names. */
export const ROLES = {
  speaker: { label: "Speaker", certName: "Speaker" },
  volunteer: { label: "Volunteer", certName: "Volunteer" },
  organiser: { label: "Organiser", certName: "Organiser" },
  milestone5: { label: "5-Year Speaker", certName: "5-Year Speaker" },
};

export function isRole(role) {
  return Object.prototype.hasOwnProperty.call(ROLES, role);
}

export function roleLabel(role) {
  return ROLES[role]?.label ?? role;
}

// --- KV keys -----------------------------------------------------------------
export const awardKey = (token) => `award:${token}`;
export const eventKey = (eventId) => `event:${eventId}`;
export const requestKey = (id) => `request:${id}`;
// person → token lookup used for idempotent (re)imports. Role-aware so a person who
// holds more than one role at the same event (e.g. a speaker who also earns a
// milestone5) gets a distinct badge per role instead of one overwriting the other.
export const idxKey = (eventId, role, email) => `idx:${eventId}:${role}:${personKey(email)}`;

/** eventId = slug(name)-year (stable across re-imports of the same event+year). */
export function makeEventId(name, year) {
  return `${slug(name)}-${String(year).trim()}`;
}

/**
 * Deterministic-ish, human-readable certificate id, e.g. SCOTTISH-SUMM-2026-A1B2C3.
 * Prefix derives from the event name; the suffix is drawn from the (unguessable)
 * token so re-minting the same award reproduces the same id.
 */
export function makeCertId(eventName, year, token) {
  const prefix = String(eventName ?? "cert")
    .toUpperCase()
    .replace(/[^A-Z0-9]+/g, " ")
    .trim()
    .split(/\s+/)
    .slice(0, 2)
    .map((w) => w.slice(0, 5))
    .join("-") || "CERT";
  const suffix = String(token).replace(/[^a-z0-9]/gi, "").slice(0, 6).toUpperCase();
  return `${prefix}-${String(year).trim()}-${suffix}`;
}

/**
 * Build a LinkedIn "Add to profile" URL. The query MUST carry, verbatim, the
 * keys LinkedIn's certification flow expects (tests assert on these):
 *   startTask=CERTIFICATION_NAME, name, organizationId, issueYear, issueMonth,
 *   certUrl, certId.
 */
export function linkedInUrl({ certName, orgId, orgName, year, month, certUrl, certId }) {
  const p = new URLSearchParams();
  p.set("startTask", "CERTIFICATION_NAME");
  p.set("name", certName || "Certification");
  if (orgId) p.set("organizationId", String(orgId));
  else if (orgName) p.set("organizationName", orgName);
  if (year) p.set("issueYear", String(year));
  if (month) p.set("issueMonth", String(month));
  if (certUrl) p.set("certUrl", certUrl);
  if (certId) p.set("certId", certId);
  return `https://www.linkedin.com/profile/add?${p.toString()}`;
}

/** Absolute badge (verify) page URL for a token. */
export function badgeUrl(origin, token) {
  return `${origin.replace(/\/+$/, "")}/b/${token}`;
}

/** Absolute image URL for an R2 key (used for og:image and <img>). */
export function imageUrl(origin, key) {
  return `${origin.replace(/\/+$/, "")}/img/${key}`;
}

/**
 * Mint (create-or-refresh) one award and persist it to KV. Idempotent per
 * (eventId, email): reuses the existing token from the idx lookup so a re-import
 * updates the same record instead of creating a duplicate.
 *
 * `person`: { name, email, role, month?, orgId?, orgName? }
 * `event`:  a stored event record (provides defaults + artKeys).
 */
export async function mintAward(env, origin, event, person) {
  const email = person.email;
  const role = person.role;
  const idx = idxKey(event.eventId, role, email);
  let token = email ? await env.BADGES.get(idx) : null;
  const isNew = !token;
  if (!token) token = newToken();

  const year = String(person.year ?? event.year);
  const month = String(person.month ?? event.month ?? "");
  const orgId = person.orgId ?? event.orgId ?? "";
  const orgName = person.orgName ?? event.orgName ?? event.eventName;
  const artKey = event.artKeys?.[role] || "";

  // Preserve the issue date on re-mint so historic badges keep their date.
  let issued = todayISO();
  let emailedAt = null;
  if (!isNew) {
    const existing = await getAward(env, token);
    if (existing) { issued = existing.issued || issued; emailedAt = existing.emailedAt ?? null; }
  }

  const certId = makeCertId(event.eventName, year, token);
  const award = {
    token,
    certId,
    eventId: event.eventId,
    name: person.name,
    role,
    email: email || "",
    eventName: event.eventName,
    year,
    month,
    orgId: String(orgId),
    orgName,
    artKey,
    issued,
    emailedAt,
  };

  await env.BADGES.put(awardKey(token), JSON.stringify(award));
  if (email) await env.BADGES.put(idx, token);

  const links = awardLinks(origin, award);
  return { award, isNew, ...links };
}

/** Compute the shareable links for an award. */
export function awardLinks(origin, award) {
  const url = badgeUrl(origin, award.token);
  const certName = ROLES[award.role]?.certName || roleLabel(award.role);
  const li = linkedInUrl({
    certName,
    orgId: award.orgId,
    orgName: award.orgName,
    year: award.year,
    month: award.month,
    certUrl: url,
    certId: award.certId,
  });
  return { badgeUrl: url, linkedInUrl: li };
}

// --- KV accessors ------------------------------------------------------------
export async function getAward(env, token) {
  const raw = await env.BADGES.get(awardKey(token));
  return raw ? JSON.parse(raw) : null;
}

export async function putAward(env, award) {
  await env.BADGES.put(awardKey(award.token), JSON.stringify(award));
}

export async function getEvent(env, eventId) {
  const raw = await env.BADGES.get(eventKey(eventId));
  return raw ? JSON.parse(raw) : null;
}

export async function putEvent(env, event) {
  await env.BADGES.put(eventKey(event.eventId), JSON.stringify(event));
}

/**
 * Create or update an event record, merging in any provided fields and keeping
 * existing artKeys/logo unless overridden.
 */
export async function upsertEvent(env, fields) {
  const eventId = fields.eventId || makeEventId(fields.name || fields.eventName, fields.year);
  const existing = (await getEvent(env, eventId)) || {};
  const event = {
    eventId,
    eventName: fields.name || fields.eventName || existing.eventName || eventId,
    year: String(fields.year ?? existing.year ?? ""),
    month: String(fields.month ?? existing.month ?? ""),
    orgId: String(fields.orgId ?? existing.orgId ?? ""),
    orgName: fields.orgName ?? existing.orgName ?? (fields.name || existing.eventName || ""),
    logoKey: fields.logoKey ?? existing.logoKey ?? "",
    artKeys: { ...(existing.artKeys || {}), ...(fields.artKeys || {}) },
    issued: existing.issued || todayISO(),
  };
  await putEvent(env, event);
  return event;
}

/** List all awards by scanning the KV `award:` prefix (admin-only paths). */
export async function listAwards(env) {
  const out = [];
  let cursor;
  do {
    const page = await env.BADGES.list({ prefix: "award:", cursor });
    for (const k of page.keys) {
      const raw = await env.BADGES.get(k.name);
      if (raw) out.push(JSON.parse(raw));
    }
    cursor = page.list_complete ? undefined : page.cursor;
  } while (cursor);
  return out;
}
