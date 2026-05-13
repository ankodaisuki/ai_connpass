<x-app-layout>
    <x-slot:title>ログイン - AI Connpass</x-slot:title>

    <div class="max-w-md mx-auto">
        <div class="rounded-2xl bg-white dark:bg-[#161615] border border-slate-200 dark:border-[#3E3E3A] shadow-sm p-6 sm:p-8">
            <div class="text-center mb-6">
                <div class="inline-flex h-12 w-12 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-500 to-purple-600 text-white font-bold mb-3">
                    AC
                </div>
                <h1 class="text-2xl font-bold tracking-tight">ログイン</h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">アカウントにログインします</p>
            </div>

            <form method="POST" action="{{ route('login') }}" class="space-y-4">
                @csrf

                <!-- メールアドレス -->
                <div>
                    <label for="email" class="block text-sm font-medium mb-1.5 text-slate-700 dark:text-slate-300">メールアドレス</label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        value="{{ old('email') }}"
                        class="w-full px-4 py-2.5 rounded-lg border border-slate-300 dark:border-[#3E3E3A] bg-white dark:bg-[#0a0a0a] text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition @error('email') border-red-500 focus:ring-red-500 @enderror"
                        required
                        autofocus
                    />
                    @error('email')
                        <p class="text-red-500 text-sm mt-1.5">{{ $message }}</p>
                    @enderror
                </div>

                <!-- パスワード -->
                <div>
                    <label for="password" class="block text-sm font-medium mb-1.5 text-slate-700 dark:text-slate-300">パスワード</label>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        class="w-full px-4 py-2.5 rounded-lg border border-slate-300 dark:border-[#3E3E3A] bg-white dark:bg-[#0a0a0a] text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition @error('password') border-red-500 focus:ring-red-500 @enderror"
                        required
                    />
                    @error('password')
                        <p class="text-red-500 text-sm mt-1.5">{{ $message }}</p>
                    @enderror
                </div>

                <!-- ログイン状態を保持 -->
                <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300 cursor-pointer">
                    <input type="checkbox" name="remember" value="1"
                        class="h-4 w-4 rounded border-slate-300 dark:border-[#3E3E3A] text-indigo-600 focus:ring-indigo-500" />
                    ログイン状態を保持する
                </label>

                <!-- 送信ボタン -->
                <button
                    type="submit"
                    class="w-full px-4 py-2.5 rounded-lg bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-700 hover:to-purple-700 text-white font-semibold shadow-sm transition mt-6"
                >
                    ログインする
                </button>
            </form>

            <p class="text-center text-sm text-slate-500 dark:text-slate-400 mt-6">
                アカウントをお持ちでないですか？
                <a href="{{ route('register') }}" class="text-indigo-600 dark:text-indigo-400 hover:underline font-medium">
                    新規登録
                </a>
            </p>
        </div>
    </div>
</x-app-layout>
