(() => {
  const tabs = Array.from(document.querySelectorAll('[data-product-tab]'));
  const panels = Array.from(document.querySelectorAll('[data-product-panel]'));

  const activate = (id, focus = false) => {
    tabs.forEach((tab) => {
      const selected = tab.dataset.productTab === id;
      tab.classList.toggle('active', selected);
      tab.setAttribute('aria-selected', selected ? 'true' : 'false');
      tab.tabIndex = selected ? 0 : -1;
      if (selected && focus) tab.focus();
    });

    panels.forEach((panel) => {
      const selected = panel.dataset.productPanel === id;
      panel.hidden = !selected;
      panel.classList.toggle('active', selected);
      panel.classList.toggle('featured', selected && id === 'sentinel');
    });
  };

  tabs.forEach((tab, index) => {
    tab.addEventListener('click', () => activate(tab.dataset.productTab));
    tab.addEventListener('keydown', (event) => {
      if (!['ArrowRight', 'ArrowLeft', 'Home', 'End'].includes(event.key)) return;
      event.preventDefault();
      let next = index;
      if (event.key === 'ArrowRight') next = (index + 1) % tabs.length;
      if (event.key === 'ArrowLeft') next = (index - 1 + tabs.length) % tabs.length;
      if (event.key === 'Home') next = 0;
      if (event.key === 'End') next = tabs.length - 1;
      activate(tabs[next].dataset.productTab, true);
    });
  });
})();