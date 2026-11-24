/*
 * The website bar: a small button in the bottom-right corner of the website, for people
 * signed in to the control panel. Tapped, it opens to "Edit this page", the control
 * panel, Visits, and "Hide this bar". Dragged, it moves out of the way, and stays
 * where it was put in this browser ("slim-killer-bar-place" in localStorage).
 * Flicked, it flies off the screen: the same as "Hide this bar".
 *
 * Loaded by visits.js, and only in a browser the control panel marked as signed in
 * ("slim-killer-editor" in localStorage, see cp.js). Visitors never download it.
 *
 * The marker is not trusted: first this asks /cp/me, with the panel's own cookie,
 * whether the person is still signed in. If not, the marker goes and nothing shows.
 * Everything behind the links still needs sign-in.
 *
 * Drawn in a shadow root, with its own stylesheet, so the website's styles can't
 * reach it and it can't reach theirs. No inline styles or scripts, so a site's
 * Content-Security-Policy that allows its own files is enough.
 *
 * Plain old JavaScript on purpose: it must run on an iPhone 7 (Safari 15).
 */
(function () {
  'use strict';

  var KEY = 'slim-killer-editor';
  var PLACE = 'slim-killer-bar-place';
  var EDGE = 16;      // the closest it goes to the edge of the screen
  var NUDGE = 6;      // how far a finger moves before a tap becomes a drag
  var FLICK = 1.2;    // pixels a millisecond: let go this fast, and it's a flick
  var BASE = '/cp';

  function marker() {
    try {
      return JSON.parse(window.localStorage.getItem(KEY) || 'null');
    } catch (e) {
      return null;
    }
  }

  function remember(value) {
    try {
      if (value === null) {
        window.localStorage.removeItem(KEY);
      } else {
        window.localStorage.setItem(KEY, JSON.stringify(value));
      }
    } catch (e) {
      // Private browsing: nothing to keep.
    }
  }

  function element(tag, attributes, text) {
    var made = document.createElement(tag);
    Object.keys(attributes || {}).forEach(function (name) {
      made.setAttribute(name, attributes[name]);
    });
    if (text) {
      made.textContent = text;
    }
    return made;
  }

  function draw(me) {
    var host = element('div', { id: 'slim-killer-website-bar' });
    var root = host.attachShadow({ mode: 'open' });
    var sheet = element('link', { rel: 'stylesheet', href: BASE + '/assets/editor-bar.css' });
    var bar = element('div', { 'class': 'bar' });
    bar.hidden = true; // until the stylesheet is in, so nothing flashes unstyled
    sheet.addEventListener('load', function () { bar.hidden = false; });
    window.setTimeout(function () { bar.hidden = false; }, 2000); // in case 'load' never comes

    var toggle = element('button', {
      type: 'button',
      'class': 'toggle',
      'aria-expanded': 'false',
      'aria-controls': 'slim-killer-menu',
      'aria-label': 'Slim Killer: edit this website'
    });
    toggle.appendChild(element('img', { src: BASE + '/assets/logo.svg', alt: '' }));

    var menu = element('div', { id: 'slim-killer-menu', 'class': 'menu' });
    menu.hidden = true;

    var who = element('p', { 'class': 'who' }, 'Signed in as ');
    who.appendChild(element('strong', {}, me.name || 'you'));
    menu.appendChild(who);
    menu.appendChild(element('p', { 'class': 'note' }, 'Your visits aren\'t counted.'));
    if (me.env === 'staging') {
      menu.appendChild(element('p', { 'class': 'note' }, 'Staging: a test copy, hidden from search engines.'));
    }

    if (me.edit) {
      menu.appendChild(element('a', { href: me.edit, 'class': 'link primary' }, 'Edit this page'));
    } else {
      menu.appendChild(element('p', { 'class': 'note' }, 'This page isn\'t edited in the control panel.'));
    }
    menu.appendChild(element('a', { href: me.dashboard || BASE + '/', 'class': 'link' }, 'Control panel'));
    menu.appendChild(element('a', { href: me.visits || BASE + '/visits', 'class': 'link' }, 'Visits'));

    var hide = element('button', { type: 'button', 'class': 'link hide' }, 'Hide this bar');
    menu.appendChild(hide);
    var later = element('p', { 'class': 'small' }, 'Drag the button to move it, or flick it away to hide it. It comes back when you next open the control panel. To keep it away, see ');
    later.appendChild(element('a', { href: me.preferences || BASE + '/preferences' }, 'Preferences'));
    later.appendChild(document.createTextNode('.'));
    menu.appendChild(later);

    function open(yes) {
      menu.hidden = !yes;
      toggle.setAttribute('aria-expanded', yes ? 'true' : 'false');
    }

    // Where the button sits: distances from the right and bottom of the screen, kept
    // inside it however the screen turns or shrinks.
    var place = { right: EDGE, bottom: EDGE };
    try {
      var kept = JSON.parse(window.localStorage.getItem(PLACE) || 'null');
      if (kept && typeof kept.right === 'number' && typeof kept.bottom === 'number') {
        place = kept;
      }
    } catch (e) {
      // Nothing kept: the corner.
    }

    // The menu opens towards the middle of the screen, so it never runs off an edge:
    // in the top half the bar hangs from its top, in the left half from its left.
    function settle() {
      var size = toggle.offsetWidth || 52;
      var width = window.innerWidth;
      var height = window.innerHeight;
      place.right = Math.max(EDGE, Math.min(place.right, width - size - EDGE));
      place.bottom = Math.max(EDGE, Math.min(place.bottom, height - size - EDGE));
      var onLeft = place.right > width / 2;
      var nearTop = place.bottom > height / 2;
      bar.classList.toggle('on-left', onLeft);
      bar.classList.toggle('near-top', nearTop);
      bar.style.right = onLeft ? 'auto' : place.right + 'px';
      bar.style.left = onLeft ? (width - place.right - size) + 'px' : 'auto';
      bar.style.bottom = nearTop ? 'auto' : place.bottom + 'px';
      bar.style.top = nearTop ? (height - place.bottom - size) + 'px' : 'auto';
      // The menu lines up with the button's outer edge; it may be no wider than the
      // room between there and the far edge of the screen.
      var room = onLeft ? width - (width - place.right - size) - EDGE : width - place.right - EDGE;
      menu.style.maxWidth = Math.max(160, room) + 'px';
    }

    var drag = null;
    var dragged = false;
    toggle.addEventListener('pointerdown', function (event) {
      if (event.button !== 0) {
        return;
      }
      event.preventDefault(); // no text selection or image dragging on a desktop
      drag = { x: event.clientX, y: event.clientY, right: place.right, bottom: place.bottom, moving: false, trail: [] };
    });
    // Followed on the whole window, so a quick drag can't outrun the button.
    window.addEventListener('pointermove', function (event) {
      if (!drag) {
        return;
      }
      var dx = event.clientX - drag.x;
      var dy = event.clientY - drag.y;
      if (!drag.moving && Math.abs(dx) + Math.abs(dy) < NUDGE) {
        return;
      }
      if (!drag.moving) {
        drag.moving = true;
        open(false);
        bar.classList.add('dragging');
      }
      place.right = drag.right - dx;
      place.bottom = drag.bottom - dy;
      settle();
      drag.trail.push({ x: event.clientX, y: event.clientY, t: Date.now() });
      if (drag.trail.length > 5) {
        drag.trail.shift();
      }
    });

    // How fast the finger was going when it let go, from its last few moves.
    function speed(trail) {
      if (trail.length < 2) {
        return null;
      }
      var first = trail[0];
      var last = trail[trail.length - 1];
      var ms = Math.max(1, last.t - first.t);
      if (Date.now() - last.t > 80) {
        return null; // it stopped before letting go: a drag, not a flick
      }
      return { x: (last.x - first.x) / ms, y: (last.y - first.y) / ms };
    }

    function flick(velocity, from) {
      // It comes back where it was before the flick, next time.
      place = from;
      try {
        window.localStorage.setItem(PLACE, JSON.stringify(place));
      } catch (e) {
        // Private browsing.
      }
      var reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
      if (reduced) {
        close();
        return;
      }
      var far = Math.max(window.innerWidth, window.innerHeight);
      var length = Math.sqrt(velocity.x * velocity.x + velocity.y * velocity.y) || 1;
      bar.classList.add('flung');
      bar.style.transform = 'translate(' + Math.round(velocity.x / length * far) + 'px, ' + Math.round(velocity.y / length * far) + 'px)';
      window.setTimeout(close, 300);
    }
    function letGo() {
      var velocity = drag && drag.moving ? speed(drag.trail) : null;
      if (velocity && Math.sqrt(velocity.x * velocity.x + velocity.y * velocity.y) > FLICK) {
        var from = { right: drag.right, bottom: drag.bottom };
        drag = null;
        dragged = true;
        window.setTimeout(function () { dragged = false; }, 0);
        flick(velocity, from);
        return;
      }
      if (drag && drag.moving) {
        // The click that follows a drag doesn't open the menu; if no click comes (the
        // finger left the button), the next tap works as usual.
        dragged = true;
        window.setTimeout(function () { dragged = false; }, 0);
        bar.classList.remove('dragging');
        try {
          window.localStorage.setItem(PLACE, JSON.stringify(place));
        } catch (e) {
          // Private browsing: it stays put for this page only.
        }
      }
      drag = null;
    }
    window.addEventListener('pointerup', letGo);
    window.addEventListener('pointercancel', letGo);
    window.addEventListener('resize', settle);

    toggle.addEventListener('click', function () {
      if (dragged) {
        dragged = false;
        return;
      }
      open(menu.hidden);
    });
    function close() {
      var saved = marker() || { bar: 'show' };
      saved.closed = true;
      remember(saved);
      if (host.parentNode) {
        host.parentNode.removeChild(host);
      }
    }
    hide.addEventListener('click', close);
    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape' && !menu.hidden) {
        open(false);
        toggle.focus();
      }
    });
    document.addEventListener('click', function (event) {
      if (!menu.hidden && event.target !== host) {
        open(false);
      }
    });

    bar.appendChild(menu);
    bar.appendChild(toggle);
    root.appendChild(sheet);
    root.appendChild(bar);
    document.body.appendChild(host);
    settle();
  }

  function start() {
    var url = BASE + '/me?path=' + encodeURIComponent(window.location.pathname);
    window.fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
      .then(function (response) {
        return response.ok ? response.json() : null;
      })
      .then(function (me) {
        if (!me) {
          return; // The panel didn't answer: show nothing, change nothing.
        }
        if (!me.signedIn) {
          remember(null); // Signed out, or the session ended: count this browser again.
          return;
        }
        var saved = marker() || {};
        if (me.bar === 'hide') {
          remember({ bar: 'hide', closed: !!saved.closed });
          return;
        }
        draw(me);
      })
      .catch(function () {
        // Never trouble the page.
      });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', start);
  } else {
    start();
  }
})();
