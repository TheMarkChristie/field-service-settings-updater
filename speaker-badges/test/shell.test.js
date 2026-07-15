import { describe, it, expect } from "vitest";
import { requestPage, badgePage, notFoundPage } from "../src/render.js";
import { adminPage } from "../src/admin.js";

const AWARD = {
  token: "abc", certId: "SCOTT-SUMMI-2026-A1B2C3", eventId: "scottish-summit-2026",
  name: "Ada Lovelace", role: "speaker", email: "ada@x.com", eventName: "Scottish Summit",
  year: "2026", month: "6", orgId: "999", orgName: "Scottish Summit", artKey: "", issued: "2026-07-15",
};

const pages = {
  badge: badgePage(AWARD, "https://badges.scottishsummit.com"),
  request: requestPage([{ eventId: "scottish-summit-2026", eventName: "Scottish Summit", year: "2026" }], ""),
  admin: adminPage(),
  notFound: notFoundPage(),
};

describe("site shell present on all page types", () => {
  for (const [name, page] of Object.entries(pages)) {
    it(`${name}: header nav, charity line and social links`, () => {
      // Header nav (site links + Badges)
      expect(page).toContain("Speakers");
      expect(page).toContain("Workshops");
      expect(page).toContain(">Badges<");
      // Footer charity line
      expect(page).toContain("Charity Number SC052785");
      // Social links
      expect(page).toContain("LinkedIn");
      expect(page).toContain("YouTube");
      // Valid document with a unique title and lang
      expect(page).toContain('lang="en-GB"');
      expect(page).toMatch(/<title>[^<]+<\/title>/);
    });
  }

  it("wordmark colour treatment: Scottish white, Summit pink", () => {
    expect(pages.badge).toContain('class="wm-a">Scottish');
    expect(pages.badge).toContain('class="wm-b">Summit');
  });
});
