/* Avesta Stage 3.1 shared appearance. Stores ONLY allowlisted UI preferences.
 * External classic script: initialize before first paint; never touches form state.
 * No account/data/API calls. Hosting authentication remains a separate requirement.
 */
(() => {
  'use strict';
  if (window.AvestaAppearance) return;
  const root = document.documentElement;
  const themeKey = 'avesta.stage7b.appearance';
  const motionKey = 'avesta.stage7b.motion';
  const themeValue = value => ['light', 'dark', 'system'].includes(value) ? value : 'system';
  const motionValue = value => value === 'reduce' ? 'reduce' : 'system';
  let storageAvailable = true;
  function read(key) {
    try { return window.localStorage.getItem(key); }
    catch { storageAvailable = false; return null; }
  }
  function write(key, value) {
    try { window.localStorage.setItem(key, value); storageAvailable = true; }
    catch { storageAvailable = false; }
  }
  function media(query) {
    try { return window.matchMedia(query); }
    catch { return {matches: false}; }
  }
  const darkMedia = media('(prefers-color-scheme: dark)');
  const reducedMedia = media('(prefers-reduced-motion: reduce)');
  let preference = themeValue(read(themeKey));
  let motion = motionValue(read(motionKey));
  let transitionTimer;
  const isReduced = () => motion === 'reduce' || reducedMedia.matches;
  const effectiveTheme = () => preference === 'system' ? (darkMedia.matches ? 'dark' : 'light') : preference;
  function updateControls() {
    document.querySelectorAll('[data-appearance-select]').forEach(select => { select.value = preference; select.disabled = false; });
    document.querySelectorAll('[data-motion-toggle]').forEach(input => { input.checked = motion === 'reduce'; input.disabled = false; });
    document.querySelectorAll('[data-appearance-status]').forEach(el => {
      const label = effectiveTheme() === 'dark' ? 'Dark' : 'Light';
      el.textContent = `${label} appearance${preference === 'system' ? ' · following your device' : ''}. ` +
        (storageAvailable ? 'Only appearance preferences are remembered on this browser.' : 'This browser is not saving preferences; this choice applies to the current page.') +
        (reducedMedia.matches ? ' Device requests reduced motion.' : '');
    });
    const meta = document.querySelector('meta[name="theme-color"]');
    if (meta) meta.setAttribute('content', effectiveTheme() === 'dark' ? '#10261f' : '#faf7f1');
  }
  function apply(animate = false) {
    clearTimeout(transitionTimer);
    root.removeAttribute('data-appearance-changing');
    if (animate && !isReduced()) {
      root.dataset.appearanceChanging = 'true';
      transitionTimer = window.setTimeout(() => root.removeAttribute('data-appearance-changing'), 200);
    }
    root.dataset.theme = effectiveTheme();
    root.dataset.themePreference = preference;
    root.dataset.reduceMotion = String(isReduced());
    updateControls();
    document.dispatchEvent(new CustomEvent('avesta:appearance', {detail: {theme: effectiveTheme(), preference, reducedMotion: isReduced()}}));
  }
  function setTheme(value) { preference = themeValue(value); write(themeKey, preference); apply(true); }
  function setMotion(value) { motion = motionValue(value); write(motionKey, motion); apply(false); }
  function close(control, returnFocus) {
    control.open = false;
    if (returnFocus) control.querySelector('summary')?.focus({preventScroll: true});
  }
  function bind() {
    updateControls();
    document.querySelectorAll('[data-appearance-control]').forEach(control => {
      if (control.dataset.appearanceBound) return;
      control.dataset.appearanceBound = 'true';
      control.querySelector('[data-appearance-select]')?.addEventListener('change', e => setTheme(e.target.value));
      control.querySelector('[data-motion-toggle]')?.addEventListener('change', e => setMotion(e.target.checked ? 'reduce' : 'system'));
      control.querySelector('[data-appearance-close]')?.addEventListener('click', () => close(control, true));
    });
  }
  function subscribe(mql, fn) {
    if (mql.addEventListener) mql.addEventListener('change', fn);
    else if (mql.addListener) mql.addListener(fn);
  }
  subscribe(darkMedia, () => { if (preference === 'system') apply(); });
  subscribe(reducedMedia, () => apply());
  window.addEventListener('storage', event => {
    if (event.key !== null && event.key !== themeKey && event.key !== motionKey) return;
    // Only localStorage changes; sessionStorage or unrelated UI revisions are ignored.
    try { if (event.storageArea && event.storageArea !== window.localStorage) return; } catch { return; }
    if (event.key === null) { preference = 'system'; motion = 'system'; }
    else if (event.key === themeKey) preference = themeValue(event.newValue);
    else motion = motionValue(event.newValue);
    apply();
  });
  window.addEventListener('pageshow', event => {
    if (event.persisted && storageAvailable) {
      preference = themeValue(read(themeKey)); motion = motionValue(read(motionKey)); apply();
    }
  });
  document.addEventListener('click', event => {
    document.querySelectorAll('[data-appearance-control][open]').forEach(control => {
      if (event.target instanceof Node && !control.contains(event.target)) close(control, false);
    });
  });
  document.addEventListener('keydown', event => {
    if (event.key === 'Escape') document.querySelectorAll('[data-appearance-control][open]').forEach(control => close(control, control.contains(document.activeElement)));
  });
  document.addEventListener('focusin', event => {
    document.querySelectorAll('[data-appearance-control][open]').forEach(control => {
      if (!control.contains(event.target)) close(control, false);
    });
  });
  window.AvestaAppearance = Object.freeze({
    get theme() { return effectiveTheme(); }, get preference() { return preference; },
    get reducedMotion() { return isReduced(); }, setTheme, setMotion
  });
  apply();
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', bind, {once: true});
  else bind();
})();
