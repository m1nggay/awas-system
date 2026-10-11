@extends('layouts.app')

@section('title', 'Pay Bill')

@push('styles')
@include('resident._pay-styles')
@endpush

@section('content')
@include('resident._pay-panel')
@endsection

@push('scripts')
<script src="{{ asset('assets/js/pay-panel.js') }}?v={{ @filemtime(public_path('assets/js/pay-panel.js')) ?: '1' }}"></script>
<script>initPayPanel(document.querySelector('[data-pay-panel]'));</script>
@endpush
