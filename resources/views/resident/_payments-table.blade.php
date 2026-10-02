<table class="table table-hover align-middle mb-0 data-table">
  <thead><tr><th>Reference</th><th>Billing Period</th><th>Amount</th><th>Method</th><th>GCash Ref. No.</th><th>Status</th><th>Date</th><th>Receipt</th></tr></thead>
  <tbody>
  @forelse ($payments as $p)
    <tr>
      <td>{{ $p->payment_reference }}</td>
      <td>{{ billingPeriodLabel($p->billing_period) }}</td>
      <td>{{ formatCurrency($p->amount_paid) }}</td>
      <td>{{ paymentMethodLabel($p->payment_method) }}<div class="text-muted" style="font-size:11px;">{{ paymentChannelLabel($p->channel ?? null) }}</div></td>
      <td>{{ $p->payment_gateway_txn_id ?: '—' }}</td>
      <td>
        <span class="badge {{ paymentStatusBadgeClass($p->status) }}">{{ paymentStatusLabel($p->status) }}</span>
        @if (!empty($p->rejection_reason))<div class="text-danger" style="font-size:11px;">{{ $p->rejection_reason }}</div>@endif
      </td>
      <td>{{ formatDateTime($p->payment_date) }}</td>
      <td class="text-nowrap">
        <a href="{{ route('payments.receipt', $p->payment_id) }}" class="btn btn-outline btn-sm" title="View Receipt">🧾 View</a>
        <a href="{{ route('payments.receipt', ['payment' => $p->payment_id, 'download' => 1]) }}" class="btn btn-outline btn-sm" title="Download Receipt">⬇️ Download</a>
      </td>
    </tr>
  @empty
    <tr class="empty-row"><td colspan="8">{{ $emptyText }}</td></tr>
  @endforelse
  </tbody>
</table>
