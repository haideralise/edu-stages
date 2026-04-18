@extends('edu.layouts.app')

@section('content')

    <div class="h3">操作記錄</div>

    <div class="section list">
        <table class="table">
            <thead>
            <tr>
                <td>ID</td>
                <td>管理員</td>
                <td>操作</td>
                <td>班級</td>
                <td>時間</td>
                <td>考試項目</td>
                <td>學生</td>
                <td>修改前</td>
                <td>修改後</td>
                <td>記錄時間</td>
            </tr>
            </thead>
            <tbody>
            @forelse($rows as $row)
                <tr>
                    <td>{{ $row['id'] }}</td>
                    <td>{{ $row['admin_name'] }}</td>
                    <td>{{ $row['handle'] }}</td>
                    <td>
                        <a href="{{ $row['exam_link'] }}">
                            {{ $row['exam_label'] }}
                        </a>
                    </td>
                    <td>{{ $row['exam_date'] }}</td>
                    <td>{{ $row['level_name'] }}</td>
                    <td>{{ $row['student_name'] }}</td>
                    <td>{{ $row['before'] }}</td>
                    <td>{{ $row['after'] }}</td>
                    <td>{{ $row['created'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="10" style="text-align:center;">暫無記錄</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

@endsection