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

        <h3>教練上課記錄</h3>
        <div class="table teacher_classes_record">
            <ul>
                @foreach($calc['classes'] ?? [] as $value)
                    <li>
                        <div>{{ $value['class_name'] }} &nbsp; ({{ $value['month'] }})</div>
                        @php
                            $summary    = null;
                            $monthRaw   = $value['month'];
                            $monthComp  = preg_match('/^\d{4}-\d{2}$/', $monthRaw)
                                ? ((int) date('n', strtotime($monthRaw . '-01'))) . '月'
                                : $monthRaw;

                            foreach ($userAttendMonths as $um) {
                                $umMonth = $um['original_month'] ?? $um['month'];
                                if (
                                    $um['class_name'] === $value['class_name'] &&
                                    (
                                        $umMonth === $monthComp ||
                                        $um['month'] === $monthComp ||
                                        preg_match('/(^|-)' . preg_quote($monthComp, '/') . '($|-)/u', (string) $umMonth) ||
                                        preg_match('/(^|-)' . preg_quote($umMonth, '/') . '($|-)/u', (string) $monthComp)
                                    )
                                ) {
                                    $summary = $um['attends_text'];
                                    break;
                                }
                            }
                        @endphp

                        @if($summary)
                            <div class="txt">
                                @include('edu.components.attendance-summary', [
                                    'summary'    => $summary,
                                    'hideAbsent' => false,
                                ])
                            </div>
                        @else
                            @foreach($value['days2'] ?? [] as $date => $v2)
                                <span>{{ $date }}</span>
                            @endforeach
                        @endif
                    </li>
                @endforeach

                <p>平日入場費: {{ $calc['workday_num'] ?? 0 }} * 17 = ${{ $calc['workday_fee'] ?? 0 }}</p>
                <p>周末入場費: {{ $calc['weekend_num'] ?? 0 }} * 19 = ${{ $calc['weekend_fee'] ?? 0 }}</p>
                <p>課時薪資: {{ $calc['class_num'] ?? 0 }} * {{ $hourly_wage }} = ${{ $calc['class_fee'] ?? 0 }}</p>

                @if(!empty($calc['bonus_info']['bonus_details']))
                    <div style="margin-top:20px; padding:15px; background:#f0f8ff; border-radius:5px;">
                        <h4 style="margin:0 0 10px 0; color:#2c5aa0;">🏆 續報獎金明細</h4>
                        <table style="width:100%; border-collapse:collapse; font-size:14px;">
                            <thead>
                            <tr style="background:#e6f3ff;">
                                <th style="border:1px solid #ccc; padding:8px; text-align:left;">泳班名稱</th>
                                <th style="border:1px solid #ccc; padding:8px; text-align:center;">學員名</th>
                                <th style="border:1px solid #ccc; padding:8px; text-align:right;">學費</th>
                                <th style="border:1px solid #ccc; padding:8px; text-align:center;">獎金百分比</th>
                                <th style="border:1px solid #ccc; padding:8px; text-align:right;">總數</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($calc['bonus_info']['bonus_details'] as $bonusDetail)
                                @foreach($bonusDetail['students'] as $studentBonus)
                                    @if($studentBonus['fee'] > 0)
                                        <tr>
                                            <td style="border:1px solid #ccc; padding:8px;">{{ $bonusDetail['class_name'] }}</td>
                                            <td style="border:1px solid #ccc; padding:8px; text-align:center;">{{ $studentBonus['student_name'] }}</td>
                                            <td style="border:1px solid #ccc; padding:8px; text-align:right;">${{ number_format($studentBonus['fee'], 2) }}</td>
                                            <td style="border:1px solid #ccc; padding:8px; text-align:center;">{{ number_format($studentBonus['bonus_rate'] * 100, 0) }}%</td>
                                            <td style="border:1px solid #ccc; padding:8px; text-align:right;">${{ number_format($studentBonus['bonus_amount'], 2) }}</td>
                                        </tr>
                                    @endif
                                @endforeach
                            @endforeach
                            </tbody>
                        </table>
                        <p style="margin:10px 0 0 0; font-weight:bold; color:#2c5aa0;">
                            總獎金: ${{ number_format($calc['bonus_info']['total_bonus'] ?? 0, 2) }}
                        </p>
                    </div>
                @endif

                <p><b>合計薪資</b>: ${{ $calc['amount'] ?? 0 }}</p>
            </ul>

            <div>
                <div class="h3">按月查詢</div>
                <ul class="month_list">
                    @php
                        $navDate    = clone $startDate;
                        $currentMonth = request()->get('month', date('Y-m'));
                    @endphp
                    @while($navDate <= $endDate)
                        @php $navMonth = $navDate->format('Y-m'); @endphp
                        <li @class(['on' => $navMonth === $currentMonth || (!request()->get('month') && date('Y-m') === $navMonth)])>
                            <a href="{{ route('edu.student.show', ['user_id' => $user_id, 'month' => $navMonth]) }}">
                                {{ $navMonth }}
                            </a>
                        </li>
                        @php $navDate->modify('+1 month'); @endphp
                    @endwhile
                </ul>
            </div>

            <br>

            <div style="text-align:center; padding-bottom:15px;">
                <a href="{{ route('edu.student.salary', ['id' => $user_id, 'month' => $currentMonth]) }}"
                   class="form-button"
                   style="width:150px;">下載薪資表</a>
            </div>
        </div>

        @include('edu.components.note', ['note' => $note])

    </form>

    <a href="javascript:history.back();"
       class="form-button mgt15"
       style="display:block;">返回上一頁</a>

    <input type="hidden" id="student_id"      value="{{ $student_id }}">
    <input type="hidden" id="user_id"         value="{{ $user_id }}">
    <input type="hidden" id="url_order_add"   value="{{ route('edu.order.add') }}">
    <input type="hidden" id="url_order_renew" value="{{ route('edu.order.renew') }}">
    <input type="hidden" id="url_student"     value="{{ route('edu.student.show') }}">

@endsection

@push('scripts')
    <script src="{{ asset('assets/js/views/class_student_coach.js') }}"></script>
@endpush