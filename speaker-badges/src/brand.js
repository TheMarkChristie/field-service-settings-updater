// Brand assets in one place.
//
// ⚠️ PLACEHOLDER LOGO. The header currently uses a hand-drawn approximation of the
// Scottish Summit hexagonal badge (indigo hexagon, pink border, angled wordmark,
// white sash). The brief (§5) calls for the *real* logo, downloaded from the site
// header asset `/_next/static/.../logo.*.svg` and inlined here (or stored in R2).
//
// TO INSTALL THE REAL LOGO — two options:
//   1. Inline (preferred, CSP-friendly): paste the SVG markup into SITE_LOGO_SVG
//      below. Keep width/height ~40x44 or set them via the .hex CSS class.
//   2. R2-hosted: upload it (kind=logo, a reserved eventId like "site") and point
//      the header at /img/logo/site.svg instead of calling siteLogoSvg().
//
// Until then this placeholder renders everywhere the shell appears.

const SITE_LOGO_SVG = `<svg class="hex" viewBox="0 0 40 44" role="img" aria-label="Scottish Summit logo">
  <polygon points="20,1 38,11 38,33 20,43 2,33 2,11" fill="#29235c" stroke="#EC1878" stroke-width="2"/>
  <path d="M3.5 25 L36.5 19.5" stroke="#fff" stroke-width="4" opacity=".92"/>
  <circle cx="13" cy="15" r="2.4" fill="#fff"/><circle cx="20" cy="13.5" r="2.6" fill="#EC1878"/><circle cx="27" cy="15" r="2.4" fill="#fff"/>
  <text x="20" y="30" text-anchor="middle" font-size="4.6" fill="#fff" font-family="Poppins,Arial" font-weight="700" transform="rotate(-6 20 30)">SCOTTISH</text>
  <text x="20" y="37" text-anchor="middle" font-size="4.6" fill="#EC1878" font-family="Poppins,Arial" font-weight="700" transform="rotate(-6 20 37)">SUMMIT</text>
</svg>`;

/** True until the real logo replaces the placeholder above (drives a dev-only hint). */
export const LOGO_IS_PLACEHOLDER = true;

export function siteLogoSvg() {
  return SITE_LOGO_SVG;
}
