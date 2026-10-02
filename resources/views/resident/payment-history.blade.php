@extends('layouts.app')

@section('title', 'Payment History')

@section('content')
<div class="card">
  <div class="card-header"><h3>Payment History</h3></div>
  <div class="card-body no-pad">
    <div class="table-responsive">
      @include('resident._payments-table', ['payments' => $payments, 'emptyText' => 'No payments recorded yet.'])
    </div>
  </div>
</div>
@endsection
