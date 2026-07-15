// Regenerate src/logo-data.js from assets/logo.png.
// Usage: node scripts/embed-logo.mjs
import { readFileSync, writeFileSync } from "node:fs";
import { fileURLToPath } from "node:url";
import { dirname, join } from "node:path";

const root = join(dirname(fileURLToPath(import.meta.url)), "..");
const b64 = readFileSync(join(root, "assets/logo.png")).toString("base64");
const out = `// Scottish Summit logo (PNG), base64-embedded so the single Worker can serve it
// from /brand/logo.png with no external fetch. Source: assets/logo.png (425x470).
// To replace: drop a new assets/logo.png and re-run scripts/embed-logo.mjs.
export const SITE_LOGO_PNG_BASE64 =
  "${b64}";
`;
writeFileSync(join(root, "src/logo-data.js"), out);
console.log(`Embedded ${b64.length} base64 chars into src/logo-data.js`);
