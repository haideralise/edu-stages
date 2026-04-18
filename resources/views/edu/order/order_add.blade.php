@extends('edu.layouts.app')

@section('content')

    <meta name="csrf-token" content="{{ csrf_token() }}">

    <table class="table">
        <tbody>

        <tr>
            <td style="width: 50px;">班級</td>
            <td style="width: 290px">
                <select name="class_id"
                        class="form-select2-ajax"
                        data-placeholder="請選擇"
                        data-url="{{ route('edu.order.classes') }}">
                    @if($last_month && !empty($last_class))
                        <option value="{{ $last_class['class_id'] }}">
                            {{ $last_class['class_name'] }}
                        </option>
                    @endif
                </select>
            </td>
        </tr>

        <tr>
            <td>年份</td>
            <td>
                <?php
                $current_year  = (int) date('Y');
                $default_year  = $current_year;
                $default_month = null;

                if (!empty($last_month['class_year']) && !empty($last_month['month'])) {
                    // Derive next year/month from last enrollment
                    // (logic matches EduOrdersService::getNextYearMonth)
                    $default_year  = (int) $last_month['class_year'];
                    $default_month = $last_month['month'] ?? null;
                }

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
                        data-url="{{ route('edu.order.months') }}"
                        data-default-month="{{ $default_month ?? '' }}">
                    @if(!empty($default_month))
                        <option value="{{ $default_month }}" selected>
                            {{ $default_month }}
                        </option>
                    @endif
                </select>
            </td>
        </tr>

        <tr>
            <td colspan="2">
                如果月份不存在，請在<b style="color: red;">班級管理</b>中新增月份
            </td>
        </tr>

        <tr>
            <td>學費</td>
            <td>
                <input type="text"
                       name="amount"
                       value="{{ $data['amount'] ?? '' }}"
                       class="form-text">
            </td>
        </tr>

        <tr>
            <td>繳費日期</td>
            <td>
                <input type="date"
                       name="order_date"
                       value="{{ $data['order_date_display'] ?? date('Y-m-d') }}"
                       class="form-text">
            </td>
        </tr>

        <tr>
            <td>付款方式</td>
            <td>
                @foreach(['轉數快', '銀行轉賬', '支付寶', 'PayMe', '八達通'] as $gw)
                    <label>
                        <input type="radio"
                               name="gateway"
                               value="{{ $gw }}"
                                @checked(($data['gateway'] ?? '') === $gw)>
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

    <input type="hidden"
           id="hidden_generate_url"
           value="{{ route('edu.class.show', ['class' => '']) }}">

@endsection

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/order-add.css') }}">
@endpush

@push('scripts')
    <script src="{{ asset('assets/js/views/order_add.js') }}"></script>
@endpush