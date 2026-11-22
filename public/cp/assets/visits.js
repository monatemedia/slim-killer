/*
 * Slim Killer's visit counter, for the website (not the control panel).
 *
 * Add it to every page, before </body>:
 *
 *     <script src="/cp/assets/visits.js" defer></script>
 *
 * It counts pages opened, taps on WhatsApp, email and phone links (and on anything
 * marked data-count="Name"), and forms sent (a "form-sent" event, as the contact form
 * fires). Each event goes to /cp/visit.php. It sets no cookies and stores nothing in
 * the browser.
 *
 * It counts nothing when the browser asks not to be tracked (Do Not Track or Global
 * Privacy Control), or when the visitor switched "Site statistics" off in the site's
 * privacy settings: { "choices": { "statistics": false } } in localStorage under
 * "privacy-choices".
 *
 * Plain old JavaScript on purpose: it must run on every phone, an iPhone 7 included.
 */
(function () {
  'use strict';

  var nav = window.navigator;
  if (nav.doNotTrack === '1' || window.doNotTrack === '1' || nav.globalPrivacyControl === true) {
    return;
  }

  function switchedOff() {
    try {
      var saved = JSON.parse(window.localStorage.getItem('privacy-choices') || 'null');
      return !!(saved && saved.choices && saved.choices.statistics === false);
    } catch (e) {
      return false;
    }
  }

  if (switchedOff()) {
    return;
  }

  var script = document.currentScript;
  var endpoint = (script && script.getAttribute('data-endpoint')) || '/cp/visit.php';
  var params = new URLSearchParams(window.location.search);

  function send(kind, label) {
    if (switchedOff()) {
      return;
    }
    var body = JSON.stringify({
      k: kind,
      p: window.location.pathname,
      r: kind === 'page' ? document.referrer : '',
      s: params.get('utm_source') || '',
      m: params.get('utm_medium') || '',
      c: params.get('utm_campaign') || '',
      g: params.has('gclid') ? 1 : 0,
      l: label || ''
    });
    try {
      if (nav.sendBeacon && nav.sendBeacon(endpoint, body)) {
        return;
      }
      window.fetch(endpoint, { method: 'POST', body: body, keepalive: true, credentials: 'omit' });
    } catch (e) {
      // Never trouble the visitor.
    }
  }

  function text(element) {
    return (element.textContent || '').replace(/\s+/g, ' ').trim().slice(0, 50);
  }

  send('page');

  document.addEventListener('click', function (event) {
    var target = event.target;
    var element = target && target.closest ? target.closest('a, button, [data-count]') : null;
    if (!element) {
      return;
    }
    var named = element.getAttribute('data-count');
    var href = element.getAttribute('href') || '';
    var kind = named || (href.indexOf('https://wa.me/') === 0 || href.indexOf('https://api.whatsapp.com/') === 0
      ? 'WhatsApp'
      : href.indexOf('mailto:') === 0 ? 'Email' : href.indexOf('tel:') === 0 ? 'Phone' : '');
    if (!kind) {
      return;
    }
    var words = text(element);
    send('tap', named || !words ? kind : kind + ': ' + words);
  }, true);

  document.addEventListener('form-sent', function (event) {
    var name = event.detail && event.detail.form;
    send('form', typeof name === 'string' && name ? name : 'Form');
  });
})();
