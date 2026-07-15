// R2 image storage: upload (admin) and serve (public). Badge artwork lives at
// art/<eventId>/<role>.<ext>; issuer logos at logo/<eventId>.<ext>.

import { json, badRequest } from "./lib.js";
import { getEvent, putEvent, isRole } from "./badges.js";

const MAX_BYTES = 2 * 1024 * 1024; // 2 MB
const EXT_BY_TYPE = {
  "image/png": "png",
  "image/jpeg": "jpg",
  "image/jpg": "jpg",
  "image/svg+xml": "svg",
  "image/webp": "webp",
};

/**
 * POST /api/upload — accepts multipart/form-data (field `file`) or a raw body with
 * a Content-Type header. Query/form params: kind=art|logo, eventId, role (for art).
 * Validates type + size, writes to R2, denormalises the key onto the event record.
 */
export async function handleUpload(request, env, url) {
  const q = url.searchParams;
  let bytes, contentType, kind, eventId, role;

  const ct = request.headers.get("content-type") || "";
  if (ct.includes("multipart/form-data")) {
    const form = await request.formData();
    const file = form.get("file");
    if (!file || typeof file === "string") return badRequest("No file provided");
    bytes = new Uint8Array(await file.arrayBuffer());
    contentType = file.type || "application/octet-stream";
    kind = form.get("kind") || q.get("kind");
    eventId = form.get("eventId") || q.get("eventId");
    role = form.get("role") || q.get("role");
  } else {
    bytes = new Uint8Array(await request.arrayBuffer());
    contentType = ct.split(";")[0].trim();
    kind = q.get("kind");
    eventId = q.get("eventId");
    role = q.get("role");
  }

  const ext = EXT_BY_TYPE[contentType.toLowerCase()];
  if (!ext) return badRequest("Unsupported image type (png, jpeg, svg or webp only)");
  if (!bytes || bytes.byteLength === 0) return badRequest("Empty upload");
  if (bytes.byteLength > MAX_BYTES) return badRequest("Image exceeds 2 MB limit");
  if (!eventId) return badRequest("eventId is required");

  let key;
  if (kind === "art") {
    if (!isRole(role)) return badRequest("Valid role required for artwork");
    key = `art/${eventId}/${role}.${ext}`;
  } else if (kind === "logo") {
    key = `logo/${eventId}.${ext}`;
  } else {
    return badRequest("kind must be 'art' or 'logo'");
  }

  await env.IMAGES.put(key, bytes, { httpMetadata: { contentType } });

  // Denormalise onto the event so mint time can copy artKey to each award.
  const event = await getEvent(env, eventId);
  if (event) {
    if (kind === "art") {
      event.artKeys = { ...(event.artKeys || {}), [role]: key };
    } else {
      event.logoKey = key;
    }
    await putEvent(env, event);
  }

  return json({ key });
}

/** GET /img/<key> — stream an R2 object with a long cache lifetime. */
export async function serveImage(env, key) {
  const object = await env.IMAGES.get(key);
  if (!object) return new Response("Not found", { status: 404 });
  const headers = new Headers();
  const ct = object.httpMetadata?.contentType || guessType(key);
  headers.set("content-type", ct);
  headers.set("cache-control", "public, max-age=31536000, immutable");
  if (object.httpEtag) headers.set("etag", object.httpEtag);
  return new Response(object.body, { headers });
}

function guessType(key) {
  const ext = key.split(".").pop().toLowerCase();
  return (
    { png: "image/png", jpg: "image/jpeg", jpeg: "image/jpeg", svg: "image/svg+xml", webp: "image/webp" }[ext] ||
    "application/octet-stream"
  );
}
