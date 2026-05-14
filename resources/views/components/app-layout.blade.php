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
                        <a href="{{ route('events.create') }}"
                            class="inline-flex items-center px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium shadow-sm transition-colors">
                            ＋ イベント作成
                        </a>
                        <details class="relative hidden sm:block group/user">
                            <summary class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-400 cursor-pointer select-none list-none rounded-lg px-2 py-1 hover:bg-slate-100 dark:hover:bg-[#1a1a18] transition-colors">
                                <span class="flex h-7 w-7 items-center justify-center rounded-full bg-indigo-100 dark:bg-indigo-900/50 text-indigo-700 dark:text-indigo-300 text-xs font-semibold">
                                    {{ mb_substr(auth()->user()->name, 0, 1) }}
                                </span>
                                {{ auth()->user()->name }}
                                <svg class="h-3.5 w-3.5 text-slate-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M5.22 8.22a.75.75 0 0 1 1.06 0L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" />
                                </svg>
                            </summary>
                            <div class="absolute right-0 mt-1 w-48 rounded-xl bg-white dark:bg-[#161615] border border-slate-200 dark:border-[#3E3E3A] shadow-lg py-1 z-50">
                                <a href="{{ route('profile') }}"
                                    class="block px-4 py-2 text-sm text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-[#1a1a18] transition-colors">
                                    プロフィール
                                </a>
                                <a href="{{ route('my.attendances') }}"
                                    class="block px-4 py-2 text-sm text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-[#1a1a18] transition-colors">
                                    申し込み一覧
                                </a>
                                <div class="my-1 border-t border-slate-100 dark:border-[#3E3E3A]"></div>
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit"
                                        class="w-full text-left px-4 py-2 text-sm text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-[#1a1a18] transition-colors">
                                        ログアウト
                                    </button>
                                </form>
                            </div>
                        </details>
                    @else
                        @if (Route::has('login'))
                            <a href="{{ route('login') }}"
                                class="inline-flex items-center px-3 py-2 rounded-lg text-slate-700 dark:text-slate-300 hover:text-indigo-600 dark:hover:text-indigo-400 text-sm font-medium transition-colors">
                                ログイン
                            </a>
                        @endif
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
