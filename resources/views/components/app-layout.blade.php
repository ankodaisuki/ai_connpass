<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? config('app.name', 'AI Connpass') }}</title>
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-gradient-to-br from-slate-50 to-slate-100 dark:from-[#0a0a0a] dark:to-[#161615] text-slate-900 dark:text-slate-100 antialiased">
    <header class="sticky top-0 z-40 bg-white/80 dark:bg-[#0a0a0a]/80 backdrop-blur-md border-b border-slate-200 dark:border-[#3E3E3A]">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex h-16 items-center justify-between">
                <a href="{{ route('events.index') }}" class="flex items-center gap-2 group">
                    <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-gradient-to-br from-indigo-500 to-purple-600 text-white font-bold text-sm group-hover:scale-105 transition-transform">
                        AC
                    </span>
                    <span class="font-bold text-lg tracking-tight">AI Connpass</span>
                </a>

                <nav class="flex items-center gap-2 sm:gap-3">
                    @auth
                        <span class="hidden sm:inline-flex items-center gap-2 text-sm text-slate-600 dark:text-slate-400">
                            <span class="flex h-7 w-7 items-center justify-center rounded-full bg-indigo-100 dark:bg-indigo-900/50 text-indigo-700 dark:text-indigo-300 text-xs font-semibold">
                                {{ mb_substr(auth()->user()->name, 0, 1) }}
                            </span>
                            {{ auth()->user()->name }}
                        </span>
                    @else
                        @if (Route::has('register'))
                            <a href="{{ route('register') }}"
                                class="inline-flex items-center px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium shadow-sm transition-colors">
                                新規登録
                            </a>
                        @endif
                    @endauth
                </nav>
            </div>
        </div>
    </header>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 lg:py-12">
        {{ $slot }}
    </main>

    <footer class="border-t border-slate-200 dark:border-[#3E3E3A] mt-16">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 text-center text-sm text-slate-500 dark:text-slate-400">
            &copy; {{ date('Y') }} AI Connpass
        </div>
    </footer>
</body>
</html>
