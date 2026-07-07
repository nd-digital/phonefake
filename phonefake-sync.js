/*!
 * PhoneFake live-sync agent — mirrors navigation, scrolling, clicks and text
 * input from one device to the others in PhoneFake's "Compare" mode.
 *
 * Why it exists: the compared screens are separate <iframe>s. When your app is
 * cross-origin (a different localhost port), the browser forbids PhoneFake from
 * reading what you do inside it — so it can't keep the other screens in sync on
 * its own. This tiny agent, running INSIDE your app, reports your actions via
 * postMessage; PhoneFake replays them on the mirror screens.
 *
 * Usage while testing in PhoneFake (dev only) — add one line to your app:
 *     <script src="http://<phonefake-host>/APPLI/phonefake-sync.js"></script>
 * For same-origin apps, PhoneFake injects it automatically (nothing to add).
 *
 * Safe by design: it only talks to the PhoneFake window that framed it, does
 * nothing when the page is opened outside an iframe, and never sends any data
 * anywhere else.
 */
(function () {
  if (window.top === window.self) return;      // not framed → do nothing
  var PARENT = window.parent;
  var suppress = false;                        // while replaying, don't re-report
  var lastHref = location.href;
  // No roles: every framed app reports its own real actions; PhoneFake (the
  // parent) decides which are the "driver" (checks the message source) and
  // replays them on the other screens. Replayed actions are suppressed here so
  // they never echo back.

  function send(msg) {
    msg.__pfsync = 1;
    try { PARENT.postMessage(msg, '*'); } catch (e) {}
  }

  // A layout-independent path to an element, so the SAME logical element is
  // targeted on every screen even though the responsive CSS differs.
  function pathOf(el) {
    if (!el || el.nodeType !== 1) return null;
    if (el.id) { try { return '#' + CSS.escape(el.id); } catch (e) { return '#' + el.id; } }
    var parts = [];
    while (el && el.nodeType === 1 && el !== document.documentElement) {
      var parent = el.parentNode;
      if (!parent) break;
      var tag = el.tagName.toLowerCase();
      var same = [];
      for (var i = 0; i < parent.children.length; i++) {
        if (parent.children[i].tagName === el.tagName) same.push(parent.children[i]);
      }
      parts.unshift(tag + ':nth-of-type(' + (same.indexOf(el) + 1) + ')');
      if (parent === document.body) { parts.unshift('body'); break; }
      el = parent;
    }
    return parts.length ? parts.join('>') : null;
  }
  function resolve(path) {
    if (!path) return null;
    try { return document.querySelector(path); } catch (e) { return null; }
  }

  function report(kind, data) {
    if (!suppress) send({ t: 'event', kind: kind, data: data });
  }

  // --- navigation (hard loads + SPA history + hash) ---
  function reportNav() {
    if (location.href !== lastHref) { lastHref = location.href; report('nav', { href: location.href }); }
  }
  ['pushState', 'replaceState'].forEach(function (m) {
    var orig = history[m];
    if (typeof orig !== 'function') return;
    history[m] = function () { var r = orig.apply(this, arguments); reportNav(); return r; };
  });
  window.addEventListener('popstate', reportNav);
  window.addEventListener('hashchange', reportNav);

  // --- scroll (throttled) ---
  var pending = false;
  window.addEventListener('scroll', function () {
    if (pending) return; pending = true;
    requestAnimationFrame(function () {
      pending = false;
      report('scroll', { x: window.scrollX, y: window.scrollY });
    });
  }, { passive: true, capture: true });

  // --- clicks (drives SPA routers + plain links/buttons) ---
  document.addEventListener('click', function (e) {
    var p = pathOf(e.target);
    if (p) report('click', { sel: p });
  }, true);

  // --- text / checkbox / radio input ---
  document.addEventListener('input', function (e) {
    var t = e.target;
    if (!t || !('value' in t)) return;
    var p = pathOf(t);
    if (p) report('input', { sel: p, value: t.value, checked: !!t.checked });
  }, true);

  // --- replay an incoming action (when PhoneFake designates us a mirror) ---
  function apply(kind, data) {
    suppress = true;
    try {
      if (kind === 'nav') {
        if (data.href && data.href !== location.href) { lastHref = data.href; location.replace(data.href); return; }
      } else if (kind === 'scroll') {
        window.scrollTo(data.x, data.y);
      } else if (kind === 'click') {
        var el = resolve(data.sel);
        if (el) el.click();
      } else if (kind === 'input') {
        var el2 = resolve(data.sel);
        if (el2) {
          if (el2.type === 'checkbox' || el2.type === 'radio') el2.checked = data.checked;
          else el2.value = data.value;
          el2.dispatchEvent(new Event('input', { bubbles: true }));
          el2.dispatchEvent(new Event('change', { bubbles: true }));
        }
      }
    } catch (e) {}
    // release on the next tick so replayed events don't echo back
    setTimeout(function () { suppress = false; }, 0);
  }

  window.addEventListener('message', function (e) {
    var d = e.data;
    if (!d || !d.__pfsync) return;
    if (d.t === 'apply') { apply(d.kind, d.data); }
    else if (d.t === 'gethref') { send({ t: 'href', href: location.href }); }
    else if (d.t === 'navto') { if (d.href && d.href !== location.href) { lastHref = d.href; location.replace(d.href); } }
  });

  send({ t: 'ready', href: location.href });
})();
