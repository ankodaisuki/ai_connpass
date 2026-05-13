<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name', 'Laravel') }} - Register</title>
        @fonts
        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @else
            <style>
                @import url('https://cdn.tailwindcss.com');
            </style>
        @endif
    </head>
    <body class="bg-[#FDFDFC] dark:bg-[#0a0a0a] text-[#1b1b18] dark:text-[#EDEDEC] flex p-6 lg:p-8 items-center justify-center min-h-screen">
        <div class="w-full max-w-md">
            <div class="bg-white dark:bg-[#161615] rounded-lg shadow-sm border border-gray-200 dark:border-[#3E3E3A] p-6 lg:p-8">
                <h1 class="text-2xl font-semibold mb-2">ユーザー登録</h1>
                <p class="text-gray-600 dark:text-gray-400 mb-6">新しいアカウントを作成します</p>

                <form method="POST" action="{{ route('register') }}" class="space-y-4">
                    @csrf

                    <!-- 名前 -->
                    <div>
                        <label for="name" class="block text-sm font-medium mb-1">名前</label>
                        <input
                            type="text"
                            id="name"
                            name="name"
                            value="{{ old('name') }}"
                            class="w-full px-4 py-2 border border-gray-300 dark:border-[#3E3E3A] rounded-md bg-white dark:bg-[#1a1a1a] focus:outline-none focus:ring-2 focus:ring-blue-500 dark:focus:ring-blue-600 @error('name') border-red-500 @enderror"
                            required
                            autofocus
                        />
                        @error('name')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- メールアドレス -->
                    <div>
                        <label for="email" class="block text-sm font-medium mb-1">メールアドレス</label>
                        <input
                            type="email"
                            id="email"
                            name="email"
                            value="{{ old('email') }}"
                            class="w-full px-4 py-2 border border-gray-300 dark:border-[#3E3E3A] rounded-md bg-white dark:bg-[#1a1a1a] focus:outline-none focus:ring-2 focus:ring-blue-500 dark:focus:ring-blue-600 @error('email') border-red-500 @enderror"
                            required
                        />
                        @error('email')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- パスワード -->
                    <div>
                        <label for="password" class="block text-sm font-medium mb-1">パスワード</label>
                        <input
                            type="password"
                            id="password"
                            name="password"
                            class="w-full px-4 py-2 border border-gray-300 dark:border-[#3E3E3A] rounded-md bg-white dark:bg-[#1a1a1a] focus:outline-none focus:ring-2 focus:ring-blue-500 dark:focus:ring-blue-600 @error('password') border-red-500 @enderror"
                            required
                        />
                        @error('password')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- パスワード確認 -->
                    <div>
                        <label for="password_confirmation" class="block text-sm font-medium mb-1">パスワード確認</label>
                        <input
                            type="password"
                            id="password_confirmation"
                            name="password_confirmation"
                            class="w-full px-4 py-2 border border-gray-300 dark:border-[#3E3E3A] rounded-md bg-white dark:bg-[#1a1a1a] focus:outline-none focus:ring-2 focus:ring-blue-500 dark:focus:ring-blue-600"
                            required
                        />
                    </div>

                    <!-- 送信ボタン -->
                    <button
                        type="submit"
                        class="w-full px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-md transition-colors mt-6"
                    >
                        登録
                    </button>
                </form>

                <!-- ホームへのリンク -->
                <p class="text-center text-sm text-gray-600 dark:text-gray-400 mt-4">
                    <a href="/" class="text-blue-600 dark:text-blue-400 hover:underline font-medium">
                        ホームに戻る
                    </a>
                </p>
            </div>
        </div>
    </body>
</html>
