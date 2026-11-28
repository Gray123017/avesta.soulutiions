/* Avesta — the pointer tilt on the artwork.
 *
 * The picture leans very slightly towards the pointer. Six degrees at the
 * corners; enough to feel responsive, not enough to read as a gimmick on a
 * page about borrowing money.
 *
 * Three things keep it from being a nuisance:
 *
 *   It only runs for a fine pointer. A thumb on a phone never triggers it,
 *   and neither does a tap, so the picture cannot be left stuck at an angle.
 *
 *   It does nothing when the visitor has asked for reduced motion, and it
 *   stops if they change that preference while the page is open.
 *
 *   It writes one transform on pointermove and nothing else — no layout is
 *   read, so there is no reflow, and the compositor does the work.
 *
 * The markup renders correctly with this file absent; the tilt is the only
 * thing that is lost.
 */
(function () {
  'use strict';

  var fine = window.matchMedia ? window.matchMedia('(hover: hover) and (pointer: fine)') : null;
  var still = window.matchMedia ? window.matchMedia('(prefers-reduced-motion: reduce)') : null;

  function allowed() {
    return !!(fine && fine.matches) && !(still && still.matches);
  }

  function reset(el) {
    el.style.transform = 'perspective(900px) rotateX(0deg) rotateY(0deg)';
  }

  function bind(el) {
    el.addEventListener('pointermove', function (e) {
      if (!allowed() || e.pointerType !== 'mouse') return;
      var box = el.getBoundingClientRect();
      if (!box.width || !box.height) return;
      var x = (e.clientX - box.left) / box.width - 0.5;
      var y = (e.clientY - box.top) / box.height - 0.5;
      el.style.transform = 'perspective(900px) rotateY(' + (x * 6).toFixed(2)
                         + 'deg) rotateX(' + (-y * 6).toFixed(2) + 'deg)';
    });
    el.addEventListener('pointerleave', function () { reset(el); });
    el.addEventListener('pointercancel', function () { reset(el); });
  }

  function scan() {
    var nodes = document.querySelectorAll('.art-tilt');
    for (var i = 0; i < nodes.length; i++) {
      if (nodes[i].dataset.avTilt === 'on') continue;
      nodes[i].dataset.avTilt = 'on';
      bind(nodes[i]);
    }
  }

  function start() {
    scan();
    /* The marketing pages are rendered by app.js after navigation, so new
       figures appear without a page load. Watch for them rather than
       binding once and wondering why the second page is inert. */
    if (window.MutationObserver) {
      new MutationObserver(scan).observe(document.body, {childList: true, subtree: true});
    }
    // If they turn reduced motion on mid-visit, put everything straight.
    if (still && still.addEventListener) {
      still.addEventListener('change', function () {
        if (still.matches) {
          var nodes = document.querySelectorAll('.art-tilt');
          for (var i = 0; i < nodes.length; i++) reset(nodes[i]);
        }
      });
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', start);
  } else {
    start();
  }
})();
