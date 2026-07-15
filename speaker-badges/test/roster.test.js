import { describe, it, expect } from "vitest";
import { makeEnv, makeCtx } from "./harness.js";
import { issueRoster, bulkImport, recomputeMilestones } from "../src/roster.js";
import { getEvent, listAwards } from "../src/badges.js";

const ORIGIN = "https://badges.scottishsummit.com";

describe("roster issue", () => {
  it("mints one award per person with correct roles and LinkedIn links", async () => {
    const env = makeEnv();
    const csv = "name,role,email\nAda Lovelace,speaker,ada@example.com\nGrace Hopper,volunteer,grace@example.com";
    const res = await issueRoster(env, ORIGIN, { name: "Scottish Summit", year: "2026", orgId: "999", csv, sendEmail: false }, makeCtx());
    expect(res.issued).toHaveLength(2);
    const ada = res.issued.find((a) => a.name === "Ada Lovelace");
    expect(ada.role).toBe("speaker");
    expect(ada.badgeUrl).toContain("/b/");
    expect(ada.linkedInUrl).toContain("startTask=CERTIFICATION_NAME");
    expect(ada.linkedInUrl).toContain("organizationId=999");
    expect(ada.linkedInUrl).toContain("issueYear=2026");
    expect(ada.linkedInUrl).toContain("certId=");
  });

  it("rejects rows with an invalid role", async () => {
    const env = makeEnv();
    const csv = "name,role,email\nNo Role,wizard,x@example.com";
    const res = await issueRoster(env, ORIGIN, { name: "Scottish Summit", year: "2026", csv }, makeCtx());
    expect(res.issued).toHaveLength(0);
    expect(res.rejected).toHaveLength(1);
  });
});

describe("bulk import + milestones", () => {
  it("creates each event once, issues everyone, and is idempotent on re-import", async () => {
    const env = makeEnv();
    const rows = [];
    for (const y of ["2020", "2021", "2022", "2023", "2024", "2026"]) {
      rows.push(`Scottish Summit,${y},Ada Lovelace,speaker,ada@example.com`);
    }
    rows.push("Scottish Summit,2026,Grace Hopper,volunteer,grace@example.com");
    const csv = "event,year,name,role,email\n" + rows.join("\n");

    const first = await bulkImport(env, ORIGIN, { csv, sendEmail: false }, makeCtx());
    expect(first.total).toBe(7);
    expect(first.groups).toHaveLength(6); // six distinct event+year groups

    // Ada appears in 6 distinct years → one milestone5 issued automatically.
    expect(first.milestones).toHaveLength(1);
    expect(first.milestones[0].name).toBe("Ada Lovelace");
    expect(first.milestones[0].role).toBe("milestone5");

    // Re-import the same file: no new awards, no duplicate milestone.
    const awardsAfterFirst = (await listAwards(env)).length;
    const second = await bulkImport(env, ORIGIN, { csv, sendEmail: false }, makeCtx());
    expect(second.milestones).toHaveLength(0);
    expect((await listAwards(env)).length).toBe(awardsAfterFirst);

    const ev = await getEvent(env, "scottish-summit-2026");
    expect(ev.eventName).toBe("Scottish Summit");
  });

  it("rejects rows without an email and reports them", async () => {
    const env = makeEnv();
    const csv = "event,year,name,role,email\nScottish Summit,2026,No Email,speaker,\nScottish Summit,2026,Has Email,speaker,e@example.com";
    const res = await bulkImport(env, ORIGIN, { csv, sendEmail: false }, makeCtx());
    expect(res.total).toBe(1);
    expect(res.rejected.some((r) => /email/.test(r.reason))).toBe(true);
  });

  it("milestone needs 5 DISTINCT years across any role", async () => {
    const env = makeEnv();
    // Same person, only 4 distinct years → no milestone.
    const csv = "event,year,name,role,email\n" +
      ["2020", "2021", "2022", "2023"].map((y) => `Scottish Summit,${y},Ada,speaker,ada@example.com`).join("\n");
    await bulkImport(env, ORIGIN, { csv, sendEmail: false }, makeCtx());
    const { milestones } = await recomputeMilestones(env, ORIGIN, makeCtx());
    expect(milestones).toHaveLength(0);
  });
});
