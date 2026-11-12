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
      field: field,
      preview: field.querySelector('[data-photo-preview]'),
      empty: field.querySelector('[data-photo-empty]'),
      file: field.querySelector('[data-photo-file]'),
      name: field.querySelector('[data-photo-name]'),
      upload: field.querySelector('[data-photo-upload]'),
    };
    var choose = field.querySelector('[data-photo-choose]');
    parts.focus = focusPicker(field, parts.preview);

    parts.upload.addEventListener('change', function () {
      var chosen = parts.upload.files && parts.upload.files[0];
      if (chosen) {
        showPhoto(parts, URL.createObjectURL(chosen), 'New photo, added when you press Save');
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

  // The photo changed, so its description belongs to the old one: clear it (keeping it
  // as a faint "Was: ..." to retype if it still fits), point at the box and say why it
  // matters. The description also names the file when the phone's own name says nothing.
  function askForDescription(field) {
    var group = imageBox(field);
    var alt = group && group.querySelector('input[name$="[alt]"]');
    if (!alt) {
      return;
    }
    if (alt.value.trim() !== '') {
      alt.placeholder = 'Was: ' + alt.value.trim();
      alt.value = '';
      alt.dispatchEvent(new Event('input', { bubbles: true }));
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
    if (parts.focus) {
      // A new photo: the old focus point was for the old one.
      parts.focus.show(src);
      parts.focus.reset();
    }
  }

  // The box an image's own fields share: its list item, or its group. Not the whole list,
  // or a change to one gallery photo would touch another's description.
  function imageBox(field) {
    return field.closest('.list-item, fieldset');
  }

  // Focus point. The "focus" box beside a photo ("center 30%") becomes the photo with a
  // dot: tap (or use the arrow keys) where it must stay in view when the website crops
  // it, and two small crops show the result. The value is "x% y%", which the site uses
  // as the photo's object-position. Without JavaScript the box stays a box.
  function focusPicker(field, preview) {
    var box = imageBox(field);
    var input = box && box.querySelector('input[name$="[focus]"]');
    if (!input) {
      return null;
    }
    var holder = input.closest('.field');
    var picker = document.createElement('div');
    picker.className = 'focus-picker';
    picker.innerHTML =
      '<div class="focus-frame" data-focus-frame tabindex="0" role="group" aria-label="Focus point: tap the part of the photo to keep in view, or use the arrow keys">' +
        '<img alt=""><span class="focus-dot" data-focus-dot></span>' +
      '</div>' +
      '<div class="focus-crops">' +
        '<figure><img class="focus-crop-wide" alt="" data-focus-crop><figcaption>Wide</figcaption></figure>' +
        '<figure><img class="focus-crop-square" alt="" data-focus-crop><figcaption>Square</figcaption></figure>' +
      '</div>' +
      '<button type="button" class="btn btn-quiet" data-focus-reset>Back to the middle</button>' +
      '<p class="field-help">Tap the part of the photo that must stay in view when the website crops it.</p>';
    input.type = 'hidden';
    input.insertAdjacentElement('afterend', picker);

    var frame = picker.querySelector('[data-focus-frame]');
    var dot = picker.querySelector('[data-focus-dot]');
    var images = [frame.querySelector('img')].concat(Array.prototype.slice.call(picker.querySelectorAll('[data-focus-crop]')));
    var point = readFocus(input.value);

    function draw() {
      dot.style.left = point.x + '%';
      dot.style.top = point.y + '%';
      picker.querySelectorAll('[data-focus-crop]').forEach(function (crop) {
        crop.style.objectPosition = point.x + '% ' + point.y + '%';
      });
    }
    function moveTo(x, y) {
      point = { x: Math.round(Math.min(100, Math.max(0, x))), y: Math.round(Math.min(100, Math.max(0, y))) };
      input.value = point.x + '% ' + point.y + '%';
      draw();
    }

    frame.addEventListener('click', function (event) {
      var area = frame.getBoundingClientRect();
      moveTo((event.clientX - area.left) / area.width * 100, (event.clientY - area.top) / area.height * 100);
    });
    frame.addEventListener('keydown', function (event) {
      var step = { ArrowLeft: [-5, 0], ArrowRight: [5, 0], ArrowUp: [0, -5], ArrowDown: [0, 5] }[event.key];
      if (step) {
        event.preventDefault();
        moveTo(point.x + step[0], point.y + step[1]);
      }
    });
    picker.querySelector('[data-focus-reset]').addEventListener('click', function () {
      api.reset();
    });

    var api = {
      show: function (src) {
        images.forEach(function (image) { image.src = src; });
        picker.hidden = !src;
      },
      reset: function () {
        input.value = '';
        point = { x: 50, y: 50 };
        draw();
      },
    };
    api.show(preview.hidden ? '' : preview.getAttribute('src'));
    draw();

    return api;
  }

  // "x% y%", or the words CSS allows ("center 30%", "top", "left bottom"); anything else
  // is the middle.
  function readFocus(value) {
    var named = { left: 0, top: 0, center: 50, right: 100, bottom: 100 };
    var words = (value || '').trim().toLowerCase().split(/\s+/).filter(Boolean);
    var part = function (word) {
      if (word in named) {
        return named[word];
      }
      var number = /^(\d+(?:\.\d+)?)%$/.exec(word);
      return number ? Math.min(100, parseFloat(number[1])) : null;
    };
    var x = 50;
    var y = 50;
    if (words.length === 1) {
      if (words[0] === 'top' || words[0] === 'bottom') {
        y = named[words[0]];
      } else if (part(words[0]) !== null) {
        x = part(words[0]);
      }
    } else if (words.length >= 2) {
      var swapped = words[0] === 'top' || words[0] === 'bottom' || words[1] === 'left' || words[1] === 'right';
      var first = part(swapped ? words[1] : words[0]);
      var second = part(swapped ? words[0] : words[1]);
      x = first === null ? 50 : first;
      y = second === null ? 50 : second;
    }
    return { x: x, y: y };
  }

  if (picker) {
    picker.querySelectorAll('[data-file]').forEach(function (option) {
      option.addEventListener('click', function () {
        if (choosingFor) {
          choosingFor.name.value = option.getAttribute('data-file');
          choosingFor.upload.value = '';
          showPhoto(choosingFor, option.getAttribute('data-preview'), option.getAttribute('data-file'));
          askForDescription(choosingFor.field);
          choosingFor.name.dispatchEvent(new Event('change', { bubbles: true }));
        }
        picker.close();
      });
    });
  }

})();
