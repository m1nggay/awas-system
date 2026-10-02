/**
 * id_capture.js — valid ID photos (front and back) for the membership application.
 *
 * Each [data-id-widget] gets:
 *  - a camera view with a landscape, card-shaped guide; only the part inside
 *    the guide is kept, so the photo is always landscape;
 *  - an upload option; uploaded photos must be landscape and are re-drawn
 *    (smaller, rotation fixed) before being sent;
 *  - a quick check that the picture looks like an ID:
 *      too dark                       → rejected;
 *      a large face (a selfie/person) → rejected ("not a valid ID");
 *      front with no ID-holder photo  → warning (the applicant may retake or confirm).
 *    The face check uses MediaPipe in the browser; if it can't load, the photo
 *    is accepted and the administrator still reviews it.
 *
 * The result goes into the widget's hidden input (data URL). The server
 * re-checks type, size and orientation.
 */
import { FaceDetector, FilesetResolver } from 'https://cdn.jsdelivr.net/npm/@mediapipe/tasks-vision@0.10.3';

const WASM_URL = 'https://cdn.jsdelivr.net/npm/@mediapipe/tasks-vision@0.10.3/wasm';
const MODEL_URL = 'https://storage.googleapis.com/mediapipe-models/face_detector/blaze_face_short_range/float16/1/blaze_face_short_range.tflite';
const CARD_RATIO = 1.586;      // ID-1 card (85.6 × 54 mm)
const MAX_WIDTH = 1400;        // px of the saved photo
const SELFIE_FACE_WIDTH = 0.33; // a face wider than this share of the photo = a picture of a person, not a card

let detectorPromise = null;
function detector() {
  if (!detectorPromise) {
    detectorPromise = FilesetResolver.forVisionTasks(WASM_URL)
      .then((files) => FaceDetector.createFromOptions(files, {
        baseOptions: { modelAssetPath: MODEL_URL },
        runningMode: 'IMAGE',
        minDetectionConfidence: 0.4,
      }))
      .catch(() => null);   // offline / blocked: skip the face check
  }
  return detectorPromise;
}

/** Faces in a canvas as fractions of its width: [{x, w}] */
function facesIn(det, canvas) {
  try {
    return (det.detect(canvas).detections || []).map((d) => ({
      x: d.boundingBox.originX / canvas.width,
      w: d.boundingBox.width / canvas.width,
    }));
  } catch (e) {
    return [];
  }
}

/** Square tiles across the card, so the small ID-holder photo is big enough for the detector. */
function tiles(canvas) {
  const size = canvas.height, out = [];
  const steps = Math.max(1, Math.ceil(canvas.width / size) * 2 - 1);
  for (let i = 0; i < steps; i++) {
    const x = steps === 1 ? 0 : Math.round((canvas.width - size) * i / (steps - 1));
    const t = document.createElement('canvas');
    t.width = t.height = Math.min(size, 640);
    t.getContext('2d').drawImage(canvas, x, 0, size, size, 0, 0, t.width, t.height);
    out.push(t);
  }
  return out;
}

function brightness(canvas) {
  const s = document.createElement('canvas');
  s.width = 64; s.height = Math.max(1, Math.round(64 * canvas.height / canvas.width));
  const ctx = s.getContext('2d');
  ctx.drawImage(canvas, 0, 0, s.width, s.height);
  const px = ctx.getImageData(0, 0, s.width, s.height).data;
  let sum = 0;
  for (let i = 0; i < px.length; i += 4) sum += 0.299 * px[i] + 0.587 * px[i + 1] + 0.114 * px[i + 2];
  return sum / (px.length / 4);
}

/** → {ok, level: 'error'|'warn'|'good', message} */
async function inspect(canvas, side) {
  const label = side === 'back' ? 'BACK' : 'FRONT';
  if (canvas.width <= canvas.height) {
    return { ok: false, level: 'error', message: 'This photo is portrait (vertical). Please use a landscape (horizontal) photo of the ' + label + ' of your ID.' };
  }
  if (brightness(canvas) < 45) {
    return { ok: false, level: 'error', message: 'The photo is too dark. Move to a brighter place and capture the ' + label + ' of your ID again.' };
  }
  const det = await detector();
  if (!det) return { ok: true, level: 'good', message: '✓ ' + (side === 'back' ? 'Back' : 'Front') + ' of ID captured.' };

  const whole = facesIn(det, canvas);
  if (whole.some((f) => f.w > SELFIE_FACE_WIDTH)) {
    return { ok: false, level: 'error', message: 'This looks like a photo of a person, not a valid ID. Please capture the ' + label + ' of your valid government ID.' };
  }
  if (side === 'front') {
    const found = whole.length > 0 || tiles(canvas).some((t) => facesIn(det, t).length > 0);
    if (!found) {
      return { ok: true, level: 'warn', message: '⚠ We could not find the ID holder\'s photo. Please make sure this is the FRONT of a valid government ID, the whole card is inside the frame, and it is clear — or retake it.' };
    }
  }
  return { ok: true, level: 'good', message: '✓ ' + (side === 'back' ? 'Back' : 'Front') + ' of ID looks good.' };
}

function toCanvas(source, sx, sy, sw, sh) {
  const scale = Math.min(1, MAX_WIDTH / sw);
  const c = document.createElement('canvas');
  c.width = Math.round(sw * scale);
  c.height = Math.round(sh * scale);
  c.getContext('2d').drawImage(source, sx, sy, sw, sh, 0, 0, c.width, c.height);
  return c;
}

/** The guide rectangle inside a vw × vh video, as fractions: largest centered card that fits. */
function guideRect(vw, vh) {
  let gw = vw * 0.9, gh = gw / CARD_RATIO;
  if (gh > vh * 0.8) { gh = vh * 0.8; gw = gh * CARD_RATIO; }
  return { x: (vw - gw) / 2 / vw, y: (vh - gh) / 2 / vh, w: gw / vw, h: gh / vh };
}

function initWidget(widget) {
  const $ = (role) => widget.querySelector('[data-role="' + role + '"]');
  const side = widget.getAttribute('data-side');
  const els = {
    open: $('open'), snap: $('snap'), retake: $('retake'), close: $('close'),
    stage: $('stage'), video: $('video'), guide: $('guide'), preview: $('preview'),
    file: $('file'), data: $('data'), status: $('status'),
  };
  let stream = null;

  const show = (el, on) => { if (el) el.hidden = !on; };
  const say = (msg, level) => {
    els.status.textContent = msg || '';
    els.status.className = 'cam-status' + (level ? ' ' + level : '');
  };
  const setResult = (url, level) => {
    els.data.value = url || '';
    widget.dataset.warn = level === 'warn' ? '1' : '';
    els.preview.className = 'id-preview' + (level ? ' ' + level : '');
  };

  function stop() {
    if (stream) stream.getTracks().forEach((t) => t.stop());
    stream = null;
    els.video.srcObject = null;
  }

  function placeGuide() {
    const r = guideRect(els.video.videoWidth || 16, els.video.videoHeight || 9);
    Object.assign(els.guide.style, { left: r.x * 100 + '%', top: r.y * 100 + '%', width: r.w * 100 + '%', height: r.h * 100 + '%' });
  }

  async function open() {
    say('');
    if (!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia)) {
      say('The camera is not available in this browser. Please upload a photo instead.', 'error');
      return;
    }
    try {
      stream = await navigator.mediaDevices.getUserMedia({
        video: { facingMode: 'environment', width: { ideal: 1920 }, height: { ideal: 1080 } }, audio: false,
      });
    } catch (e) {
      say('Could not open the camera. Allow camera access in your browser, or upload a photo instead.', 'error');
      return;
    }
    els.video.srcObject = stream;
    await els.video.play();
    placeGuide();
    show(els.stage, true); show(els.preview, false);
    show(els.open, false); show(els.retake, false); show(els.snap, true); show(els.close, true);
    say('Place the ' + (side === 'back' ? 'BACK' : 'FRONT') + ' of your ID inside the frame, hold steady, then tap Capture.');
    detector();   // start loading the checker while the user lines up the card
  }

  async function finish(canvas) {
    say('Checking the photo…');
    const result = await inspect(canvas, side);
    const url = canvas.toDataURL('image/jpeg', 0.88);
    els.preview.src = url;
    show(els.preview, true);
    setResult(result.ok ? url : '', result.level);
    say(result.message, result.level);
    show(els.retake, true); show(els.open, false);
  }

  async function snap() {
    const vw = els.video.videoWidth, vh = els.video.videoHeight;
    if (!vw || !vh) { say('The camera is still starting — try again in a moment.'); return; }
    const r = guideRect(vw, vh);
    const canvas = toCanvas(els.video, r.x * vw, r.y * vh, r.w * vw, r.h * vh);
    stop();
    show(els.stage, false); show(els.snap, false); show(els.close, false);
    els.file.value = '';
    await finish(canvas);
  }

  async function upload() {
    const f = els.file.files && els.file.files[0];
    if (!f) return;
    stop(); show(els.stage, false); show(els.snap, false); show(els.close, false);
    if (['image/jpeg', 'image/png', 'image/webp'].indexOf(f.type) === -1) {
      setResult('', 'error'); show(els.preview, false);
      say('Please choose a JPG, PNG or WEBP photo of your ID.', 'error');
      els.file.value = '';
      return;
    }
    if (typeof createImageBitmap !== 'function') { say('Photo selected.'); return; }  // old browser: the server checks it
    let bmp;
    try {
      bmp = await createImageBitmap(f);   // applies the phone's rotation tag
    } catch (e) {
      setResult('', 'error'); say('That photo could not be read. Please choose another one.', 'error');
      els.file.value = '';
      return;
    }
    const canvas = toCanvas(bmp, 0, 0, bmp.width, bmp.height);
    // The re-drawn photo is what gets sent (smaller, rotation fixed); a rejected one isn't sent at all.
    els.file.value = '';
    await finish(canvas);
  }

  els.open.addEventListener('click', open);
  els.retake.addEventListener('click', () => { setResult('', ''); show(els.preview, false); open(); });
  els.snap.addEventListener('click', snap);
  els.close.addEventListener('click', () => {
    stop(); show(els.stage, false); show(els.snap, false); show(els.close, false);
    show(els.open, true); show(els.retake, !!els.data.value);
    say('');
  });
  els.file.addEventListener('change', upload);
  els.video.addEventListener('loadedmetadata', placeGuide);
  window.addEventListener('beforeunload', stop);
}

document.querySelectorAll('[data-id-widget]').forEach(initWidget);
