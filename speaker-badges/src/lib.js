// Small, dependency-free helpers shared across the Worker.

/** Escape a string for safe interpolation into HTML text/attributes. */
export function esc(value) {
  return String(value ?? "")
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#39;");
}

/** URL/identifier slug: lowercase, alphanumerics and single dashes. */
export function slug(value) {
  return String(value ?? "")
    .trim()
    .toLowerCase()
    .normalize("NFKD")
    .replace(/[^a-z0-9]+/g, "-")
    .replace(/^-+|-+$/g, "")
    .replace(/-{2,}/g, "-");
}

/** Normalised person key for idempotency + milestone grouping (email is the identity). */
export function personKey(email) {
  return String(email ?? "").trim().toLowerCase();
}

/** Unguessable token: a UUID with the dashes removed. */
export function newToken() {
  return crypto.randomUUID().replace(/-/g, "");
}

/** Today's date as YYYY-MM-DD (UTC). */
export function todayISO() {
  return new Date().toISOString().slice(0, 10);
}

export function json(data, status = 200, headers = {}) {
  return new Response(JSON.stringify(data), {
    status,
    headers: { "content-type": "application/json; charset=utf-8", ...headers },
  });
}

export function html(body, status = 200, headers = {}) {
  return new Response(body, {
    status,
    headers: { "content-type": "text/html; charset=utf-8", ...headers },
  });
}

export function badRequest(message) {
  return json({ error: message }, 400);
}

export function forbidden(message = "Forbidden") {
  return json({ error: message }, 403);
}

/**
 * Minimal, correct CSV parser (handles quoted fields, escaped quotes, CRLF).
 * Returns an array of row objects keyed by the (lowercased, trimmed) header row.
 */
export function parseCsv(text) {
  const rows = [];
  let field = "";
  let row = [];
  let inQuotes = false;
  const src = String(text ?? "").replace(/^﻿/, ""); // strip BOM

  for (let i = 0; i < src.length; i++) {
    const ch = src[i];
    if (inQuotes) {
      if (ch === '"') {
        if (src[i + 1] === '"') { field += '"'; i++; }
        else inQuotes = false;
      } else field += ch;
    } else if (ch === '"') {
      inQuotes = true;
    } else if (ch === ",") {
      row.push(field); field = "";
    } else if (ch === "\n") {
      row.push(field); field = "";
      rows.push(row); row = [];
    } else if (ch === "\r") {
      // ignore; handled by the \n branch
    } else {
      field += ch;
    }
  }
  // flush trailing field/row
  if (field.length > 0 || row.length > 0) { row.push(field); rows.push(row); }

  const nonEmpty = rows.filter((r) => r.some((c) => c.trim() !== ""));
  if (nonEmpty.length === 0) return [];

  const header = nonEmpty[0].map((h) => h.trim().toLowerCase());
  return nonEmpty.slice(1).map((cells) => {
    const obj = {};
    header.forEach((key, idx) => { obj[key] = (cells[idx] ?? "").trim(); });
    return obj;
  });
}

/** Serialise an array of flat objects to CSV using the given column order. */
export function toCsv(columns, records) {
  const escapeCell = (v) => {
    const s = String(v ?? "");
    return /[",\n\r]/.test(s) ? `"${s.replace(/"/g, '""')}"` : s;
  };
  const lines = [columns.join(",")];
  for (const rec of records) {
    lines.push(columns.map((c) => escapeCell(rec[c])).join(","));
  }
  return lines.join("\r\n");
}
