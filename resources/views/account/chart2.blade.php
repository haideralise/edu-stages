@extends('layouts.app')

@section('title', 'Growth Chart')

@section('content')
<h1 class="text-2xl font-bold mb-4">Growth Chart</h1>

@if ($isAdmin && $students)
    <div class="mb-4">
        <label class="text-sm font-medium text-gray-700 mr-2">Select Student:</label>
        <select id="student-selector" class="border rounded px-3 py-1.5 text-sm">
            @foreach ($students as $student)
                <option value="{{ $student->ID }}">{{ $student->display_name }} ({{ $student->user_login }})</option>
            @endforeach
        </select>
    </div>
@endif

<div class="flex gap-2 mb-4">
    <button data-chart-type="height" class="px-4 py-2 rounded text-sm bg-gray-200 text-gray-700">Height</button>
    <button data-chart-type="weight" class="px-4 py-2 rounded text-sm bg-gray-200 text-gray-700">Weight</button>
    <button data-chart-type="bmi" class="px-4 py-2 rounded text-sm bg-blue-500 text-white">BMI</button>
    <button data-chart-type="hc" class="px-4 py-2 rounded text-sm bg-gray-200 text-gray-700">Head Circ.</button>
    <button data-chart-type="result" class="px-4 py-2 rounded text-sm bg-gray-200 text-gray-700">Results</button>
</div>

<div class="bg-white rounded shadow p-4">
    <div id="chart2-container"
         data-user-id="{{ $isAdmin && $students->isNotEmpty() ? $students->first()->ID : $user->ID }}"
         style="width: 100%; height: 500px;">
    </div>
</div>

@vite('resources/js/chart2.js')
@endsection
