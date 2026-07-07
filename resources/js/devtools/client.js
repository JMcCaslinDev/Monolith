/** Client-side devtools — instant, no server round-trip */

import QRCode from 'qrcode';

const UUID_BATCH = 5;

function b64Encode(s) {
  return btoa(unescape(encodeURIComponent(s)));
}

function b64Decode(s) {
  try {
    return decodeURIComponent(escape(atob(s)));
  } catch {
    throw new Error('Invalid Base64');
  }
}

function b64UrlDecode(s) {
  const pad = (4 - (s.length % 4)) % 4;
  return atob(s.replace(/-/g, '+').replace(/_/g, '/') + '='.repeat(pad));
}

export function processLocal(slug, action, input, extra = {}, inputB = '') {
  switch (slug) {
    case 'cron-parser':
      return { output: describeCron(input) };
    case 'date':
      return processDate(action, input, extra);
    case 'json-table':
      return processJsonTable(action, input);
    case 'json-yaml':
      return processJsonYaml(action, input);
    case 'number-base':
      return processNumberBase(action, input, extra);
    case 'base64-text':
      return action === 'encode' ? { output: b64Encode(input) } : { output: b64Decode(input) };
    case 'html':
      return action === 'encode'
        ? { output: input.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;') }
        : { output: new DOMParser().parseFromString(input, 'text/html').documentElement.textContent ?? '' };
    case 'url':
      return action === 'encode' ? { output: encodeURIComponent(input) } : { output: decodeURIComponent(input) };
    case 'jwt':
      return decodeJwt(input);
    case 'json':
      return processJson(action, input);
    case 'sql':
      return processSql(action, input);
    case 'xml':
      return processXml(action, input);
    case 'hash':
      return null; // async — handled separately
    case 'lorem-ipsum':
      return { output: loremIpsum(extra.paragraphs ?? 3) };
    case 'password':
      return { output: genPassword(extra.length ?? 16, extra.symbols !== false) };
    case 'uuid':
      return { output: Array.from({ length: UUID_BATCH }, () => crypto.randomUUID()).join('\n') };
    case 'jsonpath':
      return processJsonPath(input, extra.path ?? '$');
    case 'regex':
      return processRegex(input, extra.pattern ?? '', extra.flags ?? '');
    case 'xml-tester':
      return processXmlTester(action, input, extra.xpath ?? '');
    case 'escape-unescape':
      return processEscape(action, input, extra.mode ?? 'json');
    case 'list-compare':
      return processListCompare(input, inputB);
    case 'markdown-preview':
      return { output: markdownToHtml(input), html: true };
    case 'text-analyzer':
      return processTextAnalyzer(action, input);
    case 'text-compare':
      return { output: textDiff(input, inputB) };
    case 'gzip':
      return null; // async
    case 'certificate':
    case 'base64-image':
      return null; // server or special handlers
    default:
      return null;
  }
}

export async function processLocalAsync(slug, action, input, extra = {}, inputB = '') {
  if (slug === 'hash') {
    const algoMap = { sha1: 'SHA-1', sha256: 'SHA-256', sha512: 'SHA-512' };
    const algo = algoMap[extra.algorithm] ?? extra.algorithm ?? 'SHA-256';
    if (extra.algorithm === 'md5') return null;
    const buf = new TextEncoder().encode(input);
    const digest = await crypto.subtle.digest(algo, buf);
    const hex = [...new Uint8Array(digest)].map((b) => b.toString(16).padStart(2, '0')).join('');
    const lines = [`${algo}: ${hex}`];
    for (const a of ['SHA-1', 'SHA-256', 'SHA-512']) {
      if (a !== algo) {
        const d = await crypto.subtle.digest(a, buf);
        lines.push(`${a}: ${[...new Uint8Array(d)].map((b) => b.toString(16).padStart(2, '0')).join('')}`);
      }
    }
    return { output: lines.join('\n') };
  }
  if (slug === 'gzip') {
    if (action === 'compress') {
      const stream = new Blob([input]).stream().pipeThrough(new CompressionStream('gzip'));
      const buf = await new Response(stream).arrayBuffer();
      return { output: btoa(String.fromCharCode(...new Uint8Array(buf))) };
    }
    const bin = Uint8Array.from(atob(input), (c) => c.charCodeAt(0));
    const stream = new Blob([bin]).stream().pipeThrough(new DecompressionStream('gzip'));
    return { output: await new Response(stream).text() };
  }
  return processLocal(slug, action, input, extra, inputB);
}

function describeCron(expr) {
  const parts = expr.trim().split(/\s+/);
  if (parts.length !== 5 && parts.length !== 6) throw new Error('Cron must have 5 or 6 fields');
  const labels = parts.length === 6
    ? ['second', 'minute', 'hour', 'day of month', 'month', 'day of week']
    : ['minute', 'hour', 'day of month', 'month', 'day of week'];
  return parts.map((f, i) => {
    let desc = `at ${f}`;
    if (f === '*') desc = `every ${labels[i]}`;
    else if (f.startsWith('*/')) desc = `every ${f.slice(2)} ${labels[i]}(s)`;
    return `${labels[i][0].toUpperCase()}${labels[i].slice(1)}: ${desc}`;
  }).join('\n');
}

function processDate(action, input, extra) {
  const tz = extra.tz || 'UTC';
  let dt;
  if (/^\d+$/.test(input.trim())) {
    dt = new Date(parseInt(input, 10) * 1000);
  } else {
    dt = new Date(input);
  }
  if (isNaN(dt.getTime())) throw new Error('Invalid date');
  if (action === 'to_unix') return { output: String(Math.floor(dt.getTime() / 1000)), meta: { iso: dt.toISOString() } };
  if (action === 'from_unix') return { output: dt.toISOString(), meta: { unix: input } };
  if (action === 'format') {
    return { output: dt.toLocaleString('en-CA', { timeZone: tz, hour12: false }).replace(',', '') };
  }
  throw new Error('Unknown action');
}

function processJsonTable(action, input) {
  const data = JSON.parse(input);
  const rows = flattenRows(data);
  if (action === 'csv') {
    if (!rows.length) return { output: '' };
    const headers = Object.keys(rows[0]);
    const lines = [headers.join(',')];
    for (const row of rows) lines.push(headers.map((h) => csvEsc(row[h] ?? '')).join(','));
    return { output: lines.join('\n') };
  }
  if (!rows.length) return { output: '<table><tr><td>Empty</td></tr></table>', html: true };
  const headers = Object.keys(rows[0]);
  let html = '<table class="w-full text-sm border-collapse"><thead><tr>';
  for (const h of headers) html += `<th class="border border-slate-700 px-2 py-1 text-left">${esc(h)}</th>`;
  html += '</tr></thead><tbody>';
  for (const row of rows) {
    html += '<tr>';
    for (const h of headers) html += `<td class="border border-slate-700 px-2 py-1">${esc(String(row[h] ?? ''))}</td>`;
    html += '</tr>';
  }
  return { output: html + '</tbody></table>', html: true };
}

function flattenRows(data) {
  if (!Array.isArray(data)) return [typeof data === 'object' && data ? scalarize(data) : { value: data }];
  return data.map((item) => (typeof item === 'object' && item && !Array.isArray(item) ? scalarize(item) : { value: item }));
}

function scalarize(obj) {
  const out = {};
  for (const [k, v] of Object.entries(obj)) {
    out[k] = typeof v === 'object' && v !== null ? JSON.stringify(v) : v;
  }
  return out;
}

function csvEsc(v) {
  const s = String(v);
  return s.includes(',') || s.includes('"') || s.includes('\n') ? `"${s.replace(/"/g, '""')}"` : s;
}

function esc(s) {
  return s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}

function processJsonYaml(action, input) {
  if (action === 'to_yaml') {
    return { output: toYaml(JSON.parse(input)) };
  }
  return { output: JSON.stringify(parseYaml(input), null, 2) };
}

function toYaml(data, indent = 0) {
  const pad = '  '.repeat(indent);
  if (data === null) return 'null';
  if (typeof data === 'boolean') return data ? 'true' : 'false';
  if (typeof data === 'number') return String(data);
  if (typeof data === 'string') return /[:\-\[\]{}#,&*!|>'"%@`]/.test(data) || data === '' ? JSON.stringify(data) : data;
  if (Array.isArray(data)) {
    if (!data.length) return '[]';
    return data.map((item) => `${pad}- ${toYaml(item, indent + 1)}`).join('\n');
  }
  return Object.entries(data).map(([k, v]) => {
    const child = toYaml(v, indent + 1);
    return `${pad}${k}: ${child.includes('\n') ? '\n' + child : child}`;
  }).join('\n');
}

function parseYaml(text) {
  const lines = text.trim().split('\n');
  if (!lines.length) return {};
  if (lines[0].trim().startsWith('-')) return parseYamlList(lines, 0).value;
  return parseYamlMap(lines, 0).value;
}

function parseYamlMap(lines, start, base = -1) {
  const map = {};
  let i = start;
  while (i < lines.length) {
    const line = lines[i];
    if (!line.trim()) { i++; continue; }
    const indent = line.search(/\S/);
    if (base >= 0 && indent < base) break;
    const m = line.match(/^(\s*)([A-Za-z0-9_.-]+):\s*(.*)$/);
    if (!m) break;
    const [, , key, rest] = m;
    if (rest) { map[key] = parseScalar(rest); i++; continue; }
    i++;
    if (i < lines.length && /^\s*-\s/.test(lines[i])) {
      const p = parseYamlList(lines, i, indent + 2);
      map[key] = p.value; i = p.next;
    } else if (i < lines.length && /^\s+[A-Za-z]/.test(lines[i])) {
      const p = parseYamlMap(lines, i, indent + 2);
      map[key] = p.value; i = p.next;
    } else map[key] = null;
  }
  return { value: map, next: i };
}

function parseYamlList(lines, start, base = 0) {
  const list = [];
  let i = start;
  while (i < lines.length) {
    const line = lines[i];
    if (!line.trim()) { i++; continue; }
    const m = line.match(/^(\s*)-\s*(.*)$/);
    if (!m) break;
    const indent = m[1].length;
    if (i > start && indent < base) break;
    if (m[2]) { list.push(parseScalar(m[2])); i++; continue; }
    i++;
    if (i < lines.length && /^\s+[A-Za-z0-9_.-]+:/.test(lines[i])) {
      const p = parseYamlMap(lines, i, indent + 2);
      list.push(p.value); i = p.next;
    } else list.push(null);
  }
  return { value: list, next: i };
}

function parseScalar(raw) {
  const s = raw.trim();
  if (s === 'null' || s === '~') return null;
  if (s === 'true') return true;
  if (s === 'false') return false;
  if (!isNaN(Number(s))) return Number(s);
  if ((s.startsWith('"') && s.endsWith('"')) || (s.startsWith("'") && s.endsWith("'"))) return s.slice(1, -1);
  return s;
}

function processNumberBase(action, input, extra) {
  const from = extra.from_base ?? 10;
  const to = extra.to_base ?? 2;
  const n = parseInt(input.trim(), from);
  if (isNaN(n)) throw new Error('Invalid number for base');
  return { output: n.toString(to).toUpperCase(), meta: { from, to } };
}

function decodeJwt(token) {
  const parts = token.trim().split('.');
  if (parts.length !== 3) throw new Error('JWT must have 3 parts');
  const header = JSON.parse(b64UrlDecode(parts[0]));
  const payload = JSON.parse(b64UrlDecode(parts[1]));
  const meta = {};
  if (payload.exp) meta.expired = Date.now() / 1000 > payload.exp;
  return {
    output: `HEADER:\n${JSON.stringify(header, null, 2)}\n\nPAYLOAD:\n${JSON.stringify(payload, null, 2)}`,
    meta,
  };
}

function processJson(action, input) {
  const data = JSON.parse(input);
  if (action === 'format') return { output: JSON.stringify(data, null, 2) };
  if (action === 'minify') return { output: JSON.stringify(data) };
  if (action === 'validate') return { output: 'Valid JSON' };
  throw new Error('Unknown action');
}

function processSql(action, input) {
  const kws = ['SELECT', 'FROM', 'WHERE', 'JOIN', 'LEFT', 'RIGHT', 'INNER', 'OUTER', 'ON', 'AND', 'OR', 'GROUP BY', 'ORDER BY', 'HAVING', 'LIMIT', 'INSERT', 'INTO', 'VALUES', 'UPDATE', 'SET', 'DELETE'];
  if (action === 'minify') return { output: input.replace(/\s+/g, ' ').trim() };
  let sql = input;
  for (const kw of kws) {
    sql = sql.replace(new RegExp(`\\b${kw}\\b`, 'gi'), `\n${kw}`);
  }
  return { output: sql.split('\n').map((l) => l.trim()).filter(Boolean).join('\n') };
}

function processXml(action, input) {
  const doc = new DOMParser().parseFromString(input, 'text/xml');
  if (doc.querySelector('parsererror')) throw new Error('Invalid XML');
  if (action === 'validate') return { output: 'Valid XML' };
  const serialized = new XMLSerializer().serializeToString(doc);
  if (action === 'minify') return { output: serialized.replace(/>\s+</g, '><') };
  return { output: serialized };
}

function loremIpsum(paragraphs) {
  const words = ['lorem', 'ipsum', 'dolor', 'sit', 'amet', 'consectetur', 'adipiscing', 'elit', 'sed', 'do', 'eiusmod', 'tempor'];
  return Array.from({ length: paragraphs }, () => {
    const n = 40 + Math.floor(Math.random() * 40);
    const s = Array.from({ length: n }, () => words[Math.floor(Math.random() * words.length)]);
    s[0] = s[0][0].toUpperCase() + s[0].slice(1);
    return s.join(' ') + '.';
  }).join('\n\n');
}

function genPassword(len, symbols) {
  let chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
  if (symbols) chars += '!@#$%^&*()-_=+';
  const arr = new Uint32Array(len);
  crypto.getRandomValues(arr);
  return Array.from(arr, (n) => chars[n % chars.length]).join('');
}

function processJsonPath(input, path) {
  const data = JSON.parse(input);
  const matches = evalJsonPath(data, path.trim());
  return { output: JSON.stringify(matches, null, 2), meta: { count: matches.length } };
}

function evalJsonPath(data, path) {
  if (!path || path === '$') return [data];
  if (path.startsWith('$..')) {
    const key = path.slice(3);
    const found = [];
    (function walk(o) {
      if (o && typeof o === 'object') {
        if (Object.prototype.hasOwnProperty.call(o, key)) found.push(o[key]);
        for (const v of Object.values(o)) walk(v);
      }
    })(data);
    return found;
  }
  if (path.startsWith('$.')) {
    let cur = data;
    for (const seg of path.slice(2).split(/\.(?![^\[]*\])/)) {
      const m = seg.match(/^(.+)\[(\d+)\]$/);
      if (m) {
        cur = cur?.[m[1]]?.[parseInt(m[2], 10)];
      } else {
        cur = cur?.[seg];
      }
      if (cur === undefined) return [];
    }
    return [cur];
  }
  throw new Error('Use $, $.field, $.arr[0].field, or $..key');
}

function processRegex(input, pattern, flags) {
  if (!pattern) throw new Error('Enter a pattern');
  const re = new RegExp(pattern, flags);
  const matches = [...input.matchAll(new RegExp(re.source, re.flags + (re.global ? '' : 'g')))];
  const lines = [`Matches: ${matches.length}`];
  matches.forEach((m, i) => lines.push(`#${i + 1} offset ${m.index}: ${m[0]}`));
  return { output: lines.join('\n'), meta: { count: matches.length } };
}

function processXmlTester(action, input, xpath) {
  const doc = new DOMParser().parseFromString(input, 'text/xml');
  if (doc.querySelector('parsererror')) throw new Error(doc.querySelector('parsererror')?.textContent || 'Invalid XML');
  if (action === 'validate') return { output: 'Valid XML' };
  const result = doc.evaluate(xpath, doc, null, XPathResult.ORDERED_NODE_SNAPSHOT_TYPE, null);
  const nodes = [];
  for (let i = 0; i < result.snapshotLength; i++) {
    nodes.push(new XMLSerializer().serializeToString(result.snapshotItem(i)));
  }
  return { output: nodes.join('\n---\n'), meta: { count: nodes.length } };
}

function processEscape(action, input, mode) {
  if (action === 'escape') {
    if (mode === 'json') return { output: JSON.stringify(input) };
    if (mode === 'html') return { output: input.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;') };
    if (mode === 'url') return { output: encodeURIComponent(input) };
    if (mode === 'sql') return { output: input.replace(/\\/g, '\\\\').replace(/'/g, "\\'") };
    throw new Error('Unknown mode');
  }
  if (mode === 'json') return { output: JSON.parse(`"${input.replace(/"/g, '\\"')}"`) };
  if (mode === 'html') return { output: new DOMParser().parseFromString(input, 'text/html').documentElement.textContent ?? '' };
  if (mode === 'url') return { output: decodeURIComponent(input) };
  if (mode === 'sql') return { output: input.replace(/\\(.)/g, '$1') };
  return { output: input };
}

function processListCompare(a, b) {
  const left = lines(a);
  const right = lines(b);
  const onlyLeft = left.filter((x) => !right.includes(x));
  const onlyRight = right.filter((x) => !left.includes(x));
  const both = left.filter((x) => right.includes(x));
  return {
    output: `In both (${both.length}):\n${both.join('\n')}\n\nOnly left (${onlyLeft.length}):\n${onlyLeft.join('\n')}\n\nOnly right (${onlyRight.length}):\n${onlyRight.join('\n')}`,
    meta: { both: both.length, left_only: onlyLeft.length, right_only: onlyRight.length },
  };
}

function lines(text) {
  return text.split(/\r?\n/).map((l) => l.trim()).filter(Boolean);
}

function markdownToHtml(md) {
  let html = esc(md);
  html = html.replace(/^### (.+)$/gm, '<h3 class="text-lg font-semibold mt-4">$1</h3>');
  html = html.replace(/^## (.+)$/gm, '<h2 class="text-xl font-semibold mt-4">$1</h2>');
  html = html.replace(/^# (.+)$/gm, '<h1 class="text-2xl font-bold mt-4">$1</h1>');
  html = html.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
  html = html.replace(/\*(.+?)\*/g, '<em>$1</em>');
  html = html.replace(/`(.+?)`/g, '<code class="bg-slate-800 px-1 rounded">$1</code>');
  html = html.replace(/\[(.+?)\]\((.+?)\)/g, '<a href="$2" class="text-indigo-400 underline">$1</a>');
  return `<div class="prose prose-invert max-w-none">${html.replace(/\n/g, '<br>')}</div>`;
}

function processTextAnalyzer(action, input) {
  if (action === 'upper') return { output: input.toUpperCase() };
  if (action === 'lower') return { output: input.toLowerCase() };
  if (action === 'title') return { output: input.replace(/\b\w/g, (c) => c.toUpperCase()) };
  if (action === 'trim') return { output: input.trim() };
  const words = input.trim() ? input.trim().split(/\s+/).length : 0;
  const chars = input.length;
  const lineCount = input ? input.split(/\r?\n/).length : 0;
  return { output: `Characters: ${chars}\nWords: ${words}\nLines: ${lineCount}`, meta: { chars, words, lines: lineCount } };
}

function textDiff(a, b) {
  const left = a.split('\n');
  const right = b.split('\n');
  const max = Math.max(left.length, right.length);
  const out = [];
  for (let i = 0; i < max; i++) {
    const l = left[i] ?? '';
    const r = right[i] ?? '';
    if (l === r) out.push('  ' + l);
    else if (!l) out.push('+ ' + r);
    else if (!r) out.push('- ' + l);
    else { out.push('- ' + l); out.push('+ ' + r); }
  }
  return { output: out.join('\n') };
}

/** Draw a scannable QR code onto a canvas (ISO/IEC 18004 via qrcode). */
export async function renderQrToCanvas(canvas, text) {
  await QRCode.toCanvas(canvas, text, {
    width: 256,
    margin: 2,
    errorCorrectionLevel: 'M',
    color: { dark: '#000000', light: '#ffffff' },
  });
}
