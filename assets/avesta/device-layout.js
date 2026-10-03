// Keep wide data tables scrollable inside the page, including dynamically rendered tables.
(() => {
  function wrapTables() {
    document.querySelectorAll('table').forEach(table => {
      if (table.closest('.device-table-scroll,.table-container,.table-wrap')) return;
      const wrapper = document.createElement('div');
      wrapper.className = 'device-table-scroll';
      wrapper.tabIndex = 0;
      wrapper.setAttribute('role', 'region');
      wrapper.setAttribute('aria-label', 'Data table — scroll horizontally for more columns');
      table.before(wrapper);
      wrapper.append(table);
    });
  }
  wrapTables();
  new MutationObserver(wrapTables).observe(document.body, {childList: true, subtree: true});
})();
