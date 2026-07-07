import assert from 'node:assert/strict';
import {
  canConvertImage,
  extensionForMime,
  mimeFromDataUrl,
  resolveImageFormats,
} from '../resources/js/devtools/image-converter.js';

assert.deepEqual(resolveImageFormats('image/jpeg'), {
  format: 'image/jpeg',
  formats: ['image/png', 'image/jpeg', 'image/webp'],
});

const gif = resolveImageFormats('image/gif');
assert.equal(gif.format, 'image/gif');
assert.equal(gif.formats[0], 'image/gif');
assert.equal(gif.formats.length, 4);

assert.equal(mimeFromDataUrl('data:image/png;base64,abc'), 'image/png');
assert.equal(mimeFromDataUrl('not-data'), '');

assert.equal(canConvertImage('image/png', 'image/gif'), true);
assert.equal(canConvertImage('image/gif', 'image/gif'), false);
assert.equal(canConvertImage('image/jpeg', 'image/jpeg'), true);

assert.equal(extensionForMime('image/webp'), 'webp');
assert.equal(extensionForMime('image/gif'), 'gif');

console.log('devtools image-converter checks passed');
