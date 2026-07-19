<x-app-layout title="削除済みイベント">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-2xl font-bold">削除済みイベント（復元）</h1>
            <div class="flex gap-4">
                <a href="{{ route('admin.events.index') }}" class="text-sm text-slate-500 hover:underline">イベント管理へ</a>
                <a href="{{ route('admin.dashboard') }}" class="text-sm text-slate-500 hover:underline">← ダッシュボード</a>
            </div>
        </div>

        @if (session('success'))
            <div class="mb-4 rounded-md bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">{{ session('success') }}</div>
        @endif

        <p class="mb-4 text-sm text-slate-500">運営者が削除したイベントを復元できます。復元後は<strong>非公開（Private）</strong>で戻るため、主催者が内容を確認して再公開します。</p>

        <div class="rounded-2xl bg-white dark:bg-[#161615] border border-slate-200 dark:border-[#3E3E3A] shadow-sm overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 dark:bg-slate-800 text-slate-600 dark:text-slate-300">
                    <tr>
                        <th class="px-4 py-3 text-left">ID</th>
                        <th class="px-4 py-3 text-left">タイトル</th>
                        <th class="px-4 py-3 text-left">主催者</th>
                        <th class="px-4 py-3 text-left">削除日時</th>
                        <th class="px-4 py-3 text-left">操作</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                    @forelse ($events as $event)
                        <tr>
                            <td class="px-4 py-3 text-slate-500">{{ $event->id }}</td>
                            <td class="px-4 py-3 font-medium">{{ Str::limit($event->title, 30) }}</td>
                            <td class="px-4 py-3 text-slate-600 dark:text-slate-300">{{ $event->user->name }}</td>
                            <td class="px-4 py-3 text-slate-500">{{ $event->deleted_at->format('Y/m/d H:i') }}</td>
                            <td class="px-4 py-3">
                                <form method="POST" action="{{ route('admin.events.restore', $event->id) }}" class="inline flex gap-1 items-center">
                                    @csrf
                                    @method('PATCH')
                                    <input type="text" name="reason" required placeholder="復元理由"
                                        class="rounded border border-slate-300 px-2 py-0.5 text-xs w-36 dark:border-slate-600 dark:bg-slate-900" />
                                    <button type="submit" class="rounded bg-emerald-600 px-2 py-0.5 text-xs text-white hover:bg-emerald-700"
                                        onclick="return confirm('このイベントを復元しますか？非公開状態で戻ります。')">復元</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-8 text-center text-slate-400">復元できるイベントはありません。</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $events->links() }}</div>
    </div>
</x-app-layout>
