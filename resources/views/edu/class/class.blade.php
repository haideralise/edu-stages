@extends('edu.layouts.app')

@section('content')

    <meta name="csrf-token" content="{{ csrf_token() }}">

    <div class="h3">班級管理</div>

    <form action="" method="post">
        @csrf
        <input type="hidden" name="handle" value="update_user">

        <div class="filter form sublist">
            <div class="x1">
                <table class="table mytable">

                    {{-- Class name --}}
                    <tr>
                        <td>班級:</td>
                        <td class="class_name">{{ $class['class_name'] }}<u><i class="fa fa-edit"></i></u></td>
                    </tr>

                    {{-- Exam list --}}
                    <tr>
                        <td>評分:</td>
                        <td>
                            <ul class="exam_list sublist sortable">
                                @foreach ($exam as $value)
                                    <li data-id="{{ $value['id'] }}">{{ $value['name'] }}<u><i class="fa fa-close"></i></u></li>
                                @endforeach
                            </ul>
                            <div class="add_exam add_btn"><span class="form-button">新增評估</span></div>
                        </td>
                    </tr>

                    {{-- Month selection --}}
                    <tr>
                        <td>月份:</td>
                        <td>
                            <ul class="class_date sublist">
                                <li>
                                    @foreach ($class_users as $value)
                                        <label class="label-input">
                                            <input type="radio"
                                                   name="date_month"
                                                   year="{{ $value['class_year'] }}"
                                                   value="{{ $value['month'] }}"
                                                    {{ ($class_year == $value['class_year'] && $value['month'] == $class_month) ? 'checked' : '' }}>
                                            {{ $value['month'] }}
                                        </label>
                                    @endforeach
                                </li>
                            </ul>
                        </td>
                    </tr>

                    {{-- Manage months --}}
                    <tr>
                        <td colspan="2">
                            <span class="form-button class_months_btn" style="width:100px;float:right;">管理月份</span>
                        </td>
                    </tr>

                    {{-- Class dates --}}
                    <tr>
                        <td>日期</td>
                        <td class="class_days">
                            <div class="date_txt"></div>
                            <u><i class="fa fa-edit"></i></u>
                        </td>
                    </tr>

                    {{-- Daily student count --}}
                    <tr>
                        <td style="line-height:1.2">每日<br>人數</td>
                        <td id="analytisc_days"></td>
                    </tr>

                    {{-- Teachers --}}
                    <tr>
                        <td>老師:</td>
                        <td class="rtable">
                            <div class="xtable">
                                <table class="class_teacher sublist">
                                    <thead>
                                    <tr>
                                        <td>ID</td>
                                        <td>Eng Name</td>
                                        <td>中文姓名</td>
                                        <td>性別</td>
                                        <td>出席記錄</td>
                                        <td>電話</td>
                                        <td>刪除</td>
                                    </tr>
                                    </thead>
                                    <tbody class="teacher_list">
                                    @verbatim
                                        <script type="text/html">
                                            {{each teacher v i}}
                                            <tr>
                                                <td><span class="id">{{v.ID}}</span><input type="hidden" name="teacher[]" value="{{v.ID}}"></td>
                                                <td><a target="_blank" href="/edu/student?user_id={{v.ID}}">{{v.billing_last_name}}</a></td>
                                                <td><a target="_blank" href="/edu/student?user_id={{v.ID}}">{{v.billing_first_name}}</a></td>
                                                <td>{{v.billing_gender}}</td>
                                                <td style="text-align:left;">
                                                    {{if v.attendance_summary.present}}出席: {{@v.attendance_summary.present}}<br>{{/if}}
                                                    {{if v.attendance_summary.late}}請假: {{@v.attendance_summary.late}}<br>{{/if}}
                                                    {{if v.attendance_summary.absent}}取消: {{@v.attendance_summary.absent}}<br>{{/if}}
                                                    {{if v.attendance_summary.clear}}清除: {{@v.attendance_summary.clear}}<br>{{/if}}
                                                    {{if !v.attendance_summary.present && !v.attendance_summary.late && !v.attendance_summary.absent && !v.attendance_summary.clear}}
                                                    {{v.user_email||'沒有出席資料'}}
                                                    {{/if}}
                                                </td>
                                                <td><a target="_blank" href="https://wa.me/{{v.billing_phone}}">{{v.billing_phone}}</a></td>
                                                <td><u><i class="fa fa-close"></i></u></td>
                                            </tr>
                                            {{/each}}
                                        </script>
                                    @endverbatim
                                    </tbody>
                                </table>
                            </div>
                            <div class="box" tabindex="-1">
                                <div class="add_teacher add_btn add_user">
                                    <input type="text" class="form-text"><span class="form-label">新增老師</span>
                                </div>
                                <div class="suggest_wrap" data-role="teacher">
                                    <span class="close"><i class="fa fa-close"></i></span>
                                    <div class="table">
                                        <table class="suggest">
                                            @verbatim
                                                <script type="text/html">
                                                    {{each list v i}}
                                                    <tr>
                                                        <td><span class="id">{{v.ID}}</span><input type="hidden" value="{{v.ID}}"></td>
                                                        <td><a target="_blank" href="/edu/student?user_id={{v.ID}}">{{v.billing_last_name}}</a></td>
                                                        <td><a target="_blank" href="/edu/student?user_id={{v.ID}}">{{v.billing_first_name}}</a></td>
                                                        <td>{{v.billing_gender}}</td>
                                                        <td>{{v.user_email}}</td>
                                                        <td><a target="_blank" href="https://wa.me/{{v.billing_phone}}">{{v.billing_phone}}</a></td>
                                                        <td><u><i class="fa fa-close"></i></u></td>
                                                    </tr>
                                                    {{/each}}
                                                </script>
                                            @endverbatim
                                        </table>
                                    </div>
                                </div>
                            </div>
                            <br>
                        </td>
                    </tr>

                    {{-- Action buttons --}}
                    <tr>
                        <td></td>
                        <td style="padding:3px;">
                            <a href="/edu/attendance?class_id={{ $class['class_id'] }}">
                                <span class="form-button my_attn_btn" style="width:100px;float:right;">點名管理</span>
                            </a>
                            <span class="form-button order_btn" style="width:100px;float:right;margin-right:10px;">報名記錄</span>
                            <span class="form-button get_prev_btn" style="width:100px;float:right;margin-right:10px;">獲取上月資料</span>
                        </td>
                    </tr>

                    {{-- Students (regular) --}}
                    <tr>
                        <td>學生:</td>
                        <td class="rtable">
                            <div class="xtable">
                                <table class="class_student sublist">
                                    <thead>
                                    <tr>
                                        <td>ID</td>
                                        <td>Eng Name</td>
                                        <td>中文姓名</td>
                                        <td>性別</td>
                                        <td>出席記錄</td>
                                        <td>電話</td>
                                        <td>出席日期</td>
                                        <td>刪除</td>
                                    </tr>
                                    </thead>
                                    <tbody class="student_list student_list_student">
                                    @verbatim
                                        <script type="text/html">
                                            {{each student v i}}
                                            <tr>
                                                <td><span class="id">{{v.ID}}</span><input type="hidden" value="{{v.ID}}" name="student[]"></td>
                                                <td><a target="_blank" href="/edu/student?user_id={{v.ID}}">{{v.billing_last_name}}</a></td>
                                                <td><a target="_blank" href="/edu/student?user_id={{v.ID}}">{{v.billing_first_name}}</a></td>
                                                <td>{{v.billing_gender}}</td>
                                                <td style="text-align:left;">
                                                    {{if v.attendance_summary.present}}出席: {{@v.attendance_summary.present}}<br>{{/if}}
                                                    {{if v.attendance_summary.late}}請假: {{@v.attendance_summary.late}}<br>{{/if}}
                                                    {{if v.attendance_summary.absent}}取消: {{@v.attendance_summary.absent}}<br>{{/if}}
                                                    {{if v.attendance_summary.clear}}清除: {{@v.attendance_summary.clear}}<br>{{/if}}
                                                    {{if !v.attendance_summary.present && !v.attendance_summary.late && !v.attendance_summary.absent && !v.attendance_summary.clear}}
                                                    {{v.user_email||'沒有出席資料'}}
                                                    {{/if}}
                                                </td>
                                                <td><a target="_blank" href="https://wa.me/{{v.billing_phone}}">{{v.billing_phone}}</a></td>
                                                <td><u><i class="fa fa-clock-o"></i></u></td>
                                                <td><u><i class="fa fa-close"></i></u></td>
                                            </tr>
                                            {{/each}}
                                        </script>
                                    @endverbatim
                                    </tbody>
                                </table>
                            </div>
                            <div class="box" tabindex="-1">
                                <div class="add_student add_btn add_user">
                                    <input type="text" class="form-text"><span class="form-label">新增學生</span>
                                </div>
                                <div class="suggest_wrap" data-role="student">
                                    <span class="close"><i class="fa fa-close"></i></span>
                                    <div class="table">
                                        <table class="suggest">
                                            @verbatim
                                                <script type="text/html">
                                                    {{each list v i}}
                                                    <tr>
                                                        <td><span class="id">{{v.ID}}</span><input type="hidden" value="{{v.ID}}"></td>
                                                        <td><a target="_blank" href="/edu/student?user_id={{v.ID}}">{{v.billing_last_name}}</a></td>
                                                        <td><a target="_blank" href="/edu/student?user_id={{v.ID}}">{{v.billing_first_name}}</a></td>
                                                        <td>{{v.billing_gender}}</td>
                                                        <td>{{v.user_email}}</td>
                                                        <td><a target="_blank" href="https://wa.me/{{v.billing_phone}}">{{v.billing_phone}}</a></td>
                                                        <td><u><i class="fa fa-clock-o"></i></u></td>
                                                        <td><u><i class="fa fa-close"></i></u></td>
                                                    </tr>
                                                    {{/each}}
                                                </script>
                                            @endverbatim
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>

                    {{-- Makeup students (補堂 — counts for bonus) --}}
                    <tr>
                        <td style="line-height:1.2">補堂<br><small>計獎金</small></td>
                        <td class="rtable">
                            <div class="xtable">
                                <table class="class_student sublist">
                                    <thead>
                                    <tr>
                                        <td>ID</td>
                                        <td>Eng Name</td>
                                        <td>中文姓名</td>
                                        <td>性別</td>
                                        <td>出席記錄</td>
                                        <td>電話</td>
                                        <td>出席日期</td>
                                        <td>刪除</td>
                                    </tr>
                                    </thead>
                                    <tbody class="student_list student_list_makeup">
                                    @verbatim
                                        <script type="text/html">
                                            {{each student_makeup v i}}
                                            <tr>
                                                <td><span class="id">{{v.ID}}</span><input type="hidden" value="{{v.ID}}" name="student_makeup[]"></td>
                                                <td><a target="_blank" href="/edu/student?user_id={{v.ID}}">{{v.billing_last_name}}</a></td>
                                                <td><a target="_blank" href="/edu/student?user_id={{v.ID}}">{{v.billing_first_name}}</a></td>
                                                <td>{{v.billing_gender}}</td>
                                                <td style="text-align:left;">
                                                    {{if v.attendance_summary.present}}出席: {{@v.attendance_summary.present}}<br>{{/if}}
                                                    {{if v.attendance_summary.late}}請假: {{@v.attendance_summary.late}}<br>{{/if}}
                                                    {{if v.attendance_summary.absent}}取消: {{@v.attendance_summary.absent}}<br>{{/if}}
                                                    {{if v.attendance_summary.clear}}清除: {{@v.attendance_summary.clear}}<br>{{/if}}
                                                    {{if !v.attendance_summary.present && !v.attendance_summary.late && !v.attendance_summary.absent && !v.attendance_summary.clear}}
                                                    {{v.user_email||'沒有出席資料'}}
                                                    {{/if}}
                                                </td>
                                                <td><a target="_blank" href="https://wa.me/{{v.billing_phone}}">{{v.billing_phone}}</a></td>
                                                <td><u><i class="fa fa-clock-o"></i></u></td>
                                                <td><u><i class="fa fa-close"></i></u></td>
                                            </tr>
                                            {{/each}}
                                        </script>
                                    @endverbatim
                                    </tbody>
                                </table>
                            </div>
                            <div class="box" tabindex="-1">
                                <div class="add_student add_btn add_user">
                                    <input type="text" class="form-text"><span class="form-label">新增補堂生</span>
                                </div>
                                <div class="suggest_wrap" data-role="student_makeup">
                                    <span class="close"><i class="fa fa-close"></i></span>
                                    <div class="table">
                                        <table class="suggest">
                                            @verbatim
                                                <script type="text/html">
                                                    {{each list v i}}
                                                    <tr>
                                                        <td><span class="id">{{v.ID}}</span><input type="hidden" value="{{v.ID}}"></td>
                                                        <td><a target="_blank" href="/edu/student?user_id={{v.ID}}">{{v.billing_last_name}}</a></td>
                                                        <td><a target="_blank" href="/edu/student?user_id={{v.ID}}">{{v.billing_first_name}}</a></td>
                                                        <td>{{v.billing_gender}}</td>
                                                        <td>{{v.user_email}}</td>
                                                        <td><a target="_blank" href="https://wa.me/{{v.billing_phone}}">{{v.billing_phone}}</a></td>
                                                        <td><u><i class="fa fa-clock-o"></i></u></td>
                                                        <td><u><i class="fa fa-close"></i></u></td>
                                                    </tr>
                                                    {{/each}}
                                                </script>
                                            @endverbatim
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>

                    {{-- Transfer / other students (插班 — does NOT count for bonus) --}}
                    <tr>
                        <td style="line-height:1.2">插班、其他<br><small>不計獎金</small></td>
                        <td class="rtable">
                            <div class="xtable">
                                <table class="class_student sublist">
                                    <thead>
                                    <tr>
                                        <td>ID</td>
                                        <td>Eng Name</td>
                                        <td>中文姓名</td>
                                        <td>性別</td>
                                        <td>出席記錄</td>
                                        <td>電話</td>
                                        <td>出席日期</td>
                                        <td>刪除</td>
                                    </tr>
                                    </thead>
                                    <tbody class="student_list student_list_transfer">
                                    @verbatim
                                        <script type="text/html">
                                            {{each student_transfer v i}}
                                            <tr>
                                                <td><span class="id">{{v.ID}}</span><input type="hidden" value="{{v.ID}}" name="student_transfer[]"></td>
                                                <td><a target="_blank" href="/edu/student?user_id={{v.ID}}">{{v.billing_last_name}}</a></td>
                                                <td><a target="_blank" href="/edu/student?user_id={{v.ID}}">{{v.billing_first_name}}</a></td>
                                                <td>{{v.billing_gender}}</td>
                                                <td style="text-align:left;">
                                                    {{if v.attendance_summary.present}}出席: {{@v.attendance_summary.present}}<br>{{/if}}
                                                    {{if v.attendance_summary.late}}請假: {{@v.attendance_summary.late}}<br>{{/if}}
                                                    {{if v.attendance_summary.absent}}取消: {{@v.attendance_summary.absent}}<br>{{/if}}
                                                    {{if v.attendance_summary.clear}}清除: {{@v.attendance_summary.clear}}<br>{{/if}}
                                                    {{if !v.attendance_summary.present && !v.attendance_summary.late && !v.attendance_summary.absent && !v.attendance_summary.clear}}
                                                    {{v.user_email||'沒有出席資料'}}
                                                    {{/if}}
                                                </td>
                                                <td><a target="_blank" href="https://wa.me/{{v.billing_phone}}">{{v.billing_phone}}</a></td>
                                                <td><u><i class="fa fa-clock-o"></i></u></td>
                                                <td><u><i class="fa fa-close"></i></u></td>
                                            </tr>
                                            {{/each}}
                                        </script>
                                    @endverbatim
                                    </tbody>
                                </table>
                            </div>
                            <div class="box" tabindex="-1">
                                <div class="add_student add_btn add_user">
                                    <input type="text" class="form-text"><span class="form-label">新增插班生</span>
                                </div>
                                <div class="suggest_wrap" data-role="student_transfer">
                                    <span class="close"><i class="fa fa-close"></i></span>
                                    <div class="table">
                                        <table class="suggest">
                                            @verbatim
                                                <script type="text/html">
                                                    {{each list v i}}
                                                    <tr>
                                                        <td><span class="id">{{v.ID}}</span><input type="hidden" value="{{v.ID}}"></td>
                                                        <td><a target="_blank" href="/edu/student?user_id={{v.ID}}">{{v.billing_last_name}}</a></td>
                                                        <td><a target="_blank" href="/edu/student?user_id={{v.ID}}">{{v.billing_first_name}}</a></td>
                                                        <td>{{v.billing_gender}}</td>
                                                        <td>{{v.user_email}}</td>
                                                        <td><a target="_blank" href="https://wa.me/{{v.billing_phone}}">{{v.billing_phone}}</a></td>
                                                        <td><u><i class="fa fa-clock-o"></i></u></td>
                                                        <td><u><i class="fa fa-close"></i></u></td>
                                                    </tr>
                                                    {{/each}}
                                                </script>
                                            @endverbatim
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>

                </table>
            </div>
        </div>
    </form>

    <div class="section">
        <button class="form-button form-submit">保存</button>
    </div>

    <a href="javascript:history.back();" class="form-button mgt15" style="display:block;">返回上一頁</a>

    {{-- Exam level picker (hidden popup) --}}
    <div class="hide">
        <div class="level">
            <div>請選擇課程</div>
            <div class="lv1">
                <select name="lv1" class="form-select">
                    @foreach ($levelByPid as $value)
                        <option value="{{ $value['id'] }}">{{ $value['name'] }}</option>
                    @endforeach
                </select>
            </div>
            <div class="mgt">請選擇級別</div>
            <div class="lv2">
                <select name="lv2" class="form-select">
                    @verbatim
                        <script type="text/html">
                            {{each list v i}}
                            <option value="{{v.id}}">{{v.name}}</option>
                            {{/each}}
                        </script>
                    @endverbatim
                </select>
            </div>
            <div>
                <button class="form-button mgt add_exam">新增</button>
            </div>
        </div>
    </div>

    {{-- Hidden URL inputs for class.js --}}
    <input type="hidden" id="class_id"         value="{{ $class_id }}">
    <input type="hidden" id="url_class_months" value="/edu/class-months">
    <input type="hidden" id="url_class"        value="/edu/class">
    <input type="hidden" id="url_class_time"   value="/edu/class-time">
    <input type="hidden" id="url_class_day"    value="/edu/class-day">
    <input type="hidden" id="url_class_order"  value="/edu/class-order">

    <link rel="stylesheet" href="{{ asset('assets/css/class.css') }}">
    <script src="{{ asset('assets/js/views/class.js') }}"></script>

@endsection