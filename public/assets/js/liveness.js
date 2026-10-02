/**
 * liveness.js — face verification with a simple liveness (blink) check for
 * the membership application.
 *
 *   1. "Position your face inside the guide." — exactly one face, centered
 *      and close enough (checked from face landmarks).
 *   2. "Please blink." — eyes must go open → closed → open while the face
 *      stays in the guide. A printed or on-screen still photo cannot do this.
 *   3. Only then a frame is captured into #selfie_capture and
 *      #liveness_passed is set to "1". If the check fails, nothing is
 *      marked as verified.
 *
 * Uses Google MediaPipe Face Landmarker (loaded from the jsDelivr CDN, like
 * the rest of the app's front-end libraries); it runs entirely in the
 * browser — no video is uploaded. An administrator still compares the
 * captured face with the valid ID photo.
 */
import { FaceLandmarker, FilesetResolver } from 'https://cdn.jsdelivr.net/npm/@mediapipe/tasks-vision@0.10.3';

const WASM_URL = 'https://cdn.jsdelivr.net/npm/@mediapipe/tasks-vision@0.10.3/wasm';
const MODEL_URL = 'https://storage.googleapis.com/mediapipe-models/face_landmarker/face_landmarker/float16/1/face_landmarker.task';

const BLINK_CLOSED = 0.5;   // eyeBlink score above this = eyes closed
const BLINK_OPEN = 0.25;    // below this = eyes open
const STEADY_FRAMES = 12;   // frames the face must stay positioned before we ask to blink
const BLINK_TIMEOUT_MS = 10000;

const $ = (id) => document.getElementById(id);
const els = {
  stage: $('livenessStage'), video: $('livenessVideo'), guide: $('livenessGuide'),
  prompt: $('livenessPrompt'), result: $('livenessResult'),
  start: $('livenessStart'), retry: $('livenessRetry'),
  capture: $('selfie_capture'), passed: $('liveness_passed'), inPerson: $('verify_in_person'),
};

let landmarker = null;
let stream = null;
let running = false;

function say(text, tone) {
  els.prompt.textContent = text;
  els.prompt.className = 'liveness-prompt' + (tone ? ' ' + tone : '');
}

function stopCamera() {
  running = false;
  if (stream) stream.getTracks().forEach((t) => t.stop());
  stream = null;
  els.video.srcObject = null;
}

function reset() {
  els.capture.value = '';
  els.passed.value = '0';
  els.result.hidden = true;
  els.guide.classList.remove('ok');
}

async function loadModel() {
  if (landmarker) return landmarker;
  say('Loading face check… (first time may take a few seconds)');
  const files = await FilesetResolver.forVisionTasks(WASM_URL);
  const options = (delegate) => ({
    baseOptions: { modelAssetPath: MODEL_URL, delegate },
    runningMode: 'VIDEO',
    numFaces: 2,
    outputFaceBlendshapes: true,
  });
  try {
    landmarker = await FaceLandmarker.createFromOptions(files, options('GPU'));
  } catch (e) {
    landmarker = await FaceLandmarker.createFromOptions(files, options('CPU'));
  }
  return landmarker;
}

/** Is the (single) face inside the oval guide and big enough? Returns a message or null when OK. */
function positionProblem(faces) {
  if (!faces.length) return 'No face detected — look at the camera.';
  if (faces.length > 1) return 'Only one person should be in front of the camera.';
  const pts = faces[0];
  let minX = 1, maxX = 0, minY = 1, maxY = 0;
  for (const p of pts) {
    minX = Math.min(minX, p.x); maxX = Math.max(maxX, p.x);
    minY = Math.min(minY, p.y); maxY = Math.max(maxY, p.y);
  }
  const width = maxX - minX, cx = (minX + maxX) / 2, cy = (minY + maxY) / 2;
  if (width < 0.28) return 'Move closer — your face should fill the guide.';
  if (width > 0.75) return 'Move a little farther from the camera.';
  if (Math.abs(cx - 0.5) > 0.12 || Math.abs(cy - 0.5) > 0.15) return 'Center your face inside the guide.';
  if (minX < 0.02 || maxX > 0.98 || minY < 0.02 || maxY > 0.98) return 'Keep your whole face inside the guide.';
  return null;
}

function blinkScore(result) {
  const cats = result.faceBlendshapes && result.faceBlendshapes[0] ? result.faceBlendshapes[0].categories : [];
  const get = (name) => (cats.find((c) => c.categoryName === name) || {}).score || 0;
  return (get('eyeBlinkLeft') + get('eyeBlinkRight')) / 2;
}

function captureFrame() {
  const v = els.video;
  const scale = Math.min(1, 720 / v.videoWidth);
  const canvas = document.createElement('canvas');
  canvas.width = Math.round(v.videoWidth * scale);
  canvas.height = Math.round(v.videoHeight * scale);
  canvas.getContext('2d').drawImage(v, 0, 0, canvas.width, canvas.height);
  return canvas.toDataURL('image/jpeg', 0.85);
}

async function start() {
  reset();
  els.start.hidden = true;
  els.retry.hidden = true;

  if (!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia)) {
    say('Camera is not available in this browser. Tick "verify in person" below, or use another device.', 'bad');
    els.retry.hidden = false;
    return;
  }

  try {
    await loadModel();
    stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user', width: { ideal: 640 } }, audio: false });
  } catch (e) {
    say(e && e.name === 'NotAllowedError'
      ? 'Camera permission was denied. Allow camera access and try again, or tick "verify in person" below.'
      : 'Could not start the face check (camera or connection problem). Try again, or tick "verify in person" below.', 'bad');
    els.retry.hidden = false;
    return;
  }

  els.video.srcObject = stream;
  await els.video.play();
  els.stage.hidden = false;
  running = true;

  let phase = 'position';   // position -> blink -> done
  let steady = 0;
  let sawOpen = false, sawClosed = false;
  let blinkStartedAt = 0;
  let lastTime = -1;

  const loop = () => {
    if (!running) return;
    if (els.video.currentTime === lastTime) { requestAnimationFrame(loop); return; }
    lastTime = els.video.currentTime;

    const result = landmarker.detectForVideo(els.video, performance.now());
    const problem = positionProblem(result.faceLandmarks || []);

    if (phase === 'position') {
      if (problem) {
        steady = 0;
        els.guide.classList.remove('ok');
        say(problem === 'No face detected — look at the camera.' ? 'Position your face inside the guide.' : problem);
      } else if (++steady >= STEADY_FRAMES) {
        phase = 'blink';
        blinkStartedAt = performance.now();
        els.guide.classList.add('ok');
        say('Please blink.', 'good');
      } else {
        els.guide.classList.add('ok');
        say('Hold still…');
      }
    } else if (phase === 'blink') {
      if (problem) {
        // Face left the guide mid-check: start over so a swapped photo can't pass.
        phase = 'position'; steady = 0; sawOpen = sawClosed = false;
        els.guide.classList.remove('ok');
        say(problem);
      } else {
        const score = blinkScore(result);
        if (!sawClosed && score < BLINK_OPEN) sawOpen = true;
        if (sawOpen && score > BLINK_CLOSED) sawClosed = true;
        if (sawOpen && sawClosed && score < BLINK_OPEN) {
          // Eyes open again after a blink: take the picture now.
          phase = 'done';
          els.capture.value = captureFrame();
          els.passed.value = '1';
          els.result.src = els.capture.value;
          els.result.hidden = false;
          stopCamera();
          els.stage.hidden = true;
          say('✓ Face verification passed.', 'good');
          els.retry.hidden = false;
          els.retry.textContent = 'Redo face verification';
          if (els.inPerson) els.inPerson.checked = false;
          return;
        }
        if (performance.now() - blinkStartedAt > BLINK_TIMEOUT_MS) {
          stopCamera();
          els.stage.hidden = true;
          say('We did not detect a blink, so verification did not pass. Please try again.', 'bad');
          els.retry.hidden = false;
          els.retry.textContent = 'Try again';
          return;
        }
      }
    }
    requestAnimationFrame(loop);
  };
  requestAnimationFrame(loop);
}

els.start.addEventListener('click', start);
els.retry.addEventListener('click', start);
window.addEventListener('beforeunload', stopCamera);
