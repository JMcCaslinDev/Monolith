export function registerTunnels(Alpine) {
  Alpine.data('tunnelsApp', () => ({
    hubUrl: '',
    appUrl: '',
    scriptUrl: '',
    downloadCommand: '',
    hubIsLocal: false,
    tunnels: [],
    selected: null,
    selectedId: null,
    requests: [],
    lastRequestId: 0,
    clientCommand: '',
    canCreate: false,
    canManage: false,
    creating: false,
    openRequestId: null,
    pollTimer: null,
    form: {
      label: '',
      local_port: 8000,
      ttl_minutes: 480,
    },

    init() {
      const cfg = JSON.parse(this.$el.dataset.tunnelsInit || '{}');
      this.hubUrl = cfg.hubUrl || '';
      this.appUrl = cfg.appUrl || '';
      this.scriptUrl = cfg.scriptUrl || '';
      this.downloadCommand = cfg.downloadCommand || '';
      this.hubIsLocal = (this.hubUrl.includes('localhost') || this.hubUrl.includes('127.0.0.1'));
      this.refresh();
      this.pollTimer = setInterval(() => this.refresh(), 2000);
    },

    destroy() {
      if (this.pollTimer) clearInterval(this.pollTimer);
    },

    async refresh() {
      const params = new URLSearchParams();
      if (this.selectedId) params.set('tunnel_id', String(this.selectedId));
      if (this.lastRequestId) params.set('since_id', String(this.lastRequestId));
      try {
        const res = await fetch('/projects/tunnels/api/state?' + params.toString());
        if (!res.ok) return;
        const data = await res.json();
        this.tunnels = data.tunnels || [];
        this.canCreate = !!data.canCreate;
        this.canManage = !!data.canManage;
        if (data.download_command) this.downloadCommand = data.download_command;
        if (data.script_url) this.scriptUrl = data.script_url;
        if (data.hub_url) {
          this.hubUrl = data.hub_url;
          this.hubIsLocal = this.hubUrl.includes('localhost') || this.hubUrl.includes('127.0.0.1');
        }
        if (data.selected) {
          this.selected = data.selected;
          this.selectedId = data.selected.id;
          if (data.selected.client_command) {
            this.clientCommand = data.selected.client_command;
          }
        }
        const newReqs = data.requests || [];
        if (newReqs.length) {
          this.requests.push(...newReqs);
          this.lastRequestId = newReqs[newReqs.length - 1].id;
        }
        if (this.selectedId && !this.tunnels.find((t) => t.id === this.selectedId)) {
          this.selected = null;
          this.selectedId = null;
          this.requests = [];
          this.lastRequestId = 0;
          this.clientCommand = '';
        }
      } catch {
        // ponytail: poll again on next tick
      }
    },

    selectTunnel(id) {
      this.selectedId = id;
      this.selected = this.tunnels.find((t) => t.id === id) || null;
      this.requests = [];
      this.lastRequestId = 0;
      this.openRequestId = null;
      this.clientCommand = '';
      this.refresh();
    },

    async createTunnel() {
      this.creating = true;
      const token = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
      const body = new URLSearchParams({
        csrf: token,
        label: this.form.label,
        local_port: String(this.form.local_port),
        ttl_minutes: String(this.form.ttl_minutes),
      });
      try {
        const res = await fetch('/projects/tunnels/create', { method: 'POST', body });
        const data = await res.json();
        if (!res.ok) {
          alert(data.error || 'Failed to create tunnel');
          return;
        }
        this.form.label = '';
        if (data.tunnel?.client_command) {
          this.clientCommand = data.tunnel.client_command;
        }
        this.selectedId = data.tunnel.id;
        this.selected = data.tunnel;
        await this.refresh();
      } finally {
        this.creating = false;
      }
    },

    async stopTunnel() {
      if (!this.selectedId || !confirm('Stop this tunnel?')) return;
      const token = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
      const body = new URLSearchParams({ csrf: token, tunnel_id: String(this.selectedId) });
      await fetch('/projects/tunnels/stop', { method: 'POST', body });
      await this.refresh();
    },

    toggleRequest(id) {
      this.openRequestId = this.openRequestId === id ? null : id;
    },

    statusClass(status) {
      const map = {
        active: 'text-green-600 dark:text-green-400',
        pending: 'text-amber-600 dark:text-amber-400',
        stopped: 'text-gray-500',
        expired: 'text-gray-400',
      };
      return map[status] || 'text-gray-500';
    },

    methodClass(method) {
      const m = (method || 'GET').toUpperCase();
      const colors = {
        GET: 'bg-blue-100 text-blue-800 dark:bg-blue-900/50 dark:text-blue-200',
        POST: 'bg-green-100 text-green-800 dark:bg-green-900/50 dark:text-green-200',
        PUT: 'bg-amber-100 text-amber-800 dark:bg-amber-900/50 dark:text-amber-200',
        PATCH: 'bg-amber-100 text-amber-800 dark:bg-amber-900/50 dark:text-amber-200',
        DELETE: 'bg-red-100 text-red-800 dark:bg-red-900/50 dark:text-red-200',
      };
      return colors[m] || 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200';
    },

    formatJson(raw) {
      if (!raw) return '—';
      try {
        const parsed = typeof raw === 'string' ? JSON.parse(raw) : raw;
        return JSON.stringify(parsed, null, 2);
      } catch {
        return String(raw);
      }
    },

    copy(text) {
      navigator.clipboard?.writeText(text).catch(() => {});
    },
  }));
}
