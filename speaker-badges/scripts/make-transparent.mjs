// Make the logo's OUTER white background transparent without touching the white
// interior elements (sash, figures, "SCOTTISH" text). Decodes the palette PNG,
// flood-fills near-white pixels connected to the four corners → alpha 0, and
// re-encodes as RGBA. Pure Node (zlib only).
//
// Usage: node scripts/make-transparent.mjs [in.png] [out.png]
import { readFileSync, writeFileSync } from "node:fs";
import zlib from "node:zlib";

const inPath = process.argv[2] || "assets/logo.png";
const outPath = process.argv[3] || "assets/logo.png";
const buf = readFileSync(inPath);

// --- parse chunks ---
let i = 8;
let ihdr, plte, idat = [];
while (i < buf.length) {
  const len = buf.readUInt32BE(i);
  const type = buf.toString("ascii", i + 4, i + 8);
  const data = buf.subarray(i + 8, i + 8 + len);
  if (type === "IHDR") ihdr = data;
  else if (type === "PLTE") plte = data;
  else if (type === "IDAT") idat.push(data);
  else if (type === "IEND") break;
  i += 12 + len;
}
const width = ihdr.readUInt32BE(0);
const height = ihdr.readUInt32BE(4);
const colorType = ihdr[9];
if (colorType !== 3) throw new Error("expected a palette PNG (color type 3)");

// --- inflate + unfilter (1 byte/pixel palette indices) ---
const raw = zlib.inflateSync(Buffer.concat(idat));
const stride = width; // bpp = 1
const idx = new Uint8Array(width * height);
let prev = new Uint8Array(stride);
let pos = 0;
for (let y = 0; y < height; y++) {
  const filter = raw[pos++];
  const line = new Uint8Array(stride);
  for (let x = 0; x < stride; x++) {
    const rawByte = raw[pos++];
    const a = x >= 1 ? line[x - 1] : 0;
    const b = prev[x];
    const c = x >= 1 ? prev[x - 1] : 0;
    let val;
    switch (filter) {
      case 0: val = rawByte; break;
      case 1: val = rawByte + a; break;
      case 2: val = rawByte + b; break;
      case 3: val = rawByte + ((a + b) >> 1); break;
      case 4: {
        const p = a + b - c, pa = Math.abs(p - a), pb = Math.abs(p - b), pc = Math.abs(p - c);
        val = rawByte + (pa <= pb && pa <= pc ? a : pb <= pc ? b : c); break;
      }
      default: throw new Error("bad filter " + filter);
    }
    line[x] = val & 0xff;
  }
  idx.set(line, y * width);
  prev = line;
}

// --- palette → RGB, build RGBA with full alpha ---
const rgba = new Uint8Array(width * height * 4);
const isWhite = (pi) => plte[pi * 3] >= 245 && plte[pi * 3 + 1] >= 245 && plte[pi * 3 + 2] >= 245;
for (let p = 0; p < width * height; p++) {
  const pi = idx[p];
  rgba[p * 4] = plte[pi * 3];
  rgba[p * 4 + 1] = plte[pi * 3 + 1];
  rgba[p * 4 + 2] = plte[pi * 3 + 2];
  rgba[p * 4 + 3] = 255;
}

// --- flood fill white from the four corners → alpha 0 ---
const seen = new Uint8Array(width * height);
const stack = [];
const seed = (x, y) => { if (x >= 0 && x < width && y >= 0 && y < height) stack.push(y * width + x); };
seed(0, 0); seed(width - 1, 0); seed(0, height - 1); seed(width - 1, height - 1);
let cleared = 0;
while (stack.length) {
  const p = stack.pop();
  if (seen[p]) continue;
  seen[p] = 1;
  if (!isWhite(idx[p])) continue;
  rgba[p * 4 + 3] = 0; cleared++;
  const x = p % width, y = (p / width) | 0;
  if (x > 0) stack.push(p - 1);
  if (x < width - 1) stack.push(p + 1);
  if (y > 0) stack.push(p - width);
  if (y < height - 1) stack.push(p + width);
}

// --- encode RGBA PNG (color type 6, filter 0) ---
const rawOut = Buffer.alloc(height * (1 + width * 4));
for (let y = 0; y < height; y++) {
  rawOut[y * (1 + width * 4)] = 0;
  rgba.subarray(y * width * 4, (y + 1) * width * 4)
    .forEach((v, k) => { rawOut[y * (1 + width * 4) + 1 + k] = v; });
}
const compressed = zlib.deflateSync(rawOut, { level: 9 });

const crcTable = (() => {
  const t = new Uint32Array(256);
  for (let n = 0; n < 256; n++) { let c = n; for (let k = 0; k < 8; k++) c = c & 1 ? 0xedb88320 ^ (c >>> 1) : c >>> 1; t[n] = c >>> 0; }
  return t;
})();
const crc32 = (b) => { let c = 0xffffffff; for (let n = 0; n < b.length; n++) c = crcTable[(c ^ b[n]) & 0xff] ^ (c >>> 8); return (c ^ 0xffffffff) >>> 0; };
const chunk = (type, data) => {
  const len = Buffer.alloc(4); len.writeUInt32BE(data.length);
  const td = Buffer.concat([Buffer.from(type, "ascii"), data]);
  const crc = Buffer.alloc(4); crc.writeUInt32BE(crc32(td));
  return Buffer.concat([len, td, crc]);
};
const newIhdr = Buffer.alloc(13);
newIhdr.writeUInt32BE(width, 0); newIhdr.writeUInt32BE(height, 4);
newIhdr[8] = 8; newIhdr[9] = 6; // 8-bit, RGBA
const out = Buffer.concat([
  Buffer.from([0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a]),
  chunk("IHDR", newIhdr), chunk("IDAT", compressed), chunk("IEND", Buffer.alloc(0)),
]);
writeFileSync(outPath, out);
console.log(`${width}x${height}: cleared ${cleared} bg px → ${outPath} (${out.length}b)`);
