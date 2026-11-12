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

  // After "Add" or a move, put the cursor in that list item's first field.
  var marker = document.querySelector('[data-focus]');
  if (marker) {
    var item = marker.closest('.list-item');
    var first = item && item.querySelector('input:not([type=hidden]), textarea, select');
    if (first) { first.focus(); item.scrollIntoView({ block: 'start' }); }
  }

  // After a save with problems, move to the list of problems so it is read first.
  var problems = document.querySelector('.form-problems');
  if (problems) problems.focus();
  // Publishing progress: Saved → Building → Going live → Live. Asks the panel every
  // few seconds until the newest save is live (or its build failed), then stops.
  var publishing = document.querySelector('[data-publishing]');
  if (publishing) {
    var order = ['saved', 'building', 'going-live', 'live'];
    var message = publishing.querySelector('.publishing-message');
    var estimate = publishing.querySelector('.publishing-estimate');

    var show = function (report) {
      if (!report || report.stage === 'none') {
        publishing.hidden = true;
        return;
      }
      var failed = report.stage === 'failed';
      var current = failed ? order.indexOf('building') : order.indexOf(report.stage);
      publishing.querySelectorAll('[data-step]').forEach(function (step, index) {
        step.className = index < current || report.stage === 'live' ? 'done'
          : index === current ? (failed ? 'failed' : 'current') : '';
        if (index === current && !report.done) {
          step.setAttribute('aria-current', 'step');
        } else {
          step.removeAttribute('aria-current');
        }
      });
      publishing.classList.toggle('publishing-failed', failed);
      message.textContent = report.message;
      estimate.textContent = report.estimate || '';
      estimate.hidden = !report.estimate;
      publishing.hidden = false;
    };

    var check = function () {
      fetch(publishing.getAttribute('data-publishing'), { credentials: 'same-origin', headers: { Accept: 'application/json' } })
        .then(function (response) { return response.ok ? response.json() : null; })
        .then(function (report) {
          show(report);
          if (report && !report.done) {
            setTimeout(check, 5000);
          }
        })
        .catch(function () { setTimeout(check, 15000); });
    };
    check();
  }

  // Photo fields. Picking a file shows it at once (it is checked and kept when the form
  // is posted); "Choose a photo" opens the page's photo chooser and puts the chosen
  // file's name in the field. Without JavaScript the field still takes a file or a name.
  var picker = document.getElementById('photo-picker');
  var choosingFor = null;
  document.querySelectorAll('[data-photo-field]').forEach(function (field) {
    var parts = {
      preview: field.querySelector('[data-photo-preview]'),
      empty: field.querySelector('[data-photo-empty]'),
      file: field.querySelector('[data-photo-file]'),
      name: field.querySelector('[data-photo-name]'),
      upload: field.querySelector('[data-photo-upload]'),
    };
    var choose = field.querySelector('[data-photo-choose]');

    parts.upload.addEventListener('change', function () {
      var chosen = parts.upload.files && parts.upload.files[0];
      if (chosen) {
        showPhoto(parts, URL.createObjectURL(chosen), chosen.name + ' (added when you press Save)');
        askForDescription(field);
      }
    });

    if (picker && typeof picker.showModal === 'function') {
      choose.hidden = false;
      choose.addEventListener('click', function () {
        choosingFor = parts;
        picker.showModal();
      });
    }
  });

  // A new photo with no description: point at the description box and say why it
  // matters. The description also names the file when the phone's own name says nothing.
  function askForDescription(field) {
    var group = field.closest('fieldset');
    var alt = group && group.querySelector('input[name$="[alt]"]');
    if (!alt || alt.value.trim() !== '') {
      return;
    }
    var box = alt.closest('.field');
    box.classList.add('needs-description');
    if (!box.querySelector('.field-nudge')) {
      var nudge = document.createElement('p');
      nudge.className = 'field-nudge';
      nudge.textContent = 'Describe this photo: it is read out to people who cannot see it, and it names the file.';
      alt.insertAdjacentElement('afterend', nudge);
    }
    alt.addEventListener('input', function () {
      box.classList.toggle('needs-description', alt.value.trim() === '');
    });
  }

  function showPhoto(parts, src, caption) {
    parts.preview.src = src;
    parts.preview.hidden = false;
    parts.empty.hidden = true;
    parts.file.textContent = caption;
  }

  if (picker) {
    picker.querySelectorAll('[data-file]').forEach(function (option) {
      option.addEventListener('click', function () {
        if (choosingFor) {
          choosingFor.name.value = option.getAttribute('data-file');
          choosingFor.upload.value = '';
          showPhoto(choosingFor, option.getAttribute('data-preview'), option.getAttribute('data-file'));
          choosingFor.name.dispatchEvent(new Event('change', { bubbles: true }));
        }
        picker.close();
      });
    });
  }

})();
