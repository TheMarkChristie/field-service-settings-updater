// Cloudflare Access enforcement (defense in depth). Every admin route verifies the
// Cf-Access-Jwt-Assertion header against the team's JWKS with `jose`, and checks the
// audience (AUD) tag. Access also sits in front in the dashboard; this is the
// belt-and-braces check at the Worker.

import { createRemoteJWKSet, jwtVerify } from "jose";

// Cache one JWKS resolver per team domain across invocations (module scope).
const jwksCache = new Map();

function jwksFor(teamDomain) {
  const domain = teamDomain.replace(/^https?:\/\//, "").replace(/\/+$/, "");
  let set = jwksCache.get(domain);
  if (!set) {
    set = createRemoteJWKSet(new URL(`https://${domain}/cdn-cgi/access/certs`));
    jwksCache.set(domain, set);
  }
  return set;
}

/**
 * Verify the Access JWT on a request.
 * Returns { ok: true, identity } or { ok: false, reason }.
 * If ACCESS_AUD / ACCESS_TEAM_DOMAIN are unset (e.g. local dev), returns
 * { ok: false, reason: "unconfigured" } — the caller decides how to treat dev.
 */
export async function verifyAccess(request, env) {
  const aud = env.ACCESS_AUD;
  const teamDomain = env.ACCESS_TEAM_DOMAIN;
  if (!aud || !teamDomain) return { ok: false, reason: "unconfigured" };

  const token =
    request.headers.get("Cf-Access-Jwt-Assertion") ||
    request.headers.get("cf-access-jwt-assertion");
  if (!token) return { ok: false, reason: "missing" };

  try {
    const { payload } = await jwtVerify(token, jwksFor(teamDomain), {
      issuer: `https://${teamDomain.replace(/^https?:\/\//, "").replace(/\/+$/, "")}`,
      audience: aud,
    });
    return { ok: true, identity: { email: payload.email, sub: payload.sub } };
  } catch (err) {
    return { ok: false, reason: "invalid", detail: String(err?.message || err) };
  }
}

/**
 * Guard helper: resolves to null when access is allowed, or a 403 Response when not.
 * In an unconfigured environment we fail closed in production but allow when
 * ALLOW_INSECURE_ADMIN is explicitly set (local dev only).
 */
export async function requireAccess(request, env) {
  const result = await verifyAccess(request, env);
  if (result.ok) return null;
  if (result.reason === "unconfigured" && env.ALLOW_INSECURE_ADMIN === "true") return null;
  return new Response(JSON.stringify({ error: "Forbidden — Cloudflare Access required" }), {
    status: 403,
    headers: { "content-type": "application/json; charset=utf-8" },
  });
}
