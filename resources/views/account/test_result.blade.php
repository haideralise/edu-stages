@extends('layouts.app')

@section('title', 'My Test Results')

@section('content')
<h1 class="text-2xl font-bold mb-4">My Test Results</h1>

@if ($tree->isEmpty())
    <p class="text-gray-500">No assessment levels found.</p>
@else
    @foreach ($tree as $course)
        <div class="mb-6">
            <h2 class="text-xl font-semibold mb-3">{{ $course->name }}</h2>

            @if ($course->descendants && $course->descendants->count())
                @foreach ($course->descendants as $level)
                    <div class="bg-white rounded shadow mb-4 p-4">
                        <h3 class="text-lg font-medium mb-2">{{ $level->name }}</h3>

                        @if ($level->descendants && $level->descendants->count())
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Item</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Score</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Class</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    @foreach ($level->descendants as $item)
                                        @php $itemResults = $resultsByExamId->get($item->id, collect()); @endphp
                                        @if ($itemResults->count())
                                            @foreach ($itemResults as $result)
                                                <tr>
                                                    <td class="px-4 py-2 text-sm">{{ $item->name }}</td>
                                                    <td class="px-4 py-2 text-sm font-medium">{{ $result->exam_data }}</td>
                                                    <td class="px-4 py-2 text-sm">{{ $result->exam_date }}</td>
                                                    <td class="px-4 py-2 text-sm">{{ $result->class_month }} {{ $result->class_year }}</td>
                                                </tr>
                                            @endforeach
                                        @else
                                            <tr>
                                                <td class="px-4 py-2 text-sm">{{ $item->name }}</td>
                                                <td class="px-4 py-2 text-sm text-gray-400" colspan="3">No results yet</td>
                                            </tr>
                                        @endif
                                    @endforeach
                                </tbody>
                            </table>
                        @else
                            <p class="text-gray-400 text-sm">No items in this level.</p>
                        @endif
                    </div>
                @endforeach
            @endif
        </div>
    @endforeach
@endif
@endsection
