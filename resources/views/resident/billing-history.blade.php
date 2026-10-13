@extends('layouts.app')

@section('title', 'Billing History')

@section('content')
<div class="card">
  <div class="card-body no-pad">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0 data-table">
        <thead><tr><th>Billing Period</th><th>Previous Reading</th><th>Present Reading</th><th>Consumption</th><th>Total</th><th>Status</th><th>Date Paid</th><th></th></tr></thead>
        <tbody>
        @forelse ($bills as $b)
          <tr>
            <td>{{ billingPeriodLabel($b->billing_period) }}</td>
            <td>{{ $b->previous_reading !== null ? number_format($b->previous_reading, 2) : '—' }}</td>
            <td>{{ $b->current_reading !== null ? number_format($b->current_reading, 2) : '—' }}</td>
            <td>{{ number_format($b->consumption, 2) }} m³</td>
            <td>{{ formatCurrency($b->total_amount) }}</td>
            <td><span class="badge {{ billStatusBadgeClass($b->status) }}">{{ str_replace('_', ' ', $b->status) }}</span></td>
            <td>{{ isset($datePaid[$b->bill_id]) ? formatDate($datePaid[$b->bill_id]) : '—' }}</td>
            <td><button type="button" class="btn btn-outline btn-sm" data-bill-view="{{ route('bills.print', [$b->bill_id, 'embed' => 1]) }}"
                        data-bill-title="{{ billingPeriodLabel($b->billing_period) }} Bill">View Bill</button></td>
          </tr>
        @empty
          <tr class="empty-row"><td colspan="8">No paid bills yet.</td></tr>
        @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>

{{-- The bill, shown here in a pop-up instead of another tab. --}}
<div class="modal fade" id="billViewModal" tabindex="-1" aria-labelledby="billViewTitle" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable modal-fullscreen-sm-down">
    <div class="modal-content">
      <div class="modal-header">
        <h3 class="h6 mb-0" id="billViewTitle"></h3>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-0">
        <iframe id="billViewFrame" title="Water bill" style="width:100%;height:70vh;border:0;display:block;"></iframe>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        <button type="button" class="btn btn-primary" onclick="document.getElementById('billViewFrame').contentWindow.document.getElementById('downloadBtn')?.click()"><i class="ph ph-download-simple" aria-hidden="true"></i> Download Bill</button>
      </div>
    </div>
  </div>
</div>
<script>
(function () {
  const modal = document.getElementById('billViewModal'), frame = document.getElementById('billViewFrame');
  document.querySelectorAll('[data-bill-view]').forEach(btn => btn.addEventListener('click', () => {
    document.getElementById('billViewTitle').textContent = btn.dataset.billTitle;
    frame.src = btn.dataset.billView;
    bootstrap.Modal.getOrCreateInstance(modal).show();
  }));
  modal.addEventListener('hidden.bs.modal', () => { frame.src = 'about:blank'; });
})();
</script>
@endsection
