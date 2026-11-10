/*
 * Slim Killer control panel: the little JavaScript it has. No framework, served from
 * the panel itself (the Content Security Policy allows no other scripts). Every page
 * works without it; this only adds conveniences.
 */
(function () {
  'use strict';

  // Show or hide what was typed in a password field.
  document.querySelectorAll('[data-reveal]').forEach(function (button) {
    var input = document.getElementById(button.getAttribute('data-reveal'));
    if (!input) {
      return;
    }
    button.hidden = false;
    button.addEventListener('click', function () {
      var show = input.type === 'password';
      input.type = show ? 'text' : 'password';
      button.setAttribute('aria-pressed', String(show));
      button.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
      input.focus();
    });
  });

  // Save the recovery codes as a text file. Made in the browser: the codes are not
  // kept anywhere on the server, only their hashes.
  var download = document.querySelector('[data-download-codes]');
  if (download) {
    download.hidden = false;
    download.addEventListener('click', function () {
      var codes = Array.prototype.map.call(document.querySelectorAll('.codes code'), function (code) {
        return code.textContent.trim();
      });
      var text = [
        (download.getAttribute('data-site-name') || 'Slim Killer') + ' recovery codes',
        'Account: ' + download.getAttribute('data-account'),
        'Website: ' + download.getAttribute('data-site'),
        '',
        'Each code signs you in once, instead of the six-digit code from your phone.',
        'Sign in with your email and password as usual, then type a code where it asks',
        'for the six-digit code. Keep this file somewhere safe.',
        '',
      ].concat(codes).join('\n') + '\n';

      var url = URL.createObjectURL(new Blob([text], { type: 'text/plain' }));
      var link = document.createElement('a');
      link.href = url;
      link.download = 'slim-killer-recovery-codes-' + download.getAttribute('data-site') + '.txt';
      document.body.appendChild(link);
      link.click();
      link.remove();
      setTimeout(function () { URL.revokeObjectURL(url); }, 1000);
    });
  }

  // Character counters: "12 of 37 characters" under every field with a limit,
  // kept up to date as the client types.
  document.querySelectorAll('[data-count]').forEach(function (input) {
    var counter = document.getElementById(input.getAttribute('data-count'));
    var limit = input.getAttribute('maxlength');
    if (!counter || !limit) return;
    var update = function () {
      var used = Array.from(input.value).length;
      counter.textContent = used + ' of ' + limit + ' characters';
      counter.classList.toggle('is-full', used >= Number(limit));
    };
    input.addEventListener('input', update);
    update();
  });

  // After a save with problems, move to the list of problems so it is read first.
  var problems = document.querySelector('.form-problems');
  if (problems) problems.focus();
})();
