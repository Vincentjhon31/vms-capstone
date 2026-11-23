/**
 * Shared browser behaviour. Everything degrades gracefully - the forms all
 * work without JavaScript, since the server validates the same rules.
 */
(function () {
  'use strict';

  /* ---- mobile navigation ------------------------------------------- */

  var toggle = document.querySelector('.nav-toggle');
  var nav = document.getElementById('site-nav');
  if (toggle && nav) {
    toggle.addEventListener('click', function () {
      var open = nav.classList.toggle('is-open');
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      toggle.setAttribute('aria-label', open ? 'Close menu' : 'Open menu');
    });
  }

  /* ---- contact number: digits only, live 0/11 counter ---------------- */

  document.querySelectorAll('[data-digit-counter]').forEach(function (input) {
    var counter = document.getElementById(input.dataset.digitCounter);
    var max = parseInt(input.dataset.maxDigits || '11', 10);

    function sync() {
      var cleaned = input.value.replace(/\D/g, '').slice(0, max);

      // Only touch the value when it actually changed. Assigning it every
      // keystroke collapses the selection and drops the caret to the end, so
      // fixing a typo in the middle of the number becomes impossible.
      if (cleaned !== input.value) {
        var caret = input.selectionStart;
        var removedBefore = input.value.slice(0, caret).replace(/\d/g, '').length;
        input.value = cleaned;
        if (input.selectionStart !== null) {
          var next = Math.max(0, Math.min(caret - removedBefore, cleaned.length));
          try { input.setSelectionRange(next, next); } catch (e) { /* type=number has no range */ }
        }
      }

      if (counter) {
        counter.textContent = cleaned.length + '/' + max + ' digits';
        counter.style.color = (cleaned.length === 0 || cleaned.length === max) ? '' : 'var(--warning-dark)';
      }
    }
    input.addEventListener('input', sync);
    sync();
  });

  /* ---- "Others" purpose reveals a free-text box ---------------------- */

  var otherWrap = document.getElementById('purposeOtherWrap');
  if (otherWrap) {
    var otherInput = document.getElementById('purpose_other');
    document.querySelectorAll('input[name="purpose"]').forEach(function (radio) {
      radio.addEventListener('change', function () {
        var isOther = document.querySelector('input[name="purpose"]:checked').value === 'Others';
        otherWrap.hidden = !isOther;
        if (otherInput) {
          otherInput.required = isOther;
          if (isOther) otherInput.focus();
        }
      });
    });
  }

  /* ---- keyword filters on the charter and services lists ------------- */

  document.querySelectorAll('[data-filter-input]').forEach(function (input) {
    var targets = document.querySelectorAll(input.dataset.filterTarget);
    var counter = input.dataset.filterCount ? document.getElementById(input.dataset.filterCount) : null;

    function apply() {
      var term = input.value.trim().toLowerCase();
      var shown = 0;
      targets.forEach(function (item) {
        var haystack = (item.dataset.search || item.textContent).toLowerCase();
        var visible = term === '' || haystack.indexOf(term) !== -1;
        item.hidden = !visible;
        if (visible) shown++;
      });
      if (counter) counter.textContent = shown;

      // Hide a category heading once every question under it is filtered out.
      if (input.dataset.filterGroup) {
        document.querySelectorAll(input.dataset.filterGroup).forEach(function (group) {
          var visible = group.querySelectorAll('[data-faq]:not([hidden])').length;
          group.hidden = visible === 0;
        });
      }

      var empty = document.getElementById(input.dataset.filterEmpty || '');
      if (empty) empty.hidden = shown !== 0;
    }
    input.addEventListener('input', apply);
    apply();
  });

  /* ---- signature pad ---------------------------------------------------
     Drawn on in place, inside step 4 of the register form.

     Three things this has to survive:

     1. Step 4 is display:none until you reach it, so the canvas measures 0x0 on
        load. It is fitted lazily instead - on the first stroke, and whenever a
        ResizeObserver reports it has gained or changed size.
     2. Resizing a canvas wipes it, so ink is re-drawn after every refit.
     3. A stroke that wanders off the canvas must not end the line. Moves and
        releases are tracked on the window rather than on the element, so no
        pointer capture and no pointerleave handling is needed - both of which
        behave differently across touch, stylus and mouse.

     The canvas itself is transparent: the ruled-paper look is CSS on the
     surface behind it, and the PNG is composited onto white only at export, so
     the saved record is ink on white and nothing else. */

  var padCanvas = document.getElementById('signaturePad');
  if (padCanvas && padCanvas.getContext) {
    var padCtx = padCanvas.getContext('2d');
    var padWrap = document.getElementById('signaturePadWrap');
    var padField = document.getElementById('signature');
    var padState = document.getElementById('signatureState');
    var padDrawing = false;
    var padHasInk = false;
    var padLastX = 0;
    var padLastY = 0;
    var padW = 0;
    var padH = 0;

    function padStyle() {
      padCtx.lineWidth = 2.4;
      padCtx.lineCap = 'round';
      padCtx.lineJoin = 'round';
      padCtx.strokeStyle = '#0f172a';
    }

    /** Size the bitmap to the CSS box. Returns false while the step is hidden. */
    function padFit() {
      var rect = padCanvas.getBoundingClientRect();
      if (!rect.width || !rect.height) return false;

      var ratio = window.devicePixelRatio || 1;
      var w = Math.round(rect.width * ratio);
      var h = Math.round(rect.height * ratio);
      if (padCanvas.width === w && padCanvas.height === h) return true;

      var previous = padHasInk && padCanvas.width ? padCanvas.toDataURL('image/png') : null;
      padCanvas.width = w;
      padCanvas.height = h;
      padW = rect.width;
      padH = rect.height;
      padCtx.setTransform(ratio, 0, 0, ratio, 0, 0);
      padStyle();

      if (previous) {
        var img = new Image();
        img.onload = function () { padCtx.drawImage(img, 0, 0, padW, padH); };
        img.src = previous;
      }
      return true;
    }

    function padSetState(text, tone) {
      if (!padState) return;
      padState.textContent = text;
      padState.classList.toggle('is-done', tone === 'done');
      padState.classList.toggle('is-bad', tone === 'bad');
    }

    /** Ink on transparent is hard to read back; flatten onto white to store it. */
    function padExport() {
      var flat = document.createElement('canvas');
      flat.width = padCanvas.width;
      flat.height = padCanvas.height;
      var flatCtx = flat.getContext('2d');
      flatCtx.fillStyle = '#ffffff';
      flatCtx.fillRect(0, 0, flat.width, flat.height);
      flatCtx.drawImage(padCanvas, 0, 0);
      return flat.toDataURL('image/png');
    }

    /** Hold the drawing in the hidden input, and clear any "please sign" error. */
    function padCommit(message) {
      padField.value = padExport();
      padWrap.classList.add('has-signature');
      padWrap.classList.remove('is-invalid');
      var slot = (padField.closest('.field') || padWrap).querySelector('.field-error');
      if (slot) slot.hidden = true;
      padSetState(message, 'done');
    }

    function padPoint(event) {
      var rect = padCanvas.getBoundingClientRect();
      return { x: event.clientX - rect.left, y: event.clientY - rect.top };
    }

    function padStart(event) {
      if (event.button && event.button !== 0) return;   // right/middle click
      if (!padFit()) return;                            // step not visible yet
      event.preventDefault();

      var p = padPoint(event);
      padDrawing = true;
      padHasInk = true;
      padLastX = p.x;
      padLastY = p.y;

      // A tap with no movement should still leave a mark.
      padCtx.beginPath();
      padCtx.moveTo(p.x, p.y);
      padCtx.lineTo(p.x + 0.1, p.y);
      padCtx.stroke();
    }

    function padMove(event) {
      if (!padDrawing) return;
      event.preventDefault();
      var p = padPoint(event);
      padCtx.beginPath();
      padCtx.moveTo(padLastX, padLastY);
      padCtx.lineTo(p.x, p.y);
      padCtx.stroke();
      padLastX = p.x;
      padLastY = p.y;
    }

    function padEnd() {
      if (!padDrawing) return;
      padDrawing = false;
      // Committing on every stroke means a visitor who draws and goes straight
      // to Complete Registration is never told they forgot to sign.
      if (padHasInk) padCommit('Signature captured');
    }

    padCanvas.addEventListener('pointerdown', padStart);
    window.addEventListener('pointermove', padMove, { passive: false });
    window.addEventListener('pointerup', padEnd);
    window.addEventListener('pointercancel', padEnd);
    window.addEventListener('blur', padEnd);

    function padReset() {
      padHasInk = false;
      padDrawing = false;
      padCanvas.width = 0;          // makes the next padFit() rebuild a blank pad
      padFit();
      padField.value = '';
      padWrap.classList.remove('has-signature', 'is-invalid');
      padSetState('Not signed yet', '');
    }

    document.getElementById('signatureClear').addEventListener('click', padReset);

    document.getElementById('signatureSave').addEventListener('click', function () {
      if (!padHasInk) {
        padWrap.classList.add('is-invalid');
        padSetState('Draw your signature first / Gumuhit muna ng lagda', 'bad');
        return;
      }
      padCommit('Signature saved');
    });

    if ('ResizeObserver' in window) {
      new ResizeObserver(function () { padFit(); }).observe(padCanvas);
    } else {
      window.addEventListener('resize', function () { padFit(); });
    }
    padFit();
  }

  /* ---- forms: stop double submits ------------------------------------ */

  document.querySelectorAll('form[data-guard]').forEach(function (form) {
    form.addEventListener('submit', function () {
      var button = form.querySelector('[type="submit"]');
      if (button && form.checkValidity()) {
        button.disabled = true;
        button.dataset.label = button.textContent;
        button.textContent = 'Please wait...';
        setTimeout(function () {
          button.disabled = false;
          if (button.dataset.label) button.textContent = button.dataset.label;
        }, 8000);
      }
    });
  });

  /* ---- confirmations and print --------------------------------------- */

  document.querySelectorAll('[data-confirm]').forEach(function (element) {
    element.addEventListener('click', function (event) {
      if (!window.confirm(element.dataset.confirm)) event.preventDefault();
    });
  });

  document.querySelectorAll('[data-print]').forEach(function (element) {
    element.addEventListener('click', function () { window.print(); });
  });

  /* ---- signature viewer in the visitor log --------------------------- */

  var viewer = document.getElementById('signatureViewer');
  if (viewer) {
    document.querySelectorAll('[data-signature]').forEach(function (button) {
      button.addEventListener('click', function () {
        viewer.querySelector('[data-signature-image]').src = button.dataset.signature;
        viewer.querySelector('[data-signature-name]').textContent = button.dataset.name || '';
        viewer.hidden = false;
      });
    });
    viewer.querySelectorAll('[data-close-modal]').forEach(function (element) {
      element.addEventListener('click', function () { viewer.hidden = true; });
    });
    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape') viewer.hidden = true;
    });
  }

  /* ---- repeatable rows in the content editors ------------------------ */

  document.querySelectorAll('[data-repeat-add]').forEach(function (button) {
    button.addEventListener('click', function () {
      var container = document.querySelector(button.dataset.repeatAdd);
      var rows = container.querySelectorAll('[data-repeat-row]');
      var clone = rows[rows.length - 1].cloneNode(true);
      var index = rows.length;

      clone.querySelectorAll('input, textarea, select').forEach(function (field) {
        field.value = '';
        if (field.name) field.name = field.name.replace(/\[\d+]/, '[' + index + ']');
      });
      container.appendChild(clone);
      var first = clone.querySelector('input, textarea, select');
      if (first) first.focus();
      bindRemove(clone);
    });
  });

  function bindRemove(scope) {
    scope.querySelectorAll('[data-repeat-remove]').forEach(function (button) {
      button.addEventListener('click', function () {
        var row = button.closest('[data-repeat-row]');
        var container = row.parentElement;
        if (container.querySelectorAll('[data-repeat-row]').length > 1) {
          row.remove();
        } else {
          row.querySelectorAll('input, textarea').forEach(function (field) { field.value = ''; });
        }
      });
    });
  }
  bindRemove(document);

  /* ---- auto-submit filter forms -------------------------------------- */

  document.querySelectorAll('[data-auto-submit]').forEach(function (field) {
    field.addEventListener('change', function () { field.form.submit(); });
  });

  /* Citizen's Charter: filter the offices as you type, and open a service's
     sheet in a window over the list. The links work without any of this - the
     server filters too, and a Details link is a page of its own. */
  var ccDir = document.querySelector('[data-cc-dir]');
  if (ccDir) {
    var ccNorm = function (s) { return (s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, ''); };
    var ccQ = ccDir.querySelector('[data-cc-q]');
    var ccPick = ccDir.querySelector('[data-cc-office]');
    var ccOffices = Array.prototype.slice.call(ccDir.querySelectorAll('.cc-office'));

    if (ccQ && ccPick) {
      ccOffices.forEach(function (office) {
        var text = ccNorm(office.getAttribute('data-search'));
        office._items = Array.prototype.slice.call(office.querySelectorAll('.cc-list li'));
        office._items.forEach(function (li) { li._t = text + ' ' + ccNorm(li.getAttribute('data-search')); });
      });
      var ccApply = function () {
        var words = ccNorm(ccQ.value).split(/[^a-z0-9]+/).filter(function (w) { return w.length > 1; });
        var pick = ccPick.value;
        var shown = 0;
        ccOffices.forEach(function (office) {
          var mine = !pick || office.getAttribute('data-office') === pick;
          var any = false;
          office._items.forEach(function (li) {
            li.hidden = !(mine && words.every(function (w) { return li._t.indexOf(w) !== -1; }));
            if (!li.hidden) any = true;
          });
          office.querySelectorAll('.cc-group').forEach(function (group) {
            group.hidden = !Array.prototype.some.call(group.querySelectorAll('li'), function (li) { return !li.hidden; });
          });
          office.hidden = !any;
          office.open = any && (words.length > 0 || !!pick);
          if (any) shown++;
        });
        ccDir.querySelector('[data-cc-shown]').textContent = shown;
        ccDir.querySelector('[data-cc-none]').hidden = shown > 0;
        var u = new URL(location.href);
        ['q', 'office', 'service'].forEach(function (k) { u.searchParams.delete(k); });
        if (ccQ.value.trim()) u.searchParams.set('q', ccQ.value.trim());
        if (pick) u.searchParams.set('office', pick);
        history.replaceState(history.state, '', u.pathname + u.search + location.hash);
      };
      ccQ.addEventListener('input', ccApply);
      ccPick.addEventListener('change', ccApply);
      ccDir.querySelector('[data-cc-form]').addEventListener('submit', function (event) {
        event.preventDefault();
        ccApply();
        ccQ.blur();
      });
    }

    var ccDlg = document.getElementById('cc-dialog');
    if (ccDlg && typeof ccDlg.showModal === 'function') {
      var ccBody = ccDlg.querySelector('.cc-body');
      var ccPrint = ccDlg.querySelector('[data-cc-print]');
      var ccPushed = false;
      var ccReq = 0;
      var ccShow = function () {
        if (!ccDlg.open) ccDlg.showModal();
        ccDlg.scrollTop = 0;
        ccDlg.focus();
        document.documentElement.classList.add('cc-lock');
      };
      var ccLoad = function (href, push) {
        var u = new URL(href, location.href);
        u.searchParams.set('print', '1');
        ccPrint.href = u.href;
        u.searchParams.delete('print');
        u.searchParams.set('partial', '1');
        var mine = ++ccReq;
        ccBody.innerHTML = '<p class="cc-loading">Loading...</p>';
        if (push) {
          history.pushState({ cc: 1 }, '', href);
          ccPushed = true;
        }
        ccShow();
        fetch(u.href, { credentials: 'same-origin' })
          .then(function (r) { if (!r.ok) throw new Error(r.status); return r.text(); })
          .then(function (html) { if (mine === ccReq) ccBody.innerHTML = html; })
          .catch(function () { location.href = href; });
      };
      ccDir.querySelectorAll('[data-cc-open]').forEach(function (link) {
        link.addEventListener('click', function (event) {
          if (event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
          event.preventDefault();
          ccLoad(link.href, true);
        });
      });
      ccDlg.querySelector('[data-cc-close]').addEventListener('click', function (event) {
        event.preventDefault();
        ccDlg.close();
      });
      ccDlg.addEventListener('click', function (event) { if (event.target === ccDlg) ccDlg.close(); });
      ccDlg.addEventListener('close', function () {
        document.documentElement.classList.remove('cc-lock');
        if (!new URL(location.href).searchParams.has('service')) return;
        if (ccPushed) {
          ccPushed = false;
          history.back();
          return;
        }
        var u = new URL(location.href);
        u.searchParams.delete('service');
        history.replaceState(null, '', u.pathname + u.search + '#services');
      });
      window.addEventListener('popstate', function () {
        var has = new URL(location.href).searchParams.has('service');
        if (!has && ccDlg.open) {
          ccPushed = false;
          ccDlg.close();
        } else if (has && !ccDlg.open) {
          ccLoad(location.href, false);
        }
      });
      // Opened from a shared link: the page arrives with the sheet already in it.
      if (ccDlg.hasAttribute('open')) {
        ccDlg.removeAttribute('open');
        ccShow();
      }
    }
  }

  /* Organizational chart: tap or click to zoom in at that spot, drag to move
     around, tap again (or Escape) to see the whole chart. Touch screens pan
     the zoomed chart with their own scrolling. */
  document.querySelectorAll('[data-org-chart]').forEach(function (frame) {
    var img = frame.querySelector('img');
    var toggle = document.querySelector('[data-org-chart-toggle]');
    var fitSizes = img.getAttribute('sizes');
    var zoomed = false;
    var drag = null;
    var moved = false;

    var setZoom = function (on, fx, fy) {
      zoomed = on;
      frame.classList.toggle('is-zoomed', on);
      // 2000px makes the staff names readable on a phone; wider screens go to the full 2800px copy.
      img.style.width = on ? Math.min(2800, Math.max(2000, frame.clientWidth * 2.5)) + 'px' : '';
      img.sizes = on ? '2800px' : fitSizes;
      if (toggle) {
        toggle.setAttribute('aria-pressed', on ? 'true' : 'false');
        toggle.querySelector('span').textContent = on ? 'Zoom out' : 'Zoom in';
      }
      frame.setAttribute('aria-label', on ? 'Organizational chart, zoomed in. Use the arrow keys to move around, Escape to zoom out.'
                                          : 'Organizational chart. Press Enter to zoom in.');
      if (on) {
        frame.scrollLeft = fx * frame.scrollWidth - frame.clientWidth / 2;
        frame.scrollTop = fy * frame.scrollHeight - frame.clientHeight / 2;
      }
    };

    frame.addEventListener('click', function (event) {
      if (moved) {
        moved = false;
        return;
      }
      if (zoomed) {
        setZoom(false);
        return;
      }
      var box = img.getBoundingClientRect();
      setZoom(true, (event.clientX - box.left) / box.width, (event.clientY - box.top) / box.height);
    });
    frame.addEventListener('keydown', function (event) {
      if ((event.key === 'Enter' || event.key === ' ') && event.target === frame) {
        event.preventDefault();
        setZoom(!zoomed, 0.5, 0.15);
      } else if (event.key === 'Escape' && zoomed) {
        setZoom(false);
      }
    });
    if (toggle) {
      toggle.addEventListener('click', function () { setZoom(!zoomed, 0.5, 0.15); });
    }

    // Mouse drag pans the zoomed chart; touch keeps its native scrolling.
    frame.addEventListener('pointerdown', function (event) {
      if (!zoomed || event.pointerType !== 'mouse' || event.button !== 0) return;
      drag = { x: event.clientX, y: event.clientY, left: frame.scrollLeft, top: frame.scrollTop };
      moved = false;
    });
    window.addEventListener('pointermove', function (event) {
      if (!drag) return;
      var dx = event.clientX - drag.x;
      var dy = event.clientY - drag.y;
      if (!moved && Math.abs(dx) + Math.abs(dy) > 5) {
        moved = true;
        frame.classList.add('is-dragging');
      }
      if (moved) {
        frame.scrollLeft = drag.left - dx;
        frame.scrollTop = drag.top - dy;
      }
    });
    window.addEventListener('pointerup', function () {
      drag = null;
      frame.classList.remove('is-dragging');
    });
    img.addEventListener('dragstart', function (event) { event.preventDefault(); });
  });
})();
