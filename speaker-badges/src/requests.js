// The request → approve/reject queue. Public submit (honeypot + Turnstile),
// admin list/approve/reject. Approving mints a badge inheriting the event's details.

import { json, badRequest } from "./lib.js";
import { requestKey, getEvent, mintAward, isRole } from "./badges.js";
import { verifyTurnstile } from "./turnstile.js";
import { dispatchBadgeEmail, sendEmail, requestAckHtml } from "./email.js";

const REQUESTABLE_ROLES = ["speaker", "volunteer", "organiser"];

/** POST /api/request — public. Honeypot + Turnstile; stores a pending request. */
export async function submitRequest(env, origin, request, body, ctx) {
  // Honeypot: a filled `website` field means a bot — silently accept, store nothing.
  if (body.website && String(body.website).trim() !== "") {
    return json({ ok: true }); // look successful; drop silently
  }

  const remoteIp = request.headers.get("cf-connecting-ip") || "";
  const tsToken = body["cf-turnstile-response"] || body.turnstileToken;
  const okTs = await verifyTurnstile(env, tsToken, remoteIp);
  if (!okTs) return badRequest("Verification failed — please complete the challenge and try again.");

  if (!body.name || !body.email) return badRequest("Name and email are required");
  if (!REQUESTABLE_ROLES.includes(String(body.role))) return badRequest("Please choose a valid role");
  if (!body.eventId) return badRequest("Please choose an event");
  const event = await getEvent(env, body.eventId);
  if (!event) return badRequest("Unknown event");

  const id = crypto.randomUUID();
  const record = {
    id, name: String(body.name).trim(), email: String(body.email).trim(),
    eventId: event.eventId, eventName: event.eventName, role: body.role,
    message: String(body.message || "").slice(0, 1000),
    status: "pending", created: new Date().toISOString(),
    token: null, badgeUrl: null, linkedInUrl: null,
  };
  await env.BADGES.put(requestKey(id), JSON.stringify(record));

  // Acknowledge to the requester (fire-and-forget).
  if (ctx?.waitUntil) ctx.waitUntil(sendEmail(env, record.email, "We received your Scottish Summit badge request", requestAckHtml(record)));

  return json({ ok: true, id });
}

/** GET /api/requests — admin. */
export async function listRequests(env) {
  const out = [];
  let cursor;
  do {
    const page = await env.BADGES.list({ prefix: "request:", cursor });
    for (const k of page.keys) {
      const raw = await env.BADGES.get(k.name);
      if (raw) out.push(JSON.parse(raw));
    }
    cursor = page.list_complete ? undefined : page.cursor;
  } while (cursor);
  out.sort((a, b) => (a.created < b.created ? 1 : -1));
  return json({ requests: out });
}

/** POST /api/requests/approve — admin. Mints the badge for the corrected address. */
export async function approveRequest(env, origin, body, ctx) {
  const raw = body.id ? await env.BADGES.get(requestKey(body.id)) : null;
  if (!raw) return badRequest("Request not found");
  const req = JSON.parse(raw);
  if (req.status === "approved") return json({ ok: true, request: req });

  const event = await getEvent(env, req.eventId);
  if (!event) return badRequest("Event no longer exists");

  const result = await mintAward(env, origin, event, {
    name: req.name, email: req.email, role: isRole(req.role) ? req.role : "speaker",
  });

  req.status = "approved";
  req.token = result.award.token;
  req.badgeUrl = result.badgeUrl;
  req.linkedInUrl = result.linkedInUrl;
  await env.BADGES.put(requestKey(req.id), JSON.stringify(req));

  dispatchBadgeEmail(env, origin, result.award, ctx);
  return json({ ok: true, request: req });
}

/** POST /api/requests/reject — admin. */
export async function rejectRequest(env, body) {
  const raw = body.id ? await env.BADGES.get(requestKey(body.id)) : null;
  if (!raw) return badRequest("Request not found");
  const req = JSON.parse(raw);
  req.status = "rejected";
  await env.BADGES.put(requestKey(req.id), JSON.stringify(req));
  return json({ ok: true, request: req });
}

/** GET /api/events — public list for the request dropdown. */
export async function listEvents(env) {
  const out = [];
  let cursor;
  do {
    const page = await env.BADGES.list({ prefix: "event:", cursor });
    for (const k of page.keys) {
      const raw = await env.BADGES.get(k.name);
      if (raw) { const e = JSON.parse(raw); out.push({ eventId: e.eventId, eventName: e.eventName, year: e.year }); }
    }
    cursor = page.list_complete ? undefined : page.cursor;
  } while (cursor);
  out.sort((a, b) => (Number(b.year) - Number(a.year)) || a.eventName.localeCompare(b.eventName));
  return out;
}
