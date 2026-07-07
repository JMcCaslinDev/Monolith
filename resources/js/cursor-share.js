export function registerCursorShare(Alpine) {
  Alpine.data('cursorShareApp', () => ({
    categories: {},
    csrf: '',
    tab: 'browse',
    posts: [],
    top: {},
    selected: null,
    selectedId: null,
    canPost: false,
    canVote: false,
    canDownload: false,
    currentUserId: 0,
    saving: false,
    formError: '',
    filters: {
      q: '',
      category: '',
      sort: 'popular',
      date_from: '',
      date_to: '',
    },
    form: {
      post_id: 0,
      category: 'rules',
      title: '',
      description: '',
      filename: '',
      version: '',
      tags: '',
      content: '',
    },

    init() {
      const cfg = JSON.parse(this.$el.dataset.cursorShareInit || '{}');
      this.categories = cfg.categories || {};
      this.csrf = cfg.csrf || document.querySelector('meta[name="csrf-token"]')?.content || '';
      this.refresh();
    },

    setTab(tab) {
      this.tab = tab;
      this.selected = null;
      this.selectedId = null;
      this.refresh();
    },

    startCreate() {
      this.form = {
        post_id: 0,
        category: 'rules',
        title: '',
        description: '',
        filename: '',
        version: '',
        tags: '',
        content: '',
      };
      this.formError = '';
      this.tab = 'create';
    },

    startEdit(post) {
      this.form = {
        post_id: post.id,
        category: post.category,
        title: post.title,
        description: post.description || '',
        filename: post.filename,
        version: post.version || '',
        tags: (post.tags || []).join(', '),
        content: post.content || '',
      };
      this.formError = '';
      this.tab = 'create';
    },

    categoryLabel(cat) {
      return this.categories[cat]?.label || cat;
    },

    filenameHint(cat) {
      return this.categories[cat]?.filename_hint || 'file';
    },

    async refresh() {
      const params = new URLSearchParams();
      if (this.tab === 'mine') params.set('mine', '1');
      if (this.filters.q) params.set('q', this.filters.q);
      if (this.filters.category) params.set('category', this.filters.category);
      if (this.filters.sort) params.set('sort', this.filters.sort);
      if (this.filters.date_from) params.set('date_from', this.filters.date_from);
      if (this.filters.date_to) params.set('date_to', this.filters.date_to);
      if (this.selectedId) params.set('post_id', String(this.selectedId));

      try {
        const res = await fetch('/projects/cursor-share/api/state?' + params.toString());
        if (!res.ok) return;
        const data = await res.json();
        this.posts = data.posts || [];
        this.top = data.top || {};
        this.canPost = !!data.canPost;
        this.canVote = !!data.canVote;
        this.canDownload = !!data.canDownload;
        this.currentUserId = data.currentUserId || 0;
        if (data.selected) {
          this.selected = data.selected;
          this.selectedId = data.selected.id;
        }
      } catch {
        // ponytail: user can retry
      }
    },

    async openPost(id) {
      this.selectedId = id;
      await this.refresh();
      await this.recordView(id);
    },

    closePost() {
      this.selected = null;
      this.selectedId = null;
      this.refresh();
    },

    async recordView(id) {
      const body = new URLSearchParams({ csrf: this.csrf, post_id: String(id) });
      await fetch('/projects/cursor-share/posts/view', { method: 'POST', body }).catch(() => {});
      if (this.selected) this.selected.views += 1;
    },

    async vote(direction) {
      if (!this.selected || !this.canVote) return;
      const body = new URLSearchParams({
        csrf: this.csrf,
        post_id: String(this.selected.id),
        direction: String(direction),
      });
      const res = await fetch('/projects/cursor-share/posts/vote', { method: 'POST', body });
      const data = await res.json();
      if (!res.ok) {
        alert(data.error || 'Vote failed');
        return;
      }
      this.selected.user_vote = data.vote;
      this.selected.upvotes = data.upvotes;
      this.selected.downvotes = data.downvotes;
      this.selected.score = data.score;
      this.refresh();
    },

    loadFile(event) {
      const file = event.target.files?.[0];
      if (!file) return;
      const reader = new FileReader();
      reader.onload = () => {
        this.form.content = String(reader.result || '');
        if (!this.form.filename) this.form.filename = file.name;
      };
      reader.readAsText(file);
    },

    async submitPost() {
      this.saving = true;
      this.formError = '';
      const url = this.form.post_id
        ? '/projects/cursor-share/posts/update'
        : '/projects/cursor-share/posts/create';
      const body = new FormData();
      body.set('csrf', this.csrf);
      if (this.form.post_id) body.set('post_id', String(this.form.post_id));
      body.set('category', this.form.category);
      body.set('title', this.form.title);
      body.set('description', this.form.description);
      body.set('filename', this.form.filename);
      body.set('version', this.form.version);
      body.set('tags', this.form.tags);
      body.set('content', this.form.content);

      try {
        const res = await fetch(url, { method: 'POST', body });
        const data = await res.json();
        if (!res.ok) {
          this.formError = data.error || 'Save failed';
          return;
        }
        this.tab = 'browse';
        this.selectedId = data.post?.id || null;
        await this.refresh();
      } finally {
        this.saving = false;
      }
    },
  }));
}
