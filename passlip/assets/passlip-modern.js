(function () {
  function normalizeStatus(text) {
    return text.trim().toLowerCase();
  }

  function statusClass(text) {
    const value = normalizeStatus(text);
    if (value === 'pending' || value.includes('waiting')) return 'pending';
    if (value === 'approved') return 'approved';
    if (value === 'done') return 'done';
    if (value === 'declined') return 'declined';
    if (value.includes('scan')) return 'scan';
    if (value.includes('partial')) return 'partial';
    if (value === 'present') return 'present';
    return '';
  }

  function enhanceStatusCells() {
    document.querySelectorAll('td').forEach(function (cell) {
      if (cell.querySelector('.passlip-status')) return;
      const value = cell.textContent.trim();
      const klass = statusClass(value);
      if (!klass) return;
      cell.innerHTML = '<span class="passlip-status ' + klass + '">' + value + '</span>';
    });
  }

  function markCurrentNav() {
    const current = location.pathname.split('/').pop();
    document.querySelectorAll('.navbar a[href], .nav a[href]').forEach(function (link) {
      const href = link.getAttribute('href') || '';
      if (href.split('?')[0] === current) {
        link.classList.add('active');
      }
    });
  }

  function improveTables() {
    document.querySelectorAll('table').forEach(function (table) {
      const parent = table.parentElement;
      if (!parent || parent.classList.contains('table-responsive') || parent.classList.contains('table-container')) return;
      if (table.closest('.modal')) return;
      const wrap = document.createElement('div');
      wrap.className = 'table-responsive passlip-auto-table';
      parent.insertBefore(wrap, table);
      wrap.appendChild(table);
    });
  }

  function boot() {
    markCurrentNav();
    improveTables();
    enhanceStatusCells();
  }

  document.addEventListener('DOMContentLoaded', boot);

  const observer = new MutationObserver(function () {
    enhanceStatusCells();
  });

  observer.observe(document.documentElement, { childList: true, subtree: true });
})();
