import { describe, it, expect } from "vitest";
import worker from "../src/worker.js";
import { makeEnv, makeCtx } from "./harness.js";

const BASE = "https://badges.scottishsummit.com";

describe("Cloudflare Access enforcement", () => {
  it("unconfigured + no dev bypass → admin API returns 403", async () => {
    const env = makeEnv({ ALLOW_INSECURE_ADMIN: "false" });
    const r = await worker.fetch(new Request(`${BASE}/api/requests`), env, makeCtx());
    expect(r.status).toBe(403);
  });

  it("configured but no/forged JWT → 403", async () => {
    const env = makeEnv({
      ALLOW_INSECURE_ADMIN: "false",
      ACCESS_AUD: "aud-tag", ACCESS_TEAM_DOMAIN: "team.cloudflareaccess.com",
    });
    const noJwt = await worker.fetch(new Request(`${BASE}/api/requests`), env, makeCtx());
    expect(noJwt.status).toBe(403);

    const forged = await worker.fetch(new Request(`${BASE}/api/requests`, {
      headers: { "Cf-Access-Jwt-Assertion": "not.a.jwt" },
    }), env, makeCtx());
    expect(forged.status).toBe(403);
  });

  it("dev bypass allows the admin console through (defense-in-depth off locally)", async () => {
    const env = makeEnv(); // ALLOW_INSECURE_ADMIN = "true"
    const r = await worker.fetch(new Request(`${BASE}/admin`), env, makeCtx());
    expect(r.status).toBe(200);
    expect(await r.text()).toContain("Badges admin console");
  });

  it("unauthenticated GET /admin is forbidden when Access is configured", async () => {
    const env = makeEnv({ ALLOW_INSECURE_ADMIN: "false", ACCESS_AUD: "a", ACCESS_TEAM_DOMAIN: "t.cloudflareaccess.com" });
    const r = await worker.fetch(new Request(`${BASE}/admin`), env, makeCtx());
    expect(r.status).toBe(403);
  });
});
