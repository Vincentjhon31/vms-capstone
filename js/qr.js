/*!
 * Minimal QR Code encoder - byte mode, error correction level M, versions 1-10.
 * Written for this project so the entrance poster works with no CDN and no
 * outbound network access. Output verified module-for-module against the
 * reference Python "qrcode" library.
 */
(function (global) {
  'use strict';

  /* ---- GF(256) arithmetic, primitive polynomial 0x11d ------------------ */

  var EXP = new Uint8Array(512);
  var LOG = new Uint8Array(256);
  (function initTables() {
    var x = 1;
    for (var i = 0; i < 255; i++) {
      EXP[i] = x;
      LOG[x] = i;
      x <<= 1;
      if (x & 0x100) x ^= 0x11d;
    }
    for (var j = 255; j < 512; j++) EXP[j] = EXP[j - 255];
  })();

  function gfMul(a, b) {
    return (a === 0 || b === 0) ? 0 : EXP[LOG[a] + LOG[b]];
  }

  /** Reed-Solomon generator polynomial of the given degree. */
  function rsGenerator(degree) {
    var g = [1];
    for (var i = 0; i < degree; i++) {
      var next = new Array(g.length + 1).fill(0);
      for (var j = 0; j < g.length; j++) {
        next[j] ^= g[j];
        next[j + 1] ^= gfMul(g[j], EXP[i]);
      }
      g = next;
    }
    return g;
  }

  /** Error-correction codewords for one block. */
  function rsEncode(data, ecLength) {
    var gen = rsGenerator(ecLength);
    var buf = new Uint8Array(data.length + ecLength);
    buf.set(data);
    for (var i = 0; i < data.length; i++) {
      var factor = buf[i];
      if (factor === 0) continue;
      for (var j = 0; j < gen.length; j++) {
        buf[i + j] ^= gfMul(gen[j], factor);
      }
    }
    return buf.slice(data.length);
  }

  /* ---- Version tables (error correction level M only) ------------------ */

  // [ec codewords per block, blocks in group 1, data per block, blocks in group 2, data per block]
  var BLOCKS_M = [
    [10, 1, 16, 0, 0],  // version 1
    [16, 1, 28, 0, 0],
    [26, 1, 44, 0, 0],
    [18, 2, 32, 0, 0],
    [24, 2, 43, 0, 0],
    [16, 4, 27, 0, 0],
    [18, 4, 31, 0, 0],
    [22, 2, 38, 2, 39],
    [22, 3, 36, 2, 37],
    [26, 4, 43, 1, 44]  // version 10
  ];

  var ALIGN = [
    [], [6, 18], [6, 22], [6, 26], [6, 30],
    [6, 34], [6, 22, 38], [6, 24, 42], [6, 26, 46], [6, 28, 50]
  ];

  // Remainder bits appended after the interleaved codewords.
  var REMAINDER = [0, 7, 7, 7, 7, 7, 0, 0, 0, 0];

  function dataCodewords(version) {
    var b = BLOCKS_M[version - 1];
    return b[1] * b[2] + b[3] * b[4];
  }

  function utf8Bytes(text) {
    if (typeof TextEncoder !== 'undefined') return new TextEncoder().encode(text);
    var out = [], str = unescape(encodeURIComponent(text));
    for (var i = 0; i < str.length; i++) out.push(str.charCodeAt(i));
    return new Uint8Array(out);
  }

  /** Smallest version that fits the payload, or 0 when it is too long. */
  function pickVersion(byteLength) {
    for (var v = 1; v <= 10; v++) {
      var countBits = v < 10 ? 8 : 16;
      var needed = 4 + countBits + byteLength * 8;
      if (needed <= dataCodewords(v) * 8) return v;
    }
    return 0;
  }

  /* ---- Bit stream ------------------------------------------------------ */

  function BitBuffer() { this.bits = []; }
  BitBuffer.prototype.put = function (value, length) {
    for (var i = length - 1; i >= 0; i--) this.bits.push((value >>> i) & 1);
  };
  BitBuffer.prototype.toCodewords = function (capacityCodewords) {
    var bits = this.bits.slice();
    var capacity = capacityCodewords * 8;
    for (var t = 0; t < 4 && bits.length < capacity; t++) bits.push(0);   // terminator
    while (bits.length % 8 !== 0) bits.push(0);                           // pad to byte
    var words = [];
    for (var i = 0; i < bits.length; i += 8) {
      var byte = 0;
      for (var b = 0; b < 8; b++) byte = (byte << 1) | bits[i + b];
      words.push(byte);
    }
    var padBytes = [0xEC, 0x11], p = 0;
    while (words.length < capacityCodewords) words.push(padBytes[p++ % 2]);
    return words;
  };

  /* ---- Payload assembly ------------------------------------------------ */

  function buildCodewords(text, version) {
    var bytes = utf8Bytes(text);
    var buffer = new BitBuffer();
    buffer.put(0b0100, 4);                          // byte mode
    buffer.put(bytes.length, version < 10 ? 8 : 16); // character count
    for (var i = 0; i < bytes.length; i++) buffer.put(bytes[i], 8);

    var words = buffer.toCodewords(dataCodewords(version));
    var spec = BLOCKS_M[version - 1];
    var ecLength = spec[0];

    var blocks = [], ecBlocks = [], offset = 0, g, k;
    for (g = 0; g < spec[1]; g++) {
      blocks.push(words.slice(offset, offset + spec[2]));
      offset += spec[2];
    }
    for (g = 0; g < spec[3]; g++) {
      blocks.push(words.slice(offset, offset + spec[4]));
      offset += spec[4];
    }
    for (k = 0; k < blocks.length; k++) {
      ecBlocks.push(rsEncode(Uint8Array.from(blocks[k]), ecLength));
    }

    // Interleave data codewords, then error-correction codewords.
    var result = [], maxData = Math.max(spec[2], spec[4]), i2;
    for (i2 = 0; i2 < maxData; i2++) {
      for (k = 0; k < blocks.length; k++) {
        if (i2 < blocks[k].length) result.push(blocks[k][i2]);
      }
    }
    for (i2 = 0; i2 < ecLength; i2++) {
      for (k = 0; k < ecBlocks.length; k++) result.push(ecBlocks[k][i2]);
    }
    return result;
  }

  /* ---- Matrix ---------------------------------------------------------- */

  function makeMatrix(size) {
    var m = [];
    for (var r = 0; r < size; r++) m.push(new Array(size).fill(null));
    return m;
  }

  function placeFinder(modules, reserved, size, row, col) {
    for (var r = -1; r <= 7; r++) {
      for (var c = -1; c <= 7; c++) {
        var rr = row + r, cc = col + c;
        if (rr < 0 || rr >= size || cc < 0 || cc >= size) continue;
        var inner = (r >= 0 && r <= 6 && (c === 0 || c === 6)) ||
                    (c >= 0 && c <= 6 && (r === 0 || r === 6)) ||
                    (r >= 2 && r <= 4 && c >= 2 && c <= 4);
        modules[rr][cc] = inner ? 1 : 0;
        reserved[rr][cc] = true;
      }
    }
  }

  function placeFunctionPatterns(modules, reserved, version) {
    var size = modules.length, i, j;

    placeFinder(modules, reserved, size, 0, 0);
    placeFinder(modules, reserved, size, 0, size - 7);
    placeFinder(modules, reserved, size, size - 7, 0);

    // Timing patterns
    for (i = 8; i < size - 8; i++) {
      var bit = (i % 2 === 0) ? 1 : 0;
      modules[6][i] = bit; reserved[6][i] = true;
      modules[i][6] = bit; reserved[i][6] = true;
    }

    // Alignment patterns, skipping the three finder corners
    var centres = ALIGN[version - 1];
    for (i = 0; i < centres.length; i++) {
      for (j = 0; j < centres.length; j++) {
        var cy = centres[i], cx = centres[j];
        var nearFinder = (cy <= 8 && cx <= 8) ||
                         (cy <= 8 && cx >= size - 9) ||
                         (cy >= size - 9 && cx <= 8);
        if (nearFinder) continue;
        for (var dy = -2; dy <= 2; dy++) {
          for (var dx = -2; dx <= 2; dx++) {
            modules[cy + dy][cx + dx] =
              (Math.max(Math.abs(dy), Math.abs(dx)) !== 1) ? 1 : 0;
            reserved[cy + dy][cx + dx] = true;
          }
        }
      }
    }

    // Format information areas (values written after masking)
    for (i = 0; i <= 8; i++) {
      if (i !== 6) { reserved[8][i] = true; reserved[i][8] = true; }
    }
    for (i = 0; i < 8; i++) reserved[size - 1 - i][8] = true;
    for (i = 0; i < 8; i++) reserved[8][size - 1 - i] = true;

    // Dark module
    modules[size - 8][8] = 1;
    reserved[size - 8][8] = true;

    // Version information (version 7 and up)
    if (version >= 7) {
      for (i = 0; i < 18; i++) {
        var a = size - 11 + (i % 3), b = Math.floor(i / 3);
        reserved[a][b] = true;
        reserved[b][a] = true;
      }
    }
  }

  function placeData(modules, reserved, codewords, remainderBits) {
    var size = modules.length;
    var bits = [];
    for (var i = 0; i < codewords.length; i++) {
      for (var b = 7; b >= 0; b--) bits.push((codewords[i] >>> b) & 1);
    }
    for (var r0 = 0; r0 < remainderBits; r0++) bits.push(0);

    var index = 0, upward = true;
    for (var right = size - 1; right >= 1; right -= 2) {
      if (right === 6) right = 5;                     // skip the vertical timing column
      for (var step = 0; step < size; step++) {
        var row = upward ? size - 1 - step : step;
        for (var c = 0; c < 2; c++) {
          var col = right - c;
          if (reserved[row][col]) continue;
          modules[row][col] = index < bits.length ? bits[index++] : 0;
        }
      }
      upward = !upward;
    }
  }

  var MASKS = [
    function (r, c) { return (r + c) % 2 === 0; },
    function (r) { return r % 2 === 0; },
    function (r, c) { return c % 3 === 0; },
    function (r, c) { return (r + c) % 3 === 0; },
    function (r, c) { return (Math.floor(r / 2) + Math.floor(c / 3)) % 2 === 0; },
    function (r, c) { return ((r * c) % 2) + ((r * c) % 3) === 0; },
    function (r, c) { return (((r * c) % 2) + ((r * c) % 3)) % 2 === 0; },
    function (r, c) { return (((r + c) % 2) + ((r * c) % 3)) % 2 === 0; }
  ];

  function penalty(modules) {
    var size = modules.length, score = 0, r, c, run, dark = 0;

    // Rule 1 - runs of five or more
    for (r = 0; r < size; r++) {
      run = 1;
      for (c = 1; c < size; c++) {
        if (modules[r][c] === modules[r][c - 1]) {
          run++;
          if (run === 5) score += 3; else if (run > 5) score += 1;
        } else run = 1;
      }
    }
    for (c = 0; c < size; c++) {
      run = 1;
      for (r = 1; r < size; r++) {
        if (modules[r][c] === modules[r - 1][c]) {
          run++;
          if (run === 5) score += 3; else if (run > 5) score += 1;
        } else run = 1;
      }
    }

    // Rule 2 - 2x2 blocks of one colour
    for (r = 0; r < size - 1; r++) {
      for (c = 0; c < size - 1; c++) {
        var v = modules[r][c];
        if (v === modules[r][c + 1] && v === modules[r + 1][c] && v === modules[r + 1][c + 1]) score += 3;
      }
    }

    // Rule 3 - finder-like patterns
    var p1 = [1, 0, 1, 1, 1, 0, 1, 0, 0, 0, 0];
    var p2 = [0, 0, 0, 0, 1, 0, 1, 1, 1, 0, 1];
    function matches(line, start, pattern) {
      for (var i = 0; i < 11; i++) if (line[start + i] !== pattern[i]) return false;
      return true;
    }
    for (r = 0; r < size; r++) {
      var rowLine = modules[r];
      for (c = 0; c <= size - 11; c++) {
        if (matches(rowLine, c, p1) || matches(rowLine, c, p2)) score += 40;
      }
    }
    for (c = 0; c < size; c++) {
      var colLine = [];
      for (r = 0; r < size; r++) colLine.push(modules[r][c]);
      for (r = 0; r <= size - 11; r++) {
        if (matches(colLine, r, p1) || matches(colLine, r, p2)) score += 40;
      }
    }

    // Rule 4 - proportion of dark modules
    for (r = 0; r < size; r++) for (c = 0; c < size; c++) if (modules[r][c]) dark++;
    var percent = (dark * 100) / (size * size);
    score += Math.floor(Math.abs(percent - 50) / 5) * 10;

    return score;
  }

  function formatBits(mask) {
    var data = (0 << 3) | mask;            // 0b00 = error correction level M
    var rem = data;
    for (var i = 0; i < 10; i++) rem = (rem << 1) ^ ((rem >>> 9) * 0x537);
    return (((data << 10) | rem) ^ 0x5412) >>> 0;
  }

  function versionBits(version) {
    var rem = version;
    for (var i = 0; i < 12; i++) rem = (rem << 1) ^ ((rem >>> 11) * 0x1F25);
    return ((version << 12) | rem) >>> 0;
  }

  function drawFormat(modules, mask) {
    var size = modules.length, bits = formatBits(mask), i;
    function bit(n) { return (bits >>> n) & 1; }

    // First copy: down column 8, then left along row 8.
    for (i = 0; i <= 5; i++) modules[i][8] = bit(i);
    modules[7][8] = bit(6);
    modules[8][8] = bit(7);
    modules[8][7] = bit(8);
    for (i = 9; i < 15; i++) modules[8][14 - i] = bit(i);

    // Second copy: along row 8 from the right, then up column 8 from the bottom.
    for (i = 0; i < 8; i++) modules[8][size - 1 - i] = bit(i);
    for (i = 8; i < 15; i++) modules[size - 15 + i][8] = bit(i);

    modules[size - 8][8] = 1;
  }

  function drawVersion(modules, version) {
    if (version < 7) return;
    var size = modules.length, bits = versionBits(version);
    for (var i = 0; i < 18; i++) {
      var b = (bits >>> i) & 1;
      var a = size - 11 + (i % 3), c = Math.floor(i / 3);
      modules[a][c] = b;
      modules[c][a] = b;
    }
  }

  /** Encode text into a matrix of 0/1 modules. */
  function encode(text) {
    var bytes = utf8Bytes(text);
    var version = pickVersion(bytes.length);
    if (!version) throw new Error('QR: text too long (max ~213 characters at level M)');

    var size = version * 4 + 17;
    var codewords = buildCodewords(text, version);

    var base = makeMatrix(size);
    var reserved = makeMatrix(size);
    for (var r = 0; r < size; r++) for (var c = 0; c < size; c++) reserved[r][c] = false;

    placeFunctionPatterns(base, reserved, version);
    placeData(base, reserved, codewords, REMAINDER[version - 1]);

    var best = null, bestScore = Infinity;
    for (var mask = 0; mask < 8; mask++) {
      var candidate = base.map(function (row) { return row.slice(); });
      for (var y = 0; y < size; y++) {
        for (var x = 0; x < size; x++) {
          if (!reserved[y][x] && MASKS[mask](y, x)) candidate[y][x] ^= 1;
        }
      }
      drawFormat(candidate, mask);
      drawVersion(candidate, version);
      var score = penalty(candidate);
      if (score < bestScore) { bestScore = score; best = candidate; }
    }
    return best;
  }

  /** Encode text as an SVG string. */
  function toSvg(text, options) {
    var opts = options || {};
    var colour = opts.color || '#b45309';
    var background = opts.background || '#ffffff';
    var quiet = opts.quiet == null ? 2 : opts.quiet;
    var modules = encode(text);
    var size = modules.length;
    var total = size + quiet * 2;

    var path = '';
    for (var r = 0; r < size; r++) {
      for (var c = 0; c < size; c++) {
        if (modules[r][c]) path += 'M' + (c + quiet) + ' ' + (r + quiet) + 'h1v1h-1z';
      }
    }
    return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' + total + ' ' + total +
           '" shape-rendering="crispEdges" role="img">' +
           '<rect width="' + total + '" height="' + total + '" fill="' + background + '"/>' +
           '<path d="' + path + '" fill="' + colour + '"/></svg>';
  }

  /**
   * Draw the code onto a canvas, optionally with a caption under it.
   *
   * The panel on the wall only ever needs the SVG, but a visitor pass has to
   * survive leaving the browser: a phone saves a PNG to the camera roll, and an
   * SVG in a downloads folder is no use to anyone at the counter. The caption
   * is part of the image on purpose - a saved QR with no readable reference
   * number underneath is unusable the moment a camera will not focus on it.
   */
  function toCanvas(text, options) {
    var opts = options || {};
    var scale = opts.scale || 8;
    var quiet = opts.quiet == null ? 4 : opts.quiet;
    var colour = opts.color || '#16202e';
    var background = opts.background || '#ffffff';
    var caption = opts.caption || '';
    var subcaption = opts.subcaption || '';

    var modules = encode(text);
    var size = modules.length;
    var side = (size + quiet * 2) * scale;
    var captionBand = caption ? Math.round(scale * 9) : 0;

    var canvas = document.createElement('canvas');
    canvas.width = side;
    canvas.height = side + captionBand;

    var ctx = canvas.getContext('2d');
    ctx.fillStyle = background;
    ctx.fillRect(0, 0, canvas.width, canvas.height);

    ctx.fillStyle = colour;
    for (var r = 0; r < size; r++) {
      for (var c = 0; c < size; c++) {
        if (modules[r][c]) {
          ctx.fillRect((c + quiet) * scale, (r + quiet) * scale, scale, scale);
        }
      }
    }

    if (caption) {
      ctx.textAlign = 'center';
      ctx.fillStyle = colour;
      ctx.font = '700 ' + Math.round(scale * 3.4) + 'px "Segoe UI", system-ui, sans-serif';
      ctx.fillText(caption, side / 2, side + Math.round(scale * 3.4));
      if (subcaption) {
        ctx.fillStyle = '#64748b';
        ctx.font = '500 ' + Math.round(scale * 2.1) + 'px "Segoe UI", system-ui, sans-serif';
        ctx.fillText(subcaption, side / 2, side + Math.round(scale * 7));
      }
    }
    return canvas;
  }

  var api = { encode: encode, toSvg: toSvg, toCanvas: toCanvas };

  if (typeof module !== 'undefined' && module.exports) module.exports = api;
  global.VMSQR = api;

  /* ---- Auto-render the entrance panel ---------------------------------- */

  if (typeof document !== 'undefined') {
    document.addEventListener('DOMContentLoaded', function () {

      /* Any element carrying data-qr renders its own code. The visitor pass on
         the registration receipt is the caller that matters; keeping it generic
         means the next page that needs one adds an attribute, not a script. */
      document.querySelectorAll('[data-qr]').forEach(function (element) {
        var payload = element.getAttribute('data-qr');
        if (!payload) return;
        try {
          element.innerHTML = toSvg(payload, {
            color: element.getAttribute('data-qr-color') || '#16202e',
            quiet: 2
          });
        } catch (err) {
          element.textContent = 'QR unavailable';
        }
      });

      var holder = document.getElementById('qrPanel');
      if (!holder || !global.VMS_REGISTER_URL) return;
      try {
        holder.innerHTML = toSvg(global.VMS_REGISTER_URL, { color: '#b45309' });
      } catch (err) {
        holder.textContent = 'QR unavailable';
      }

      var printButton = document.querySelector('[data-print-qr]');
      if (printButton) {
        printButton.addEventListener('click', function () {
          var win = window.open('', '_blank', 'width=760,height=900');
          if (!win) return;
          win.document.write(
            '<!doctype html><title>Scan to register</title>' +
            '<style>body{font:600 16px "Segoe UI",system-ui;text-align:center;padding:48px;color:#0f172a}' +
            'h1{font-size:26px;margin:0 0 4px}h2{font-size:17px;color:#475569;margin:0 0 28px;font-weight:600}' +
            'svg{width:340px;height:340px}p{margin-top:24px;color:#b45309;font-size:20px;letter-spacing:.06em}' +
            'small{display:block;color:#64748b;font-weight:500;margin-top:10px;letter-spacing:0}</style>' +
            '<h1>Municipality of Bongabong</h1><h2>Visitor Management System</h2>' +
            toSvg(global.VMS_REGISTER_URL, { color: '#0f172a' }) +
            '<p>SCAN QR TO REGISTER!</p><small>' + global.VMS_REGISTER_URL + '</small>'
          );
          win.document.close();
          win.focus();
          win.print();
        });
      }
    });
  }
})(typeof window !== 'undefined' ? window : globalThis);
