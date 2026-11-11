@extends('layouts.app')

@section('title', __('My Dashboard'))

@section('content')
<div class="alert alert-warning">{{ __('Your login is not yet linked to a water account. Please contact the barangay water office.') }}</div>
@endsection
