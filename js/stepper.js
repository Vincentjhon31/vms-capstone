/**
 * Multi-step form: panel switching, progress, and per-field validation.
 *
 * The form still submits as one POST and the server re-validates everything -
 * this only decides what is on screen. With JavaScript off, every panel is
 * shown at once and the form works exactly as it did before.
 *
 * Markup contract:
 *   form[data-stepper]
 *     .stepper > .stepper__item        (one per step, in order)
 *     .progress__bar
 *     [data-step-panel="1"] ...        (panels, 1-indexed)
 *     [data-step-next] [data-step-back] [data-step-submit]
 *   Fields opt in to validation with [data-validate="rule"].
 */
(function () {
  'use strict';

  var form = document.querySelector('form[data-stepper]');
  if (!form) return;

  // JS is running, so hand the stepper control of what is visible.
  form.classList.add('stepper-ready');

  var panels  = Array.prototype.slice.call(form.querySelectorAll('[data-step-panel]'));
  var items   = Array.prototype.slice.call(form.querySelectorAll('.stepper__item'));
  var bar     = form.querySelector('.progress__bar');
  var nowEl   = form.querySelector('[data-step-now]');
  var labelEl = form.querySelector('[data-step-label]');
  var total   = panels.length;
  var current = 1;

  if (!total) return;

  /* ---- validation ------------------------------------------------------ */

  var RULES = {
    required: function (value) {
      return value.trim() !== '' || 'This field is required.';
    },
    name: function (value) {
      if (value.trim() === '') return 'Please enter your full name.';
      if (value.trim().length < 2) return 'Please enter your full name.';
      return true;
    },
    age: function (value) {
      if (value.trim() === '') return 'Please enter your age.';
      var n = Number(value);
      if (!Number.isInteger(n) || n < 1 || n > 120) return 'Please enter an age between 1 and 120.';
      return true;
    },
    contact: function (value) {
      var digits = value.replace(/\D/g, '');
      if (digits === '') return true;                       // optional
      if (digits.length !== 11) return 'The number must be exactly 11 digits.';
      if (digits.charAt(0) !== '0') return 'A mobile number starts with 0, e.g. 09171234567.';
      return true;
    },
    address: function (value) {
      return value.trim() !== '' || 'Please enter your complete address.';
    },
    email: function (value) {
      if (value.trim() === '') return 'Please enter your email address.';
      return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(value.trim()) || 'Please enter a valid email address.';
    }
  };

  function fieldWrap(field) {
    return field.closest('.field') || field.parentElement;
  }

  function showError(field, message) {
    var wrap = fieldWrap(field);
    var slot = wrap.querySelector('[data-error-for="' + field.name + '"]');
    if (!slot) {
      slot = document.createElement('span');
      slot.className = 'field-error';
      slot.setAttribute('data-error-for', field.name);
      var hint = wrap.querySelector('.field-hint');
      if (hint && hint.nextSibling) wrap.insertBefore(slot, hint.nextSibling);
      else wrap.appendChild(slot);
    }
    slot.textContent = message;
    slot.hidden = false;
    field.classList.add('is-invalid');
    field.classList.remove('is-valid');
    field.setAttribute('aria-invalid', 'true');
  }

  function clearError(field, markValid) {
    var wrap = fieldWrap(field);
    var slot = wrap.querySelector('[data-error-for="' + field.name + '"]');
    if (slot) slot.hidden = true;
    field.classList.remove('is-invalid');
    field.removeAttribute('aria-invalid');
    if (markValid && field.value.trim() !== '') field.classList.add('is-valid');
    else field.classList.remove('is-valid');
  }

  function validateField(field, markValid) {
    var rule = RULES[field.dataset.validate];
    if (!rule) return true;
    var result = rule(field.value);
    if (result === true) { clearError(field, markValid); return true; }
    showError(field, result);
    return false;
  }

  /* Radio groups and the signature are not plain fields - check them by name. */
  function validateGroup(panel) {
    var ok = true;

    panel.querySelectorAll('[data-validate-group]').forEach(function (group) {
      var name = group.dataset.validateGroup;
      var checked = form.querySelector('input[name="' + name + '"]:checked');
      var slot = group.querySelector('.field-error') ||
                 (function () {
                   var s = document.createElement('span');
                   s.className = 'field-error';
                   group.appendChild(s);
                   return s;
                 })();
      if (checked) {
        slot.hidden = true;
      } else {
        slot.textContent = group.dataset.validateMessage || 'Please choose an option.';
        slot.hidden = false;
        ok = false;
      }
    });

    // Consent checkbox
    panel.querySelectorAll('[data-validate-checked]').forEach(function (box) {
      var wrap = box.closest('.field') || box.parentElement;
      var slot = wrap.querySelector('.field-error') ||
                 (function () {
                   var s = document.createElement('span');
                   s.className = 'field-error';
                   wrap.appendChild(s);
                   return s;
                 })();
      if (box.checked) {
        slot.hidden = true;
      } else {
        slot.textContent = box.dataset.validateChecked || 'Please tick this box to continue.';
        slot.hidden = false;
        ok = false;
      }
    });

    // Signature: a hidden input that must hold a data URL
    panel.querySelectorAll('[data-validate-signature]').forEach(function (hidden) {
      var wrap = hidden.closest('.field') || hidden.parentElement;
      var slot = wrap.querySelector('.field-error') ||
                 (function () {
                   var s = document.createElement('span');
                   s.className = 'field-error';
                   wrap.appendChild(s);
                   return s;
                 })();
      if (hidden.value.indexOf('data:image/png') === 0) {
        slot.hidden = true;
      } else {
        slot.textContent = 'Please sign in the box above.';
        slot.hidden = false;
        ok = false;
      }
    });

    return ok;
  }

  function validatePanel(panel) {
    var ok = true;
    panel.querySelectorAll('[data-validate]').forEach(function (field) {
      if (!validateField(field, true)) ok = false;
    });
    if (!validateGroup(panel)) ok = false;
    return ok;
  }

  /* ---- live validation -------------------------------------------------
     Validate on blur, not on every keystroke - telling someone their email is
     invalid while they are still typing the @ is nagging, not helping. Once a
     field has been marked wrong, re-check as they fix it so the error clears
     the moment it is true. */

  form.querySelectorAll('[data-validate]').forEach(function (field) {
    field.addEventListener('blur', function () { validateField(field, true); });
    field.addEventListener('input', function () {
      if (field.classList.contains('is-invalid')) validateField(field, true);
    });
  });

  form.querySelectorAll('[data-validate-group] input').forEach(function (input) {
    input.addEventListener('change', function () {
      var group = input.closest('[data-validate-group]');
      var slot = group && group.querySelector('.field-error');
      if (slot) slot.hidden = true;
    });
  });

  form.querySelectorAll('[data-validate-checked]').forEach(function (box) {
    box.addEventListener('change', function () {
      if (!box.checked) return;
      var wrap = box.closest('.field') || box.parentElement;
      var slot = wrap.querySelector('.field-error');
      if (slot) slot.hidden = true;
    });
  });

  /* ---- moving between steps -------------------------------------------- */

  function render(direction) {
    panels.forEach(function (panel) {
      var index = parseInt(panel.dataset.stepPanel, 10);
      var active = index === current;
      panel.classList.toggle('is-active', active);
      panel.classList.remove('is-entering', 'is-entering-back');
      if (active) {
        // Restart the entrance animation on each change.
        void panel.offsetWidth;
        panel.classList.add(direction === 'back' ? 'is-entering-back' : 'is-entering');
      }
    });

    items.forEach(function (item, i) {
      var n = i + 1;
      item.classList.toggle('is-done', n < current);
      item.classList.toggle('is-current', n === current);
      if (n === current) item.setAttribute('aria-current', 'step');
      else item.removeAttribute('aria-current');
      var dot = item.querySelector('.stepper__dot');
      if (dot) dot.innerHTML = n < current ? CHECK_SVG : String(n);
    });

    if (bar) {
      bar.style.transform = 'scaleX(' + (current / total) + ')';
      var track = bar.parentElement;
      if (track) track.setAttribute('aria-valuenow', String(current));
    }
    if (nowEl) nowEl.textContent = String(current);
    if (labelEl) {
      var item = items[current - 1];
      var label = item && item.querySelector('.stepper__label');
      labelEl.textContent = label ? label.textContent.trim() : '';
    }

    // Move focus to the panel heading so a screen reader announces the step.
    var heading = panels[current - 1] && panels[current - 1].querySelector('h2');
    if (heading) {
      heading.setAttribute('tabindex', '-1');
      heading.focus({ preventScroll: true });
    }

    var card = form.closest('.card') || form;
    var top = card.getBoundingClientRect().top + window.scrollY - 90;
    if (window.scrollY > top) window.scrollTo({ top: top, behavior: 'smooth' });
  }

  var CHECK_SVG = '<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" ' +
                  'stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' +
                  '<path d="m5 12.5 4.5 4.5L19 7"/></svg>';

  function goTo(step, direction) {
    current = Math.min(Math.max(step, 1), total);
    render(direction);
  }

  form.querySelectorAll('[data-step-next]').forEach(function (button) {
    button.addEventListener('click', function () {
      var panel = panels[current - 1];
      if (!validatePanel(panel)) {
        var firstBad = panel.querySelector('.is-invalid, .field-error:not([hidden])');
        if (firstBad) {
          var focusTarget = firstBad.classList.contains('is-invalid')
            ? firstBad
            : (firstBad.closest('.field') || firstBad).querySelector('input, select, textarea, button');
          if (focusTarget) focusTarget.focus();
        }
        return;
      }
      if (current === total - 1) fillReview();
      goTo(current + 1, 'next');
    });
  });

  form.querySelectorAll('[data-step-back]').forEach(function (button) {
    button.addEventListener('click', function () { goTo(current - 1, 'back'); });
  });

  // Let a completed step be revisited by clicking its dot.
  items.forEach(function (item, i) {
    var n = i + 1;
    item.addEventListener('click', function () {
      if (n < current) goTo(n, 'back');
    });
    item.addEventListener('keydown', function (event) {
      if ((event.key === 'Enter' || event.key === ' ') && n < current) {
        event.preventDefault();
        goTo(n, 'back');
      }
    });
  });

  // Enter should advance the step, not submit the half-filled form.
  form.addEventListener('keydown', function (event) {
    if (event.key !== 'Enter') return;
    var tag = (event.target.tagName || '').toLowerCase();
    if (tag === 'textarea' || tag === 'button') return;
    if (current < total) {
      event.preventDefault();
      var next = panels[current - 1].querySelector('[data-step-next]') ||
                 form.querySelector('[data-step-next]');
      if (next) next.click();
    }
  });

  /* ---- the review step -------------------------------------------------- */

  function fillReview() {
    form.querySelectorAll('[data-review-for]').forEach(function (slot) {
      var name = slot.dataset.reviewFor;
      var value = '';

      if (name === 'signature') {
        var sig = form.querySelector('#signature');
        slot.innerHTML = sig && sig.value
          ? '<img src="' + sig.value + '" alt="Your signature">'
          : '<span class="muted">Not signed</span>';
        return;
      }

      var radio = form.querySelector('input[name="' + name + '"]:checked');
      if (radio) {
        value = radio.value;
        if (value === 'Others') {
          var other = form.querySelector('#purpose_other');
          if (other && other.value.trim()) value += ' - ' + other.value.trim();
        }
      } else {
        var field = form.querySelector('[name="' + name + '"]');
        value = field ? field.value.trim() : '';
      }

      slot.textContent = value !== '' ? value : '—';
      slot.classList.toggle('muted', value === '');
    });
  }

  /* ---- submit ----------------------------------------------------------- */

  form.addEventListener('submit', function (event) {
    // Re-check every panel, in case a value changed after its step was passed.
    for (var i = 0; i < panels.length; i++) {
      if (!validatePanel(panels[i])) {
        event.preventDefault();
        goTo(i + 1, 'back');
        return;
      }
    }
  });

  goTo(1, 'next');
})();
