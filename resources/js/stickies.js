export function registerStickies(Alpine) {
  const BOARD_W = 168;
  const BOARD_H = 168;

  Alpine.data('stickiesApp', () => ({
    csrf: '',
    canManage: false,
    palette: {},
    loading: true,
    ready: false,
    saving: false,
    error: '',
    notes: [],
    categories: [],
    sections: [],
    colors: [],
    search: '',
    filterCategory: 'all',
    expandedId: null,
    configOpenId: null,
    drag: null,
    skipOpenId: null,

    init() {
      const cfg = JSON.parse(this.$el.dataset.stickiesInit || '{}');
      this.csrf = cfg.csrf || document.querySelector('meta[name="csrf-token"]')?.content || '';
      this.canManage = !!cfg.canManage;
      this.palette = cfg.palette || {};
      this.refresh();
    },

    async refresh(silent = false) {
      if (!this.canManage) {
        this.loading = false;
        return;
      }
      if (!silent) this.loading = true;
      this.error = '';
      try {
        const params = new URLSearchParams();
        if (this.search.trim()) params.set('q', this.search.trim());
        if (this.filterCategory !== 'all') params.set('category', this.filterCategory);
        const qs = params.toString();
        const res = await fetch('/projects/stickies/api/notes' + (qs ? '?' + qs : ''));
        if (!res.ok) throw new Error('Could not load stickies');
        const data = await res.json();
        this.notes = data.notes || [];
        this.categories = data.categories || [];
        this.sections = data.sections || [];
        this.colors = data.colors || [];
        if (data.palette) this.palette = data.palette;
      } catch (e) {
        this.error = e.message || 'Load failed';
      } finally {
        this.loading = false;
        this.ready = true;
      }
    },

    onSearchInput() {
      clearTimeout(this._searchTimer);
      this._searchTimer = setTimeout(() => this.refresh(true), 250);
    },

    categoryOptions() {
      const cats = [...this.categories];
      if (this.filterCategory !== 'all' && !cats.includes(this.filterCategory)) {
        cats.push(this.filterCategory);
      }
      return cats.sort();
    },

    sectionGroups() {
      const map = {};
      for (const n of this.notes) {
        const s = n.section || 'board';
        (map[s] ||= []).push(n);
      }
      const names = Object.keys(map).sort();
      if (names.length === 0) return [{ name: 'board', notes: [] }];
      return names.map((name) => ({ name, notes: map[name] }));
    },

    noteStyle(note) {
      const bg = this.palette[note.color] || '#fef08a';
      return `background:${bg};left:${note.pos_x}px;top:${note.pos_y}px;width:${BOARD_W}px;min-height:${BOARD_H}px`;
    },

    previewText(content) {
      const t = (content || '').trim();
      if (!t) return 'Empty note…';
      return t.length > 120 ? t.slice(0, 120) + '…' : t;
    },

    openNote(note) {
      if (this.skipOpenId === note.id) {
        this.skipOpenId = null;
        return;
      }
      this.expandedId = note.id;
      this.configOpenId = null;
      this.$nextTick(() => {
        const el = this.$refs['editor-' + note.id];
        if (el) el.focus();
      });
    },

    closeExpanded() {
      this.expandedId = null;
      this.configOpenId = null;
    },

    toggleConfig(id) {
      this.configOpenId = this.configOpenId === id ? null : id;
    },

    isExpanded(id) {
      return this.expandedId === id;
    },

    isConfigOpen(id) {
      return this.configOpenId === id;
    },

    async post(path, body) {
      const res = await fetch(path, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ csrf: this.csrf, ...body }),
      });
      const data = await res.json();
      if (!res.ok) throw new Error(data.error || 'Request failed');
      return data;
    },

    applyPayload(data) {
      this.notes = data.notes || this.notes;
      this.categories = data.categories || this.categories;
      this.sections = data.sections || this.sections;
    },

    async saveNote(note) {
      this.saving = true;
      this.error = '';
      try {
        const data = await this.post('/projects/stickies/note/save', {
          note_id: String(note.id || 0),
          content: note.content || '',
          category: note.category || 'general',
          color: note.color || 'yellow',
          section: note.section || 'board',
          pos_x: String(note.pos_x ?? 0),
          pos_y: String(note.pos_y ?? 0),
        });
        this.applyPayload(data);
        if (note.id) {
          const updated = data.notes.find((n) => n.id === note.id);
          if (updated) Object.assign(note, updated);
        }
      } catch (e) {
        this.error = e.message;
      } finally {
        this.saving = false;
      }
    },

    async createNote() {
      this.saving = true;
      try {
        const data = await this.post('/projects/stickies/note/save', {
          content: '',
          category: 'general',
          color: 'yellow',
          section: 'board',
        });
        this.applyPayload(data);
        const newest = data.notes[data.notes.length - 1];
        if (newest) this.openNote(newest);
      } catch (e) {
        this.error = e.message;
      } finally {
        this.saving = false;
      }
    },

    async deleteNote(note) {
      if (!confirm('Delete this sticky?')) return;
      try {
        const data = await this.post('/projects/stickies/note/delete', {
          note_id: String(note.id),
        });
        this.applyPayload(data);
        if (this.expandedId === note.id) this.closeExpanded();
      } catch (e) {
        this.error = e.message;
      }
    },

    async persistMove(note) {
      try {
        const data = await this.post('/projects/stickies/note/move', {
          note_id: String(note.id),
          pos_x: String(note.pos_x),
          pos_y: String(note.pos_y),
          section: note.section,
        });
        this.applyPayload(data);
      } catch (e) {
        this.error = e.message;
      }
    },

    startDrag(note, ev, sectionName) {
      if (!this.canManage || this.isExpanded(note.id)) return;
      ev.preventDefault();
      const card = ev.currentTarget;
      const rect = card.getBoundingClientRect();
      const parent = card.offsetParent?.getBoundingClientRect() || { left: 0, top: 0 };
      this.drag = {
        id: note.id,
        section: sectionName,
        moved: false,
        offsetX: ev.clientX - rect.left,
        offsetY: ev.clientY - rect.top,
        parentLeft: parent.left,
        parentTop: parent.top,
      };
      const onMove = (e) => this.onDrag(e, note);
      const onUp = () => {
        window.removeEventListener('pointermove', onMove);
        window.removeEventListener('pointerup', onUp);
        const moved = !!this.drag?.moved;
        if (moved) {
          this.skipOpenId = note.id;
          this.persistMove(note);
        }
        this.drag = null;
      };
      window.addEventListener('pointermove', onMove);
      window.addEventListener('pointerup', onUp);
    },

    onDrag(ev, note) {
      if (!this.drag || this.drag.id !== note.id) return;
      this.drag.moved = true;
      const x = Math.max(0, ev.clientX - this.drag.parentLeft - this.drag.offsetX);
      const y = Math.max(0, ev.clientY - this.drag.parentTop - this.drag.offsetY);
      note.pos_x = Math.round(x);
      note.pos_y = Math.round(y);
    },

    sectionLabel(name) {
      return name.charAt(0).toUpperCase() + name.slice(1);
    },
  }));
}
