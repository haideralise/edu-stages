@extends('edu.layouts.app')

@section('content')

    <meta name="csrf-token" content="{{ csrf_token() }}">

    <link rel="stylesheet" href="{{ asset('assets/css/class-student.css') }}">

    <form action="" method="post">

        @include('edu.components.personal-info', [
            'student'       => $student,
            'calStudentAge' => $calStudentAge,
            'note'          => $note,
        ])

        <h3>出席記錄</h3>
        <div style="padding:15px; background:#fff; border-radius:0 0 6px 6px; border:solid 1px #ddd;">
            @if(empty($class_months))
                <p tc>沒有任何記錄</p>
            @else
                @foreach($userAttendMonths as $data)
                    <div class="tit">{{ $data['class_name'] }} ({{ $data['month'] }})</div>
                    <div class="txt">
                        @include('edu.components.attendance-summary', [
                            'summary'     => $data['attends_text'],
                            'hideAbsent'  => false,
                        ])
                    </div>
                @endforeach
            @endif
        </div>

        <div id="loading_fee">
            <h3>網上報名</h3>
            <div class="table">
                <table class="table">
                    <thead>
                    <tr>
                        <td>訂單</td>
                        <td>班級</td>
                        <td>學費</td>
                        <td>交費日期</td>
                        <td>每堂學費</td>
                        <td>最後一堂</td>
                        <td>下次付款</td>
                        <td>管理</td>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($display_orders as $item)
                        @php
                            $order           = $item['order'];
                            $class           = $item['class'];
                            $class_name      = $item['class_name'];
                            $pa_month        = $item['pa_month'];
                            $class_year      = $item['class_year'];
                            $renew           = $item['renew'];
                            $renew_status    = $item['renew_status'];
                            $renew_stop      = $item['renew_stop_status'];
                            $renew_btn_text  = $item['renew_button_text'];
                            $renew_current   = $item['renew_current'];
                        @endphp
                        <tr>
                            <td>{{ $order['order_id'] ?? '' }}</td>
                            <td tl>
                                @if($class)
                                    <a href="{{ route('edu.class.show', ['class' => $class['class_id'], 'month' => $pa_month, 'year' => $class_year]) }}"
                                       target="_blank">
                                        {{ $class_name }} (<b>{{ $pa_month }}</b>)
                                    </a>
                                @else
                                    {{ $order['order_item_name'] ?? '' }}
                                @endif
                            </td>
                            <td>{{ $order['_order_total'] ?? '' }}</td>
                            <td>
                                @if(!empty($order['_paid_date']))
                                    {{ date('Y-m-d', strtotime($order['_paid_date'])) }}
                                @endif
                            </td>
                            <td>{{ $renew['price'] ?? '' }}</td>
                            <td>{{ !empty($renew['class_days']) ? end($renew['class_days']) : '' }}</td>
                            <td>{{ $renew['renew'] ?? '' }}</td>
                            <td handle renew_current{{ $renew_current }}>
                                @if(!empty($renew['renew']))
                                    <u hidden
                                       renew_status{{ $renew_status }}
                                       order_renew
                                       order-id="0"
                                       order-class_id="{{ $class['class_id'] ?? '' }}"
                                       order-month="{{ $pa_month }}"
                                       order-class_year="{{ $class_year }}"
                                       order-renew="{{ $renew['renew'] }}">
                                        {{ $renew_btn_text }}
                                    </u>
                                    @if($renew_stop)
                                        <s>不續費</s>
                                    @endif
                                @endif
                            </td>
                        </tr>

                        @if($renew_current && !$renew_status && !$renew_stop)
                            <tr>
                                <td colspan="8" tl>
                                    <div renew_text style="background:#eee; padding:15px; border-radius:4px; line-height:1.5;">
                                        {{ $renew['text'] ?? '' }}
                                    </div>
                                    <div renew_btns>
                                        <div>
                                            <span renew_status{{ $renew_status }}
                                                  order_renew
                                                  order-id="0"
                                                  order-class_id="{{ $class['class_id'] ?? '' }}"
                                                  order-month="{{ $pa_month }}"
                                                  order-class_year="{{ $class_year }}"
                                                  order-renew="{{ $renew['renew'] ?? '' }}">繼續報名</span>
                                        </div>
                                        <div>
                                            <span renew_stop
                                                  order-id="woo_{{ $order['order_id'] ?? '' }}">不續費</span>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @endif
                    @endforeach
                    </tbody>
                </table>
            </div>

            <h3>WhatsApp報名</h3>
            <div class="table">
                <table class="table table1">
                    <thead>
                    <tr>
                        <td>訂單</td>
                        <td>班級</td>
                        <td>學費</td>
                        <td>交費日期</td>
                        <td>退款</td>
                        <td>退款原因</td>
                        <td>退款日期</td>
                        <td>付款方式</td>
                        <td>下次付款</td>
                        <td tc>管理</td>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($whatsapp_orders as $item)
                        @php
                            $order          = $item['order'];
                            $renew          = $item['renew'];
                            $class_year_wa  = $item['class_year'];
                        @endphp
                        <tr>
                            <td>{{ $order['id'] ?? '' }}</td>
                            <td tl>
                                <a href="{{ route('edu.class.show', ['class' => $order['class_id'], 'month' => $item['month'], 'year' => $class_year_wa]) }}"
                                   target="_blank">
                                    {{ $item['class_name'] }} (<b>{{ $item['month'] }}</b>)
                                </a>
                            </td>
                            <td>{{ $item['amount'] }}</td>
                            <td>
                                @if(!empty($order['order_date']))
                                    {{ date('Y-m-d', $order['order_date']) }}
                                @endif
                            </td>
                            <td>{{ $order['refund_fee'] ?? '' }}</td>
                            <td>{{ $order['refund_reason'] ?? '' }}</td>
                            <td>
                                @if(!empty($order['refund_date']))
                                    {{ date('Y-m-d', $order['refund_date']) }}
                                @endif
                            </td>
                            <td>{{ $order['gateway'] ?? '' }}</td>
                            <td tc>{{ $renew['renew'] ?? '' }}</td>
                            <td tc handle renew_current{{ $item['renew_current'] }}>
                                <u hidden
                                   renew_status{{ $item['renew_status'] }}
                                   order_renew
                                   order-id="{{ $order['id'] ?? '' }}"
                                   order-renew="{{ $renew['renew'] ?? '' }}">
                                    {{ $item['renew_button_text'] }}
                                </u>
                                @if($item['renew_stop_status'])
                                    <s>不續費</s>
                                @endif
                                <u order_refund order-id="{{ $order['id'] ?? '' }}">退款</u>
                                <u order_del    order-id="{{ $order['id'] ?? '' }}">刪除</u>
                            </td>
                        </tr>

                        @if($item['renew_current'] && !$item['renew_status'] && !$item['renew_stop_status'])
                            <tr>
                                <td colspan="11" tl>
                                    <div renew_text style="background:#eee; padding:15px; border-radius:4px; line-height:1.5;">
                                        {{ $renew['text'] ?? '' }}
                                    </div>
                                    <div renew_btns>
                                        <div>
                                            <span renew_status{{ $item['renew_status'] }}
                                                  order_renew
                                                  order-id="{{ $order['id'] ?? '' }}"
                                                  order-renew="{{ $renew['renew'] ?? '' }}">繼續報名</span>
                                        </div>
                                        <div>
                                            <span renew_stop
                                                  order-id="whatsapp_{{ $order['id'] ?? '' }}">不續費</span>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @endif
                    @endforeach
                    </tbody>
                </table>
            </div>

        </div>

        <div style="text-align:right;" mgt>
        <span class="form-button" add_order style="width:60px;">
            <i class="fa fa-plus"></i> 新增
        </span>
        </div>

        @include('edu.components.note', ['note' => $note])

    </form>

    <a href="javascript:history.back();"
       class="form-button mgt15"
       style="display:block;">返回上一頁</a>

    {{-- Hidden inputs — used by class_student_admin.js to build URLs --}}
    <input type="hidden" id="student_id"      value="{{ $student_id }}">
    <input type="hidden" id="user_id"         value="{{ $user_id }}">
    <input type="hidden" id="url_order_add"   value="{{ route('edu.order.add') }}">
    <input type="hidden" id="url_order_renew" value="{{ route('edu.order.renew') }}">
    <input type="hidden" id="url_order_refund"value="{{ route('edu.order.refund') }}">
    <input type="hidden" id="url_student"     value="{{ route('edu.student.show') }}">

@endsection

@push('scripts')
    <script src="{{ asset('assets/js/views/class_student_admin.js') }}"></script>
@endpush