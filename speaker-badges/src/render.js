// Server-rendered HTML: the shared site shell (header/footer matching
// scottishsummit.com) and the four page types — badge/verify, request, admin, 404.

import { esc } from "./lib.js";
import { themeCss } from "./theme.js";
import { roleLabel, awardLinks, imageUrl } from "./badges.js";

const NAV = [
  ["Home", "https://scottishsummit.com/"],
  ["About", "https://scottishsummit.com/about"],
  ["Sponsors", "https://scottishsummit.com/sponsors"],
  ["Speakers", "https://scottishsummit.com/speakers"],
  ["Workshops", "https://scottishsummit.com/workshops"],
  ["Events", "https://scottishsummit.com/events"],
  ["Guidelines", "https://scottishsummit.com/guidelines"],
];

const SOCIALS = [
  ["X", "https://x.com/scottishsummit"],
  ["LinkedIn", "https://www.linkedin.com/company/scottish-summit"],
  ["Facebook", "https://www.facebook.com/scottishsummit"],
  ["YouTube", "https://www.youtube.com/@scottishsummit"],
  ["Instagram", "https://www.instagram.com/scottishsummit"],
];

// Hexagonal badge logo (indigo hexagon, pink border, angled wordmark, sash).
function hexLogo() {
  return `<svg class="hex" viewBox="0 0 40 44" role="img" aria-label="Scottish Summit logo">
  <polygon points="20,1 38,11 38,33 20,43 2,33 2,11" fill="#29235c" stroke="#EC1878" stroke-width="2"/>
  <path d="M4 24 L36 20" stroke="#fff" stroke-width="4" opacity=".92"/>
  <text x="20" y="16" text-anchor="middle" font-size="6" fill="#fff" font-family="Poppins,Arial" font-weight="700">SCOT</text>
  <text x="20" y="34" text-anchor="middle" font-size="6" fill="#EC1878" font-family="Poppins,Arial" font-weight="700">SUMMIT</text>
</svg>`;
}

export function wordmark(a = "Scottish", b = "Summit") {
  return `<span class="wordmark"><span class="wm-a">${esc(a)}</span> <span class="wm-b">${esc(b)}</span></span>`;
}

function header(current) {
  const nav = NAV.map(
    ([label, href]) => `<a href="${href}">${esc(label)}</a>`
  ).join("");
  const badgesCurrent = current === "badges" ? ' aria-current="page"' : "";
  return `<header class="site-header on-dark">
  <div class="bar">
    <a class="logo" href="https://scottishsummit.com/">${hexLogo()}${wordmark()}</a>
    <button class="navtoggle" aria-expanded="false" aria-controls="nav" onclick="var n=document.getElementById('nav');var o=n.classList.toggle('open');this.setAttribute('aria-expanded',o)">Menu</button>
    <nav class="site-nav" id="nav" aria-label="Primary">
      ${nav}
      <a href="/request"${badgesCurrent}>Badges</a>
    </nav>
  </div>
</header>`;
}

function footer() {
  const socials = SOCIALS.map(
    ([label, href]) => `<a href="${href}" rel="me noopener">${esc(label)}</a>`
  ).join("");
  return `<footer class="site-footer">
  <div class="inner">
    <p class="charity">Scottish Summit is run by Scottish Summit SCIO, Charity Number SC052785.</p>
    <nav class="socials" aria-label="Social media">${socials}</nav>
    <p><a href="https://scottishsummit.com/code-of-conduct">Code of Conduct</a> &amp; <a href="https://scottishsummit.com/privacy">Privacy Policy</a></p>
  </div>
</footer>`;
}

/** Full HTML document wrapping page content in the site shell. */
export function layout({ title, description = "", ogImage = "", current = "", body = "", head = "", multiSection = false }) {
  const og = ogImage
    ? `<meta property="og:image" content="${esc(ogImage)}"><meta name="twitter:image" content="${esc(ogImage)}"><meta name="twitter:card" content="summary_large_image">`
    : "";
  const skip = multiSection ? `<a class="skip" href="#main">Skip to content</a>` : "";
  return `<!doctype html>
<html lang="en-GB">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>${esc(title)}</title>
<meta name="description" content="${esc(description)}">
<meta property="og:title" content="${esc(title)}">
<meta property="og:description" content="${esc(description)}">
<meta property="og:type" content="website">
${og}
<style>${themeCss()}</style>
${head}
</head>
<body>
${skip}
${header(current)}
<main id="main">
${body}
</main>
${footer()}
</body>
</html>`;
}

/** The public server-rendered verify/badge page. */
export function badgePage(award, origin) {
  const { badgeUrl, linkedInUrl } = awardLinks(origin, award);
  const role = roleLabel(award.role);
  const title = `${award.name} — ${role} — ${esc(award.eventName)} ${esc(award.year)}`;
  const artUrl = award.artKey ? imageUrl(origin, award.artKey) : "";
  const visual = award.artKey
    ? `<img class="badge-art" src="${esc(artUrl)}" alt="${esc(role)} badge — ${esc(award.eventName)} ${esc(award.year)}" width="280" height="280">`
    : `<div class="badge-art placeholder" role="img" aria-label="${esc(role)} badge — ${esc(award.eventName)} ${esc(award.year)}"><span>${esc(role)}</span><small>${esc(award.eventName)} ${esc(award.year)}</small></div>`;

  const body = `
<section class="section tartan on-dark" style="color:#fff">
  <div class="inner" style="max-width:640px">
    <div class="card bubble" style="text-align:center">
      ${visual}
      <h1 style="margin-top:16px">${esc(award.name)}</h1>
      <p style="font-size:20px;font-weight:700;margin:.2em 0;color:var(--indigo)">${esc(role)} · ${esc(award.eventName)} <span style="color:var(--pink-btn)">${esc(award.year)}</span></p>
      <p style="font-weight:800;color:#0d6a2f;font-size:18px">✔ Verified</p>
      <p style="color:#555">${esc(award.eventName)}${award.orgName && award.orgName !== award.eventName ? " · " + esc(award.orgName) : ""}</p>
      <p style="margin-top:18px">
        <a class="btn" href="${esc(linkedInUrl)}" rel="noopener">Add to LinkedIn profile</a>
      </p>
      <p style="font-size:12px;color:#777;margin-top:14px">Certificate ID: <code>${esc(award.certId)}</code><br>Issued ${esc(award.issued)}</p>
    </div>
  </div>
</section>
<div class="wrap" style="text-align:center">
  <p><a href="${esc(badgeUrl)}">${esc(badgeUrl)}</a></p>
  <p style="font-size:13px;color:#666">This badge is verified against the ${esc(award.eventName)} roster.</p>
</div>`;

  const head = artUrl ? "" : ""; // og handled by layout
  const styleHead = `<style>
.badge-art{border-radius:16px;display:block;margin:0 auto;object-fit:contain}
.badge-art.placeholder{width:280px;height:280px;display:flex;flex-direction:column;align-items:center;justify-content:center;
  background:repeating-linear-gradient(135deg,#f4eef8 0 12px,#efe7f6 12px 24px);border:3px dashed var(--pink);color:var(--indigo)}
.badge-art.placeholder span{font-size:30px;font-weight:800}
.badge-art.placeholder small{font-weight:700;color:#6a6390;margin-top:6px}
</style>`;

  return layout({
    title,
    description: `${award.name} is a verified ${role} at ${award.eventName} ${award.year}.`,
    ogImage: artUrl,
    current: "badges",
    body,
    head: styleHead + head,
    multiSection: true,
  });
}

/** 404 / unknown-token page, in the site shell. */
export function notFoundPage() {
  const body = `
<section class="section striped on-dark">
  <div class="inner" style="max-width:640px;text-align:center">
    <h1>Badge not found</h1>
    <p>We couldn't verify a badge at this link. It may have been mistyped, or the badge hasn't been issued yet.</p>
    <p style="margin-top:18px"><a class="btn inverse" href="/request">Request a badge</a></p>
  </div>
</section>`;
  return layout({ title: "Badge not found — Scottish Summit", current: "badges", body, multiSection: true });
}

/** The public request-a-badge form (honeypot + Turnstile). */
export function requestPage(events, turnstileSiteKey) {
  const opts = events
    .map((e) => `<option value="${esc(e.eventId)}">${esc(e.eventName)} ${esc(e.year)}</option>`)
    .join("");
  const turnstile = turnstileSiteKey
    ? `<div class="cf-turnstile" data-sitekey="${esc(turnstileSiteKey)}" data-callback="tsOk"></div>
       <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>`
    : `<p class="hint" style="font-size:13px;color:#777">Spam protection is not configured on this environment.</p>`;

  const body = `
<section class="section striped on-dark">
  <div class="inner" style="max-width:640px">
    <h1>Request your ${wordmark("Scottish", "Summit")} badge</h1>
    <p>Missed on the roster, or got the wrong email? Ask the organisers to verify your badge.</p>
  </div>
</section>
<div class="wrap" style="max-width:640px">
  <div class="card">
    <form id="reqform" novalidate>
      <label for="name">Full name</label>
      <input id="name" name="name" required autocomplete="name">

      <label for="email">Email</label>
      <input id="email" name="email" type="email" required autocomplete="email">

      <label for="eventId">Event</label>
      <select id="eventId" name="eventId" required>${opts || '<option value="">No events yet</option>'}</select>

      <label for="role">Role</label>
      <select id="role" name="role" required>
        <option value="speaker">Speaker</option>
        <option value="volunteer">Volunteer</option>
        <option value="organiser">Organiser</option>
      </select>

      <label for="message">Anything to add? (optional)</label>
      <textarea id="message" name="message" rows="3"></textarea>

      <div class="hp" aria-hidden="true">
        <label for="website">Leave this field empty</label>
        <input id="website" name="website" tabindex="-1" autocomplete="off">
      </div>

      <div style="margin:16px 0">${turnstile}</div>

      <button class="btn" type="submit">Send request</button>
      <p id="status" class="status" role="status" aria-live="polite"></p>
    </form>
  </div>
</div>
<script>
window.tsOk = function(){};
document.getElementById('reqform').addEventListener('submit', async function(e){
  e.preventDefault();
  var s = document.getElementById('status'); s.className='status'; s.textContent='Sending…';
  var f = e.target;
  var body = {
    name: f.name.value, email: f.email.value, eventId: f.eventId.value,
    role: f.role.value, message: f.message.value, website: f.website.value
  };
  var tok = f.querySelector('[name=cf-turnstile-response]');
  if (tok) body['cf-turnstile-response'] = tok.value;
  try {
    var r = await fetch('/api/request', {method:'POST', headers:{'content-type':'application/json'}, body: JSON.stringify(body)});
    var j = await r.json().catch(function(){return {};});
    if (r.ok) { s.className='status ok'; s.textContent='Thanks — your request is pending organiser approval.'; f.reset(); if(window.turnstile) window.turnstile.reset(); }
    else { s.className='status err'; s.textContent = j.error || 'Sorry, that could not be submitted.'; }
  } catch(err) { s.className='status err'; s.textContent='Network error — please try again.'; }
});
</script>`;

  return layout({
    title: "Request a badge — Scottish Summit",
    description: "Request a verified Scottish Summit event badge.",
    current: "badges",
    body,
    multiSection: true,
  });
}
