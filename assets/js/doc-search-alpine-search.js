// assets/js/alpine-search.js
// - POST to REST with DocSearch nonces
// - Taxonomy slugs are read from data attribute via initFromData($el)
// - Email gate + logging (includes post title)

(() => {
  const docSearchSearchFactory = (endpoint) => ({
    endpoint,
    tax: [],

    query: '',
    results: [],
    loading: false,
    error: false,

    // Download workflow state
    requireEmail: !!(window.DocSearchOptions && DocSearchOptions.requireEmail),
    requireName: !!(window.DocSearchOptions && DocSearchOptions.requireName),
    requirePhone: !!(window.DocSearchOptions && DocSearchOptions.requirePhone),
    logEndpoint: (window.DocSearchOptions && DocSearchOptions.logEndpoint) ? String(DocSearchOptions.logEndpoint) : '',
    email: '',
    name: '',
    phone: '',
    formValid: false,
    emailInvalid: false,
    nameInvalid: false,
    phoneInvalid: false,
    emailMessage: '',
    nameMessage: '',
    phoneMessage: '',
    downloading: false,
    pendingItem: null,
    pendingFileName: '',

    _debounceTimer: null,
    _abortController: null,

    // Read JSON array from data-dd-tax attribute
    initFromData(el) {
      try {
        const raw = el?.dataset?.docSearchTax ?? '[]';
        const parsed = JSON.parse(raw);
        if (Array.isArray(parsed)) this.tax = parsed;
      } catch (e) {
        this.tax = [];
      }
    },

    debouncedSearch() {
      clearTimeout(this._debounceTimer);
      this._debounceTimer = setTimeout(() => this.search(), 500);
    },

    async search() {
      const q = this.query.trim();

      if (q.length < 3) {
        this.results = [];
        this.loading = false;
        this.error = false;
        if (this._abortController) {
          this._abortController.abort();
          this._abortController = null;
        }
        return;
      }

      if (this._abortController) {
        this._abortController.abort();
      }
      this._abortController = new AbortController();

      this.loading = true;
      this.error = false;

      try {
        const headers = {
          'Accept': 'application/json',
          'Content-Type': 'application/json'
        };

        const wpRestNonce = (window.DocSearchRest && DocSearchRest.wpRestNonce) ? String(DocSearchRest.wpRestNonce) : '';
        const ddNonce     = (window.DocSearchRest && DocSearchRest.ddNonce) ? String(DocSearchRest.ddNonce) : '';

        if (wpRestNonce) headers['X-WP-Nonce'] = wpRestNonce;
        if (ddNonce)     headers['X-DD-Nonce'] = ddNonce;

        const body = { search: q, tax: this.tax };
        if (ddNonce) body.nonce = ddNonce;

        const res = await fetch(this.endpoint, {
          method: 'POST',
          headers,
          credentials: 'same-origin',
          cache: 'no-store',
          body: JSON.stringify(body),
          signal: this._abortController.signal
        });

        if (!res.ok) {
          this.results = [];
          this.error = true;
          return;
        }

        const data = await res.json();
        this.results = Array.isArray(data) ? data : [];
        this.error = false;
      } catch (err) {
        if (!err || err.name !== 'AbortError') {
          this.results = [];
          this.error = true;
        }
      } finally {
        this.loading = false;
      }
    },

    onItemClick(item) {
      const filename = this.fileName(item.url, item.title, item.ext);
      this.pendingItem = item;
      this.pendingFileName = filename;

      if (!this.requireEmail && !this.requireName && !this.requirePhone) {
        this.doDownload(item.url, filename);
        return;
      }

      this.email = '';
      this.name = '';
      this.phone = '';
      this.formValid = false;
      this.emailInvalid = false;
      this.nameInvalid = false;
      this.phoneInvalid = false;
      this.emailMessage = '';
      this.nameMessage = '';
      this.phoneMessage = '';
      if (this.$refs && this.$refs.dlg && typeof this.$refs.dlg.showModal === 'function') {
        this.$refs.dlg.showModal();
      }
    },

    validateForm() {
      let valid = true;

      // Check email if required
      if (this.requireEmail) {
        const e = (this.email || '').trim();
        if (e.length === 0) {
          this.emailInvalid = true;
          this.emailMessage = 'Email address is required';
          valid = false;
        } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(e)) {
          this.emailInvalid = true;
          this.emailMessage = 'Please enter a valid email address';
          valid = false;
        } else {
          this.emailInvalid = false;
          this.emailMessage = '';
        }
      }

      // Check name if required
      if (this.requireName) {
        const n = (this.name || '').trim();
        if (n.length === 0) {
          this.nameInvalid = true;
          this.nameMessage = 'Name is required';
          valid = false;
        } else if (n.length < 2) {
          this.nameInvalid = true;
          this.nameMessage = 'Name must be at least 2 characters';
          valid = false;
        } else {
          this.nameInvalid = false;
          this.nameMessage = '';
        }
      }

      // Check phone if required
      if (this.requirePhone) {
        const p = (this.phone || '').trim();
        if (p.length === 0) {
          this.phoneInvalid = true;
          this.phoneMessage = 'Phone number is required';
          valid = false;
        } else if (p.length < 7) {
          this.phoneInvalid = true;
          this.phoneMessage = 'Please enter a valid phone number';
          valid = false;
        } else {
          this.phoneInvalid = false;
          this.phoneMessage = '';
        }
      }

      this.formValid = valid;
    },

    async submitEmailAndDownload() {
      if (!this.pendingItem) return;
      const item = this.pendingItem;
      const filename = this.pendingFileName || this.fileName(item.url, item.title, item.ext);

      this.downloading = true;
      try {
        if (this.logEndpoint && this.formValid) {
          const headers = {
            'Accept': 'application/json',
            'Content-Type': 'application/json'
          };
          const wpRestNonce = (window.DocSearchRest && DocSearchRest.wpRestNonce) ? String(DocSearchRest.wpRestNonce) : '';
          const ddNonce     = (window.DocSearchRest && DocSearchRest.ddNonce) ? String(DocSearchRest.ddNonce) : '';
          if (wpRestNonce) headers['X-WP-Nonce'] = wpRestNonce;
          if (ddNonce)     headers['X-DD-Nonce'] = ddNonce;

          const payload = {
            email: String(this.email || ''),
            name: String(this.name || ''),
            phone: String(this.phone || ''),
            filename,
            title: String(item.title || ''),
            url: String(item.url || '')
          };
          if (ddNonce) payload.nonce = ddNonce;

          await fetch(this.logEndpoint, {
            method: 'POST',
            headers,
            credentials: 'same-origin',
            cache: 'no-store',
            body: JSON.stringify(payload)
          });
        }
      } catch (e) {}

      try { if (this.$refs && this.$refs.dlg) this.$refs.dlg.close(); } catch (e) {}
      await this.doDownload(item.url, filename);
      this.downloading = false;
    },

    async doDownload(url, filename) {
      try {
        const res = await fetch(url, { credentials: 'same-origin', cache: 'no-store' });
        if (!res.ok) return;
        const blob = await res.blob();
        const href = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = href;
        a.download = filename || 'download';
        document.body.appendChild(a);
        a.click();
        a.remove();
        setTimeout(() => URL.revokeObjectURL(href), 1000);
      } catch (e) {
        try { window.location.href = url; } catch (e2) {}
      }
    },

    fileName(url, title, ext) {
      try {
        const u = new URL(url, window.location.origin);
        let base = (u.pathname.split('/').pop() || '').split('?')[0];
        if (base && /\.[a-z0-9]+$/i.test(base)) return base;
        let name = (title || 'document').toString().trim().toLowerCase()
          .replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
        if (!name) name = 'document';
        const e = (ext || '').toLowerCase();
        if (e && !name.endsWith('.' + e)) name += '.' + e;
        return name;
      } catch {
        let name = (title || 'document').toString().trim().toLowerCase()
          .replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
        if (!name) name = 'document';
        const e = (ext || '').toLowerCase();
        if (e && !name.endsWith('.' + e)) name += '.' + e;
        return name;
      }
    },

    iconFor(ext) {
      const map = (window.DocSearchIconsInline || {});
      const key = (ext || 'file').toLowerCase();
      return map[key] || map.file || '';
    }
  });

  // Make it available both ways:
  window.docSearchSearch = docSearchSearchFactory; // direct global for x-data="ddSearch(...)"
  document.addEventListener('alpine:init', () => {
    if (window.Alpine && typeof window.Alpine.data === 'function') {
      window.Alpine.data('docSearchSearch', docSearchSearchFactory);
    }
  });
})();
