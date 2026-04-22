@extends('edu.layouts.app')

@section('content')

    <meta name="csrf-token" content="{{ csrf_token() }}">

    <div class="h3">教練列表</div>

    <div class="section pd">
        <ul>
            @foreach($users as $user)
                <li>
                    <a href="{{ route('edu.student.show', ['user_id' => $user['ID']]) }}"
                       target="_blank">
                        {{ $user['first_name'] }} &nbsp; {{ $user['last_name'] }}
                    </a>

                    <span data-id="{{ $user['ID'] }}">
                    <label>時薪</label>
                    <input type="text"
                           name="hourly_wage"
                           value="{{ $hourly_wage[$user['ID']]['hourly_wage'] ?? 200.00 }}"
                           class="form-text">
                </span>
                </li>
            @endforeach
        </ul>
    </div>

    <a href="javascript:history.back();"
       class="form-button mgt15"
       style="display: block;">返回上一頁</a>

@endsection

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/coach.css') }}">
@endpush

@push('scripts')
    <script src="{{ asset('assets/js/views/class_coach.js') }}"></script>
@endpush