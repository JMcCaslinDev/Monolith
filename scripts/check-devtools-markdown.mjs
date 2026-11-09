import assert from 'node:assert/strict';
import { processLocal } from '../resources/js/devtools/client.js';

const render = (input) => processLocal('markdown-preview', 'run', input).output;

for (const bad of [
  'javascript:alert(1)',
  'JaVaScRiPt:alert(1)',
  ' javascript:alert(1)',
  'data:text/html,x',
  'vbscript:x',
  '//evil.example',
]) {
  const html = render(`[click](${bad})`);
  assert.ok(!html.includes('href='), `unsafe scheme kept: ${bad}`);
  assert.ok(html.includes('click'), 'link label still rendered');
}

const injected = render('[x](https://a.co/" onmouseover="alert(1))');
assert.ok(!/href="[^"]*" onmouseover/.test(injected), 'quote must not close href');

const ok = render('[a](https://example.com/x?y=1&z=2) [b](/local) [c](mailto:a@b.co)');
assert.ok(ok.includes('href="https://example.com/x?y=1&amp;z=2"'));
assert.ok(ok.includes('href="/local"'));
assert.ok(ok.includes('href="mailto:a@b.co"'));
assert.ok(ok.includes('rel="noopener noreferrer"'));

console.log('devtools markdown preview ok');
