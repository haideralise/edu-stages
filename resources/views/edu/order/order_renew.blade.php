@extends('edu.layouts.app')

@section('content')

    <meta name="csrf-token" content="{{ csrf_token() }}">

    <table class="table">
        <tbody>
        <tr>
            <td style="width: 50px;">班級</td>
            <td style="width: 290px;">
                <select name="class_id"
                        class="form-select2-ajax"
                        data-placeholder="請選擇"
                        data-url="{{ route('edu.order.classes') }}">
                    <option value="{{ $order['class_id'] ?? '' }}">
                        {{ $order['class_name'] ?? '' }}
                    </option>
                </select>
            </td>
        </tr>
        <tr>
            <td>年份</td>
            <td>
                <?php
                $current_year = (int) date('Y');
                $default_year = !empty($next_year)
                    ? (int) $next_year
                    : (!empty($order['class_year']) ? (int) $order['class_year'] : $current_year);

                $min_year = min($current_year - 1, $default_year);
                $max_year = max($current_year + 1, $default_year);
                ?>
                <select name="class_year" class="form-select">
                    @for($i = $max_year; $i >= $min_year; $i--)
                        <option value="{{ $i }}" @selected($i == $default_year)>
                            {{ $i }}
                        </option>
                    @endfor
                </select>
            </td>
        </tr>
        <tr>
            <td>月份</td>
            <td>
                <select name="month"
                        data-placeholder="請選擇"
                        data-url="{{ route('edu.order.renew-months') }}"
                        data-default-month="{{ $next_month ?? '' }}">
                    @if(!empty($next_month))
                        <option value="{{ $next_month }}" selected>
                            {{ $next_month }}
                        </option>
                    @endif
                </select>
            </td>
        </tr>
        <tr>
            <td>金額</td>
            <td>
                <input type="text"
                       name="amount"
                       value=""
                       class="form-text">
            </td>
        </tr>
        <tr>
            <td>繳費日期</td>
            <td>
                <input type="date"
                       name="order_date"
                       value="{{ $data['order_date'] ?? date('Y-m-d') }}"
                       class="form-text">
            </td>
        </tr>
        <tr>
            <td>付款方式</td>
            <td>
                @foreach(['轉數快', '銀行轉賬', '支付寶', 'PayMe', '八達通'] as $gw)
                    <label>
                        <input type="radio" name="gateway" value="{{ $gw }}">
                        {{ $gw }}
                    </label>
                @endforeach
            </td>
        </tr>
        <tr>
            <td colspan="2">
                <button class="form-button">保存</button>
            </td>
        </tr>
        </tbody>
    </table>

    <input type="hidden" id="student_id" value="{{ $student_id }}">
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/order-renew.css') }}">
@endpush

@push('scripts')
    <script src="{{ asset('assets/js/views/order_renew.js') }}"></script>
@endpush