// Speaker Badges — single Cloudflare Worker. Serves the admin console, the public
// badge/verify + request pages, and the JSON/upload API. Storage: KV (BADGES) + R2
// (IMAGES). Admin routes are protected by Cloudflare Access (verified with jose).
//
// See CLAUDE.md for the full brief. This is the router; domain logic lives in the
// sibling modules (badges, roster, requests, images, email, access, render).

import { json, html, badRequest } from "./lib.js";
import { getAward, upsertEvent } from "./badges.js";
import { requireAccess } from "./access.js";
import { badgePage, requestPage, notFoundPage } from "./render.js";
import { siteLogoBytes } from "./brand.js";
import { adminPage } from "./admin.js";
import { handleUpload, serveImage } from "./images.js";
import { issueRoster, bulkImport, recomputeMilestones, emailUnsent } from "./roster.js";
import {
  submitRequest, listRequests, approveRequest, rejectRequest, listEvents,
} from "./requests.js";

/** Absolute origin for building badge links / og:image — prefer the configured value. */
function resolveOrigin(env, url) {
  return (env.PUBLIC_ORIGIN && env.PUBLIC_ORIGIN.trim()) || url.origin;
}

async function readJson(request) {
  try { return await request.json(); } catch { return {}; }
}

export default {
  async fetch(request, env, ctx) {
    const url = new URL(request.url);
    const { pathname } = url;
    const method = request.method;
    const origin = resolveOrigin(env, url);

    try {
      // --- Public GET pages --------------------------------------------------
      if (method === "GET" && (pathname === "/request")) {
        const events = await listEvents(env);
        return html(requestPage(events, env.TURNSTILE_SITE_KEY || ""));
      }

      if (method === "GET" && pathname.startsWith("/b/")) {
        const token = decodeURIComponent(pathname.slice(3));
        const award = token ? await getAward(env, token) : null;
        return award ? html(badgePage(award, origin)) : html(notFoundPage(), 404);
      }

      if (method === "GET" && pathname === "/brand/logo.png") {
        return new Response(siteLogoBytes(), {
          headers: {
            "content-type": "image/png",
            "cache-control": "public, max-age=31536000, immutable",
          },
        });
      }

      if (method === "GET" && pathname.startsWith("/img/")) {
        const key = decodeURIComponent(pathname.slice(5));
        return serveImage(env, key);
      }

      if (method === "GET" && pathname.startsWith("/og/") && pathname.endsWith(".png")) {
        // Phase 2 (Task C): composed OG card. For now, redirect to the raw artwork.
        const token = pathname.slice(4, -4);
        const award = await getAward(env, token);
        if (award?.artKey) return Response.redirect(`${origin}/img/${award.artKey}`, 302);
        return new Response("Not found", { status: 404 });
      }

      // --- Public JSON API ---------------------------------------------------
      if (method === "GET" && pathname === "/api/verify") {
        const token = url.searchParams.get("token");
        const award = token ? await getAward(env, token) : null;
        if (!award) return json({ verified: false }, 404);
        return json({
          verified: true, name: award.name, role: award.role,
          eventName: award.eventName, year: award.year, certId: award.certId,
          issued: award.issued, artUrl: award.artKey ? `${origin}/img/${award.artKey}` : null,
        });
      }

      if (method === "GET" && pathname === "/api/events") {
        return json({ events: await listEvents(env) });
      }

      if (method === "POST" && pathname === "/api/request") {
        const body = await readJson(request);
        return submitRequest(env, origin, request, body, ctx);
      }

      // --- Admin (Cloudflare Access protected) -------------------------------
      if (pathname === "/" || pathname === "/admin") {
        const denied = await requireAccess(request, env);
        if (denied) {
          // Browsers hitting the console without Access should land at the login.
          if (method === "GET") {
            return new Response("Forbidden — sign in via Cloudflare Access.", {
              status: 403, headers: { "content-type": "text/plain; charset=utf-8" },
            });
          }
          return denied;
        }
        return html(adminPage());
      }

      if (pathname.startsWith("/api/") && isAdminApi(pathname)) {
        const denied = await requireAccess(request, env);
        if (denied) return denied;
        return handleAdminApi(pathname, method, request, env, origin, ctx);
      }

      return html(notFoundPage(), 404);
    } catch (err) {
      console.log(`[error] ${method} ${pathname}: ${err?.stack || err}`);
      return json({ error: "Internal error" }, 500);
    }
  },
};

function isAdminApi(pathname) {
  return [
    "/api/event", "/api/roster", "/api/import", "/api/milestones/recompute",
    "/api/upload", "/api/requests", "/api/requests/approve", "/api/requests/reject",
    "/api/email/unsent",
  ].includes(pathname);
}

async function handleAdminApi(pathname, method, request, env, origin, ctx) {
  const url = new URL(request.url);

  if (pathname === "/api/event" && method === "POST") {
    const body = await readJson(request);
    if (!body.name || !body.year) return badRequest("name and year required");
    const event = await upsertEvent(env, body);
    return json({ eventId: event.eventId, event });
  }

  if (pathname === "/api/roster" && method === "POST") {
    const body = await readJson(request);
    return json(await issueRoster(env, origin, body, ctx));
  }

  if (pathname === "/api/import" && method === "POST") {
    const body = await readJson(request);
    return json(await bulkImport(env, origin, body, ctx));
  }

  if (pathname === "/api/milestones/recompute" && method === "POST") {
    const body = await readJson(request).catch(() => ({}));
    return json(await recomputeMilestones(env, origin, ctx, body?.sendEmail === true));
  }

  if (pathname === "/api/upload" && method === "POST") {
    return handleUpload(request, env, url);
  }

  if (pathname === "/api/email/unsent" && method === "POST") {
    return json(await emailUnsent(env, origin, ctx));
  }

  if (pathname === "/api/requests" && method === "GET") {
    return listRequests(env);
  }
  if (pathname === "/api/requests/approve" && method === "POST") {
    return approveRequest(env, origin, await readJson(request), ctx);
  }
  if (pathname === "/api/requests/reject" && method === "POST") {
    return rejectRequest(env, await readJson(request));
  }

  return badRequest("Unknown admin endpoint or method");
}
