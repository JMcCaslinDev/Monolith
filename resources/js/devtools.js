import { processLocal, processLocalAsync, renderQrToCanvas } from './devtools/client.js';
import {
  canConvertImage,
  extensionForMime,
  formatLabel as imageFormatLabel,
  mimeFromDataUrl,
  resolveImageFormats,
} from './devtools/image-converter.js';
import { initOpenCategories, scrollPanelToTop, toggleCategoryState } from './devtools/sidebar.js';

function logDevtools(tool, action, meta = {}) {
  window.logAction?.(`devtools.${tool}.${action}`, { tool, ...meta });
}

function logFieldFocus(tool, el) {
  const tag = el.tagName.toLowerCase();
  const type = el.type && el.type !== 'text' ? `:${el.type}` : '';
  logDevtools(tool, 'focus', { field: `${tag}${type}` });
}

export function registerDevtools(Alpine) {
  Alpine.data('devtoolsApp', () => ({
    activeTool: '',
    openCategories: {},
    input: '',
    inputB: '',
    output: '',
    outputIsHtml: false,
    error: '',
    status: '',
    extra: {},
    loading: false,
    toolNames: {},
    imageReady: false,
    convSource: '',
    convUploadType: '',
    convFormats: ['image/png', 'image/jpeg', 'image/webp'],

    init() {
      const raw = this.$el.dataset.devtoolsInit || '{}';
      const cfg = JSON.parse(raw);
      this.openCategories = initOpenCategories(cfg.categories ?? []);
      this.$el.querySelectorAll('[data-tool-slug]').forEach((el) => {
        this.toolNames[el.dataset.toolSlug] = el.dataset.toolName;
      });
      const initial = cfg.tool || '';
      if (initial) {
        this.selectTool(initial, false);
      } else {
        const first = this.$el.querySelector('[data-tool-slug]');
        if (first) this.selectTool(first.dataset.toolSlug, false);
      }
      window.addEventListener('popstate', () => {
        const slug = location.pathname.split('/').pop();
        if (slug && slug !== 'devtools') {
          this.activeTool = slug;
          this.extra = this.defaultExtra(slug);
          this.scrollMainToTop();
          logDevtools(slug, 'navigate');
          if (slug === 'uuid') {
            this.$nextTick(() => this.run('run'));
          }
        }
      });
      this.$el.addEventListener('focusin', (e) => {
        const el = e.target;
        if (!el?.matches?.('.devtools-input, .devtools-input-sm, input[type="file"]')) {
          return;
        }
        logFieldFocus(this.activeTool, el);
      });
      logDevtools(initial || 'hub', 'page.load');
    },

    selectTool(slug, push = true) {
      this.activeTool = slug;
      this.input = '';
      this.inputB = '';
      this.output = '';
      this.outputIsHtml = false;
      this.error = '';
      this.status = '';
      this.imageReady = false;
      this.convSource = '';
      this.convUploadType = '';
      this.convFormats = ['image/png', 'image/jpeg', 'image/webp'];
      this.extra = this.defaultExtra(slug);
      if (push) {
        history.pushState({}, '', `/projects/devtools/${slug}`);
      }
      this.scrollMainToTop();
      if (slug === 'uuid') {
        this.$nextTick(() => this.run('run'));
      }
      logDevtools(slug, 'open');
    },

    scrollMainToTop() {
      this.$nextTick(() => {
        scrollPanelToTop(this.$refs.mainPanel);
        window.scrollTo(0, 0);
      });
    },

    toggleCategory(id) {
      const next = toggleCategoryState(this.openCategories, id);
      const opened = !!next[id];
      this.openCategories = next;
      logDevtools('sidebar', opened ? 'category.open' : 'category.close', { category: id });
    },

    defaultExtra(slug) {
      const defaults = {
        date: { tz: 'UTC', format: 'Y-m-d H:i:s' },
        'number-base': { from_base: 10, to_base: 2 },
        hash: { algorithm: 'SHA-256' },
        'lorem-ipsum': { paragraphs: 3 },
        password: { length: 16, symbols: true },
        'escape-unescape': { mode: 'json' },
        regex: { flags: 'i' },
        'xml-tester': { xpath: '' },
        'color-blindness': { mode: 'protanopia' },
        'image-converter': { format: 'image/png' },
      };
      return { ...(defaults[slug] ?? {}) };
    },

    async run(action = 'run') {
      this.error = '';
      this.status = '';
      const clientOnly = ['qr-code', 'color-blindness', 'image-converter', 'base64-image'];
      if (clientOnly.includes(this.activeTool)) {
        await this.runClient(action);
        return;
      }
      try {
        const result = await processLocalAsync(
          this.activeTool,
          action,
          this.input,
          this.extra,
          this.inputB,
        );
        if (!result) {
          await this.runServer(action);
          return;
        }
        this.applyResult(result);
        logDevtools(this.activeTool, action, {
          input_bytes: this.input.length,
          ...(result.error ? { error: 'tool_error' } : {}),
        });
      } catch (e) {
        this.error = e.message || 'Processing failed';
        this.output = '';
        this.outputIsHtml = false;
        logDevtools(this.activeTool, `${action}.error`, { error: 'processing_failed' });
      }
    },

    applyResult(result) {
      if (result.error) {
        this.error = result.error;
        this.output = '';
        this.outputIsHtml = false;
        return;
      }
      this.output = result.output ?? '';
      this.outputIsHtml = !!result.html;
      this.status = this.activeTool === 'uuid' ? '' : 'Done';
    },

    async runServer(action) {
      this.loading = true;
      try {
        const token = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
        const body = new URLSearchParams({
          csrf: token,
          tool: this.activeTool,
          action,
          input: this.input,
          input_b: this.inputB,
          extra: JSON.stringify(this.extra),
        });
        const res = await fetch('/projects/devtools/process', { method: 'POST', body });
        const result = await res.json();
        this.applyResult(result);
        logDevtools(this.activeTool, action, {
          input_bytes: this.input.length,
          ...(result.error ? { error: 'tool_error' } : {}),
        });
      } catch (e) {
        this.error = e.message || 'Request failed';
      } finally {
        this.loading = false;
      }
    },

    async runClient(action) {
      if (this.activeTool === 'qr-code') {
        await this.renderQr();
        return;
      }
      if (this.activeTool === 'color-blindness') {
        this.applyColorBlindness();
        return;
      }
      if (this.activeTool === 'image-converter') {
        await this.convertImage();
        return;
      }
      if (this.activeTool === 'base64-image') {
        await this.inspectBase64Image();
      }
    },

    async inspectBase64Image() {
      let data = this.input.trim();
      const m = data.match(/^data:image\/[^;]+;base64,(.+)$/i);
      if (m) data = m[1];
      try {
        const bin = atob(data);
        const blob = new Blob([Uint8Array.from(bin, (c) => c.charCodeAt(0))]);
        const bmp = await createImageBitmap(blob).catch(() => null);
        if (bmp) {
          this.output = `${blob.type || 'image'} ${bmp.width}x${bmp.height}, ${bin.length} bytes`;
          bmp.close();
        } else {
          this.output = `Decoded ${bin.length} bytes (not a recognized image)`;
        }
        this.outputIsHtml = false;
        this.status = 'Done';
      } catch {
        this.error = 'Invalid Base64 image data';
      }
    },

    async renderQr() {
      const canvas = this.$refs.qrCanvas;
      if (!canvas || !this.input.trim()) {
        this.error = 'Enter text to encode';
        return;
      }
      try {
        await renderQrToCanvas(canvas, this.input.trim());
        this.status = 'QR code generated';
        this.error = '';
        logDevtools('qr-code', 'generate');
      } catch (e) {
        this.error = e.message || 'Could not encode QR code';
        this.status = '';
      }
    },

    applyColorBlindness() {
      const img = this.$refs.cbImage;
      const canvas = this.$refs.cbCanvas;
      if (!img?.complete || !img.naturalWidth || !canvas) {
        this.error = 'Upload an image first';
        return;
      }
      canvas.width = img.naturalWidth;
      canvas.height = img.naturalHeight;
      const ctx = canvas.getContext('2d');
      ctx.drawImage(img, 0, 0);
      const mode = this.extra.mode || 'protanopia';
      const matrix = {
        protanopia: [0.567, 0.433, 0, 0.558, 0.442, 0, 0, 0.242, 0.758],
        deuteranopia: [0.625, 0.375, 0, 0.7, 0.3, 0, 0, 0.3, 0.7],
        tritanopia: [0.95, 0.05, 0, 0, 0.433, 0.567, 0, 0.475, 0.525],
      }[mode];
      const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
      const d = imageData.data;
      for (let i = 0; i < d.length; i += 4) {
        const r = d[i];
        const g = d[i + 1];
        const b = d[i + 2];
        d[i] = r * matrix[0] + g * matrix[1] + b * matrix[2];
        d[i + 1] = r * matrix[3] + g * matrix[4] + b * matrix[5];
        d[i + 2] = r * matrix[6] + g * matrix[7] + b * matrix[8];
      }
      ctx.putImageData(imageData, 0, 0);
      this.status = `Applied ${mode} simulation`;
      this.error = '';
      logDevtools('color-blindness', mode);
    },

    onImageUpload(event) {
      const file = event.target.files?.[0];
      if (!file) return;
      logDevtools(this.activeTool, 'file.select', { field: 'input:file' });
      const reader = new FileReader();
      reader.onload = (e) => {
        if (this.activeTool === 'color-blindness') {
          this.$refs.cbImage.onload = () => this.applyColorBlindness();
          this.$refs.cbImage.src = e.target.result;
        } else if (this.activeTool === 'image-converter') {
          this.imageReady = false;
          this.output = '';
          this.convSource = e.target.result;
          const uploadType = (file.type || mimeFromDataUrl(this.convSource)).toLowerCase();
          this.convUploadType = uploadType;
          const resolved = resolveImageFormats(uploadType);
          this.convFormats = resolved.formats;
          this.extra = { ...this.extra, format: resolved.format };
          this.$refs.convImage.onload = () => {
            this.imageReady = true;
            this.error = '';
          };
          this.$refs.convImage.onerror = () => {
            this.imageReady = false;
            this.convSource = '';
            this.error = 'Could not load image';
          };
          this.$refs.convImage.src = this.convSource;
        } else if (this.activeTool === 'base64-image') {
          this.input = e.target.result;
          this.inspectBase64Image();
        }
      };
      reader.readAsDataURL(file);
    },

    async ensureImageLoaded(img) {
      if (img.complete && img.naturalWidth) {
        return;
      }
      await new Promise((resolve, reject) => {
        img.onload = () => resolve();
        img.onerror = () => reject(new Error('Could not load image'));
      });
    },

    convertedFilename() {
      return `converted.${extensionForMime(this.extra.format || 'image/png')}`;
    },

    imageFormatLabel(mime) {
      return imageFormatLabel(mime);
    },

    canConvertImage() {
      return canConvertImage(this.extra.format || 'image/png', this.convUploadType);
    },

    downloadConverted() {
      if (!this.output) {
        return;
      }
      const a = document.createElement('a');
      a.href = this.output;
      a.download = this.convertedFilename();
      document.body.appendChild(a);
      a.click();
      a.remove();
    },

    async convertImage() {
      const img = this.$refs.convImage;
      const canvas = this.$refs.convCanvas;
      if (!img?.src) {
        this.error = 'Upload an image first';
        return;
      }
      if (!this.canConvertImage()) {
        this.error = 'Select PNG, JPEG, or WebP to convert';
        return;
      }
      try {
        await this.ensureImageLoaded(img);
      } catch {
        this.error = 'Could not load image';
        return;
      }
      if (!canvas || !img.naturalWidth) {
        this.error = 'Upload an image first';
        return;
      }
      const format = this.extra.format || 'image/png';
      canvas.width = img.naturalWidth;
      canvas.height = img.naturalHeight;
      canvas.getContext('2d').drawImage(img, 0, 0);
      this.output = canvas.toDataURL(format, 0.92);
      this.outputIsHtml = false;
      this.status = `Converted to ${format.split('/')[1]?.toUpperCase() || 'image'}`;
      this.error = '';
      this.downloadConverted();
      logDevtools('image-converter', format);
    },

    isActive(slug) {
      return this.activeTool === slug;
    },

    /** Lorem output grows with paragraph count so generated text stays visible without scrolling. */
    outputRows() {
      if (this.activeTool !== 'lorem-ipsum') {
        return 10;
      }
      const paras = Math.max(1, Number(this.extra.paragraphs) || 3);
      const fromCount = paras * 5;
      const fromText = this.output ? this.output.split('\n').length + 1 : 0;
      return Math.min(48, Math.max(15, fromCount, fromText));
    },

    toolTitle() {
      return this.toolNames[this.activeTool] || this.activeTool;
    },
  }));
}
