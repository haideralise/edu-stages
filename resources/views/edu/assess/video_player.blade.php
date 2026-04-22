@extends('edu.layouts.app')

@section('content')

    <input type="hidden" id="fileLevelBase64" value="{{ base64_encode($level['file_level'] ?? '') }}">

@endsection

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/asses-video-player.css') }}">
@endpush

@push('scripts')
    <script src="{{ asset('assets/js/views/asses_video_player.js') }}"></script>
@endpush
