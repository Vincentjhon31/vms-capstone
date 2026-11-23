/**
 * Live validation for ordinary (non-stepped) forms.
 *
 * The stepped registration form has its own copy inside stepper.js, because it
 * also has to decide whether a step may be left. This handles everything else:
 * feedback, concerns, and anything later that marks a field with data-validate.
 *
 * The server re-validates all of it. This exists so someone finds out about a
 * mistyped email before they press submit, not after a page reload.
 */
(function () {
  'use strict';

  var RULES = {
    required: function (value) {
      return value.trim() !== '' || 'This field is required.';
    },
    name: function (value) {
      if (value.trim().length < 2) return 'Please enter your full name.';
      return true;
    },
    email: function (value) {
      if (value.trim() === '') return 'Please enter your email address.';
      return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(value.trim()) || 'Please enter a valid email address.';
    },
    contact: function (value) {
      if (value.trim() === '') return true;                 // optional everywhere it is used
      return /^[0-9+\-\s()]{7,20}$/.test(value.trim()) || 'Please enter a valid contact number.';
    }
  };

  // Fields inside the stepped form are handled there.
  var fields = Array.prototype.filter.call(
    document.querySelectorAll('[data-validate]'),
    function (field) { return !field.closest('form[data-stepper]'); }
  );
  if (!fields.length) return;

  function slotFor(field) {
    var wrap = field.closest('.field') || field.parentElement;
    var slot = wrap.querySelector('[data-error-for="' + field.name + '"]');
    if (!slot) {
      slot = document.createElement('span');
      slot.className = 'field-error';
      slot.setAttribute('data-error-for', field.name);
      slot.hidden = true;
      wrap.appendChild(slot);
    }
    return slot;
  }

  function check(field) {
    var rule = RULES[field.dataset.validate];
    if (!rule) return true;
    var result = rule(field.value);
    var slot = slotFor(field);

    if (result === true) {
      slot.hidden = true;
      field.classList.remove('is-invalid');
      field.removeAttribute('aria-invalid');
      if (field.value.trim() !== '') field.classList.add('is-valid');
      else field.classList.remove('is-valid');
      return true;
    }

    slot.textContent = result;
    slot.hidden = false;
    field.classList.add('is-invalid');
    field.classList.remove('is-valid');
    field.setAttribute('aria-invalid', 'true');
    return false;
  }

  fields.forEach(function (field) {
    // On blur, not on every keystroke - flagging an email as invalid while
    // someone is still typing the @ is nagging, not helping.
    field.addEventListener('blur', function () { check(field); });
    field.addEventListener('input', function () {
      if (field.classList.contains('is-invalid')) check(field);
    });
  });

  // Catch the whole form on submit so nothing invalid leaves the browser.
  var forms = [];
  fields.forEach(function (field) {
    if (field.form && forms.indexOf(field.form) === -1) forms.push(field.form);
  });

  forms.forEach(function (form) {
    form.addEventListener('submit', function (event) {
      var firstBad = null;
      fields.forEach(function (field) {
        if (field.form !== form) return;
        if (!check(field) && !firstBad) firstBad = field;
      });
      if (firstBad) {
        event.preventDefault();
        firstBad.focus();
        firstBad.scrollIntoView({ block: 'center', behavior: 'smooth' });
      }
    });
  });
})();
