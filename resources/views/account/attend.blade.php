@extends('layouts.app')

@section('title', 'Attendance Records')

@section('content')
<h1 class="text-2xl font-bold mb-4">Attendance Records</h1>

@if (empty($records))
    <p class="text-gray-500">No attendance records found.</p>
@else
    @foreach ($records as $classId => $months)
        <div class="mb-6">
            <h2 class="text-xl font-semibold mb-3">{{ $classes[$classId] ?? "Class #{$classId}" }}</h2>

            @foreach ($months as $month => $dates)
                <div class="bg-white rounded shadow mb-4 p-4">
                    <h3 class="text-lg font-medium mb-2">{{ $month }}</h3>

                    @if (empty($dates))
                        <p class="text-gray-400 text-sm">No attendance data for this month.</p>
                    @else
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                @foreach ($dates as $date => $status)
                                    @php
                                        $label = ucfirst($status);
                                        $color = match ($status) {
                                            'present' => 'text-green-600 bg-green-50',
                                            'leave' => 'text-yellow-600 bg-yellow-50',
                                            'cancelled' => 'text-red-600 bg-red-50',
                                            default => 'text-gray-600 bg-gray-50',
                                        };
                                    @endphp
                                    <tr>
                                        <td class="px-4 py-2 text-sm">{{ $date }}</td>
                                        <td class="px-4 py-2 text-sm">
                                            <span class="inline-block px-2 py-1 rounded text-xs font-medium {{ $color }}">
                                                {{ $label }}
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            @endforeach
        </div>
    @endforeach
@endif
@endsection
