import '../css/app.css';
import Alpine from 'alpinejs';
import { registerDevtools } from './devtools.js';
import { registerTunnels } from './tunnels.js';
import { registerBudgetTracker } from './budget-tracker.js';
import { registerCursorShare } from './cursor-share.js';

const STORAGE_KEY = 'monolith-theme';

function getStoredTheme() {
  return localStorage.getItem(STORAGE_KEY) || 'system';
}

function resolveTheme(preference) {
  if (preference === 'system') {
    return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
  }
  return preference;
}

function applyTheme(preference) {
  document.documentElement.classList.toggle('dark', resolveTheme(preference) === 'dark');
}

function setTheme(preference) {
  localStorage.setItem(STORAGE_KEY, preference);
  applyTheme(preference);
  logAction('settings.theme', { theme: preference });
  const token = document.querySelector('meta[name="csrf-token"]')?.content;
  if (token) {
    fetch('/profile/theme', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: `theme=${encodeURIComponent(preference)}&csrf=${encodeURIComponent(token)}`,
    }).catch(() => {});
  }
}

const LOG_META_KEYS = new Set(['input_bytes', 'field', 'category', 'tool', 'theme', 'error']);

function logAction(action, meta = {}) {
  const token = document.querySelector('meta[name="csrf-token"]')?.content;
  if (!token) return;
  const body = new URLSearchParams({ csrf: token, action });
  for (const [key, value] of Object.entries(meta)) {
    if (!LOG_META_KEYS.has(key)) continue;
    body.set(key, String(value).slice(0, 64));
  }
  fetch('/events/action', { method: 'POST', body }).catch(() => {});
}

window.logAction = logAction;

function bootstrapTimezone() {
  const token = document.querySelector('meta[name="csrf-token"]')?.content;
  const configured = document.querySelector('meta[name="timezone-configured"]')?.content === '1';
  if (!token || configured) return;
  const tz = Intl.DateTimeFormat().resolvedOptions().timeZone;
  if (!tz) return;
  fetch('/profile/timezone', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: `timezone=${encodeURIComponent(tz)}&auto=1&csrf=${encodeURIComponent(token)}`,
  }).catch(() => {});
}

window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
  if (getStoredTheme() === 'system') applyTheme('system');
});

registerDevtools(Alpine);
registerTunnels(Alpine);
registerCursorShare(Alpine);
registerBudgetTracker(Alpine);

Alpine.data('themeSettings', () => ({
  preference: getStoredTheme(),
  set(value) {
    this.preference = value;
    setTheme(value);
  },
}));

window.Alpine = Alpine;
Alpine.start();
bootstrapTimezone();
