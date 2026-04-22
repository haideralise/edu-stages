@extends('edu.layouts.app')

@section('content')

    <meta name="csrf-token" content="{{ csrf_token() }}">

    <div class="h3">地區管理</div>

    <div class="filter form condition">
        <ul>
            @foreach($list as $item)
                <li>
                    <input type="text"
                           name="district_name"
                           value="{{ $item['name'] }}"
                           data-id="{{ $item['term_id'] }}"
                           class="form-text district-name-input">
                    <button class="form-button update-district">修改</button>
                </li>
            @endforeach
        </ul>
    </div>

    <div class="add_district form">
        <div><b>新增區域:</b></div>
        <div style="margin-top: 5px; position: relative;">
            <input type="text"
                   name="district_name"
                   id="new_district_name"
                   value=""
                   class="form-text"
                   placeholder="請輸入區域名稱">
            <button class="form-button" id="add-district">新增</button>
        </div>
    </div>

@endsection

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/class-district.css') }}">
@endpush

@push('scripts')
    <script src="{{ asset('assets/js/views/district.js') }}"></script>
@endpush