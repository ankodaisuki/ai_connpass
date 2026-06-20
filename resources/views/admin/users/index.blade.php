<x-app-layout title="ユーザー管理">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-2xl font-bold">ユーザー管理</h1>
            <a href="{{ route('admin.dashboard') }}" class="text-sm text-slate-500 hover:underline">← ダッシュボード</a>
        </div>

        @if (session('success'))
            <div class="mb-4 rounded-md bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">{{ session('success') }}</div>
        @endif

        <form method="GET" class="mb-4 flex gap-2">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="名前・メールで検索"
                class="rounded-md border border-slate-300 px-3 py-1.5 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100 w-64" />
            <button type="submit" class="rounded-md bg-slate-600 px-3 py-1.5 text-sm text-white hover:bg-slate-700">検索</button>
        </form>

        <div class="rounded-2xl bg-white dark:bg-[#161615] border border-slate-200 dark:border-[#3E3E3A] shadow-sm overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 dark:bg-slate-800 text-slate-600 dark:text-slate-300">
                    <tr>
                        <th class="px-4 py-3 text-left">ID</th>
                        <th class="px-4 py-3 text-left">名前</th>
                        <th class="px-4 py-3 text-left">メール</th>
                        <th class="px-4 py-3 text-left">ステータス</th>
                        <th class="px-4 py-3 text-left">操作</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                    @foreach ($users as $user)
                        <tr>
                            <td class="px-4 py-3 text-slate-500">{{ $user->id }}</td>
                            <td class="px-4 py-3 font-medium">{{ $user->name }}</td>
                            <td class="px-4 py-3 text-slate-600 dark:text-slate-300">{{ $user->email }}</td>
                            <td class="px-4 py-3">
                                @if ($user->isFrozen())
                                    <span class="rounded-full bg-red-100 px-2 py-0.5 text-xs text-red-700">凍結中</span>
                                @elseif ($user->status->name === 'Active')
                                    <span class="rounded-full bg-green-100 px-2 py-0.5 text-xs text-green-700">有効</span>
                                @else
                                    <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-600">{{ $user->status->name }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                @if ($user->isFrozen())
                                    <form method="POST" action="{{ route('admin.users.unfreeze', $user) }}" class="inline flex gap-1 items-center">
                                        @csrf
                                        <input type="text" name="reason" required placeholder="解除理由"
                                            class="rounded border border-slate-300 px-2 py-0.5 text-xs w-36 dark:border-slate-600 dark:bg-slate-900" />
                                        <button type="submit" class="rounded bg-emerald-600 px-2 py-0.5 text-xs text-white hover:bg-emerald-700">解除</button>
                                    </form>
                                @elseif ($user->status->name === 'Active')
                                    <form method="POST" action="{{ route('admin.users.freeze', $user) }}" class="inline flex gap-1 items-center">
                                        @csrf
                                        <input type="text" name="reason" required placeholder="凍結理由"
                                            class="rounded border border-slate-300 px-2 py-0.5 text-xs w-36 dark:border-slate-600 dark:bg-slate-900" />
                                        <button type="submit" class="rounded bg-red-600 px-2 py-0.5 text-xs text-white hover:bg-red-700">凍結</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $users->links() }}</div>
    </div>
</x-app-layout>
