@extends('layouts.app')

@section('title', 'Payment History')

@section('content')
<div class="card">
  <div class="card-body no-pad">
    <div class="table-responsive">
      @include('resident._payments-table', ['payments' => $payments, 'emptyText' => 'No payments recorded yet.', 'receiptPopup' => true])
    </div>
  </div>
</div>
@include('resident._receipt-modal')
@endsection
