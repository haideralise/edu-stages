@extends('layouts.app')

@section('title', 'My BMI Records')

@section('content')
<div class="flex items-center justify-between mb-4">
    <h1 class="text-2xl font-bold">My BMI Records</h1>
    <a href="{{ route('account.bmi.create') }}" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
        Add BMI
    </a>
</div>

@if ($records->isEmpty())
    <p class="text-gray-500">No BMI records found.</p>
@else
    <div class="bg-white rounded shadow overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Height (cm)</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Weight (kg)</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">HC (cm)</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">BMI</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Category</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @foreach ($records as $record)
                <tr>
                    <td class="px-4 py-3 text-sm">{{ date('Y-m-d', $record->date) }}</td>
                    <td class="px-4 py-3 text-sm">{{ $record->height }}</td>
                    <td class="px-4 py-3 text-sm">{{ $record->weight }}</td>
                    <td class="px-4 py-3 text-sm">{{ $record->hc ?: '-' }}</td>
                    <td class="px-4 py-3 text-sm font-medium">{{ $record->bmi }}</td>
                    <td class="px-4 py-3 text-sm">
                        @php
                            $colors = [
                                'underweight' => 'bg-yellow-100 text-yellow-800',
                                'normal'      => 'bg-green-100 text-green-800',
                                'overweight'  => 'bg-orange-100 text-orange-800',
                                'obese'       => 'bg-red-100 text-red-800',
                            ];
                        @endphp
                        <span class="px-2 py-1 rounded text-xs font-medium {{ $colors[$record->category] ?? 'bg-gray-100' }}">
                            {{ ucfirst($record->category) }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-sm flex gap-2">
                        <a href="{{ route('account.bmi.edit', $record) }}" class="text-blue-600 hover:underline">Edit</a>
                        <form action="{{ route('account.bmi.delete', $record) }}" method="POST" class="inline">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-red-600 hover:underline" onclick="return confirm('Delete this record?')">Delete</button>
                        </form>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
@endsection
