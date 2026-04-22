@extends('edu.layouts.app')

@section('content')

    <meta name="csrf-token" content="{{ csrf_token() }}">

    <div class="h3">評估項目</div>
    <div class="level section pd">
        <ul>
            <li><b>課程</b>:
                @foreach(collect($level)->where('pid', 0) as $value)
                    <a @if($lv1_id == $value['id']) class="on" @endif
                       href="?route=field&lv1={{ $value['id'] }}"
                       data-id="{{ $value['id'] }}">{{ $value['name'] }}</a>
                @endforeach
            </li>
            <li class="mgt"><b>級別</b>:
                @if($lv1_id)
                    @foreach(collect($level)->where('pid', $lv1_id) as $value)
                        <a @if($lv2_id == $value['id']) class="on" @endif
                           href="?route=field&lv1={{ $lv1_id }}&lv2={{ $value['id'] }}"
                           data-id="{{ $value['id'] }}">{{ $value['name'] }}</a>
                    @endforeach
                @endif
            </li>
        </ul>
    </div>
    <div class="list">
        <table class="table">
            <thead>
                <tr>
                    <th>評估項目</th>
                    <th>答題類型</th>
                    <th>選項(單選/多選必填)</th>
                    <th>是否必填</th>
                    <th>修改</th>
                </tr>
            </thead>
            <tbody class="field_list">
                @if($lv2_id)
                    @foreach(collect($level)->where('pid', $lv2_id) as $value)
                        @php $item = json_decode($value['data'] ?? '{}', true) ?? []; @endphp
                        <tr>
                            <td>
                                <input type="text" value="{{ $item['name'] ?? '' }}" name="name" class="form-text">
                                <input type="hidden" name="id" value="{{ $value['id'] }}">
                                <input type="hidden" name="_handle" value="update_item">
                                <div class="file_level">
                                    <input type="text" value="{{ $value['file_level'] ?? '' }}" name="file_level" class="form-text">
                                    <span class="uploader"><i class="fa fa-upload"></i></span>
                                </div>
                            </td>
                            <td>
                                <form class="label">
                                    <label><input type="radio" name="type" value="text"     @checked(($item['type'] ?? '') === 'text')>文字</label>
                                    <label><input type="radio" name="type" value="number"   @checked(($item['type'] ?? '') === 'number')>數字</label>
                                    <label><input type="radio" name="type" value="time"     @checked(($item['type'] ?? '') === 'time')>時間</label>
                                    <label><input type="radio" name="type" value="radio"    @checked(($item['type'] ?? '') === 'radio')>單選</label>
                                    <label><input type="radio" name="type" value="checkbox" @checked(($item['type'] ?? '') === 'checkbox')>多選</label>
                                </form>
                            </td>
                            <td>
                                <label><textarea name="item" class="form-textarea" placeholder="選項(單選/多選必填);每行一個選項">{{ $item['item'] ?? '' }}</textarea></label>
                            </td>
                            <td>
                                <input type="checkbox" name="required" value="1" @checked(!empty($item['required']))>
                            </td>
                            <td>
                                <button class="form-button update_item">保存</button>
                                <button class="form-button delete_item mgt">刪除</button>
                            </td>
                        </tr>
                    @endforeach
                @endif
            </tbody>
        </table>
    </div>
    <div class="section add">
        <table class="table">
            <tr>
                <td colspan="4"><input type="text" name="lv1" class="form-text"></td>
                <td><button class="form-button add_lv1">增加課程</button></td>
            </tr>
            @if($lv1_id)
                <tr>
                    <td colspan="4"><input type="text" name="lv2" class="form-text"></td>
                    <td><button class="form-button add_lv2">增加級別</button></td>
                </tr>
            @endif
            @if($lv2_id)
                <tr>
                    <td>
                        <input type="text" value="" name="name" class="form-text" placeholder="請輸入評估項目, eg: 遊泳時間">
                        <input type="hidden" name="_handle" value="add_item">
                    </td>
                    <td>
                        <form class="label">
                            <label><input type="radio" name="type" value="text">文字</label>
                            <label><input type="radio" name="type" value="number">數字</label>
                            <label><input type="radio" name="type" value="time">時間</label>
                            <label><input type="radio" name="type" value="radio">單選</label>
                            <label><input type="radio" name="type" value="checkbox">多選</label>
                        </form>
                    </td>
                    <td>
                        <label><textarea name="item" class="form-textarea" placeholder="選項(單選/多選必填);每行一個選項"></textarea></label>
                    </td>
                    <td><input type="checkbox" name="required" value="1">必填</td>
                    <td><button class="form-button add_item">增加評估項目</button></td>
                </tr>
            @endif
        </table>
    </div>

    @if($lv1_id)
        <input type="hidden" id="lv1_id" value="{{ $lv1_id }}">
    @endif

@endsection

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/asses-field.css') }}">
@endpush

@push('scripts')
    <script src="{{ asset('assets/js/views/asses_field.js') }}"></script>
@endpush
