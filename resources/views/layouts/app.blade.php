<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'EDU Swimming')</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .sidebar-link {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            color: #333;
            text-decoration: none;
            font-size: 14px;
            border-left: 3px solid transparent;
            transition: all 0.15s;
        }
        .sidebar-link:hover {
            background: #f0f7ff;
        }
        .sidebar-link.active {
            border-left-color: #3b82f6;
            color: #3b82f6;
            background: #f0f7ff;
            font-weight: 500;
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">
    {{-- Top navigation bar --}}
    <nav class="bg-white shadow relative z-10">
        <div class="px-4 py-3 flex items-center justify-between">
            <span class="font-bold text-lg text-blue-600">EDU Swimming</span>
            @auth
                @php $role = auth()->user()->resolveRole(); @endphp
                <div class="flex items-center gap-4 text-sm">
                    <span class="text-gray-600">{{ auth()->user()->display_name }} ({{ $role }})</span>
                    <form method="POST" action="{{ route('logout') }}" class="inline">
                        @csrf
                        <button type="submit" class="text-red-500 hover:underline">Logout</button>
                    </form>
                </div>
            @endauth
        </div>
    </nav>

    <div class="flex min-h-[calc(100vh-56px)]">
        {{-- Left sidebar --}}
        @auth
        @php $role = auth()->user()->resolveRole(); @endphp
        <aside class="w-[250px] bg-white shadow-sm flex-shrink-0">
            <div class="px-5 py-4 border-b">
                <h2 class="font-bold text-sm text-gray-700">Student Area</h2>
            </div>
            <nav class="py-2">
                @if ($role === 'student' || $role === 'admin')
                    <a href="{{ route('account.mybmi') }}"
                       class="sidebar-link {{ request()->routeIs('account.mybmi') ? 'active' : '' }}">
                        Dashboard
                    </a>
                    <a href="#"
                       class="sidebar-link">
                        Student Manual
                    </a>
                    <a href="{{ route('account.info') }}"
                       class="sidebar-link {{ request()->routeIs('account.info') ? 'active' : '' }}">
                        Account Info
                    </a>
                    <a href="{{ route('account.test-result') }}"
                       class="sidebar-link {{ request()->routeIs('account.test-result') ? 'active' : '' }}">
                        Assessment
                    </a>
                    <a href="{{ route('account.mybmi') }}"
                       class="sidebar-link {{ request()->routeIs('account.mybmi*') ? 'active' : '' }}">
                        Health Records
                    </a>
                    <a href="{{ route('account.chart2') }}"
                       class="sidebar-link {{ request()->routeIs('account.chart2') ? 'active' : '' }}">
                        Growth Chart
                    </a>
                @endif

                @if ($role === 'coach' || $role === 'admin')
                    <a href="{{ route('coach.results') }}"
                       class="sidebar-link {{ request()->routeIs('coach.results') ? 'active' : '' }}">
                        Coach Results
                    </a>
                    <a href="{{ route('coach.history') }}"
                       class="sidebar-link {{ request()->routeIs('coach.history') ? 'active' : '' }}">
                        History Results
                    </a>
                @endif

                @if ($role === 'admin')
                    <a href="#"
                       class="sidebar-link">
                        System Admin
                    </a>
                @endif

                <div class="border-t mt-2 pt-2">
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="sidebar-link w-full text-left text-red-500 hover:text-red-600">
                            Logout
                        </button>
                    </form>
                </div>
            </nav>
        </aside>
        @endauth

        {{-- Main content area --}}
        <main class="flex-1 p-6">
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
    </div>
</body>
</html>
