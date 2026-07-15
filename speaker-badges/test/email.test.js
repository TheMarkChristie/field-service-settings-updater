import { describe, it, expect, vi, afterEach } from "vitest";
import { makeEnv, makeCtx } from "./harness.js";
import { issueRoster, emailUnsent } from "../src/roster.js";
import { listAwards } from "../src/badges.js";

const ORIGIN = "https://badges.scottishsummit.com";

afterEach(() => { vi.unstubAllGlobals(); });

function stubResendCounter() {
  const calls = [];
  vi.stubGlobal("fetch", vi.fn(async (url, init) => {
    calls.push({ url: String(url), body: init?.body });
    return new Response(JSON.stringify({ id: "mock" }), { status: 200 });
  }));
  return calls;
}

describe("transactional email", () => {
  it("sends one styled email per person when the box is ticked", async () => {
    const calls = stubResendCounter();
    const env = makeEnv({ EMAIL_API_KEY: "re_test", EMAIL_FROM: "badges@scottishsummit.com" });
    const ctx = makeCtx();
    await issueRoster(env, ORIGIN, {
      name: "Scottish Summit", year: "2026",
      csv: "name,role,email\nAda,speaker,ada@x.com\nGrace,volunteer,grace@x.com",
      sendEmail: true,
    }, ctx);
    await ctx.drain();
    const sends = calls.filter((c) => c.url.includes("resend.com"));
    expect(sends).toHaveLength(2);
    expect(String(sends[0].body)).toContain("badge"); // styled HTML body
  });

  it("is a silent no-op when EMAIL_API_KEY is unset", async () => {
    const calls = stubResendCounter();
    const env = makeEnv(); // no EMAIL_API_KEY
    const ctx = makeCtx();
    await issueRoster(env, ORIGIN, {
      name: "Scottish Summit", year: "2026", csv: "name,role,email\nAda,speaker,ada@x.com", sendEmail: true,
    }, ctx);
    await ctx.drain();
    expect(calls.filter((c) => c.url.includes("resend.com"))).toHaveLength(0);
  });

  it("'email unsent' targets exactly the un-emailed awards", async () => {
    const calls = stubResendCounter();
    const env = makeEnv({ EMAIL_API_KEY: "re_test" });

    // Issue WITHOUT emailing → nobody has emailedAt.
    let ctx = makeCtx();
    await issueRoster(env, ORIGIN, {
      name: "Scottish Summit", year: "2026",
      csv: "name,role,email\nAda,speaker,ada@x.com\nGrace,volunteer,grace@x.com", sendEmail: false,
    }, ctx);
    await ctx.drain();
    expect(calls.length).toBe(0);

    // Now send unsent → two emails, then emailedAt set on both.
    ctx = makeCtx();
    const res = await emailUnsent(env, ORIGIN, ctx);
    await ctx.drain();
    expect(res.sent).toBe(2);

    // Running again sends nothing (all now marked emailed).
    ctx = makeCtx();
    const again = await emailUnsent(env, ORIGIN, ctx);
    await ctx.drain();
    expect(again.sent).toBe(0);

    const awards = await listAwards(env);
    expect(awards.every((a) => a.emailedAt)).toBe(true);
  });
});
