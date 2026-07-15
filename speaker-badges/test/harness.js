// In-memory KV + R2 mocks and a fetch helper for the Worker, so the suite runs
// under plain vitest (node) without the Workers pool. The same code runs on the
// real runtime unchanged; swap in @cloudflare/vitest-pool-workers for integration.

export class MockKV {
  constructor() { this.store = new Map(); }
  async get(key) { return this.store.has(key) ? this.store.get(key) : null; }
  async put(key, value) { this.store.set(key, typeof value === "string" ? value : String(value)); }
  async delete(key) { this.store.delete(key); }
  async list({ prefix = "", cursor } = {}) {
    const keys = [...this.store.keys()].filter((k) => k.startsWith(prefix)).map((name) => ({ name }));
    return { keys, list_complete: true, cursor: undefined };
  }
}

export class MockR2 {
  constructor() { this.store = new Map(); }
  async put(key, bytes, opts = {}) {
    const buf = bytes instanceof Uint8Array ? bytes : new Uint8Array(bytes);
    this.store.set(key, { bytes: buf, httpMetadata: opts.httpMetadata || {}, httpEtag: `"etag-${key.length}"` });
  }
  async get(key) {
    const o = this.store.get(key);
    if (!o) return null;
    return { body: o.bytes, httpMetadata: o.httpMetadata, httpEtag: o.httpEtag };
  }
}

export function makeEnv(overrides = {}) {
  return {
    BADGES: new MockKV(),
    IMAGES: new MockR2(),
    PUBLIC_ORIGIN: "https://badges.scottishsummit.com",
    TURNSTILE_SITE_KEY: "",
    ALLOW_INSECURE_ADMIN: "true", // dev bypass for admin routes in most tests
    ...overrides,
  };
}

/** A ctx whose waitUntil awaits inline so tests can observe fire-and-forget work. */
export function makeCtx() {
  const pending = [];
  return {
    waitUntil: (p) => pending.push(p),
    async drain() { await Promise.all(pending); },
  };
}

/** Build a Request with a JSON body. */
export function jsonRequest(url, body, method = "POST", headers = {}) {
  return new Request(url, {
    method,
    headers: { "content-type": "application/json", ...headers },
    body: JSON.stringify(body),
  });
}
