#!/usr/bin/env node
/**
 * Monolith tunnel client — download from /tunnel-client.mjs, no npm install.
 * Requires Node.js 20+ (fetch). Node 21+ for built-in WebSocket.
 */
function parseArgs(argv) {
  const out = { token: '', port: 8000, hub: 'http://localhost:8787' };
  for (let i = 0; i < argv.length; i++) {
    const a = argv[i];
    if (a === '--token') out.token = argv[++i] || '';
    else if (a === '--port') out.port = Number(argv[++i] || 8000);
    else if (a === '--hub') out.hub = argv[++i] || out.hub;
  }
  return out;
}

if (typeof WebSocket === 'undefined') {
  console.error('Node.js 21+ required (built-in WebSocket). See https://nodejs.org');
  process.exit(1);
}

const { token, port, hub } = parseArgs(process.argv.slice(2));
if (!token) {
  console.error('Usage: node tunnel-client.mjs --token <token> [--port 8000] [--hub http://localhost:8787]');
  process.exit(1);
}

const hubBase = hub.replace(/\/$/, '');
const wsUrl = hubBase.replace(/^http/, 'ws') + '/connect?token=' + encodeURIComponent(token);

function connect() {
  const ws = new WebSocket(wsUrl);

  ws.onopen = () => {
    console.log(`Connected — forwarding public traffic to http://127.0.0.1:${port}`);
  };

  ws.onmessage = async (event) => {
    let msg;
    try {
      msg = JSON.parse(String(event.data));
    } catch {
      return;
    }
    if (msg.type !== 'request' || !msg.id) return;

    const path = msg.path || '/';
    const query = msg.query ? (msg.query.startsWith('?') ? msg.query : '?' + msg.query) : '';
    const target = `http://127.0.0.1:${port}${path}${query}`;

    const headers = { ...(msg.headers || {}) };
    delete headers.host;
    delete headers.connection;

    let body;
    if (msg.body) {
      body = Buffer.from(msg.body, 'base64');
    }

    try {
      const res = await fetch(target, {
        method: msg.method || 'GET',
        headers,
        body: body && !['GET', 'HEAD'].includes((msg.method || 'GET').toUpperCase()) ? body : undefined,
        redirect: 'manual',
      });
      const resBody = Buffer.from(await res.arrayBuffer());
      const resHeaders = {};
      res.headers.forEach((v, k) => {
        if (k.toLowerCase() === 'transfer-encoding') return;
        resHeaders[k] = v;
      });
      ws.send(JSON.stringify({
        type: 'response',
        id: msg.id,
        status: res.status,
        headers: resHeaders,
        body: resBody.toString('base64'),
      }));
    } catch (e) {
      ws.send(JSON.stringify({
        type: 'response',
        id: msg.id,
        status: 502,
        headers: { 'Content-Type': 'text/plain' },
        body: Buffer.from(String(e.message)).toString('base64'),
      }));
    }
  };

  ws.onclose = (event) => {
    console.log(`Disconnected (${event.code}) — reconnecting in 3s…`);
    setTimeout(connect, 3000);
  };

  ws.onerror = () => {
    console.error('WebSocket error');
  };
}

connect();
