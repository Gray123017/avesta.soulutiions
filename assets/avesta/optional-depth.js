/* Optional, staging-only: active solely on elements explicitly marked enabled. */
(() => {
  'use strict';
  if (!window.matchMedia || !window.matchMedia('(hover: hover) and (pointer: fine) and (prefers-reduced-motion: no-preference)').matches) return;
  const selector = '.avesta-depth[data-avesta-depth="enabled"]';
  let active = null;
  function clear(card) {
    if (!card) return;
    card.style.removeProperty('--avesta-rx');
    card.style.removeProperty('--avesta-ry');
  }
  document.addEventListener('pointermove', event => {
    const card = event.target instanceof Element ? event.target.closest(selector) : null;
    if (card !== active) clear(active);
    active = card;
    if (!card) return;
    const bounds = card.getBoundingClientRect();
    if (!bounds.width || !bounds.height) return;
    const x = (event.clientX - bounds.left) / bounds.width - 0.5;
    const y = (event.clientY - bounds.top) / bounds.height - 0.5;
    card.style.setProperty('--avesta-ry', (x * 6).toFixed(2) + 'deg');
    card.style.setProperty('--avesta-rx', (-y * 6).toFixed(2) + 'deg');
  }, { passive: true });
  document.addEventListener('pointerout', event => {
    if (active && !active.contains(event.relatedTarget)) { clear(active); active = null; }
  });
  window.addEventListener('blur', () => { clear(active); active = null; });
})();
