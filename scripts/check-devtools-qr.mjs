import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import QRCode from 'qrcode';

const root = join(dirname(fileURLToPath(import.meta.url)), '..');
const clientJs = readFileSync(join(root, 'resources/js/devtools/client.js'), 'utf8');

const payload = 'https://example.com/test';
const qr = QRCode.create(payload, { errorCorrectionLevel: 'M' });
assert.ok(qr.modules.size >= 21, 'QR must encode to a valid module grid');
assert.ok(qr.modules.get(0, 0), 'finder pattern top-left corner');
assert.ok(qr.modules.get(6, 0), 'finder pattern top-left edge');

const dataUrl = await QRCode.toDataURL(payload, { width: 256 });
assert.match(dataUrl, /^data:image\/png;base64,/);

assert.match(clientJs, /renderQrToCanvas/, 'devtools must use real QR encoder');
assert.doesNotMatch(clientJs, /QR preview generated/, 'fake hash-based QR must be removed');

console.log('devtools QR checks passed');
