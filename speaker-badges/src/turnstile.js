// Task F: server-side Cloudflare Turnstile verification for the request form.

/**
 * Verify a Turnstile token. Returns true when valid.
 * If TURNSTILE_SECRET is unset the check is treated as disabled (returns true) so
 * local/dev environments without a widget still work — production must set it.
 */
export async function verifyTurnstile(env, token, remoteIp) {
  if (!env.TURNSTILE_SECRET) return true; // not configured → disabled
  if (!token) return false;
  try {
    const form = new FormData();
    form.append("secret", env.TURNSTILE_SECRET);
    form.append("response", token);
    if (remoteIp) form.append("remoteip", remoteIp);
    const res = await fetch("https://challenges.cloudflare.com/turnstile/v0/siteverify", {
      method: "POST",
      body: form,
    });
    const data = await res.json();
    return data.success === true;
  } catch {
    return false;
  }
}

/** True when a widget is configured (so the caller can require a token). */
export function turnstileEnabled(env) {
  return !!env.TURNSTILE_SECRET;
}
