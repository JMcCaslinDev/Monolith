export function registerBlog(Alpine) {
  Alpine.data('blogApp', () => ({
    csrf: '',
    canManage: false,
    canAnalytics: false,
    tab: 'drafts',
    posts: [],
    analytics: null,
    saving: false,
    formError: '',
    filters: { q: '' },
    form: {
      post_id: 0,
      title: '',
      slug: '',
      excerpt: '',
      content: '',
      tags: '',
      meta_title: '',
      meta_description: '',
      og_image_url: '',
    },

    init() {
      const cfg = JSON.parse(this.$el.dataset.blogInit || '{}');
      this.csrf = cfg.csrf || document.querySelector('meta[name="csrf-token"]')?.content || '';
      this.canManage = !!cfg.canManage;
      this.canAnalytics = !!cfg.canAnalytics;
      this.refresh();
    },

    setTab(tab) {
      this.tab = tab;
      this.refresh();
    },

    startCreate() {
      this.form = {
        post_id: 0,
        title: '',
        slug: '',
        excerpt: '',
        content: '',
        tags: '',
        meta_title: '',
        meta_description: '',
        og_image_url: '',
      };
      this.formError = '';
      this.tab = 'editor';
    },

    startEdit(post) {
      this.form = {
        post_id: post.id,
        title: post.title,
        slug: post.slug,
        excerpt: post.excerpt || '',
        content: post.content || '',
        tags: (post.tags || []).join(', '),
        meta_title: post.meta_title || '',
        meta_description: post.meta_description || '',
        og_image_url: post.og_image_url || '',
      };
      this.formError = '';
      this.tab = 'editor';
      this.loadPostContent(post.id);
    },

    async loadPostContent(id) {
      const params = new URLSearchParams({ post_id: String(id) });
      try {
        const res = await fetch('/projects/blog/api/state?' + params.toString());
        if (!res.ok) return;
        const data = await res.json();
        if (data.selected?.content) {
          this.form.content = data.selected.content;
        }
      } catch {
        // ponytail: editor still works with list data
      }
    },

    statusFilter() {
      if (this.tab === 'published') return 'published';
      if (this.tab === 'drafts') return 'draft';
      return '';
    },

    async refresh() {
      const params = new URLSearchParams();
      const status = this.statusFilter();
      if (status) params.set('status', status);
      if (this.filters.q) params.set('q', this.filters.q);
      if (this.tab === 'analytics') {
        params.set('analytics', '1');
        params.set('days', '30');
      }

      try {
        const res = await fetch('/projects/blog/api/state?' + params.toString());
        if (!res.ok) return;
        const data = await res.json();
        this.posts = data.posts || [];
        this.canManage = !!data.canManage;
        this.canAnalytics = !!data.canAnalytics;
        if (this.tab === 'analytics') {
          this.analytics = data.analytics;
        }
      } catch {
        // ponytail: user can retry
      }
    },

    maxDailyViews() {
      const days = this.analytics?.daily_views || [];
      if (!days.length) return 1;
      return Math.max(1, ...days.map((d) => d.views));
    },

    barHeight(views) {
      const max = this.maxDailyViews();
      return Math.max(4, Math.round((views / max) * 120));
    },

    async uploadImage(event) {
      const file = event.target.files?.[0];
      if (!file) return;
      const body = new FormData();
      body.set('csrf', this.csrf);
      body.set('image', file);
      try {
        const res = await fetch('/projects/blog/upload', { method: 'POST', body });
        const data = await res.json();
        if (!res.ok) {
          alert(data.error || 'Upload failed');
          return;
        }
        const markdown = `\n![${file.name}](${data.url})\n`;
        this.form.content = (this.form.content || '') + markdown;
        if (!this.form.og_image_url) {
          this.form.og_image_url = data.url;
        }
      } finally {
        event.target.value = '';
      }
    },

    async submitPost() {
      this.saving = true;
      this.formError = '';
      const url = this.form.post_id
        ? '/projects/blog/posts/update'
        : '/projects/blog/posts/create';
      const body = new FormData();
      body.set('csrf', this.csrf);
      if (this.form.post_id) body.set('post_id', String(this.form.post_id));
      body.set('title', this.form.title);
      body.set('slug', this.form.slug);
      body.set('excerpt', this.form.excerpt);
      body.set('content', this.form.content);
      body.set('tags', this.form.tags);
      body.set('meta_title', this.form.meta_title);
      body.set('meta_description', this.form.meta_description);
      body.set('og_image_url', this.form.og_image_url);

      try {
        const res = await fetch(url, { method: 'POST', body });
        const data = await res.json();
        if (!res.ok) {
          this.formError = data.error || 'Save failed';
          return;
        }
        this.form.post_id = data.post?.id || this.form.post_id;
        this.tab = 'drafts';
        await this.refresh();
      } finally {
        this.saving = false;
      }
    },

    async publishPost(id) {
      const body = new URLSearchParams({ csrf: this.csrf, post_id: String(id) });
      const res = await fetch('/projects/blog/posts/publish', { method: 'POST', body });
      const data = await res.json();
      if (!res.ok) {
        alert(data.error || 'Publish failed');
        return;
      }
      this.tab = 'published';
      await this.refresh();
    },

    async unpublishPost(id) {
      const body = new URLSearchParams({ csrf: this.csrf, post_id: String(id) });
      const res = await fetch('/projects/blog/posts/unpublish', { method: 'POST', body });
      const data = await res.json();
      if (!res.ok) {
        alert(data.error || 'Unpublish failed');
        return;
      }
      this.tab = 'drafts';
      await this.refresh();
    },

    async deletePost(id) {
      if (!confirm('Delete this post?')) return;
      const body = new URLSearchParams({ csrf: this.csrf, post_id: String(id) });
      const res = await fetch('/projects/blog/posts/delete', { method: 'POST', body });
      const data = await res.json();
      if (!res.ok) {
        alert(data.error || 'Delete failed');
        return;
      }
      await this.refresh();
    },
  }));
}
