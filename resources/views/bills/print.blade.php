<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Water Bill — Meter {{ $bill->meter_number }} — {{ billingPeriodLabel($bill->billing_period) }}</title>
<link rel="stylesheet" href="{{ asset('assets/css/style.css') }}?v={{ @filemtime(public_path('assets/css/style.css')) ?: '1' }}">
<style>
  body{background:#fff;padding:30px;}
  .invoice{max-width:760px;margin:0 auto;border:1px solid var(--border);border-radius:10px;overflow:hidden;}
  .invoice-head{background:linear-gradient(135deg,var(--primary-dark),var(--primary));color:#fff;padding:24px 30px;display:flex;justify-content:space-between;align-items:center;}
  .invoice-body{padding:26px 30px;}
  .info{display:grid;grid-template-columns:1fr 1fr;gap:4px 30px;}
  .row{display:flex;justify-content:space-between;gap:12px;margin-bottom:6px;font-size:13.5px;}
  .totals{margin-top:18px;border-top:2px solid var(--border);padding-top:12px;}
  .totals .row.grand{font-size:18px;font-weight:700;color:var(--primary-dark);}
  .totals .total-highlight{background:#e8f6fb;border:1px solid #bfe3f0;border-radius:8px;padding:10px 12px;margin-bottom:10px;-webkit-print-color-adjust:exact;print-color-adjust:exact;}
  table.bill-table{width:100%;border-collapse:collapse;margin-top:16px;}
  table.bill-table th, table.bill-table td{border:1px solid var(--border);padding:9px 12px;font-size:13px;text-align:left;}
  table.bill-table th{background:#f4fafc;}
  .num{text-align:right !important;}
  .actions-bar{max-width:760px;margin:0 auto 16px;text-align:right;}
  @media (max-width:640px){ .info{grid-template-columns:1fr;} }
</style>
</head>
<body>
  <div class="actions-bar no-print">
    <button class="btn btn-primary" onclick="window.print()">🖨️ Print / Save as PDF</button>
  </div>
  <div class="invoice">
    <div class="invoice-head">
      <div>
        <div style="display:flex;align-items:center;gap:8px;font-size:20px;font-weight:700;">
          <img src="{{ asset('assets/img/logo.png') }}" alt="AGAS logo" style="width:34px;height:34px;object-fit:contain;">AGAS
        </div>
        <div style="font-size:12px;opacity:.85;">{{ $barangayName }} — Water Billing Statement</div>
      </div>
      <div style="text-align:right;">
        <div style="font-size:12px;opacity:.85;">Meter Number</div>
        <div style="font-size:18px;font-weight:700;">{{ $bill->meter_number ?: '—' }}</div>
      </div>
    </div>
    <div class="invoice-body">
      <div class="info">
        <div>
          <div class="row"><span class="text-muted">Meter Number</span><strong>{{ $bill->meter_number ?: '—' }}</strong></div>
          <div class="row"><span class="text-muted">Name</span><strong>{{ $bill->full_name }}</strong></div>
          <div class="row"><span class="text-muted">Purok</span><strong>{{ $bill->purok_name }}</strong></div>
          <div class="row"><span class="text-muted">Type of Consumer</span><strong>{{ consumerTypeLabel($bill->consumer_type) }}</strong></div>
        </div>
        <div>
          <div class="row"><span class="text-muted">Month of</span><strong>{{ billingPeriodLabel($bill->billing_period) }}</strong></div>
          <div class="row"><span class="text-muted">Status</span><span class="badge {{ billStatusBadgeClass($bill->status) }}">{{ str_replace('_', ' ', $bill->status) }}</span></div>
        </div>
      </div>

      <table class="bill-table">
        <thead><tr><th>Previous Reading</th><th>Present Reading</th><th>Consumption</th><th class="num">Total</th></tr></thead>
        <tbody>
          <tr>
            <td>{{ $bill->previous_reading !== null ? number_format($bill->previous_reading, 2) : '—' }}</td>
            <td>{{ $bill->current_reading !== null ? number_format($bill->current_reading, 2) : '—' }}</td>
            <td>{{ number_format($bill->consumption, 2) }} m³</td>
            <td class="num"><strong>{{ formatCurrency($bill->total_amount) }}</strong></td>
          </tr>
        </tbody>
      </table>

      <table class="bill-table">
        <thead><tr><th>Charges</th><th>Consumption</th><th>Rate</th><th class="num">Amount</th></tr></thead>
        <tbody>
          <tr>
            <td>Minimum Charge (first {{ rtrim(rtrim(number_format($includedCum, 2), '0'), '.') }} m³)</td>
            <td>{{ number_format(min((float)$bill->consumption, $includedCum), 2) }} m³</td>
            <td>—</td>
            <td class="num">{{ formatCurrency($minCharge) }}</td>
          </tr>
          @if ($excessCum > 0)
          <tr>
            <td>Excess Consumption</td>
            <td>{{ number_format($excessCum, 2) }} m³</td>
            <td>{{ formatCurrency($excessRate) }}/m³</td>
            <td class="num">{{ formatCurrency($excessCharge) }}</td>
          </tr>
          @endif
          @if ($bill->discount_amount > 0)
          <tr><td colspan="3">Senior Citizen Discount</td><td class="num">− {{ formatCurrency($bill->discount_amount) }}</td></tr>
          @endif
          @if ($bill->penalty_amount > 0)
          <tr><td colspan="3">Overdue Penalty</td><td class="num">{{ formatCurrency($bill->penalty_amount) }}</td></tr>
          @endif
        </tbody>
      </table>

      <div class="totals">
        <div class="row grand total-highlight"><span>Total Amount</span><span>{{ formatCurrency($bill->total_amount) }}</span></div>
        <div class="row">
          <span>Balance<br><small class="text-muted">Unpaid from the previous bill (not paid in full)</small></span>
          <span class="{{ $previousBalance > 0 ? 'text-danger' : '' }}">{{ formatCurrency($previousBalance) }}</span>
        </div>
        <div class="row"><span>Due Date</span><strong>{{ formatDate($bill->due_date) }}</strong></div>
        <div class="row"><span>Disconnection Date</span><strong class="text-danger">{{ $bill->disconnection_date ? formatDate($bill->disconnection_date) : '—' }}</strong></div>
        @if ((float)$bill->amount_paid > 0)
          <div class="row"><span>Amount Paid{{ $datePaid ? ' (' . formatDate($datePaid) . ')' : '' }}</span><span>− {{ formatCurrency($bill->amount_paid) }}</span></div>
        @endif
      </div>

      <p class="text-muted" style="margin-top:24px;font-size:11.5px;">
        This is a system-generated water billing statement from the AGAS Smart Water Management and Billing System of {{ $barangayName }}.
        Please settle your bill on or before the due date to avoid penalties or service disconnection.
      </p>
    </div>
  </div>
</body>
</html>
