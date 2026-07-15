import { describe, it, expect } from "vitest";
import worker from "../src/worker.js";
import { makeEnv, makeCtx, jsonRequest } from "./harness.js";
import { issueRoster } from "../src/roster.js";

const BASE = "https://badges.scottishsummit.com";

async function seedRoster(env, extra = {}) {
  return issueRoster(env, BASE, {
    name: "Scottish Summit", year: "2026", orgId: "999",
    csv: "name,role,email\nAda Lovelace,speaker,ada@example.com", sendEmail: false, ...extra,
  }, makeCtx());
}

describe("verify / badge page", () => {
  it("renders name, role, Verified and the LinkedIn anchor", async () => {
    const env = makeEnv();
    const res = await seedRoster(env);
    const token = res.issued[0].token;
    const r = await worker.fetch(new Request(`${BASE}/b/${token}`), env, makeCtx());
    expect(r.status).toBe(200);
    const body = await r.text();
    expect(body).toContain("Ada Lovelace");
    expect(body).toContain("Speaker");
    expect(body).toContain("Verified");
    expect(body).toContain("linkedin.com/profile/add");
    expect(body).toContain("startTask=CERTIFICATION_NAME");
  });

  it("unknown token → 404 not-found page with the site shell", async () => {
    const env = makeEnv();
    const r = await worker.fetch(new Request(`${BASE}/b/doesnotexist`), env, makeCtx());
    expect(r.status).toBe(404);
    const body = await r.text();
    expect(body).toContain("Badge not found");
    expect(body).toContain("SC052785"); // footer charity line present
  });

  it("uses a styled placeholder and NO og:image when there is no artwork", async () => {
    const env = makeEnv();
    const res = await seedRoster(env);
    const r = await worker.fetch(new Request(`${BASE}/b/${res.issued[0].token}`), env, makeCtx());
    const body = await r.text();
    expect(body).toContain("badge-art placeholder");
    expect(body).not.toContain('property="og:image"');
  });

  it("/api/verify returns badge JSON, 404 for unknown", async () => {
    const env = makeEnv();
    const res = await seedRoster(env);
    const ok = await worker.fetch(new Request(`${BASE}/api/verify?token=${res.issued[0].token}`), env, makeCtx());
    expect(ok.status).toBe(200);
    expect((await ok.json()).verified).toBe(true);
    const bad = await worker.fetch(new Request(`${BASE}/api/verify?token=nope`), env, makeCtx());
    expect(bad.status).toBe(404);
  });
});

describe("R2 artwork", () => {
  const PNG = new Uint8Array([0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a, 1, 2, 3, 4]);

  it("uploads, serves with cache headers, and shows the art + absolute og:image on the badge", async () => {
    const env = makeEnv();
    // Create the event first so the artKey denormalises onto it.
    await worker.fetch(jsonRequest(`${BASE}/api/event`, { name: "Scottish Summit", year: "2026" }), env, makeCtx());
    const up = await worker.fetch(new Request(`${BASE}/api/upload?kind=art&eventId=scottish-summit-2026&role=speaker`, {
      method: "POST", headers: { "content-type": "image/png" }, body: PNG,
    }), env, makeCtx());
    expect(up.status).toBe(200);
    const { key } = await up.json();
    expect(key).toBe("art/scottish-summit-2026/speaker.png");

    const img = await worker.fetch(new Request(`${BASE}/img/${key}`), env, makeCtx());
    expect(img.status).toBe(200);
    expect(img.headers.get("content-type")).toBe("image/png");
    expect(img.headers.get("cache-control")).toContain("max-age=31536000");
    expect(new Uint8Array(await img.arrayBuffer())).toEqual(PNG);

    // Now issue a roster — the award should pick up the artKey.
    const res = await seedRoster(env);
    const badge = await worker.fetch(new Request(`${BASE}/b/${res.issued[0].token}`), env, makeCtx());
    const body = await badge.text();
    expect(body).toContain(`/img/${key}`);
    expect(body).toContain(`property="og:image" content="${BASE}/img/${key}"`);
  });

  it("rejects wrong type and oversize uploads", async () => {
    const env = makeEnv();
    const bad = await worker.fetch(new Request(`${BASE}/api/upload?kind=art&eventId=e-2026&role=speaker`, {
      method: "POST", headers: { "content-type": "application/pdf" }, body: PNG,
    }), env, makeCtx());
    expect(bad.status).toBe(400);

    const big = new Uint8Array(2 * 1024 * 1024 + 1);
    const oversize = await worker.fetch(new Request(`${BASE}/api/upload?kind=art&eventId=e-2026&role=speaker`, {
      method: "POST", headers: { "content-type": "image/png" }, body: big,
    }), env, makeCtx());
    expect(oversize.status).toBe(400);
  });

  it("missing image key → 404", async () => {
    const env = makeEnv();
    const r = await worker.fetch(new Request(`${BASE}/img/art/none/x.png`), env, makeCtx());
    expect(r.status).toBe(404);
  });
});

describe("request → approve/reject", () => {
  async function seedEvent(env) {
    await worker.fetch(jsonRequest(`${BASE}/api/event`, { name: "Scottish Summit", year: "2026", orgId: "42" }), env, makeCtx());
  }

  it("drops honeypot submissions silently", async () => {
    const env = makeEnv();
    await seedEvent(env);
    const r = await worker.fetch(jsonRequest(`${BASE}/api/request`, {
      name: "Bot", email: "bot@x.com", eventId: "scottish-summit-2026", role: "speaker", website: "http://spam",
    }), env, makeCtx());
    expect(r.status).toBe(200);
    const list = await (await worker.fetch(new Request(`${BASE}/api/requests`), env, makeCtx())).json();
    expect(list.requests).toHaveLength(0);
  });

  it("stores a genuine request pending, approve mints inheriting org/year, reject flips status", async () => {
    const env = makeEnv();
    await seedEvent(env);
    await worker.fetch(jsonRequest(`${BASE}/api/request`, {
      name: "Ada", email: "ada@x.com", eventId: "scottish-summit-2026", role: "speaker",
    }), env, makeCtx());
    let list = await (await worker.fetch(new Request(`${BASE}/api/requests`), env, makeCtx())).json();
    expect(list.requests).toHaveLength(1);
    expect(list.requests[0].status).toBe("pending");
    const id = list.requests[0].id;

    const appr = await (await worker.fetch(jsonRequest(`${BASE}/api/requests/approve`, { id }), env, makeCtx())).json();
    expect(appr.request.status).toBe("approved");
    expect(appr.request.linkedInUrl).toContain("organizationId=42");
    expect(appr.request.linkedInUrl).toContain("issueYear=2026");

    // Second request → reject.
    await worker.fetch(jsonRequest(`${BASE}/api/request`, { name: "Bo", email: "bo@x.com", eventId: "scottish-summit-2026", role: "volunteer" }), env, makeCtx());
    list = await (await worker.fetch(new Request(`${BASE}/api/requests`), env, makeCtx())).json();
    const pend = list.requests.find((r) => r.status === "pending");
    const rej = await (await worker.fetch(jsonRequest(`${BASE}/api/requests/reject`, { id: pend.id }), env, makeCtx())).json();
    expect(rej.request.status).toBe("rejected");
  });
});
