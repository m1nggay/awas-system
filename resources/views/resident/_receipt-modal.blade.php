{{-- Payment receipt in a pop-up (same style as View Bill), opened by [data-receipt-view] buttons. --}}
<div class="modal fade" id="receiptViewModal" tabindex="-1" aria-labelledby="receiptViewTitle" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable modal-fullscreen-sm-down">
    <div class="modal-content">
      <div class="modal-header">
        <h3 class="h6 mb-0" id="receiptViewTitle"></h3>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-0">
        <iframe id="receiptViewFrame" title="Payment receipt" style="width:100%;height:70vh;border:0;display:block;"></iframe>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        <button type="button" class="btn btn-primary" id="receiptViewDownload"><i class="ph ph-download-simple" aria-hidden="true"></i> Download Receipt</button>
      </div>
    </div>
  </div>
</div>
<script>
(function () {
  const modal = document.getElementById('receiptViewModal'), frame = document.getElementById('receiptViewFrame');
  document.querySelectorAll('[data-receipt-view]').forEach(btn => btn.addEventListener('click', () => {
    document.getElementById('receiptViewTitle').textContent = btn.dataset.receiptTitle;
    frame.src = btn.dataset.receiptView;
    bootstrap.Modal.getOrCreateInstance(modal).show();
  }));
  // Uses the receipt's own "Download Receipt (PDF)".
  document.getElementById('receiptViewDownload').addEventListener('click', () => {
    frame.contentWindow.document.getElementById('downloadBtn')?.click();
  });
  modal.addEventListener('hidden.bs.modal', () => { frame.src = 'about:blank'; });
})();
</script>
