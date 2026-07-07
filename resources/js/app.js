import '../css/app.css';
import Alpine from 'alpinejs';

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

function logAction(action, meta = {}) {
  const token = document.querySelector('meta[name="csrf-token"]')?.content;
  if (!token) return;
  const body = new URLSearchParams({ csrf: token, action });
  for (const [key, value] of Object.entries(meta)) {
    body.set(key, String(value));
  }
  fetch('/events/action', { method: 'POST', body }).catch(() => {});
}

window.logAction = logAction;

window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
  if (getStoredTheme() === 'system') applyTheme('system');
});

Alpine.data('themeSettings', () => ({
  preference: getStoredTheme(),
  set(value) {
    this.preference = value;
    setTheme(value);
  },
}));

window.Alpine = Alpine;
Alpine.start();
