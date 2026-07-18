/**
 * Self-hosted websocket chat relay (T41) — deploy-time swap for the
 * polling transport (B4 decision). One room per match/meeting.
 *
 * Auth: clients connect with ?token=<JWT access token issued by the
 * plugin>; the relay validates it against WordPress by calling
 * /wp-json/prx3/v1/me. Messages are persisted through the same plugin API
 * (single source of truth: moderation, word filter, retention all apply),
 * so this relay only fans out realtime events.
 *
 * Run: WP_BASE=https://club.example node server.js
 */
'use strict';

const http = require('http');
const crypto = require('crypto');

const WP_BASE = process.env.WP_BASE || 'http://localhost:8080';
const PORT = Number(process.env.PORT || 8787);

/** room -> Set of sockets */
const rooms = new Map();

async function verifyMember(token) {
  const response = await fetch(`${WP_BASE}/wp-json/prx3/v1/me`, {
    headers: { Authorization: `Bearer ${token}` },
  });
  if (!response.ok) return null;
  return response.json();
}

async function persistMessage(token, room, body) {
  const response = await fetch(`${WP_BASE}/wp-json/prx3/v1/chat/${room}`, {
    method: 'POST',
    headers: { Authorization: `Bearer ${token}`, 'Content-Type': 'application/json' },
    body: JSON.stringify({ body }),
  });
  return response.ok ? response.json() : null;
}

// Minimal RFC6455 websocket implementation (no dependencies).
const server = http.createServer((req, res) => {
  res.writeHead(200, { 'Content-Type': 'application/json' });
  res.end(JSON.stringify({ ok: true, rooms: rooms.size }));
});

server.on('upgrade', async (req, socket) => {
  const url = new URL(req.url, 'http://x');
  const room = (url.pathname.replace(/^\//, '') || '').replace(/[^a-z0-9\-]/g, '');
  const token = url.searchParams.get('token') || '';
  const member = room && token ? await verifyMember(token).catch(() => null) : null;
  if (!member || !member.is_owner) {
    socket.write('HTTP/1.1 401 Unauthorized\r\n\r\n');
    socket.destroy();
    return;
  }
  const key = req.headers['sec-websocket-key'];
  const accept = crypto
    .createHash('sha1')
    .update(key + '258EAFA5-E914-47DA-95CA-C5AB0DC85B11')
    .digest('base64');
  socket.write(
    'HTTP/1.1 101 Switching Protocols\r\nUpgrade: websocket\r\nConnection: Upgrade\r\n' +
      `Sec-WebSocket-Accept: ${accept}\r\n\r\n`
  );

  if (!rooms.has(room)) rooms.set(room, new Set());
  rooms.get(room).add(socket);
  socket.on('close', () => rooms.get(room)?.delete(socket));
  socket.on('error', () => rooms.get(room)?.delete(socket));

  socket.on('data', async (buffer) => {
    const text = decodeFrame(buffer);
    if (text === null) return;
    // Persist through the plugin (moderation/word-filter/mute all apply);
    // fan out only what the plugin accepted and did not hold.
    const saved = await persistMessage(token, room, text).catch(() => null);
    if (!saved || saved.held) return;
    const frame = encodeFrame(
      JSON.stringify({ author: member.name, body: text, id: saved.id })
    );
    for (const client of rooms.get(room) || []) {
      if (!client.destroyed) client.write(frame);
    }
  });
});

function decodeFrame(buffer) {
  if (buffer.length < 2) return null;
  const opcode = buffer[0] & 0x0f;
  if (opcode !== 1) return null; // Text frames only.
  let length = buffer[1] & 0x7f;
  let offset = 2;
  if (length === 126) {
    length = buffer.readUInt16BE(2);
    offset = 4;
  } else if (length === 127) {
    return null; // Oversized frames rejected (500-char limit anyway).
  }
  const mask = buffer.subarray(offset, offset + 4);
  const payload = buffer.subarray(offset + 4, offset + 4 + length);
  const out = Buffer.alloc(payload.length);
  for (let i = 0; i < payload.length; i++) out[i] = payload[i] ^ mask[i % 4];
  return out.toString('utf8').slice(0, 500);
}

function encodeFrame(text) {
  const payload = Buffer.from(text, 'utf8');
  const header =
    payload.length < 126
      ? Buffer.from([0x81, payload.length])
      : Buffer.concat([Buffer.from([0x81, 126]), (() => { const b = Buffer.alloc(2); b.writeUInt16BE(payload.length); return b; })()]);
  return Buffer.concat([header, payload]);
}

server.listen(PORT, () => {
  console.log(`Chat relay listening on :${PORT}, WordPress at ${WP_BASE}`);
});
