<x-filament-panels::page>
 <div class="h3">Class Management
        @if($no_teacher_num)
            <span class="no_teacher_num" title="Classes without coach">
                {{ $no_teacher_num }}
            </span>
        @endif
        @if($no_days_num)
            <span class="no_days_num" title="Classes with non-compliant session count">
                {{ $no_days_num }}
            </span>
        @endif
    </div>

    @include('edu.components.district-menu')
    @include('edu.includes.search-classes')
    @include('edu.components.month-selector')

    <div class="filter form condition page_classes_list">
        <ul>
            @forelse ($processedClasses as $_class)
                <li class="class {{ $_class['style'] ?? '' }} {{ $_class['style2'] ?? '' }}">
                    <h3>
                        <a href="{{ route('edu.class.show', [
                            'class' => $_class['class_id'],
                            'month' => $_class['class_month'],
                        ]) }}">
                            {{ $_class['class_name'] }}
                        </a>

                        <span>
                            <a class="edit" href="{{ route('edu.attendance', [
                                'district_id'  => $district_id,
                                'district_id2' => $district_id2,
                                'lv3'          => $lv3,
                                'class_id'     => $_class['class_id'],
                            ]) }}">
                                Attendance <i class="fa fa-edit"></i>
                            </a>
                        </span>
                    </h3>

                    <ul>
                        <li>Teacher: {{ $_class['teacher_txt'] ?? '—' }}</li>
                        <li>Students: {{ $_class['student_txt'] ?? '—' }}</li>
                        <li>Make-up: {{ $_class['student_transfer_txt'] ?? '—' }}</li>
                        <li>Class Dates: {{ $_class['class_every_day'] ?? 'N/A' }}</li>
                    </ul>
                </li>
            @empty
                {{-- Stage 1 placeholder — removed once ClassMonthFacade is wired --}}
                <li class="no-data">
                    No classes loaded yet — data will appear in Stage 3.
                </li>
            @endforelse
        </ul>
    </div>

    @if($is_admin)
        <div class="section">
            <a href="{{ route('edu.class.create') }}">
                <button class="form-button">Add Class</button>
            </a>
        </div>
    @endif

    <link rel="stylesheet" href="{{ asset('assets/css/classes.css') }}">
    <script src="{{ asset('assets/js/views/class_classes.js') }}"></script>

</x-filament-panels::page>