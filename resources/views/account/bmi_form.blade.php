@extends('layouts.app')

@section('title', $bmi ? 'Edit BMI' : 'Add BMI')

@section('content')
<h1 class="text-2xl font-bold mb-4">{{ $bmi ? 'Edit BMI Record' : 'Add BMI Record' }}</h1>

<div class="bg-white rounded shadow p-6 max-w-lg">
    <form method="POST"
          action="{{ $bmi ? route('account.bmi.update', $bmi) : route('account.bmi.store') }}">
        @csrf
        @if ($bmi)
            @method('PUT')
        @endif

        <div class="mb-4">
            <label for="date" class="block text-sm font-medium text-gray-700 mb-1">Date</label>
            <input type="date" id="date_picker" class="w-full border rounded px-3 py-2"
                   value="{{ old('date_picker', $bmi ? date('Y-m-d', $bmi->date) : '') }}"
                   onchange="document.getElementById('date').value = Math.floor(new Date(this.value).getTime()/1000)">
            <input type="hidden" name="date" id="date"
                   value="{{ old('date', $bmi ? $bmi->date : '') }}">
            @error('date')
                <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div class="mb-4">
            <label for="height" class="block text-sm font-medium text-gray-700 mb-1">Height (cm)</label>
            <input type="number" name="height" id="height" step="0.1" min="30" max="250"
                   class="w-full border rounded px-3 py-2"
                   value="{{ old('height', $bmi?->height) }}">
            @error('height')
                <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div class="mb-4">
            <label for="weight" class="block text-sm font-medium text-gray-700 mb-1">Weight (kg)</label>
            <input type="number" name="weight" id="weight" step="0.1" min="1" max="300"
                   class="w-full border rounded px-3 py-2"
                   value="{{ old('weight', $bmi?->weight) }}">
            @error('weight')
                <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div class="mb-4">
            <label for="hc" class="block text-sm font-medium text-gray-700 mb-1">Head Circumference (cm, optional)</label>
            <input type="number" name="hc" id="hc" step="0.1" min="20" max="100"
                   class="w-full border rounded px-3 py-2"
                   value="{{ old('hc', $bmi?->hc) }}">
            @error('hc')
                <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex gap-3">
            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                {{ $bmi ? 'Update' : 'Save' }}
            </button>
            <a href="{{ route('account.mybmi') }}" class="px-4 py-2 rounded border hover:bg-gray-50">Cancel</a>
        </div>
    </form>
</div>
@endsection
