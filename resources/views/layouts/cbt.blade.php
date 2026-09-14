<!DOCTYPE html>
<html lang="id" class="dark h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>SimulasiKu - Sistem Ujian & CBT Online</title>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Tailwind CSS CDN Fallback & Alpine.js -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Plus Jakarta Sans', 'sans-serif'],
                    },
                    colors: {
                        brand: {
                            50: '#eff6ff',
                            100: '#dbeafe',
                            500: '#3b82f6',
                            600: '#2563eb',
                            700: '#1d4ed8',
                        }
                    }
                }
            }
        }
    </script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <style>
        [x-cloak] { display: none !important; }
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: #09090b;
            color: #f4f4f5;
        }
        /* Custom scrollbar for sleek dark mode */
        ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }
        ::-webkit-scrollbar-track {
            background: #18181b;
        }
        ::-webkit-scrollbar-thumb {
            background: #3f3f46;
            border-radius: 9999px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: #52525b;
        }
    </style>
</head>
<body class="min-h-full flex flex-col antialiased selection:bg-blue-600 selection:text-white bg-zinc-950">

    <!-- Navigation Header -->
    <header class="border-b border-zinc-800/80 bg-zinc-900/60 backdrop-blur-md sticky top-0 z-40">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            <a href="{{ route('simulasi.wizard') }}" class="flex items-center gap-2.5 group">
                <div class="w-9 h-9 rounded-xl bg-blue-600/20 border border-blue-500/30 flex items-center justify-center text-blue-400 group-hover:scale-105 transition-transform">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z"/>
                    </svg>
                </div>
                <span class="font-extrabold text-xl tracking-tight text-white">Simulasi<span class="text-blue-500">Ku</span></span>
            </a>

            <div class="flex items-center gap-3">
                <!-- Role Switcher Shortcuts -->
                <div class="hidden md:flex items-center gap-1.5 p-1 bg-zinc-900 rounded-lg border border-zinc-800 text-xs">
                    <a href="{{ route('simulasi.wizard') }}" class="px-3 py-1.5 rounded-md font-medium transition {{ request()->is('simulasi') || request()->is('/') ? 'bg-blue-600 text-white shadow-sm' : 'text-zinc-400 hover:text-zinc-200' }}">
                        Murid (Simulasi)
                    </a>
                    <a href="{{ route('guru.dashboard') }}" class="px-3 py-1.5 rounded-md font-medium transition {{ request()->is('guru*') ? 'bg-blue-600 text-white shadow-sm' : 'text-zinc-400 hover:text-zinc-200' }}">
                        Guru (Kelola Soal)
                    </a>
                    <a href="{{ route('admin.dashboard') }}" class="px-3 py-1.5 rounded-md font-medium transition {{ request()->is('admin*') ? 'bg-blue-600 text-white shadow-sm' : 'text-zinc-400 hover:text-zinc-200' }}">
                        Admin (Kelola User)
                    </a>
                </div>

                @auth
                    <div class="flex items-center gap-3">
                        <div class="text-right hidden sm:block">
                            <div class="text-sm font-semibold text-zinc-100">{{ Auth::user()->name }}</div>
                            <div class="text-xs text-blue-400 capitalize flex items-center justify-end gap-1">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                {{ Auth::user()->role }}
                            </div>
                        </div>
                        <form action="{{ route('logout') }}" method="POST">
                            @csrf
                            <button type="submit" class="px-3 py-1.5 text-xs font-semibold text-zinc-300 hover:text-white bg-zinc-800 hover:bg-zinc-700 rounded-lg transition border border-zinc-700">
                                Logout
                            </button>
                        </form>
                    </div>
                @else
                    <a href="{{ route('simulasi.wizard') }}" class="px-4 py-2 text-xs font-bold text-white bg-blue-600 hover:bg-blue-500 rounded-lg transition shadow-lg shadow-blue-600/20">
                        Masuk / Mulai Ujian
                    </a>
                @endauth
            </div>
        </div>
    </header>

    <!-- Main Content Area -->
    <main class="flex-1 flex flex-col justify-center py-6 px-4 sm:px-6 lg:px-8">
        @yield('content')
    </main>

    <!-- Footer -->
    <footer class="border-t border-zinc-900 py-4 text-center text-xs text-zinc-500">
        &copy; {{ date('Y') }} SimulasiKu - Computer Based Test & Online Examination System. All rights reserved.
    </footer>

    @yield('scripts')
</body>
</html>
