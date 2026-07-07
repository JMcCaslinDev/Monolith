#!/usr/bin/env node
/**
 * Tunnel hub — public HTTP edge + WebSocket relay for Monolith tunnels.
 * ponytail: single-process in-memory registry; upgrade path = Redis + dedicated ECS service on AWS.
 */
import { createServer } from 'node:http';
import { randomUUID } from 'node:crypto';
import { WebSocketServer } from 'ws';
import { loadEnv } from './load-env.mjs';

const env = loadEnv();
const PORT = Number(env.TUNNEL_HUB_PORT || 8787);
const APP_URL = (env.APP_URL || 'http://localhost:8000').replace(/\/$/, '');
const SECRET = env.TUNNEL_HUB_SECRET || 'local-tunnel-dev-secret';
const REQUEST_TIMEOUT_MS = 30_000;

/** @type {Map<number, { ws: import('ws').WebSocket, slug: string, localPort: number }>} */
const clients = new Map();

/** @type {Map<string, { resolve: (v: object) => void, reject: (e: Error) => void, timer: NodeJS.Timeout }>} */
const pending = new Map();

async function hubApi(path, body) {
  const res = await fetch(`${APP_URL}${path}`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-Tunnel-Hub-Secret': SECRET,
    },
    body: JSON.stringify(body),
  });
  const data = await res.json().catch(() => ({}));
  if (!res.ok) {
    throw new Error(data.error || `Hub API ${path} failed (${res.status})`);
  }
  return data;
}

async function logRequest(payload) {
  try {
    await hubApi('/tunnel-hub/log-request', payload);
  } catch (e) {
    console.error('log-request failed:', e.message);
  }
}

function readBody(req) {
  return new Promise((resolve, reject) => {
    const chunks = [];
    req.on('data', (c) => chunks.push(c));
    req.on('end', () => resolve(Buffer.concat(chunks)));
    req.on('error', reject);
  });
}

function collectHeaders(req) {
  /** @type {Record<string, string>} */
  const headers = {};
  for (const [k, v] of Object.entries(req.headers)) {
    if (v === undefined) continue;
    headers[k] = Array.isArray(v) ? v.join(', ') : v;
  }
  return headers;
}

function forwardToClient(tunnelId, message) {
  return new Promise((resolve, reject) => {
    const client = clients.get(tunnelId);
    if (!client || client.ws.readyState !== 1) {
      reject(new Error('Tunnel client not connected'));
      return;
    }
    const id = randomUUID();
    const timer = setTimeout(() => {
      pending.delete(id);
      reject(new Error('Upstream timeout'));
    }, REQUEST_TIMEOUT_MS);
    pending.set(id, { resolve, reject, timer });
    client.ws.send(JSON.stringify({ ...message, id }));
  });
}

async function handleTunnelHttp(req, res, slug, forwardPath, queryString = '') {
  const started = Date.now();
  const clientIp = req.socket.remoteAddress || '';
  let tunnel;
  try {
    tunnel = await hubApi('/tunnel-hub/lookup-slug', { slug });
  } catch {
    res.writeHead(404, { 'Content-Type': 'text/plain' });
    res.end('Tunnel not found');
    return;
  }

  const body = await readBody(req);
  const query = queryString;
  const reqHeaders = collectHeaders(req);
  delete reqHeaders.host;

  const basePayload = {
    tunnel_id: tunnel.id,
    request_method: req.method || 'GET',
    request_path: forwardPath || '/',
    query_string: query,
    request_headers: reqHeaders,
    request_body: body.toString('utf8'),
    client_ip: clientIp,
  };

  try {
    const response = await forwardToClient(tunnel.id, {
      type: 'request',
      method: req.method || 'GET',
      path: forwardPath || '/',
      query,
      headers: reqHeaders,
      body: body.toString('base64'),
    });

    const resBody = Buffer.from(response.body || '', 'base64');
    const resHeaders = response.headers || {};
    const status = response.status || 502;

    await logRequest({
      ...basePayload,
      response_status: status,
      response_headers: resHeaders,
      response_body: resBody.toString('utf8'),
      duration_ms: Date.now() - started,
      forwarded: true,
    });

    res.writeHead(status, resHeaders);
    res.end(resBody);
  } catch (e) {
    await logRequest({
      ...basePayload,
      response_status: 502,
      response_headers: { 'Content-Type': 'text/plain' },
      response_body: '',
      duration_ms: Date.now() - started,
      forwarded: false,
      error_message: e.message,
    });
    res.writeHead(502, { 'Content-Type': 'text/plain' });
    res.end('Tunnel unavailable: ' + e.message);
  }
}

const server = createServer(async (req, res) => {
  const url = new URL(req.url || '/', `http://127.0.0.1:${PORT}`);
  if (url.pathname === '/health') {
    res.writeHead(200, { 'Content-Type': 'application/json' });
    res.end(JSON.stringify({ status: 'ok', clients: clients.size }));
    return;
  }

  const match = url.pathname.match(/^\/t\/([a-z0-9]{8,16})(\/.*)?$/);
  if (!match) {
    res.writeHead(404, { 'Content-Type': 'text/plain' });
    res.end('Not found');
    return;
  }

  const slug = match[1];
  const forwardPath = match[2] || '/';
  await handleTunnelHttp(req, res, slug, forwardPath, url.search.slice(1));
});

const wss = new WebSocketServer({ noServer: true });

server.on('upgrade', async (req, socket, head) => {
  const url = new URL(req.url || '/', `http://127.0.0.1:${PORT}`);
  if (url.pathname !== '/connect') {
    socket.destroy();
    return;
  }
  const token = url.searchParams.get('token') || '';
  let tunnel;
  try {
    tunnel = await hubApi('/tunnel-hub/lookup-token', { token });
  } catch {
    socket.write('HTTP/1.1 403 Forbidden\r\n\r\n');
    socket.destroy();
    return;
  }

  wss.handleUpgrade(req, socket, head, (ws) => {
    const existing = clients.get(tunnel.id);
    if (existing) {
      existing.ws.close(4000, 'replaced');
      clients.delete(tunnel.id);
    }

    clients.set(tunnel.id, { ws, slug: tunnel.slug, localPort: tunnel.local_port });
    console.log(`tunnel connected: ${tunnel.slug} → 127.0.0.1:${tunnel.local_port}`);

    hubApi('/tunnel-hub/connected', { tunnel_id: tunnel.id, slug: tunnel.slug }).catch(() => {});

    ws.on('message', (raw) => {
      let msg;
      try {
        msg = JSON.parse(String(raw));
      } catch {
        return;
      }
      if (msg.type === 'response' && msg.id && pending.has(msg.id)) {
        const p = pending.get(msg.id);
        pending.delete(msg.id);
        clearTimeout(p.timer);
        p.resolve(msg);
      }
    });

    ws.on('close', () => {
      if (clients.get(tunnel.id)?.ws === ws) {
        clients.delete(tunnel.id);
        hubApi('/tunnel-hub/disconnected', { tunnel_id: tunnel.id }).catch(() => {});
        console.log(`tunnel disconnected: ${tunnel.slug}`);
      }
    });
  });
});

server.listen(PORT, () => {
  console.log(`Tunnel hub listening on http://127.0.0.1:${PORT}`);
  console.log(`App API: ${APP_URL}`);
});
