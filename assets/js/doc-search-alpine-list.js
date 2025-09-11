// assets/js/alpine-list.js
// Document list component - shows all documents by default with filtering
// - GET all documents on load
// - Filter documents client-side

(() => {
  const docSearchListFactory = (endpoint) => ({
    endpoint,
    tax: [],

    query: '',
    allDocuments: [],
    filteredResults: [],
    currentPageResults: [],
    loading: false,
    error: false,

    // Pagination state
    pagination: {
      enabled: false,
      currentPage: 0,
      rowsPerPage: 50,
      pageCount: 10,
      showPagination: true,
      totalPages: 0,
      totalItems: 0,
      visiblePages: []
    },

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

    // Read JSON data from data attributes
    initFromData(el) {
      try {
        const raw = el?.dataset?.docSearchTax ?? '[]';
        const parsed = JSON.parse(raw);
        if (Array.isArray(parsed)) this.tax = parsed;
      } catch (e) {
        this.tax = [];
      }

      // Initialize pagination from data attribute
      try {
        const paginationRaw = el?.dataset?.docSearchPagination ?? '{}';
        const paginationData = JSON.parse(paginationRaw);
        if (paginationData && typeof paginationData === 'object') {
          this.pagination.enabled = !!paginationData.enabled;
          this.pagination.rowsPerPage = Math.max(1, parseInt(paginationData.rowsPerPage) || 50);
          this.pagination.pageCount = Math.max(1, parseInt(paginationData.pageCount) || 10);
          this.pagination.showPagination = paginationData.showPagination !== false;
        }
      } catch (e) {
        // Keep defaults
      }
    },

    async loadAllDocuments() {
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

        const body = { search: '', tax: this.tax };
        if (ddNonce) body.nonce = ddNonce;

        const res = await fetch(this.endpoint, {
          method: 'POST',
          headers,
          credentials: 'same-origin',
          cache: 'no-store',
          body: JSON.stringify(body)
        });

        if (!res.ok) {
          this.allDocuments = [];
          this.filteredResults = [];
          this.error = true;
          return;
        }

        const data = await res.json();
        this.allDocuments = Array.isArray(data) ? data : [];
        this.filteredResults = [...this.allDocuments];
        this.error = false;
        
        // Initialize pagination after loading data
        this.updatePagination();
      } catch (err) {
        this.allDocuments = [];
        this.filteredResults = [];
        this.currentPageResults = [];
        this.error = true;
      } finally {
        this.loading = false;
      }
    },

    debouncedFilter() {
      clearTimeout(this._debounceTimer);
      this._debounceTimer = setTimeout(() => this.filterDocuments(), 300);
    },

    filterDocuments() {
      const q = this.query.trim().toLowerCase();
      
      if (q === '') {
        this.filteredResults = [...this.allDocuments];
      } else {
        this.filteredResults = this.allDocuments.filter(item => {
          return item.title.toLowerCase().includes(q);
        });
      }
      
      // Update pagination after filtering
      this.updatePagination();
    },

    // Pagination methods
    updatePagination() {
      if (!this.pagination.enabled) {
        this.currentPageResults = this.filteredResults;
        return;
      }

      this.pagination.totalItems = this.filteredResults.length;
      this.pagination.totalPages = Math.ceil(this.pagination.totalItems / this.pagination.rowsPerPage);
      
      // Reset to first page if current page is out of bounds
      if (this.pagination.currentPage >= this.pagination.totalPages) {
        this.pagination.currentPage = Math.max(0, this.pagination.totalPages - 1);
      }
      
      // Calculate visible page numbers
      this.calculateVisiblePages();
      
      // Get current page results
      this.updateCurrentPageResults();
    },

    calculateVisiblePages() {
      const totalPages = this.pagination.totalPages;
      const currentPage = this.pagination.currentPage;
      const pageCount = this.pagination.pageCount;
      
      if (totalPages <= pageCount) {
        // Show all pages if total is less than or equal to pageCount
        this.pagination.visiblePages = Array.from({length: totalPages}, (_, i) => i + 1);
      } else {
        // Calculate range around current page
        const halfCount = Math.floor(pageCount / 2);
        let startPage = Math.max(1, (currentPage + 1) - halfCount);
        let endPage = Math.min(totalPages, startPage + pageCount - 1);
        
        // Adjust if we're near the end
        if (endPage - startPage + 1 < pageCount) {
          startPage = Math.max(1, endPage - pageCount + 1);
        }
        
        this.pagination.visiblePages = Array.from({length: endPage - startPage + 1}, (_, i) => startPage + i);
      }
    },

    updateCurrentPageResults() {
      if (!this.pagination.enabled) {
        this.currentPageResults = this.filteredResults;
        return;
      }

      const start = this.pagination.currentPage * this.pagination.rowsPerPage;
      const end = start + this.pagination.rowsPerPage;
      this.currentPageResults = this.filteredResults.slice(start, end);
    },

    goToPage(pageIndex) {
      if (pageIndex < 0 || pageIndex >= this.pagination.totalPages) return;
      this.pagination.currentPage = pageIndex;
      this.updateCurrentPageResults();
      this.calculateVisiblePages();
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

  // Handle external pagination shortcodes
  function linkExternalPagination() {
    const paginationElements = document.querySelectorAll('[data-pagination-target]');
    
    paginationElements.forEach(paginationEl => {
      const targetId = paginationEl.getAttribute('data-pagination-target');
      const targetEl = document.getElementById(targetId);
      
      if (!targetEl) return;
      
      // Wait for Alpine to initialize on this element if needed
      if (!targetEl._x_dataStack) {
        // Try again later if Alpine hasn't initialized yet
        setTimeout(() => {
          if (targetEl._x_dataStack) {
            linkSinglePagination(paginationEl, targetEl);
          }
        }, 500);
        return;
      }
      
      linkSinglePagination(paginationEl, targetEl);
    });
  }
  
  function linkSinglePagination(paginationEl, targetEl) {
    // Get the Alpine component data
    const component = targetEl._x_dataStack?.[0];
    if (!component || !component.pagination) return;
    
    // Skip if this pagination element is already linked
    if (paginationEl._linkedToPagination) return;
    paginationEl._linkedToPagination = true;
      
      // Create pagination HTML based on component state
      const updatePaginationHTML = () => {
        if (!component.pagination.enabled || component.pagination.totalPages <= 1) {
          paginationEl.classList.add('doc-search__pagination--hidden');
          return;
        }
        
        // Show pagination by removing hidden class
        paginationEl.classList.remove('doc-search__pagination--hidden');
        const ul = paginationEl.querySelector('.doc-search__pagination-list');
        if (!ul) return;
        
        let html = '';
        
        // Previous button
        const prevDisabled = component.pagination.currentPage === 0;
        html += `<li class="doc-search__pagination-item">
          <button type="button" class="doc-search__pagination-link doc-search__pagination-link--prev" ${prevDisabled ? 'disabled' : ''}>
            <span aria-hidden="true">&laquo;</span>
            <span class="doc-search__pagination-text">Prev</span>
          </button>
        </li>`;
        
        // Page numbers
        component.pagination.visiblePages.forEach(page => {
          const current = page === component.pagination.currentPage + 1;
          html += `<li class="doc-search__pagination-item">
            <button type="button" class="doc-search__pagination-link${current ? ' doc-search__pagination-link--current' : ''}" data-page="${page - 1}" ${current ? 'aria-current="page"' : ''}>
              ${page}
            </button>
          </li>`;
        });
        
        // Next button
        const nextDisabled = component.pagination.currentPage === component.pagination.totalPages - 1;
        html += `<li class="doc-search__pagination-item">
          <button type="button" class="doc-search__pagination-link doc-search__pagination-link--next" ${nextDisabled ? 'disabled' : ''}>
            <span class="doc-search__pagination-text">Next</span>
            <span aria-hidden="true">&raquo;</span>
          </button>
        </li>`;
        
        ul.innerHTML = html;
        
        // Add click handlers
        ul.addEventListener('click', (e) => {
          if (e.target.matches('button[data-page]')) {
            const page = parseInt(e.target.getAttribute('data-page'));
            component.goToPage(page);
          } else if (e.target.closest('.doc-search__pagination-link--prev')) {
            component.goToPage(component.pagination.currentPage - 1);
          } else if (e.target.closest('.doc-search__pagination-link--next')) {
            component.goToPage(component.pagination.currentPage + 1);
          }
        });
      };
      
      // Watch for changes in pagination state
      const observer = new MutationObserver(() => {
        updatePaginationHTML();
      });
      
      observer.observe(targetEl, { 
        attributes: false, 
        childList: true, 
        subtree: true 
      });
      
      // Initial render
      setTimeout(updatePaginationHTML, 100);
  }
  
  // Initialize external pagination when Alpine is ready
  document.addEventListener('alpine:initialized', () => {
    linkExternalPagination();
  });
  
  // Additional initialization after DOM content loaded
  document.addEventListener('DOMContentLoaded', () => {
    setTimeout(linkExternalPagination, 500);
  });
  
  // Fallback for when Alpine isn't available
  setTimeout(() => {
    linkExternalPagination();
  }, 1000);

  // Make it available both ways:
  window.docSearchList = docSearchListFactory; // direct global for x-data="docSearchList(...)"
  
  // Register with Alpine.js only once to prevent conflicts with multiple instances
  if (!window._docSearchRegistered) {
    window._docSearchRegistered = {};
  }
  
  document.addEventListener('alpine:init', () => {
    if (window.Alpine && typeof window.Alpine.data === 'function' && !window._docSearchRegistered.list) {
      window.Alpine.data('docSearchList', docSearchListFactory);
      window._docSearchRegistered.list = true;
    }
  });
})();