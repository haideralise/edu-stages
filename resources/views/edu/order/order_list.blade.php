@extends('edu.layouts.app')

@section('content')

    <div class="h3">訂單</div>

    <div class="section">
        <table class="table">
            <tr>
                <td>日期</td>
                <td>
                    <input type="text"
                           daterange
                           value="{{ request()->get('daterange', date('Y-m-01').' - '.date('Y-m-d')) }}"
                           class="form-text">
                </td>
            </tr>
        </table>
    </div>

    <div class="section pd">
        統計: &nbsp;
        #收款: {{ $amount }} &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
        #退款: {{ $refund }}
    </div>

    <table class="table mgt">
        <thead>
        <tr>
            <td>訂單ID</td>
            <td>姓名</td>
            <td>班級</td>
            <td>學費</td>
            <td>繳費日期</td>
            <td>來源</td>
            <td>狀態</td>
        </tr>
        </thead>
        <tbody>
        @forelse($list as $val)
            <tr>
                <td>{{ $val['woo_order_id'] }}</td>
                <td>{{ $users[$val['user_id']]['first_name'] ?? '—' }}</td>
                <td tl>{{ $val['woo_class_name'] }}</td>
                <td>{{ $val['amount'] }}</td>
                <td>{{ date('Y-m-d', $val['order_date']) }}</td>
                <td>{{ $val['order_source'] }}</td>
                <td>{{ $val['woo_status'] }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="7" style="text-align:center;">No orders found.</td>
            </tr>
        @endforelse
        </tbody>
    </table>

@endsection

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/order-list.css') }}">
@endpush

@push('scripts')
    <script src="{{ asset('assets/js/views/order_list.js') }}"></script>
@endpush