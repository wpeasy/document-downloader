(() => {
  const onReady = (fn) => {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', fn);
    } else {
      fn();
    }
  };

  // Short-lived flag so PHP routes to /uploads/documents during our media session
  const setRouteCookie = (on) => {
    const maxAge = on ? 600 : 0; // 10 minutes
    document.cookie = `das_route_uploads=${on ? '1' : ''}; Max-Age=${maxAge}; Path=/; SameSite=Lax`;
  };

  onReady(() => {
    const box = document.getElementById('das-pdf-meta-box');
    if (!box || !window.wp || !wp.media) return;

    const selectBtn = document.getElementById('das_select_pdf');
    const removeBtn = document.getElementById('das_remove_pdf');
    const input     = document.getElementById('das_pdf_id');
    const preview   = document.getElementById('das_pdf_preview');

    const allowedExt = (window.DASAdmin && Array.isArray(DASAdmin.allowedExt)) ? DASAdmin.allowedExt : ['pdf','doc','docx','xls','xlsx'];

    let frame = null;

    const isAllowed = (att) => {
      const url = (att && (att.url || att.link)) || '';
      const filename = (att && (att.filename || '')) || '';
      const fromUrl = url.split('?')[0].split('/').pop() || '';
      const base = (filename || fromUrl).toLowerCase();
      const ext = base.includes('.') ? base.split('.').pop() : '';
      return allowedExt.includes(ext);
    };

    const openFrame = () => {
      if (frame) { frame.open(); return; }

      frame = wp.media({
        title: (window.DASAdmin && DASAdmin.title) || 'Select file',
        button: { text: (window.DASAdmin && DASAdmin.button) || 'Use this file' },
        // Do not set a restrictive library.type; allow office/PDF files to show
        multiple: false
      });

      frame.on('open', () => setRouteCookie(true));
      frame.on('close', () => setRouteCookie(false));

      frame.on('select', () => {
        const selection = frame.state().get('selection').first();
        if (!selection) return;
        const att = selection.toJSON();

        if (!isAllowed(att)) {
          alert('Please select a PDF, DOC, DOCX, XLS, or XLSX file.');
          return;
        }

        if (att && att.id) {
          input.value = String(att.id);
          if (removeBtn) removeBtn.style.display = '';
          if (preview) preview.innerHTML = `<a href="${att.url}" target="_blank" rel="noopener">${att.url}</a>`;
        }
      });

      frame.open();
    };

    const clearSelection = (e) => {
      if (e) e.preventDefault();
      input.value = '';
      if (removeBtn) removeBtn.style.display = 'none';
      if (preview) preview.innerHTML = '<em>No file selected.</em>';
    };

    if (selectBtn) selectBtn.addEventListener('click', (e) => { e.preventDefault(); openFrame(); });
    if (removeBtn) removeBtn.addEventListener('click', clearSelection);
  });
})();
