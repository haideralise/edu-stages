<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'EDU Swimming')</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen">
    <nav class="bg-white shadow">
        <div class="max-w-7xl mx-auto px-4 py-3 flex items-center justify-between">
            <span class="font-bold text-lg">EDU Swimming</span>
            @auth
                @php $role = auth()->user()->resolveRole(); @endphp
                <div class="flex items-center gap-4 text-sm">
                    @if ($role === 'student' || $role === 'admin')
                        <a href="{{ route('account.mybmi') }}" class="hover:underline">My BMI</a>
                        <a href="{{ route('account.test-result') }}" class="hover:underline">Test Results</a>
                    @endif
                    @if ($role === 'coach' || $role === 'admin')
                        <a href="{{ route('coach.results') }}" class="hover:underline">Coach Results</a>
                    @endif
                    <span class="text-gray-500">{{ auth()->user()->display_name }} ({{ $role }})</span>
                    <form method="POST" action="{{ route('logout') }}" class="inline">
                        @csrf
                        <button type="submit" class="text-red-500 hover:underline">Logout</button>
                    </form>
                </div>
            @endauth
        </div>
    </nav>

    <main class="max-w-7xl mx-auto px-4 py-6">
        @if (session('success'))
            <div class="mb-4 p-3 bg-green-100 text-green-700 rounded">
                {{ session('success') }}
            </div>
        @endif

        @if (session('error'))
            <div class="mb-4 p-3 bg-red-100 text-red-700 rounded">
                {{ session('error') }}
            </div>
        @endif

        @yield('content')
    </main>
</body>
</html>
