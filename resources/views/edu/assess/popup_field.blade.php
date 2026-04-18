@extends('edu.layouts.app')

@section('content')

    <meta name="csrf-token" content="{{ csrf_token() }}">

    <table class="table">
        <tbody>
            <tr>
                <td>名稱</td>
                <td><input type="text" name="name" value="{{ $data['name'] ?? '' }}" class="form-text"></td>
            </tr>
            <tr>
                <td>連結</td>
                <td><input type="text" name="link" value="{{ $data['link'] ?? '' }}" class="form-text"></td>
            </tr>
            <tr>
                <td colspan="2">
                    <input type="hidden" name="_handle" value="update">
                    <button class="form-button">保存</button>
                </td>
            </tr>
        </tbody>
    </table>

@endsection

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/asses-pop-field.css') }}">
@endpush

@push('scripts')
    <script src="{{ asset('assets/js/views/asses_popup_field.js') }}"></script>
@endpush
