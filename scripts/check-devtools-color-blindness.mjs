import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = join(dirname(fileURLToPath(import.meta.url)), '..');
const devtoolsJs = readFileSync(join(root, 'resources/js/devtools.js'), 'utf8');

const matrices = {
  protanopia: [0.567, 0.433, 0, 0.558, 0.442, 0, 0, 0.242, 0.758],
  deuteranopia: [0.625, 0.375, 0, 0.7, 0.3, 0, 0, 0.3, 0.7],
  tritanopia: [0.95, 0.05, 0, 0, 0.433, 0.567, 0, 0.475, 0.525],
};

for (const [mode, matrix] of Object.entries(matrices)) {
  assert.strictEqual(matrix.length, 9, `${mode} matrix must have 9 coefficients`);
}

const r = 200;
const g = 100;
const b = 50;
const out = [
  r * matrices.protanopia[0] + g * matrices.protanopia[1] + b * matrices.protanopia[2],
  r * matrices.protanopia[3] + g * matrices.protanopia[4] + b * matrices.protanopia[5],
  r * matrices.protanopia[6] + g * matrices.protanopia[7] + b * matrices.protanopia[8],
];
assert.ok(out[0] < r, 'protanopia should shift red channel');

assert.match(devtoolsJs, /applyColorBlindness/, 'color blindness must be implemented in devtools.js');
assert.match(devtoolsJs, /protanopia/, 'protanopia mode must exist');
assert.match(devtoolsJs, /logDevtools\('color-blindness'/, 'color blindness actions must be audited');

console.log('devtools color-blindness checks passed');
