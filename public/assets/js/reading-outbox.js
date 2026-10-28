/**
 * reading-outbox.js — Meter Reader only. Recording works with slow or no internet:
 *
 *  - Save Reading puts the reading in an outbox on this phone (localStorage) at once;
 *    it is then sent to the server in the background, one by one, retrying until it
 *    gets through. Each reading has its own code (client_ref), so a resend after a
 *    dropped connection never makes a second reading or bill.
 *  - "Download my puroks" keeps the meters of the reader's puroks on the phone, so the
 *    Record Reading form can find a meter and its previous reading without internet.
 *
 * Readings come from a photo of the meter, read on the server in the form; the item carries the
 * photo's name (photo). Without internet the photo can't be read: the meter reader types the
 * meter number and reading (typed: true) and the photo itself is kept in IndexedDB (too big for
 * localStorage) until the item is sent. The server saves what was typed and checks the photo later.
 *
 * Statuses: pending (on the phone) → sending → sent, or error (the server refused it).
 * Runs on every page of the Meter Reader (the layout loads it), so sending continues
 * while they move around AWAS. Exposes window.AwasOutbox for the Record Reading form.
 */
(function () {
  const cfg = window.AWAS_OUTBOX;
  if (!cfg) return;

  const OUTBOX_KEY = 'awas.outbox.' + cfg.userId;
  const METERS_KEY = 'awas.meters.' + cfg.userId;
  const KEEP_SENT_MS = 2 * 24 * 3600 * 1000;    // sent readings stay in the list for 2 days
  const AUTO_DOWNLOAD_MS = 30 * 60 * 1000;      // refresh the meter list every 30 minutes when online
  const TIMEOUT_MS = 45000;                     // slow internet: give each send time to finish

  // ---- Storage (can be blocked, e.g. private mode: then the form saves the old way) ----
  function read(key, fallback) {
    try { const v = localStorage.getItem(key); return v ? JSON.parse(v) : fallback; } catch (e) { return fallback; }
  }
  function write(key, value) {
    try { localStorage.setItem(key, JSON.stringify(value)); return true; } catch (e) { return false; }
  }
  const available = (function () {
    try { localStorage.setItem('awas.test', '1'); localStorage.removeItem('awas.test'); return true; } catch (e) { return false; }
  })();
  if (!available) { window.AwasOutbox = { available: false }; return; }

  const listeners = [];
  const changed = () => { updateBadge(); renderPanel(); listeners.forEach(fn => { try { fn(); } catch (e) {} }); };
  const items = () => read(OUTBOX_KEY, []);
  const saveItems = list => write(OUTBOX_KEY, list);

  function updateItem(ref, patch) {
    const list = items();
    const it = list.find(i => i.ref === ref);
    if (it) { Object.assign(it, patch); saveItems(list); }
  }

  function newRef() {
    if (window.crypto && crypto.randomUUID) return crypto.randomUUID();
    return 'r' + Date.now().toString(36) + '-' + Math.random().toString(36).slice(2, 12);
  }

  const periodLabel = p => {
    const [y, m] = String(p).split('-').map(Number);
    return (y && m) ? new Date(y, m - 1, 1).toLocaleString('en-US', { month: 'long', year: 'numeric' }) : p;
  };

  // ---- Photos of typed readings (IndexedDB, one per item ref) ------------------------
  let photoDb = null;
  function photos() {
    if (!photoDb) {
      photoDb = new Promise((resolve, reject) => {
        if (!window.indexedDB) { reject(new Error('no indexedDB')); return; }
        const open = indexedDB.open('awas-outbox-photos', 1);
        open.onupgradeneeded = () => open.result.createObjectStore('photos');
        open.onsuccess = () => resolve(open.result);
        open.onerror = () => reject(open.error);
      });
      photoDb.catch(() => { photoDb = null; });
    }
    return photoDb;
  }
  function photoTx(mode, fn) {
    return photos().then(db => new Promise((resolve, reject) => {
      const tx = db.transaction('photos', mode);
      const req = fn(tx.objectStore('photos'));
      tx.oncomplete = () => resolve(req ? req.result : undefined);
      tx.onerror = tx.onabort = () => reject(tx.error);
    }));
  }
  const putPhoto = (ref, dataUrl) => photoTx('readwrite', s => s.put(dataUrl, ref));
  const getPhoto = ref => photoTx('readonly', s => s.get(ref));
  const deletePhoto = ref => photoTx('readwrite', s => s.delete(ref)).catch(() => {});
  /** Can typed readings keep their photo on this phone? */
  const canKeepPhotos = () => photos().then(() => true, () => false);

  // ---- Downloaded meters ------------------------------------------------------------
  const meterData = () => read(METERS_KEY, null);

  /**
   * Readings on the phone that the downloaded list doesn't have yet: their meter's previous reading moves forward.
   * Sent readings count only when sent after the download started (a later download already has them).
   */
  function applyOutbox(data, since) {
    if (!data) return data;
    items().filter(i => i.status === 'pending' || i.status === 'sending' || (i.status === 'sent' && i.sentAt >= since)).sort((a, b) => a.savedAt - b.savedAt).forEach(i => {
      const c = data.consumers.find(x => x.meter === i.meter);
      if (!c || c.read_periods.includes(i.period)) return;
      c.read_periods.unshift(i.period);
      c.previous_reading = i.curr;
      c.last_period = periodLabel(i.period);
    });
    return data;
  }

  let downloading = null;
  function download() {
    if (downloading) return downloading;
    const startedAt = Date.now();
    downloading = fetchJson(cfg.offlineUrl, { method: 'GET' })
      .then(res => {
        if (!res.ok || !res.data || !Array.isArray(res.data.consumers)) throw new Error('download');
        const data = res.data;
        data.saved_at = Date.now();
        write(METERS_KEY, applyOutbox(data, startedAt));
        return data;
      })
      .finally(() => { downloading = null; changed(); });
    return downloading;
  }

  function findMeter(meter) {
    const data = meterData();
    return data ? data.consumers.find(c => c.meter === meter) || null : null;
  }

  function searchMeters(q) {
    const data = meterData();
    if (!data) return null;
    q = q.toLowerCase();
    return data.consumers.filter(c => c.name.toLowerCase().includes(q) || c.meter.startsWith(q)).slice(0, 8);
  }

  // ---- Outbox -----------------------------------------------------------------------
  /** Save a reading on the phone; it is sent in the background. */
  function add(r) {
    const item = {
      ref: r.ref || newRef(), meter: r.meter || '', name: r.name || '', period: r.period, prev: r.prev, curr: r.curr,
      date: r.date, remarks: r.remarks || '', status: 'pending', message: '', total: null,
      savedAt: Date.now(), sentAt: null,
      photo: r.photo || null, typed: !!r.typed,
    };
    const list = items();
    list.push(item);
    if (!saveItems(list)) return null;
    const data = meterData();
    if (data) write(METERS_KEY, applyOutbox(data, Infinity));
    changed();
    kick(0);
    return item;
  }

  /** A reading typed without internet, with its photo (a data URL). Resolves to the item, or null if it couldn't be kept. */
  function addTyped(r, photoData) {
    const ref = newRef();
    return putPhoto(ref, photoData).then(() => {
      const item = add(Object.assign({}, r, { ref, typed: true, photo: null }));
      if (!item) deletePhoto(ref);
      return item;
    }).catch(() => null);
  }

  function remove(ref) { saveItems(items().filter(i => i.ref !== ref)); deletePhoto(ref); changed(); }

  function counts() {
    const list = items();
    return {
      waiting: list.filter(i => i.status === 'pending' || i.status === 'sending').length,
      errors: list.filter(i => i.status === 'error').length,
    };
  }

  // ---- Sending ----------------------------------------------------------------------
  let busy = false, timer = null, delay = 5000, needLogin = false, lastTry = null;

  function fetchJson(url, opts) {
    const ctrl = window.AbortController ? new AbortController() : null;
    const t = ctrl ? setTimeout(() => ctrl.abort(), TIMEOUT_MS) : null;
    return fetch(url, Object.assign({
      headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': cfg.csrf },
      credentials: 'same-origin',
      signal: ctrl ? ctrl.signal : undefined,
    }, opts))
      .then(r => r.json().catch(() => null).then(data => ({ ok: r.ok, status: r.status, data })))
      .finally(() => { if (t) clearTimeout(t); });
  }

  function kick(wait) {
    clearTimeout(timer);
    timer = setTimeout(run, wait);
  }

  function run() {
    if (busy || needLogin) return;
    // One tab sends at a time (the server would ignore a double send anyway).
    if (navigator.locks && navigator.locks.request) {
      navigator.locks.request('awas-outbox-' + cfg.userId, { ifAvailable: true }, lock => lock ? sendAll() : null);
    } else {
      sendAll();
    }
  }

  async function sendAll() {
    busy = true;
    try {
      let next;
      while ((next = items().find(i => i.status === 'pending' || i.status === 'sending'))) {
        updateItem(next.ref, { status: 'sending' }); changed();
        lastTry = Date.now();
        const body = {
          client_ref: next.ref, billing_period: next.period, reading_date: next.date, remarks: next.remarks,
          photo: next.photo,
        };
        if (next.typed) {
          const photoData = await getPhoto(next.ref).catch(() => null);
          if (!photoData) {
            updateItem(next.ref, { status: 'error', message: 'The photo of this reading is no longer on this phone. Record it again.' });
            changed();
            continue;
          }
          Object.assign(body, { photo_data: photoData, typed_meter: next.meter, typed_reading: next.curr });
        }
        let res;
        try {
          res = await fetchJson(cfg.syncUrl, { method: 'POST', body: JSON.stringify(body) });
        } catch (e) {
          res = null;   // no internet / timed out
        }

        if (res && (res.status === 401 || res.status === 419)) {
          // Signed out (idle timeout) or the page is too old: log in again, readings stay on the phone.
          updateItem(next.ref, { status: 'pending' });
          needLogin = true;
          changed();
          return;
        }
        if (res && res.ok && res.data && res.data.status === 'saved') {
          const d = res.data;
          updateItem(next.ref, { status: 'sent', sentAt: Date.now(), total: d.total, message: '' });
          if (next.typed) deletePhoto(next.ref);
          delay = 5000;
          changed();
          continue;
        }
        if (res && res.data && res.data.status === 'error') {
          updateItem(next.ref, { status: 'error', message: res.data.message });
          if (next.typed) deletePhoto(next.ref);
          changed();
          continue;
        }
        if (res && res.status === 403) {
          updateItem(next.ref, { status: 'error', message: (res.data && res.data.error) || 'You are not allowed to record this reading.' });
          changed();
          continue;
        }
        // Slow / no internet or the server is busy: try again later.
        updateItem(next.ref, { status: 'pending' });
        changed();
        kick(delay);
        delay = Math.min(delay * 2, 60000);
        return;
      }
    } finally {
      busy = false;
      prune();
    }
  }

  function prune() {
    const now = Date.now();
    const list = items();
    const keep = list.filter(i => i.status !== 'sent' || now - (i.sentAt || 0) < KEEP_SENT_MS);
    if (keep.length !== list.length) { saveItems(keep); changed(); }
  }

  // ---- Topbar badge -----------------------------------------------------------------
  function updateBadge() {
    const el = document.getElementById('outboxBadge');
    if (!el) return;
    const c = counts();
    if (!c.waiting && !c.errors && !needLogin) { el.hidden = true; return; }
    el.hidden = false;
    el.className = 'outbox-badge ' + (needLogin || c.errors ? 'is-error' : 'is-waiting');
    el.querySelector('span').textContent = needLogin ? 'Log in to send ' + c.waiting
      : c.waiting ? c.waiting + ' pending' : c.errors + ' need' + (c.errors === 1 ? 's' : '') + ' attention';
  }

  // ---- "Readings on this phone" panel (Meter Readings page) -------------------------
  const esc = s => String(s == null ? '' : s).replace(/[&<>"]/g, ch => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[ch]));
  const peso = n => '₱' + Number(n).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  const time = ts => new Date(ts).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });
  const when = ts => {
    const d = new Date(ts), today = new Date();
    return d.toDateString() === today.toDateString() ? time(ts) : d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' }) + ', ' + time(ts);
  };
  const STATUS = {
    pending: ['badge-warning', 'ph-clock', 'Pending'],
    sending: ['badge-info', 'ph-arrows-clockwise', 'Sending…'],
    sent:    ['badge-success', 'ph-check-circle', 'Sent'],
    error:   ['badge-danger', 'ph-warning', 'Needs attention'],
  };

  function renderPanel() {
    const panel = document.getElementById('outboxPanel');
    if (!panel) return;
    const data = meterData();
    const c = counts();
    const order = { error: 0, sending: 1, pending: 2, sent: 3 };
    const list = items().sort((a, b) => order[a.status] - order[b.status] || b.savedAt - a.savedAt);

    let status;
    if (needLogin) {
      status = '<div class="alert alert-danger py-2 mb-2"><strong>Your session ended.</strong> ' + c.waiting
        + ' reading' + (c.waiting === 1 ? ' is' : 's are') + ' safe on this phone. <a href="' + esc(cfg.loginUrl) + '">Log in again</a> to send them.</div>';
    } else if (c.waiting) {
      status = '<div class="text-muted small mb-2">' + (navigator.onLine === false ? 'No internet — ' : '')
        + c.waiting + ' waiting to send. They are kept on this phone and sent automatically'
        + (lastTry ? ' (last try ' + time(lastTry) + ')' : '') + '.</div>';
    } else {
      status = '<div class="text-muted small mb-2">All readings on this phone have been sent.</div>';
    }

    const meters = data
      ? '<i class="ph ph-download-simple" aria-hidden="true"></i> ' + data.consumers.length + ' meters downloaded · ' + when(data.saved_at)
      : '<span class="text-danger"><i class="ph ph-warning" aria-hidden="true"></i> Meters not downloaded yet — tap Download while you have internet.</span>';

    const rows = list.map(i => {
      const s = STATUS[i.status] || STATUS.pending;
      const used = (Number(i.curr) - Number(i.prev)).toFixed(2);
      return '<div class="list-group-item d-flex flex-wrap justify-content-between align-items-center gap-2">'
        + '<div><strong>Meter ' + esc(i.meter) + '</strong>' + (i.name ? ' · ' + esc(i.name) : '')
        + '<div class="text-muted" style="font-size:12px;">' + esc(periodLabel(i.period)) + ' · '
        + esc(Number(i.prev).toFixed(2)) + ' → ' + esc(Number(i.curr).toFixed(2)) + ' (' + used + ' m³)'
        + (i.total != null ? ' · ' + peso(i.total) : '') + ' · saved ' + when(i.savedAt) + '</div>'
        + (i.status === 'error' ? '<div class="text-danger" style="font-size:12px;">' + esc(i.message) + '</div>' : '')
        + '</div>'
        + '<div class="d-flex align-items-center gap-2"><span class="badge ' + s[0] + '"><i class="ph ' + s[1] + '" aria-hidden="true"></i> ' + s[2] + '</span>'
        + (i.status === 'error' ? '<button type="button" class="btn btn-outline btn-sm" data-outbox-remove="' + esc(i.ref) + '">Remove</button>' : '')
        + '</div></div>';
    }).join('');

    panel.innerHTML = '<div class="card mb-3"><div class="card-header flex-wrap gap-2"><h3>Readings on this phone</h3>'
      + '<div class="d-flex gap-2">'
      + '<button type="button" class="btn btn-outline btn-sm" data-outbox-download' + (downloading ? ' disabled' : '') + '>'
      + (downloading ? 'Downloading…' : 'Download my puroks') + '</button>'
      + (c.waiting && !needLogin ? '<button type="button" class="btn btn-primary btn-sm" data-outbox-send' + (busy ? ' disabled' : '') + '>Send now</button>' : '')
      + '</div></div><div class="card-body">' + status
      + '<div class="small mb-2">' + meters + '</div>'
      + (rows ? '<div class="list-group">' + rows + '</div>' : '')
      + '</div></div>';
  }

  document.addEventListener('click', e => {
    const rm = e.target.closest('[data-outbox-remove]');
    if (rm && confirm('Remove this reading from the phone? It was not saved on the server. Record it again if needed.')) remove(rm.dataset.outboxRemove);
    if (e.target.closest('[data-outbox-send]')) { delay = 5000; kick(0); }
    if (e.target.closest('[data-outbox-download]')) {
      download().catch(() => alert('Could not download the meters. Please try again when the internet is better.'));
      changed();
    }
  });

  /** Logout: warn while readings are still on the phone. */
  window.awasOutboxCanLeave = function () {
    const c = counts();
    if (!c.waiting) return true;
    return confirm(c.waiting + ' reading' + (c.waiting === 1 ? ' is' : 's are') + ' not sent yet. They stay on this phone and are sent the next time you log in on it.\n\nLog out anyway?');
  };

  window.addEventListener('online', () => { delay = 5000; kick(0); });
  document.addEventListener('visibilitychange', () => { if (document.visibilityState === 'visible') kick(0); });
  window.addEventListener('storage', e => { if (e.key === OUTBOX_KEY || e.key === METERS_KEY) changed(); });

  // A send interrupted by closing the page is tried again. Photos kept from the old offline mode
  // (no photo name: never read) can't be sent any more, so they are dropped.
  saveItems(items().filter(i => i.photo || i.typed || i.status === 'sent')
    .map(i => i.status === 'sending' ? Object.assign(i, { status: 'pending' }) : i));

  window.AwasOutbox = { available: true, add, addTyped, canKeepPhotos, remove, items, counts, findMeter, searchMeters, meterData, download, periodLabel, onChange: fn => listeners.push(fn) };

  function start() {
    changed();
    kick(500);
    const data = meterData();
    if (navigator.onLine !== false && (!data || Date.now() - data.saved_at > AUTO_DOWNLOAD_MS)) download().catch(() => {});
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start); else start();
})();
