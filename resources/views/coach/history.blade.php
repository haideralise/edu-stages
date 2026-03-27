@extends('layouts.app')

@section('title', 'Coach - History Results')

@section('content')
<div class="flex items-center justify-between mb-4">
    <h1 class="text-2xl font-bold">History Results</h1>

    <form method="GET" action="{{ route('coach.history') }}" class="flex items-center gap-2">
        <select name="class_year" class="border rounded px-3 py-1.5 text-sm">
            <option value="">All Years</option>
            @foreach ($years as $year)
                <option value="{{ $year }}" {{ request('class_year') == $year ? 'selected' : '' }}>{{ $year }}</option>
            @endforeach
        </select>
        <button type="submit" class="bg-blue-500 text-white px-3 py-1.5 rounded text-sm hover:bg-blue-600">Filter</button>
    </form>
</div>

@if ($resultsByClassMonth->isEmpty())
    <p class="text-gray-500">No history results found.</p>
@else
    @foreach ($resultsByClassMonth as $period => $results)
        <div class="bg-white rounded shadow mb-4 p-4">
            <h2 class="text-lg font-semibold mb-3">{{ $period }}</h2>

            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Student</th>
                        @if ($isAdmin)
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Coach</th>
                        @endif
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Exam</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Score</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @foreach ($results as $result)
                        @php $student = $students->get($result->user_id); @endphp
                        <tr>
                            <td class="px-4 py-2 text-sm">{{ $student ? $student->display_name : "Student #{$result->user_id}" }}</td>
                            @if ($isAdmin)
                                <td class="px-4 py-2 text-sm text-gray-500">{{ $coaches->get($result->class_id, '—') }}</td>
                            @endif
                            <td class="px-4 py-2 text-sm">{{ $result->exam_name }}</td>
                            <td class="px-4 py-2 text-sm font-medium">{{ $result->exam_data }}</td>
                            <td class="px-4 py-2 text-sm">{{ $result->exam_date }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endforeach
@endif
@endsection
