/*
 * Loaded in the page's head, before the stylesheet: applies the theme the person chose
 * with the switch (kept on this device), so the page is never drawn in the other one
 * first. Without a choice, the panel follows the phone or computer's own setting.
 */
(function () {
  'use strict';
  try {
    var theme = window.localStorage.getItem('sk-theme');
    if (theme === 'light' || theme === 'dark') {
      document.documentElement.setAttribute('data-theme', theme);
    }
  } catch (e) {
    // Storage blocked (Safari private browsing): follow the device's setting.
  }
})();
