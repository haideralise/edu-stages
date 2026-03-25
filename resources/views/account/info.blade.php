@extends('layouts.app')

@section('title', 'Account Info — EDU Swimming')

@section('content')
<div class="flex items-start justify-center min-h-[70vh]">
    <div class="w-full max-w-md bg-white rounded-lg shadow p-8 mt-4">
        <h2 class="text-2xl font-bold text-center mb-6">Account Info</h2>

        {{-- Read-only user details --}}
        <div class="mb-6 space-y-2 text-sm text-gray-600">
            <div><span class="font-medium text-gray-700">Username:</span> {{ $user->user_login }}</div>
            <div><span class="font-medium text-gray-700">Email:</span> {{ $user->user_email }}</div>
            <div><span class="font-medium text-gray-700">Display Name:</span> {{ $user->display_name }}</div>
            @if ($user->birthdate)
                <div><span class="font-medium text-gray-700">Age:</span> {{ \Carbon\Carbon::parse($user->birthdate)->age }} years old</div>
            @endif
        </div>

        <hr class="mb-6">

        <form method="POST" action="{{ route('account.info.update') }}">
            @csrf

            <div class="mb-4">
                <label for="birthdate" class="block text-sm font-medium text-gray-700 mb-1">Date of Birth</label>
                <input type="date" name="birthdate" id="birthdate"
                       value="{{ old('birthdate', $user->birthdate) }}"
                       class="w-full border border-gray-300 rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                       required>
                @error('birthdate')
                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div class="mb-6">
                <label for="gender" class="block text-sm font-medium text-gray-700 mb-1">Gender</label>
                <select name="gender" id="gender"
                        class="w-full border border-gray-300 rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                        required>
                    <option value="">— Select —</option>
                    <option value="male" @selected(old('gender', $user->gender) === 'male')>Male</option>
                    <option value="female" @selected(old('gender', $user->gender) === 'female')>Female</option>
                </select>
                @error('gender')
                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <button type="submit"
                    class="w-full bg-blue-600 text-white py-2 px-4 rounded hover:bg-blue-700 font-medium">
                Save
            </button>
        </form>
    </div>
</div>
@endsection
