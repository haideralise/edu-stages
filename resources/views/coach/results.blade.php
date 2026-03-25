@extends('layouts.app')

@section('title', 'Coach - Student Results')

@section('content')
<h1 class="text-2xl font-bold mb-4">Student Results</h1>

@if ($resultsByStudent->isEmpty())
    <p class="text-gray-500">No results found for your students.</p>
@else
    @foreach ($resultsByStudent as $userId => $results)
        @php $student = $students->get($userId); @endphp
        <div class="bg-white rounded shadow mb-4 p-4">
            <h2 class="text-lg font-semibold mb-3">
                {{ $student ? $student->display_name : "Student #{$userId}" }}
            </h2>

            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Exam</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Score</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Class Period</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @foreach ($results as $result)
                        <tr>
                            <td class="px-4 py-2 text-sm">{{ $result->exam_name }}</td>
                            <td class="px-4 py-2 text-sm font-medium">{{ $result->exam_data }}</td>
                            <td class="px-4 py-2 text-sm">{{ $result->exam_date }}</td>
                            <td class="px-4 py-2 text-sm">{{ $result->class_month }} {{ $result->class_year }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endforeach
@endif
@endsection
