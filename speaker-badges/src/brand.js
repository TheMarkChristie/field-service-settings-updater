// Brand assets in one place.
//
// The Scottish Summit logo is the real mark, base64-embedded in logo-data.js and
// served by the Worker from /brand/logo.png (see worker.js). The header references
// that same-origin URL so each HTML page stays lean and the browser caches the
// image. To replace the logo, drop a new assets/logo.png and run
// `node scripts/embed-logo.mjs`.

import { SITE_LOGO_PNG_BASE64 } from "./logo-data.js";

export const LOGO_PATH = "/brand/logo.png";

/** Header markup for the logo (same-origin <img>, cached via the /brand route). */
export function siteLogoMarkup() {
  return `<img class="hex" src="${LOGO_PATH}" width="46" height="51" alt="Scottish Summit logo" decoding="async">`;
}

/** Decoded PNG bytes, for the /brand/logo.png route. */
export function siteLogoBytes() {
  const bin = atob(SITE_LOGO_PNG_BASE64);
  const bytes = new Uint8Array(bin.length);
  for (let i = 0; i < bin.length; i++) bytes[i] = bin.charCodeAt(i);
  return bytes;
}
