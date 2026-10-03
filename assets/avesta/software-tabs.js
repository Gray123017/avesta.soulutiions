(() => {
  const tabs = [...document.querySelectorAll('.software-tab')];
  if (!tabs.length) return;
  function select(tab, focus = false) {
    for (const item of tabs) {
      const active = item === tab;
      item.setAttribute('aria-selected', String(active));
      item.tabIndex = active ? 0 : -1;
      document.getElementById(item.getAttribute('aria-controls')).hidden = !active;
    }
    document.getElementById('software-prompt').hidden = true;
    if (focus) tab.focus();
    history.replaceState(null, '', '#' + tab.getAttribute('aria-controls'));
  }
  tabs.forEach((tab, index) => {
    tab.addEventListener('click', () => select(tab));
    tab.addEventListener('keydown', event => {
      let target;
      if (event.key === 'ArrowRight' || event.key === 'ArrowDown') target = tabs[(index + 1) % tabs.length];
      if (event.key === 'ArrowLeft' || event.key === 'ArrowUp') target = tabs[(index - 1 + tabs.length) % tabs.length];
      if (event.key === 'Home') target = tabs[0];
      if (event.key === 'End') target = tabs[tabs.length - 1];
      if (target) { event.preventDefault(); select(target, true); }
    });
  });
  function fromHash() {
    const tab = tabs.find(item => '#' + item.getAttribute('aria-controls') === location.hash);
    if (tab) select(tab);
  }
  addEventListener('hashchange', fromHash);
  fromHash();
})();
