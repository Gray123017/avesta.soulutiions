/* Optional Avesta motion enhancements: never blocks primary rendering. */
(() => {
  'use strict';
  try {
    const motion = window.matchMedia('(prefers-reduced-motion: reduce)');
    const fine = window.matchMedia('(hover: hover) and (pointer: fine)');
    if (motion.matches || !fine.matches) return;
    // Purely decorative CSS handles supported card hover effects.
    // Intentionally no fetch, DOM replacement, timers, or event interception.
  } catch (_) {
    // Motion must never affect the application.
  }
})();
