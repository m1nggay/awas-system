@extends('layouts.app')

@section('title', __('Consumer History — :name', ['name' => $consumer->full_name]))

@section('content')
<a href="{{ route('admin.consumers.index') }}" class="btn btn-secondary btn-sm mb-2">{{ __('← Back to Consumers') }}</a>

@include('admin.consumers._history-body')
@endsection
