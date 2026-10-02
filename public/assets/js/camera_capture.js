/**
 * camera_capture.js — browser camera capture for apply_membership.php.
 *
 * Any element with class "cam-widget" is wired up. Children are found by
 * data-role: open, video, snap, retake, close, preview, data (the hidden
 * input that receives the captured JPEG as a data URL), status, and the
 * optional "fallback" (an <input type="file"> shown when the browser or
 * device has no usable camera API) and "file" (a file input to clear when
 * a capture replaces it). data-facing="user|environment" picks the camera.
 * The photo never leaves the browser until the form is submitted.
 */
(function () {
  'use strict';

  var supported = !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia);

  function init(widget) {
    var $ = function (role) { return widget.querySelector('[data-role="' + role + '"]'); };
    var openBtn = $('open'), video = $('video'), snapBtn = $('snap'), retakeBtn = $('retake');
    var closeBtn = $('close'), preview = $('preview'), data = $('data'), status = $('status');
    var fallback = $('fallback'), file = $('file'), stage = $('stage');
    var facing = widget.getAttribute('data-facing') || 'user';
    var stream = null;

    function show(el, on) { if (el) el.hidden = !on; }
    function say(msg) { if (status) status.textContent = msg || ''; }

    function stop() {
      if (stream) { stream.getTracks().forEach(function (t) { t.stop(); }); stream = null; }
      video.srcObject = null;
    }

    function reset() {
      stop();
      show(stage, false); show(snapBtn, false); show(closeBtn, false); show(retakeBtn, false);
      show(openBtn, true);
    }

    function start() {
      say('');
      navigator.mediaDevices.getUserMedia({ video: { facingMode: facing }, audio: false })
        .then(function (s) {
          stream = s;
          video.srcObject = s;
          video.play();
          show(stage, true); show(preview, false); show(video, true);
          show(openBtn, false); show(retakeBtn, false); show(snapBtn, true); show(closeBtn, true);
        })
        .catch(function () {
          say('Could not open the camera. Allow camera access in your browser, or use the upload option instead.');
          if (fallback) show(fallback, true);
        });
    }

    function snap() {
      var w = video.videoWidth, h = video.videoHeight;
      if (!w || !h) { say('The camera is still starting — try again in a moment.'); return; }
      var scale = Math.min(1, 1280 / w);
      var canvas = document.createElement('canvas');
      canvas.width = Math.round(w * scale);
      canvas.height = Math.round(h * scale);
      canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);
      var url = canvas.toDataURL('image/jpeg', 0.85);
      data.value = url;
      if (file) file.value = '';
      preview.src = url;
      stop();
      show(video, false); show(preview, true); show(stage, true);
      show(snapBtn, false); show(closeBtn, false); show(retakeBtn, true); show(openBtn, false);
      say('Photo captured. Use "Retake" if it is not clear.');
    }

    if (!supported) {
      show(openBtn, false);
      if (fallback) show(fallback, true);
      say('Camera capture is not available in this browser. Please upload a photo instead.');
      return;
    }

    openBtn.addEventListener('click', start);
    retakeBtn.addEventListener('click', function () { data.value = ''; start(); });
    snapBtn.addEventListener('click', snap);
    closeBtn.addEventListener('click', reset);
    if (file) {
      file.addEventListener('change', function () {
        if (file.files && file.files[0]) {
          data.value = '';
          preview.src = URL.createObjectURL(file.files[0]);
          show(stage, true); show(video, false); show(preview, true);
          show(snapBtn, false); show(closeBtn, false); show(retakeBtn, false);
          stop(); show(openBtn, true);
        }
      });
    }
    window.addEventListener('beforeunload', stop);
  }

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.cam-widget').forEach(init);
  });
})();
