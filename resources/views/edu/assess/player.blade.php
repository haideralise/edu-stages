@extends('edu.layouts.app')

@section('content')

    @if(empty($file))
        <div style="text-align:center;padding:15px;">無文件可查看!</div>
    @elseif(str_ends_with($file, '.mp4'))
        <video src="{{ $file }}"></video>
    @else
        <img src="{{ $file }}">
    @endif

@endsection

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/asses-player.css') }}">
@endpush
