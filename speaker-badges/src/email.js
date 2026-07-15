// Transactional email (Task E). Provider-agnostic tiny helper defaulting to the
// Resend API shape. All sends are fire-and-forget via ctx.waitUntil so issuing
// never blocks on the provider; if EMAIL_API_KEY is unset it is a silent no-op.

import { esc } from "./lib.js";
import { awardLinks, roleLabel, getAward, putAward } from "./badges.js";

/** Low-level send. Returns true on success, false on no-op/failure (never throws). */
export async function sendEmail(env, to, subject, html) {
  if (!env.EMAIL_API_KEY) {
    console.log(`[email] no EMAIL_API_KEY set — skipping send to ${to}: ${subject}`);
    return false;
  }
  const from = env.EMAIL_FROM || "badges@scottishsummit.com";
  try {
    const res = await fetch("https://api.resend.com/emails", {
      method: "POST",
      headers: {
        authorization: `Bearer ${env.EMAIL_API_KEY}`,
        "content-type": "application/json",
      },
      body: JSON.stringify({ from, to, subject, html }),
    });
    if (!res.ok) {
      console.log(`[email] provider ${res.status} sending to ${to}`);
      return false;
    }
    return true;
  } catch (err) {
    console.log(`[email] send failed to ${to}: ${err}`);
    return false;
  }
}

function shell(inner) {
  return `<div style="font-family:'Segoe UI',Arial,sans-serif;max-width:560px;margin:0 auto;border:1px solid #e6e2f0;border-radius:12px;overflow:hidden">
    <div style="background:#29235c;color:#fff;padding:20px 24px;font-weight:800;font-size:18px">
      Scottish <span style="color:#EC1878">Summit</span>
    </div>
    <div style="padding:24px;color:#241f4a;line-height:1.55">${inner}</div>
    <div style="background:#1d1942;color:#e9e6f5;font-size:12px;padding:16px 24px">
      Scottish Summit is run by Scottish Summit SCIO, Charity Number SC052785.
    </div>
  </div>`;
}

export function badgeEmailHtml(origin, award) {
  const { badgeUrl, linkedInUrl } = awardLinks(origin, award);
  const role = roleLabel(award.role);
  return shell(`
    <h1 style="font-size:20px;margin:0 0 12px">You've earned a ${esc(role)} badge!</h1>
    <p>Hi ${esc(award.name)},</p>
    <p>Your verified <strong>${esc(role)}</strong> badge for <strong>${esc(award.eventName)} ${esc(award.year)}</strong> is ready.</p>
    <p style="margin:22px 0">
      <a href="${esc(badgeUrl)}" style="background:#C4157C;color:#fff;text-decoration:none;font-weight:800;padding:12px 20px;border-radius:12px;display:inline-block">View your badge</a>
      &nbsp;
      <a href="${esc(linkedInUrl)}" style="background:#fff;color:#C4157C;border:2px solid #C4157C;text-decoration:none;font-weight:800;padding:10px 18px;border-radius:12px;display:inline-block">Add to LinkedIn</a>
    </p>
    <p style="font-size:13px;color:#666">Badge link: <a href="${esc(badgeUrl)}">${esc(badgeUrl)}</a></p>
  `);
}

export function requestAckHtml(req) {
  return shell(`
    <h1 style="font-size:20px;margin:0 0 12px">Request received</h1>
    <p>Hi ${esc(req.name)},</p>
    <p>Thanks — we've received your badge request for <strong>${esc(req.eventName)}</strong> as a ${esc(req.role)}. An organiser will review it shortly; you'll get your badge by email once it's approved.</p>
  `);
}

/** Send a badge email and record emailedAt on the award (fire-and-forget). */
export function dispatchBadgeEmail(env, origin, award, ctx) {
  const task = (async () => {
    if (!award.email) return;
    const ok = await sendEmail(env, award.email, `Your ${roleLabel(award.role)} badge — ${award.eventName} ${award.year}`, badgeEmailHtml(origin, award));
    if (ok) {
      const fresh = await getAward(env, award.token);
      if (fresh) { fresh.emailedAt = new Date().toISOString(); await putAward(env, fresh); }
    }
  })();
  if (ctx?.waitUntil) ctx.waitUntil(task);
  return task;
}
