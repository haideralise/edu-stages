@extends('edu.layouts.app')

@section('content')

    <meta name="csrf-token" content="{{ csrf_token() }}">

    <table class="table">
        <tbody>

        <tr>
            <td style="width: 80px;">班級</td>
            <td style="width: 250px;" tl>{{ $order['class_name'] ?? '—' }}</td>
        </tr>

        <tr>
            <td style="width: 80px;">年份</td>
            <td tl>{{ $order['class_year'] ?? '—' }}</td>
        </tr>

        <tr>
            <td style="width: 80px;">月份</td>
            <td tl>{{ $order['month'] ?? '—' }}</td>
        </tr>

        <tr>
            <td style="width: 80px;">支付金額</td>
            <td tl>{{ $order['amount'] ?? '—' }}</td>
        </tr>

        <tr>
            <td style="width: 80px;">退款金額</td>
            <td>
                <input type="text"
                       name="refund_fee"
                       value="{{ $order['refund_fee'] ?: ($order['amount'] ?? '') }}"
                       class="form-text">
            </td>
        </tr>

        <tr>
            <td style="width: 80px;">退款原因</td>
            <td>
                <label>
                    <input type="radio"
                           name="refund_reason"
                           value="沒有足夠人數開班">
                    沒有足夠人數開班
                </label>
                <label>
                    <input type="radio"
                           name="refund_reason"
                           value="缺少教練">
                    缺少教練
                </label>
                <label>
                    <input type="text"
                           name="refund_reason2"
                           value="{{ $order['refund_reason'] ?? '' }}"
                           placeholder="其他退款原因, 請輸入"
                           class="form-text">
                </label>
            </td>
        </tr>

        <tr>
            <td style="width: 80px;">退款日期</td>
            <td>
                <input type="date"
                       name="refund_date"
                       value="{{ date('Y-m-d') }}"
                       class="form-text">
            </td>
        </tr>

        <tr>
            <td colspan="2">
                <button class="form-button">保存</button>
            </td>
        </tr>

        </tbody>
    </table>

@endsection

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/order-refund.css') }}">
@endpush

@push('scripts')
    <script src="{{ asset('assets/js/views/order_refund.js') }}"></script>
@endpush