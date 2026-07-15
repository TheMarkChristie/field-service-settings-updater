import { describe, it, expect, vi, afterEach } from "vitest";
import worker from "../src/worker.js";
import { makeEnv, makeCtx, jsonRequest } from "./harness.js";

const BASE = "https://badges.scottishsummit.com";

afterEach(() => { vi.unstubAllGlobals(); });

async function seedEvent(env) {
  await worker.fetch(jsonRequest(`${BASE}/api/event`, { name: "Scottish Summit", year: "2026" }), env, makeCtx());
}

describe("Turnstile on the request form", () => {
  it("rejects a request with a missing/invalid token when Turnstile is configured", async () => {
    vi.stubGlobal("fetch", vi.fn(async () => new Response(JSON.stringify({ success: false }), { status: 200 })));
    const env = makeEnv({ TURNSTILE_SECRET: "sk_test" });
    await seedEvent(env);
    const r = await worker.fetch(jsonRequest(`${BASE}/api/request`, {
      name: "Ada", email: "ada@x.com", eventId: "scottish-summit-2026", role: "speaker",
      "cf-turnstile-response": "bad",
    }), env, makeCtx());
    expect(r.status).toBe(400);
  });

  it("accepts a request with a valid token → stored pending", async () => {
    vi.stubGlobal("fetch", vi.fn(async (url) => {
      if (String(url).includes("siteverify")) return new Response(JSON.stringify({ success: true }), { status: 200 });
      return new Response("{}", { status: 200 });
    }));
    const env = makeEnv({ TURNSTILE_SECRET: "sk_test" });
    await seedEvent(env);
    const r = await worker.fetch(jsonRequest(`${BASE}/api/request`, {
      name: "Ada", email: "ada@x.com", eventId: "scottish-summit-2026", role: "speaker",
      "cf-turnstile-response": "good",
    }), env, makeCtx());
    expect(r.status).toBe(200);
    const list = await (await worker.fetch(new Request(`${BASE}/api/requests`), env, makeCtx())).json();
    expect(list.requests).toHaveLength(1);
    expect(list.requests[0].status).toBe("pending");
  });

  it("honeypot still drops bots even with a valid token", async () => {
    vi.stubGlobal("fetch", vi.fn(async () => new Response(JSON.stringify({ success: true }), { status: 200 })));
    const env = makeEnv({ TURNSTILE_SECRET: "sk_test" });
    await seedEvent(env);
    const r = await worker.fetch(jsonRequest(`${BASE}/api/request`, {
      name: "Bot", email: "bot@x.com", eventId: "scottish-summit-2026", role: "speaker",
      website: "http://spam", "cf-turnstile-response": "good",
    }), env, makeCtx());
    expect(r.status).toBe(200);
    const list = await (await worker.fetch(new Request(`${BASE}/api/requests`), env, makeCtx())).json();
    expect(list.requests).toHaveLength(0);
  });
});
