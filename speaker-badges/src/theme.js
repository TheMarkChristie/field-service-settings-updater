// Design tokens extracted for the Scottish Summit brand (see CLAUDE.md §5).
// One place to update when the brand evolves. Colours are grounded in the values
// documented from the live site; where a brand colour fails WCAG AA against its
// text, the hue is kept and lightness minimally reduced — see PINK_BTN below.

export const TOKENS = {
  indigo: "#29235c", // headers / dark sections
  indigoDeep: "#1d1942", // footer / deepest panels
  pink: "#EC1878", // brand accent, decoration, large text on dark
  // Button fill: the brand pink (#EC1878) yields only ~4.2:1 with white text —
  // below the 4.5:1 AA floor for normal text — so buttons use this minimally
  // deepened hue (~5.1:1 with white), keeping the same magenta/pink hue.
  pinkBtn: "#C4157C",
  magenta: "#C4157C", // striped section backgrounds
  white: "#ffffff",
  ink: "#241f4a", // body text on light
  line: "#e6e2f0",
};

/** Relative luminance of a #rrggbb colour (WCAG 2.1). */
export function relLuminance(hex) {
  const m = /^#?([0-9a-f]{6})$/i.exec(hex);
  if (!m) return 0;
  const n = parseInt(m[1], 16);
  const chan = (c) => {
    const s = c / 255;
    return s <= 0.03928 ? s / 12.92 : ((s + 0.055) / 1.055) ** 2.4;
  };
  const r = chan((n >> 16) & 255);
  const g = chan((n >> 8) & 255);
  const b = chan(n & 255);
  return 0.2126 * r + 0.7152 * g + 0.0722 * b;
}

/** Contrast ratio between two #rrggbb colours (1..21). */
export function contrastRatio(a, b) {
  const l1 = relLuminance(a);
  const l2 = relLuminance(b);
  const [hi, lo] = l1 >= l2 ? [l1, l2] : [l2, l1];
  return (hi + 0.05) / (lo + 0.05);
}

/**
 * Base stylesheet shared by every page. Reproduces the site shell: indigo header,
 * pink dashed-outline buttons, white dashed-border cards, tartan/striped section
 * backgrounds, and the wordmark colour treatment. Honours prefers-reduced-motion
 * and reflows to 320px.
 */
export function themeCss() {
  const t = TOKENS;
  return `
:root{
  --indigo:${t.indigo}; --indigo-deep:${t.indigoDeep};
  --pink:${t.pink}; --pink-btn:${t.pinkBtn}; --magenta:${t.magenta};
  --white:${t.white}; --ink:${t.ink}; --line:${t.line};
  --radius:12px;
  --font:'Poppins','Segoe UI',system-ui,-apple-system,'Helvetica Neue',Arial,sans-serif;
  color-scheme:light;
}
*{box-sizing:border-box}
html{-webkit-text-size-adjust:100%}
body{margin:0;font-family:var(--font);color:var(--ink);background:var(--white);line-height:1.5}
h1,h2,h3{font-weight:800;line-height:1.15;margin:0 0 .4em}
a{color:var(--pink-btn)}
img{max-width:100%;height:auto}

/* Skip link */
.skip{position:absolute;left:-9999px;top:0;background:var(--pink-btn);color:#fff;padding:10px 14px;border-radius:0 0 8px 0;z-index:100}
.skip:focus{left:0}

/* Visible focus ring (>=3:1 against white/indigo) */
a:focus-visible,button:focus-visible,input:focus-visible,select:focus-visible,textarea:focus-visible{
  outline:3px solid var(--pink);outline-offset:2px;border-radius:6px}

/* The signature purple/magenta tartan: crossed translucent bands + fine pinstripes. */
.tartan{
  background-color:var(--indigo);
  background-image:
    repeating-linear-gradient(90deg, rgba(255,255,255,.06) 0 2px, transparent 2px 26px),
    repeating-linear-gradient(0deg, rgba(255,255,255,.06) 0 2px, transparent 2px 26px),
    repeating-linear-gradient(90deg, rgba(236,24,120,.16) 0 14px, transparent 14px 52px),
    repeating-linear-gradient(0deg, rgba(236,24,120,.16) 0 14px, transparent 14px 52px),
    linear-gradient(135deg,#342a70,#241d54);
}
.striped{
  background-color:var(--magenta);
  background-image:repeating-linear-gradient(135deg, rgba(255,255,255,.08) 0 8px, transparent 8px 22px);
  color:#fff;
}

/* Header / nav */
.site-header{background:var(--indigo);color:#fff;position:relative}
.site-header .bar{max-width:1120px;margin:0 auto;display:flex;align-items:center;gap:18px;padding:14px 20px}
.site-header .logo{display:inline-flex;align-items:center;gap:10px;color:#fff;text-decoration:none;font-weight:800}
.site-header .logo .hex{width:40px;height:44px;flex:0 0 auto}
.site-nav{margin-left:auto;display:flex;flex-wrap:wrap;gap:2px}
.site-nav a{color:#fff;text-decoration:none;padding:8px 12px;border-radius:8px;font-weight:600;font-size:14px}
.site-nav a:hover{background:rgba(255,255,255,.12)}
.site-nav a[aria-current=page]{color:var(--pink);text-decoration:underline}
.navtoggle{display:none;margin-left:auto;background:transparent;border:2px dashed rgba(255,255,255,.6);color:#fff;border-radius:8px;padding:8px 12px;font-weight:700;cursor:pointer}
@media (max-width:760px){
  .navtoggle{display:inline-block}
  .site-nav{display:none;flex-basis:100%;flex-direction:column;margin:0;padding:0 20px 12px}
  .site-nav.open{display:flex}
}

/* Wordmark colour treatment: "Scottish" white, "Summit" pink */
.wordmark .wm-a{color:#fff}
.wordmark .wm-b{color:var(--pink)}
.on-dark .wordmark .wm-a{color:#fff}

/* Layout */
main{display:block}
.wrap{max-width:1120px;margin:0 auto;padding:28px 20px}
.section{padding:40px 20px}
.section .inner{max-width:1120px;margin:0 auto}

/* Cards */
.card{background:#fff;border:2px dashed var(--pink);border-radius:var(--radius);padding:22px;color:var(--ink)}
.card.plain{border-style:solid;border-color:var(--line)}
.bubble{position:relative}
.bubble::after{content:"";position:absolute;left:40px;bottom:-14px;width:26px;height:26px;background:#fff;border-right:2px dashed var(--pink);border-bottom:2px dashed var(--pink);transform:rotate(45deg)}

/* Buttons */
.btn{display:inline-block;background:var(--pink-btn);color:#fff;font-weight:800;text-decoration:none;
  border:2px dashed rgba(255,255,255,.85);border-radius:var(--radius);padding:12px 20px;cursor:pointer;font-size:15px;font-family:inherit}
.btn:hover{filter:brightness(.94)}
.btn.inverse{background:#fff;color:var(--pink-btn);border-color:var(--pink-btn)}
.btn:disabled{opacity:.5;cursor:not-allowed}

/* Forms */
label{display:block;font-weight:700;margin:14px 0 5px}
input,select,textarea{width:100%;padding:11px 12px;border:2px solid var(--line);border-radius:10px;font:inherit;background:#fff;color:var(--ink)}
input:focus,select:focus,textarea:focus{border-color:var(--pink)}
.field-error{color:#8a0d3f;font-weight:700;margin-top:6px}
.hp{position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden}

/* Live status region */
.status{min-height:1.4em;font-weight:700}
.status.ok{color:#0d6a2f}
.status.err{color:#8a0d3f}

/* Footer */
.site-footer{background:var(--indigo-deep);color:#e9e6f5;padding:34px 20px}
.site-footer .inner{max-width:1120px;margin:0 auto;display:flex;flex-wrap:wrap;gap:18px;justify-content:space-between;align-items:center}
.site-footer a{color:#fff}
.site-footer .socials{display:flex;gap:14px;flex-wrap:wrap}
.site-footer .charity{font-size:13px;opacity:.9;max-width:640px}

@media (prefers-reduced-motion:reduce){*{animation:none!important;transition:none!important}}
`;
}
