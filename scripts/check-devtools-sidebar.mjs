import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import {
  initOpenCategories,
  isCategoryOpen,
  scrollPanelToTop,
  toggleCategoryState,
} from '../resources/js/devtools/sidebar.js';

const root = join(dirname(fileURLToPath(import.meta.url)), '..');
const appPhp = readFileSync(join(root, 'packages/devtools/views/app.php'), 'utf8');

const ids = ['converters', 'encoders', 'formatters'];
const open = initOpenCategories(ids);
assert.equal(Object.keys(open).length, 3);
assert.equal(isCategoryOpen(open, 'converters'), true);

const closed = toggleCategoryState(open, 'converters');
assert.equal(isCategoryOpen(closed, 'converters'), false);
assert.equal(isCategoryOpen(closed, 'encoders'), true);

const reopened = toggleCategoryState(closed, 'converters');
assert.equal(isCategoryOpen(reopened, 'converters'), true);

assert.match(appPhp, /x-show="openCategories\[/, 'sidebar tool list must hide when category is collapsed');
assert.match(appPhp, /devtools-shell/, 'shell must use fixed height for independent scroll panes');
assert.match(appPhp, /x-ref="mainPanel"/, 'main panel must be scrollable independently');

const panel = { scrollTop: 120 };
scrollPanelToTop(panel);
assert.equal(panel.scrollTop, 0);

const devtoolsJs = readFileSync(join(root, 'resources/js/devtools.js'), 'utf8');
assert.match(devtoolsJs, /scrollMainToTop/, 'tool switch must reset main panel scroll');
assert.match(devtoolsJs, /scrollPanelToTop/, 'main panel scroll reset must use shared helper');

console.log('devtools sidebar checks passed');
