/*!
 * Presensi Event — interaksi UI (vanilla JS, tanpa dependensi).
 */
(function () {
  'use strict';

  var $ = function (sel, root) { return (root || document).querySelector(sel); };
  var $$ = function (sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); };
  var csrf = function () { var m = $('meta[name="csrf-token"]'); return m ? m.getAttribute('content') : ''; };
  var assetBase = (function () {
    var s = $('script[src*="/assets/js/app.js"]');
    return s ? s.getAttribute('src').replace(/js\/app\.js.*$/, '') : '/assets/';
  })();
  var esc = function (s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  };
  var ICON = {
    check: '<svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>',
    x: '<svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18M6 6l12 12"/></svg>',
    alert: '<svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg>',
    trash: '<svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/></svg>',
    up: '<svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m18 15-6-6-6 6"/></svg>',
    down: '<svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>',
    info: '<svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>'
  };

  /* ---------------- Script loader (lazy) ---------------- */
  var loaded = {};
  function loadScript(src) {
    if (!loaded[src]) {
      loaded[src] = new Promise(function (resolve, reject) {
        var s = document.createElement('script');
        s.src = src; s.async = true;
        s.onload = resolve; s.onerror = function () { reject(new Error('Gagal memuat ' + src)); };
        document.head.appendChild(s);
      });
    }
    return loaded[src];
  }

  /* ---------------- Toast ---------------- */
  function toast(message, type) {
    type = type || 'success';
    var wrap = $('.toasts');
    if (!wrap) { wrap = document.createElement('div'); wrap.className = 'toasts'; wrap.setAttribute('role', 'status'); document.body.appendChild(wrap); }
    var el = document.createElement('div');
    el.className = 'alert toast alert-' + type;
    el.innerHTML = (type === 'success' ? ICON.check : type === 'error' ? ICON.x : ICON.info) + '<span>' + esc(message) + '</span>';
    wrap.appendChild(el);
    dismissLater(el, 3500);
  }
  function dismiss(el) { el.classList.add('is-leaving'); setTimeout(function () { el.remove(); }, 300); }
  function dismissLater(el, ms) { setTimeout(function () { if (el.isConnected) dismiss(el); }, ms); }

  /* ---------------- Modal confirm ---------------- */
  function confirmDialog(message, opts) {
    opts = opts || {};
    return new Promise(function (resolve) {
      var root = document.createElement('div');
      root.className = 'modal';
      root.setAttribute('role', 'dialog');
      root.setAttribute('aria-modal', 'true');
      var typeField = opts.typeText
        ? '<p class="small muted mb-1">Ketik <b class="mono">' + esc(opts.typeText) + '</b> untuk konfirmasi:</p><input class="input mono" data-type-input autocomplete="off">'
        : '';
      root.innerHTML = '<div class="modal-box"><div class="modal-icon">' + (opts.danger === false ? ICON.info : ICON.trash) + '</div>' +
        '<h3>' + esc(opts.title || 'Konfirmasi') + '</h3><p class="text-2">' + esc(message) + '</p>' + typeField +
        '<div class="modal-actions"><button type="button" class="btn btn-ghost" data-no>Batal</button>' +
        '<button type="button" class="btn ' + (opts.danger === false ? 'btn-primary' : 'btn-danger') + '" data-yes>' + esc(opts.ok || 'Ya, lanjutkan') + '</button></div></div>';
      document.body.appendChild(root);
      var yes = $('[data-yes]', root), input = $('[data-type-input]', root);
      if (input) { yes.disabled = true; input.addEventListener('input', function () { yes.disabled = input.value.trim() !== opts.typeText; }); setTimeout(function () { input.focus(); }, 50); }
      else { setTimeout(function () { yes.focus(); }, 50); }
      function close(v) { document.removeEventListener('keydown', onKey); root.remove(); resolve(v); }
      function onKey(e) { if (e.key === 'Escape') close(false); if (e.key === 'Enter' && !yes.disabled && (!input || document.activeElement === input)) { e.preventDefault(); close(input ? input.value.trim() : true); } }
      document.addEventListener('keydown', onKey);
      yes.addEventListener('click', function () { close(input ? input.value.trim() : true); });
      $('[data-no]', root).addEventListener('click', function () { close(false); });
      root.addEventListener('click', function (e) { if (e.target === root) close(false); });
    });
  }

  /* ---------------- Theme ---------------- */
  function initTheme() {
    $$('[data-theme-toggle]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var cur = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
        document.documentElement.setAttribute('data-theme', cur);
        try { localStorage.setItem('theme', cur); } catch (e) { /* abaikan */ }
      });
    });
  }

  /* ---------------- Global UI ---------------- */
  function initUI() {
    $$('[data-toast]').forEach(function (el) { dismissLater(el, el.classList.contains('alert-error') ? 8000 : 5000); });
    document.addEventListener('click', function (e) {
      var t = e.target.closest ? e.target : e.target.parentElement;
      if (!t) return;
      var close = t.closest('[data-toast-close]');
      if (close) { dismiss(close.closest('.toast')); return; }

      if (t.closest('[data-sidebar-toggle]')) { document.body.classList.toggle('sidebar-open'); return; }
      if (t.closest('[data-sidebar-close]')) { document.body.classList.remove('sidebar-open'); return; }

      var ddt = t.closest('[data-dropdown-toggle]');
      $$('[data-dropdown].open').forEach(function (d) { if (!ddt || d !== ddt.closest('[data-dropdown]')) d.classList.remove('open'); });
      if (ddt) { ddt.closest('[data-dropdown]').classList.toggle('open'); return; }

      var cp = t.closest('[data-copy]');
      if (cp) { copyText(cp.getAttribute('data-copy')); return; }

      var tg = t.closest('[data-toggle]');
      if (tg) { var target = $(tg.getAttribute('data-toggle')); if (target) target.hidden = !target.hidden; return; }

      if (t.closest('[data-print]')) { window.print(); return; }

      var sh = t.closest('[data-share]');
      if (sh) {
        var url = sh.getAttribute('data-share'), title = sh.getAttribute('data-share-title') || document.title;
        if (navigator.share) { navigator.share({ title: title, url: url }).catch(function () {}); } else { copyText(url); }
        return;
      }

      var qm = t.closest('[data-qr-modal]');
      if (qm) { showQrModal(qm.getAttribute('data-qr-modal')); return; }

      var dl = t.closest('[data-download-qr]');
      if (dl) { downloadTicket(dl); }
    });

    // Konfirmasi sebelum submit form berbahaya
    document.addEventListener('submit', function (e) {
      var form = e.target;
      var trigger = e.submitter && e.submitter.hasAttribute('data-confirm') ? e.submitter : null;
      var msg = trigger ? trigger.getAttribute('data-confirm') : form.getAttribute('data-confirm');
      if (!msg || form.__confirmed) { form.__confirmed = false; return; }
      e.preventDefault();
      var typeText = form.getAttribute('data-confirm-type');
      confirmDialog(msg, { typeText: typeText, title: 'Anda yakin?' }).then(function (ok) {
        if (!ok) return;
        if (typeText) { var inp = form.querySelector('input[name="confirm"]'); if (inp) inp.value = ok; }
        form.__confirmed = true;
        if (e.submitter && e.submitter.name) {
          var h = document.createElement('input'); h.type = 'hidden'; h.name = e.submitter.name; h.value = e.submitter.value; form.appendChild(h);
        }
        HTMLFormElement.prototype.submit.call(form);
      });
    }, true);

    // Cegah double submit + indikator loading
    $$('form[data-loading-form]').forEach(function (form) {
      form.addEventListener('submit', function (e) {
        if (e.defaultPrevented) return;
        if (form.__busy) { e.preventDefault(); return; }
        form.__busy = true;
        $$('button[type="submit"], button:not([type])', form).forEach(function (b) { b.classList.add('is-loading'); });
        $$('button[form="' + form.id + '"]').forEach(function (b) { b.classList.add('is-loading'); });
        setTimeout(function () { form.__busy = false; $$('.is-loading').forEach(function (b) { b.classList.remove('is-loading'); }); }, 8000);
      });
    });

    // Filter otomatis
    $$('form[data-autosubmit]').forEach(function (form) {
      var timer;
      $$('select, input[type="date"]', form).forEach(function (el) { el.addEventListener('change', function () { resetPage(form); form.submit(); }); });
      $$('input[data-debounce]', form).forEach(function (el) {
        el.addEventListener('input', function () { clearTimeout(timer); timer = setTimeout(function () { resetPage(form); form.submit(); }, 550); });
        if (el.value) { el.focus(); var v = el.value; el.value = ''; el.value = v; }
      });
    });

    // Tampilkan password
    $$('[data-toggle-password]').forEach(function (cb) {
      cb.addEventListener('change', function () { var i = $(cb.getAttribute('data-toggle-password')); if (i) i.type = cb.checked ? 'text' : 'password'; });
    });

    // Pilih semua (bulk)
    var all = $('[data-check-all]'), bar = $('[data-bulkbar]');
    if (all && bar) {
      var items = $$('[data-check-item]');
      var sync = function () {
        var n = items.filter(function (i) { return i.checked; }).length;
        $('[data-bulk-count]', bar).textContent = n;
        bar.classList.toggle('show', n > 0);
        all.checked = n > 0 && n === items.length;
        all.indeterminate = n > 0 && n < items.length;
      };
      all.addEventListener('change', function () { items.forEach(function (i) { i.checked = all.checked; }); sync(); });
      items.forEach(function (i) { i.addEventListener('change', sync); });
    }

    // Slug otomatis dari judul
    $$('[data-slug-source]').forEach(function (src) {
      var slug = $(src.getAttribute('data-slug-source')), preview = $('[data-slug-preview]');
      if (!slug) return;
      var manual = slug.hasAttribute('data-slug-locked') || slug.value !== '';
      var upd = function () { if (preview) preview.textContent = slug.value || slugify(src.value); };
      slug.addEventListener('input', function () { manual = slug.value !== ''; slug.value = slug.value.toLowerCase().replace(/[^a-z0-9-]/g, '-').replace(/-+/g, '-'); upd(); });
      src.addEventListener('input', function () { if (!manual) { slug.value = ''; } upd(); });
      upd();
    });
  }

  function resetPage(form) { var p = form.querySelector('input[name="page"]'); if (p) p.remove(); }
  function slugify(s) { return String(s).toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 60); }

  function copyText(text) {
    var done = function () { toast('Tautan disalin ke clipboard'); };
    if (navigator.clipboard && window.isSecureContext) {
      navigator.clipboard.writeText(text).then(done, function () { fallbackCopy(text); done(); });
    } else { fallbackCopy(text); done(); }
  }
  function fallbackCopy(text) {
    var ta = document.createElement('textarea'); ta.value = text; ta.style.position = 'fixed'; ta.style.opacity = '0';
    document.body.appendChild(ta); ta.select(); try { document.execCommand('copy'); } catch (e) { /* abaikan */ } ta.remove();
  }

  /* ---------------- QR code ---------------- */
  function withQr() { return window.qrcode ? Promise.resolve(window.qrcode) : loadScript(assetBase + 'js/vendor/qrcode.min.js').then(function () { return window.qrcode; }); }

  function qrCanvas(text, size) {
    var qr = window.qrcode(0, 'M');
    qr.addData(text, 'Byte');
    qr.make();
    var n = qr.getModuleCount(), quiet = 2, cell = Math.max(2, Math.floor(size / (n + quiet * 2)));
    var px = cell * (n + quiet * 2);
    var c = document.createElement('canvas'); c.width = px; c.height = px;
    var ctx = c.getContext('2d');
    ctx.fillStyle = '#fff'; ctx.fillRect(0, 0, px, px);
    ctx.fillStyle = '#1e1b3a';
    for (var r = 0; r < n; r++) for (var col = 0; col < n; col++) if (qr.isDark(r, col)) ctx.fillRect((col + quiet) * cell, (r + quiet) * cell, cell, cell);
    return c;
  }

  function initQr() {
    var boxes = $$('[data-qr]');
    if (!boxes.length) return;
    withQr().then(function () {
      boxes.forEach(function (box) {
        var c = qrCanvas(box.getAttribute('data-qr'), 400);
        c.setAttribute('role', 'img'); c.setAttribute('aria-label', 'QR code');
        box.innerHTML = ''; box.appendChild(c);
      });
    }).catch(function () { boxes.forEach(function (b) { b.textContent = 'QR tidak dapat dimuat'; }); });
  }

  function showQrModal(text) {
    withQr().then(function () {
      var root = document.createElement('div');
      root.className = 'modal';
      root.innerHTML = '<div class="modal-box text-center"><h3>QR code pendaftaran</h3><p class="muted small">Cetak di poster/banner agar peserta bisa scan & daftar.</p>' +
        '<div class="qr-box" style="width:260px;height:260px"></div><p class="mono small" style="word-break:break-all">' + esc(text) + '</p>' +
        '<div class="modal-actions" style="justify-content:center"><button class="btn btn-ghost" data-close>Tutup</button><a class="btn btn-primary" download="qr-pendaftaran.png">Unduh PNG</a></div></div>';
      document.body.appendChild(root);
      var c = qrCanvas(text, 1000);
      var preview = qrCanvas(text, 400);
      $('.qr-box', root).appendChild(preview);
      $('a[download]', root).href = c.toDataURL('image/png');
      var close = function () { root.remove(); };
      $('[data-close]', root).addEventListener('click', close);
      root.addEventListener('click', function (e) { if (e.target === root) close(); });
    });
  }

  function roundRect(ctx, x, y, w, h, r) {
    ctx.beginPath(); ctx.moveTo(x + r, y); ctx.arcTo(x + w, y, x + w, y + h, r); ctx.arcTo(x + w, y + h, x, y + h, r);
    ctx.arcTo(x, y + h, x, y, r); ctx.arcTo(x, y, x + w, y, r); ctx.closePath();
  }
  function wrapText(ctx, text, x, y, maxW, lh, maxLines) {
    var words = String(text).split(/\s+/), lines = [], line = '';
    words.forEach(function (w) {
      var test = line ? line + ' ' + w : w;
      if (ctx.measureText(test).width > maxW && line) { lines.push(line); line = w; } else line = test;
    });
    if (line) lines.push(line);
    if (lines.length > maxLines) {
      lines = lines.slice(0, maxLines);
      var last = lines[maxLines - 1];
      while (last.length && ctx.measureText(last + '…').width > maxW) last = last.slice(0, -1);
      lines[maxLines - 1] = last + '…';
    }
    lines.forEach(function (l, i) { ctx.fillText(l, x, y + i * lh); });
    return y + lines.length * lh;
  }

  /** Buat gambar PNG tiket lengkap (bukan sekadar QR). */
  function downloadTicket(btn) {
    withQr().then(function () {
      var box = $('[data-qr]'); if (!box) return;
      var W = 900, H = 1300, cs = getComputedStyle(document.body);
      var g1 = cs.getPropertyValue('--g1').trim() || '#7c3aed', g2 = cs.getPropertyValue('--g2').trim() || '#c026d3', g3 = cs.getPropertyValue('--g3').trim() || '#f472b6';
      var c = document.createElement('canvas'); c.width = W; c.height = H;
      var ctx = c.getContext('2d');
      ctx.fillStyle = '#f5f3ff'; ctx.fillRect(0, 0, W, H);
      roundRect(ctx, 40, 40, W - 80, H - 80, 48); ctx.fillStyle = '#fff'; ctx.fill();
      ctx.save(); roundRect(ctx, 40, 40, W - 80, 380, 48); ctx.clip();
      var grad = ctx.createLinearGradient(40, 40, W - 40, 420); grad.addColorStop(0, g1); grad.addColorStop(.55, g2); grad.addColorStop(1, g3);
      ctx.fillStyle = grad; ctx.fillRect(40, 40, W - 80, 380);
      ctx.fillStyle = 'rgba(255,255,255,.14)'; ctx.beginPath(); ctx.arc(W - 120, 60, 160, 0, Math.PI * 2); ctx.fill();
      ctx.restore();
      ctx.fillStyle = 'rgba(255,255,255,.85)'; ctx.font = '700 26px "Plus Jakarta Sans", sans-serif'; ctx.fillText('E-TIKET PESERTA', 100, 130);
      ctx.fillStyle = '#fff'; ctx.font = '800 50px "Plus Jakarta Sans", sans-serif';
      wrapText(ctx, btn.getAttribute('data-ticket-event') || '', 100, 205, W - 200, 62, 3);
      ctx.setLineDash([14, 12]); ctx.strokeStyle = '#d6d3ea'; ctx.lineWidth = 4; ctx.beginPath(); ctx.moveTo(90, 460); ctx.lineTo(W - 90, 460); ctx.stroke(); ctx.setLineDash([]);
      var q = qrCanvas(box.getAttribute('data-qr'), 520);
      ctx.drawImage(q, (W - 520) / 2, 500, 520, 520);
      ctx.fillStyle = '#1e1b3a'; ctx.textAlign = 'center';
      ctx.font = '800 58px ui-monospace, Menlo, Consolas, monospace'; ctx.fillText((btn.getAttribute('data-ticket-code') || '').split('').join(' '), W / 2, 1100);
      ctx.font = '800 40px "Plus Jakarta Sans", sans-serif'; ctx.fillText(btn.getAttribute('data-ticket-name') || '', W / 2, 1170, W - 160);
      ctx.fillStyle = '#7c8198'; ctx.font = '500 26px "Plus Jakarta Sans", sans-serif'; ctx.fillText('Tunjukkan QR ini saat registrasi ulang', W / 2, 1215);
      var a = document.createElement('a');
      a.download = btn.getAttribute('data-download-qr') || 'tiket.png';
      a.href = c.toDataURL('image/png');
      document.body.appendChild(a); a.click(); a.remove();
      toast('Tiket disimpan sebagai gambar');
    });
  }

  /* ---------------- Auto-redirect ke grup WA ---------------- */
  function initAutoRedirect() {
    var link = $('[data-autoredirect]');
    if (!link) return;
    var n = parseInt(link.getAttribute('data-autoredirect'), 10) || 0, out = $('[data-countdown]');
    var cancelled = false;
    link.addEventListener('click', function () { cancelled = true; var l = $('[data-countdown-label]'); if (l) l.hidden = true; });
    var t = setInterval(function () {
      if (cancelled) { clearInterval(t); return; }
      n--; if (out) out.textContent = n;
      if (n <= 0) { clearInterval(t); window.location.href = link.href; }
    }, 1000);
  }

  /* ---------------- Confetti ---------------- */
  function initConfetti() {
    if (!$('[data-confetti]') || matchMedia('(prefers-reduced-motion: reduce)').matches) return;
    var c = document.createElement('canvas'); c.className = 'confetti'; document.body.appendChild(c);
    var ctx = c.getContext('2d'), W = c.width = innerWidth, H = c.height = innerHeight;
    var colors = ['#7c3aed', '#db2777', '#f59e0b', '#10b981', '#0ea5e9', '#f472b6'];
    var parts = [];
    for (var i = 0; i < 160; i++) parts.push({ x: W / 2 + (Math.random() - .5) * 120, y: H * .35, vx: (Math.random() - .5) * 16, vy: -Math.random() * 16 - 4, s: Math.random() * 8 + 4, r: Math.random() * 6, vr: (Math.random() - .5) * .3, c: colors[i % colors.length] });
    var start = performance.now();
    (function frame(now) {
      ctx.clearRect(0, 0, W, H);
      parts.forEach(function (p) { p.vy += .45; p.vx *= .99; p.x += p.vx; p.y += p.vy; p.r += p.vr; ctx.save(); ctx.translate(p.x, p.y); ctx.rotate(p.r); ctx.fillStyle = p.c; ctx.fillRect(-p.s / 2, -p.s / 4, p.s, p.s / 2); ctx.restore(); });
      if (now - start < 3500) requestAnimationFrame(frame); else c.remove();
    })(start);
  }

  /* ---------------- Form builder (kolom tambahan) ---------------- */
  function initBuilder() {
    var wrap = $('[data-builder]'), input = $('[data-builder-input]'), add = $('[data-builder-add]');
    if (!wrap || !input) return;
    var types = JSON.parse(wrap.getAttribute('data-types') || '{}');
    var fields;
    try { fields = JSON.parse(input.value || '[]'); } catch (e) { fields = []; }
    if (!Array.isArray(fields)) fields = [];
    var needsOpts = function (t) { return t === 'select' || t === 'radio' || t === 'checkbox'; };

    function save() { input.value = JSON.stringify(fields); }
    function render() {
      wrap.innerHTML = '';
      if (!fields.length) wrap.innerHTML = '<div class="muted small">Belum ada kolom tambahan. Contoh: Jabatan, Ukuran kaos, Sesi yang dipilih.</div>';
      fields.forEach(function (f, i) {
        var el = document.createElement('div');
        el.className = 'builder-item' + (needsOpts(f.type) ? ' has-opts' : '');
        var opts = Object.keys(types).map(function (k) { return '<option value="' + k + '"' + (f.type === k ? ' selected' : '') + '>' + esc(types[k]) + '</option>'; }).join('');
        el.innerHTML =
          '<div class="row"><input class="input input-sm" data-k="label" placeholder="Label pertanyaan, mis. Jabatan" maxlength="100" value="' + esc(f.label || '') + '">' +
          '<select class="select select-sm" data-k="type">' + opts + '</select>' +
          '<label class="switch" style="font-size:.82rem"><input type="checkbox" data-k="required"' + (f.required ? ' checked' : '') + '> Wajib</label></div>' +
          '<div class="opts"><textarea class="textarea" data-k="options" rows="3" placeholder="Satu pilihan per baris">' + esc((f.options || []).join('\n')) + '</textarea></div>' +
          '<div class="tools"><input class="input input-sm grow" data-k="placeholder" placeholder="Placeholder (opsional)" maxlength="120" value="' + esc(f.placeholder || '') + '">' +
          '<button type="button" class="btn btn-sm btn-ghost" data-act="up" title="Naik">' + ICON.up + '</button>' +
          '<button type="button" class="btn btn-sm btn-ghost" data-act="down" title="Turun">' + ICON.down + '</button>' +
          '<button type="button" class="btn btn-sm btn-ghost text-danger" data-act="del" title="Hapus">' + ICON.trash + '</button></div>';
        el.addEventListener('input', function (e) {
          var k = e.target.getAttribute('data-k'); if (!k) return;
          if (k === 'required') f.required = e.target.checked;
          else if (k === 'options') f.options = e.target.value.split('\n').map(function (s) { return s.trim(); }).filter(Boolean);
          else f[k] = e.target.value;
          if (k === 'type') el.classList.toggle('has-opts', needsOpts(f.type));
          save();
        });
        el.addEventListener('change', function (e) { if (e.target.getAttribute('data-k') === 'type') { f.type = e.target.value; el.classList.toggle('has-opts', needsOpts(f.type)); save(); } });
        el.addEventListener('click', function (e) {
          var b = e.target.closest('[data-act]'); if (!b) return;
          var act = b.getAttribute('data-act');
          if (act === 'del') fields.splice(i, 1);
          if (act === 'up' && i > 0) fields.splice(i - 1, 0, fields.splice(i, 1)[0]);
          if (act === 'down' && i < fields.length - 1) fields.splice(i + 1, 0, fields.splice(i, 1)[0]);
          save(); render();
        });
        wrap.appendChild(el);
      });
    }
    add.addEventListener('click', function () {
      fields.push({ label: '', type: 'text', required: false, options: [], placeholder: '' });
      save(); render();
      var last = wrap.lastElementChild; if (last) { var l = $('[data-k="label"]', last); if (l) l.focus(); }
    });
    render();
  }

  /* ---------------- Check-in scanner ---------------- */
  function initCheckin() {
    var root = $('[data-checkin]');
    if (!root) return;
    var storeUrl = root.getAttribute('data-store-url'), searchUrl = root.getAttribute('data-search-url');
    var eventId = root.getAttribute('data-event-id') || '0';
    var video = $('[data-video]', root), result = $('[data-scan-result]', root);
    var btnStart = $('[data-scan-start]', root), btnStop = $('[data-scan-stop]', root), btnFlip = $('[data-scan-flip]', root);
    var stream = null, running = false, detector = null, facing = 'environment', lastCode = '', lastAt = 0, busy = false;
    var canvas = document.createElement('canvas'), cctx = canvas.getContext('2d', { willReadFrequently: true });
    var audioCtx = null;

    function beep(ok) {
      if (!$('[data-scan-sound]', root) || !$('[data-scan-sound]', root).checked) return;
      try {
        audioCtx = audioCtx || new (window.AudioContext || window.webkitAudioContext)();
        var o = audioCtx.createOscillator(), g = audioCtx.createGain();
        o.frequency.value = ok ? 880 : 220; o.type = 'sine'; g.gain.value = .15;
        o.connect(g); g.connect(audioCtx.destination); o.start(); o.stop(audioCtx.currentTime + (ok ? .15 : .35));
        if (navigator.vibrate) navigator.vibrate(ok ? 80 : [60, 60, 60]);
      } catch (e) { /* abaikan */ }
    }

    function show(data) {
      var cls = data.ok ? 'ok' : (data.status === 'already' || data.status === 'wrong_event' ? 'warn' : 'err');
      var reg = data.registration || {};
      result.innerHTML = '<div class="scan-result ' + cls + '"><div class="ic">' + (data.ok ? ICON.check : cls === 'warn' ? ICON.alert : ICON.x) + '</div>' +
        '<div class="grow"><div class="t">' + esc(reg.name || (data.ok ? 'Berhasil' : 'Gagal')) + '</div>' +
        '<div class="s">' + esc(data.message || '') + (reg.representative ? ' · ' + esc(reg.representative) : '') + '</div>' +
        (reg.url ? '<a class="small" href="' + esc(reg.url) + '">Lihat detail →</a>' : '') + '</div></div>';
      if (data.stats) {
        var s = data.stats;
        var set = function (sel, v) { var el = $(sel, root); if (el) el.textContent = v; };
        set('[data-stat-in]', s.checked_in.toLocaleString('id-ID'));
        set('[data-stat-total]', s.total.toLocaleString('id-ID'));
        set('[data-stat-rate]', s.total ? Math.round(s.checked_in / s.total * 100) : 0);
      }
      beep(!!data.ok);
    }

    function submit(code) {
      if (busy) return Promise.resolve();
      busy = true;
      var body = new URLSearchParams(); body.set('code', code); body.set('event_id', eventId); body.set('_token', csrf());
      return fetch(storeUrl, { method: 'POST', body: body, credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf() } })
        .then(function (r) { return r.json().catch(function () { return { ok: false, message: 'Respons server tidak valid (' + r.status + ')' }; }); })
        .then(show)
        .catch(function () { show({ ok: false, message: 'Koneksi terputus. Coba lagi.' }); })
        .then(function () { busy = false; });
    }

    function onCode(code) {
      var now = Date.now();
      if (!code || (code === lastCode && now - lastAt < 4000)) return;
      lastCode = code; lastAt = now;
      submit(code);
    }

    function tick() {
      if (!running) return;
      if (video.readyState >= 2) {
        if (detector) {
          detector.detect(video).then(function (codes) { if (codes && codes[0]) onCode(codes[0].rawValue); }).catch(function () {}).then(function () { setTimeout(tick, 250); });
          return;
        }
        if (window.jsQR) {
          var w = video.videoWidth, h = video.videoHeight, scale = Math.min(1, 640 / Math.max(w, h));
          canvas.width = w * scale; canvas.height = h * scale;
          cctx.drawImage(video, 0, 0, canvas.width, canvas.height);
          var img = cctx.getImageData(0, 0, canvas.width, canvas.height);
          var res = window.jsQR(img.data, img.width, img.height, { inversionAttempts: 'dontInvert' });
          if (res && res.data) onCode(res.data);
        }
      }
      setTimeout(tick, 200);
    }

    function start() {
      if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        show({ ok: false, message: 'Browser tidak mendukung kamera. Gunakan HTTPS dan browser terbaru, atau input kode manual.' });
        return;
      }
      var ready = ('BarcodeDetector' in window)
        ? window.BarcodeDetector.getSupportedFormats().then(function (f) { if (f.indexOf('qr_code') >= 0) detector = new window.BarcodeDetector({ formats: ['qr_code'] }); else return loadScript(root.getAttribute('data-jsqr')); })
        : loadScript(root.getAttribute('data-jsqr'));
      btnStart.classList.add('is-loading');
      Promise.resolve(ready).then(function () {
        return navigator.mediaDevices.getUserMedia({ video: { facingMode: facing, width: { ideal: 1280 }, height: { ideal: 1280 } }, audio: false });
      }).then(function (s) {
        stream = s; video.srcObject = s; video.hidden = false;
        return video.play();
      }).then(function () {
        running = true;
        $('[data-placeholder]', root).hidden = true; $('[data-frame]', root).hidden = false; $('[data-laser]', root).hidden = false;
        btnStart.hidden = true; btnStop.hidden = false; btnFlip.hidden = false;
        tick();
      }).catch(function (err) {
        show({ ok: false, message: 'Kamera tidak dapat diakses: ' + (err && err.name === 'NotAllowedError' ? 'izin ditolak. Izinkan akses kamera di browser.' : (err && err.message) || 'tidak diketahui') });
      }).then(function () { btnStart.classList.remove('is-loading'); });
    }
    function stop() {
      running = false;
      if (stream) stream.getTracks().forEach(function (t) { t.stop(); });
      stream = null; video.hidden = true;
      $('[data-placeholder]', root).hidden = false; $('[data-frame]', root).hidden = true; $('[data-laser]', root).hidden = true;
      btnStart.hidden = false; btnStop.hidden = true; btnFlip.hidden = true;
    }
    btnStart.addEventListener('click', start);
    btnStop.addEventListener('click', stop);
    btnFlip.addEventListener('click', function () { facing = facing === 'environment' ? 'user' : 'environment'; stop(); start(); });
    document.addEventListener('visibilitychange', function () { if (document.hidden && running) stop(); });

    var manual = $('[data-manual-form]', root);
    manual.addEventListener('submit', function (e) {
      e.preventDefault();
      var inp = manual.querySelector('input[name="code"]');
      var code = inp.value.trim();
      if (!code) return;
      submit(code).then(function () { inp.value = ''; inp.focus(); });
    });

    var live = $('[data-live-search]', root), box = $('[data-search-results]', root), timer;
    live.addEventListener('input', function () {
      clearTimeout(timer);
      var q = live.value.trim();
      if (q.length < 2) { box.innerHTML = ''; return; }
      timer = setTimeout(function () {
        var url = searchUrl + (searchUrl.indexOf('?') >= 0 ? '&' : '?') + 'q=' + encodeURIComponent(q) + '&event=' + encodeURIComponent(eventId);
        fetch(url, { credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
          .then(function (r) { return r.json(); })
          .then(function (d) {
            if (!d.results || !d.results.length) { box.innerHTML = '<div class="muted small">Tidak ditemukan.</div>'; return; }
            box.innerHTML = d.results.map(function (r) {
              return '<div class="sr-item"><span class="avatar">' + esc((r.name || '?').charAt(0).toUpperCase()) + '</span>' +
                '<div class="grow"><b>' + esc(r.name) + '</b><div class="small muted">' + esc(r.wa) + (r.representative ? ' · ' + esc(r.representative) : '') + ' · <span class="mono">' + esc(r.code) + '</span></div></div>' +
                (r.checked_in_at ? '<span class="badge badge-success">Hadir</span>' : '<button type="button" class="btn btn-sm btn-success" data-checkin-code="' + esc(r.code) + '">' + ICON.check + ' Hadir</button>') + '</div>';
            }).join('');
          }).catch(function () { box.innerHTML = '<div class="small text-danger">Gagal memuat hasil.</div>'; });
      }, 300);
    });
    box.addEventListener('click', function (e) {
      var b = e.target.closest('[data-checkin-code]'); if (!b) return;
      b.classList.add('is-loading');
      submit(b.getAttribute('data-checkin-code')).then(function () { live.dispatchEvent(new Event('input')); });
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    initTheme();
    initUI();
    initQr();
    initAutoRedirect();
    initConfetti();
    initBuilder();
    initCheckin();
  });
})();
