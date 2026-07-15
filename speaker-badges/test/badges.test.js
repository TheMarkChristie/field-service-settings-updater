import { describe, it, expect } from "vitest";
import { makeCertId, linkedInUrl, makeEventId } from "../src/badges.js";
import { newToken } from "../src/lib.js";

describe("cert IDs and tokens", () => {
  it("derives a readable, deterministic cert id from event + year + token", () => {
    const id = makeCertId("Scottish Summit", "2026", "a1b2c3d4e5f6");
    expect(id).toBe("SCOTT-SUMMI-2026-A1B2C3");
  });

  it("produces a stable id for the same token but differs across tokens", () => {
    const a = makeCertId("Scottish Summit", "2026", "deadbeefcafe");
    const b = makeCertId("Scottish Summit", "2026", "deadbeefcafe");
    const c = makeCertId("Scottish Summit", "2026", "0011223344ff");
    expect(a).toBe(b);
    expect(a).not.toBe(c);
  });

  it("tokens are 32 hex chars with no dashes (unguessable)", () => {
    const t = newToken();
    expect(t).toMatch(/^[0-9a-f]{32}$/);
    expect(new Set([newToken(), newToken(), newToken()]).size).toBe(3);
  });

  it("eventId slugs name + year", () => {
    expect(makeEventId("Scottish Summit", "2026")).toBe("scottish-summit-2026");
  });
});

describe("LinkedIn Add-to-Profile URL", () => {
  it("carries the certification query keys LinkedIn expects", () => {
    const u = new URL(linkedInUrl({
      certName: "Speaker", orgId: "12345678", year: "2026", month: "6",
      certUrl: "https://badges.scottishsummit.com/b/abc", certId: "SCOTT-SUMMI-2026-A1B2C3",
    }));
    expect(u.searchParams.get("startTask")).toBe("CERTIFICATION_NAME");
    expect(u.searchParams.get("organizationId")).toBe("12345678");
    expect(u.searchParams.get("issueYear")).toBe("2026");
    expect(u.searchParams.get("issueMonth")).toBe("6");
    expect(u.searchParams.get("certUrl")).toBe("https://badges.scottishsummit.com/b/abc");
    expect(u.searchParams.get("certId")).toBe("SCOTT-SUMMI-2026-A1B2C3");
    expect(u.searchParams.get("name")).toBe("Speaker");
  });
});
