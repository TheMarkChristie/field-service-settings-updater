import { describe, it, expect } from "vitest";
import { TOKENS, contrastRatio } from "../src/theme.js";

// WCAG 2.1 AA: 4.5:1 for normal text, 3:1 for large text and UI/focus indicators.
describe("palette meets WCAG 2.1 AA", () => {
  it("white text on the button pink ≥ 4.5:1", () => {
    expect(contrastRatio("#ffffff", TOKENS.pinkBtn)).toBeGreaterThanOrEqual(4.5);
  });

  it("white text on indigo header ≥ 4.5:1", () => {
    expect(contrastRatio("#ffffff", TOKENS.indigo)).toBeGreaterThanOrEqual(4.5);
  });

  it("white text on the striped magenta band ≥ 4.5:1", () => {
    expect(contrastRatio("#ffffff", TOKENS.magenta)).toBeGreaterThanOrEqual(4.5);
  });

  it("body ink on white ≥ 4.5:1", () => {
    expect(contrastRatio(TOKENS.ink, "#ffffff")).toBeGreaterThanOrEqual(4.5);
  });

  it("pink wordmark on indigo (large text) ≥ 3:1", () => {
    expect(contrastRatio(TOKENS.pink, TOKENS.indigo)).toBeGreaterThanOrEqual(3);
  });

  it("pink focus ring on white (UI indicator) ≥ 3:1", () => {
    expect(contrastRatio(TOKENS.pink, "#ffffff")).toBeGreaterThanOrEqual(3);
  });

  it("the brand button pink is genuinely deepened from the accent pink", () => {
    // Documents the intentional AA adjustment: the raw accent fails 4.5:1 with white.
    expect(contrastRatio("#ffffff", TOKENS.pink)).toBeLessThan(4.5);
    expect(contrastRatio("#ffffff", TOKENS.pinkBtn)).toBeGreaterThanOrEqual(4.5);
  });
});
