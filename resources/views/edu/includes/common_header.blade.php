@include('edu.includes.head')

<div class="menu">
    <ul>
        @if ($is_admin)
            <li><a href="{{ Route::has('filament.admin.pages.classes') ? route('filament.admin.pages.classes') : '#' }}">泳班管理</a></li>
            <li><a href="{{ route('edu.attendance.index') }}">點名管理</a></li>
            <li><a href="{{ route('admin.coach.private-classes') }}">私人泳班</a></li>
            <li><a href="{{ route('edu.coach.salary') }}">教練薪資</a></li>
            <li><a href="{{ route('admin.coach.index') }}">教練管理</a></li>
            <li><a href="{{ route('admin.order.renew.list') }}">續費管理</a></li>
            <li><a href="{{ route('admin.assess.field') }}">評估項目</a></li>
            <li><a href="{{ route('edu.assess.index') }}">查看成績</a></li>
            {{-- TODO: analysis deferred per todolist.md --}}
            <li><a href="#">統計分析</a></li>
            <li style="display: none;"><a href="#">用戶管理</a></li>
            @if (isset($user['user_login']) && $user['user_login'] === 'mssc')
                <li><a href="{{ route('admin.admin-log.index') }}" style="color: #28a745; font-weight: bold;" target="_blank">📋 log記錄</a></li>
            @endif
        @else
            {{-- TODO: result route not yet defined by P1 (coach group commented out) --}}
            <li><a href="#">教練評分</a></li>
            <li><a href="{{ route('edu.attendance.index') }}">點名管理</a></li>
            <li><a href="{{ route('edu.assess.index') }}">查看成績</a></li>
            <li><a href="{{ route('edu.coach.salary') }}">教練薪資</a></li>
            <li><a href="{{ url('/my-account') }}">回到我的賬戶</a></li>
        @endif
    </ul>
</div>
