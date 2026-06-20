<x-app-layout title="操作履歴">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-2xl font-bold">操作履歴</h1>
            <a href="{{ route('admin.dashboard') }}" class="text-sm text-slate-500 hover:underline">← ダッシュボード</a>
        </div>

        <div class="rounded-2xl bg-white dark:bg-[#161615] border border-slate-200 dark:border-[#3E3E3A] shadow-sm overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 dark:bg-slate-800 text-slate-600 dark:text-slate-300">
                    <tr>
                        <th class="px-4 py-3 text-left">日時</th>
                        <th class="px-4 py-3 text-left">運営者</th>
                        <th class="px-4 py-3 text-left">操作</th>
                        <th class="px-4 py-3 text-left">対象</th>
                        <th class="px-4 py-3 text-left">理由</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                    @foreach ($logs as $log)
                        <tr>
                            <td class="px-4 py-3 text-slate-500 whitespace-nowrap">{{ $log->created_at->format('m/d H:i') }}</td>
                            <td class="px-4 py-3">{{ $log->admin->name }}</td>
                            <td class="px-4 py-3">
                                @if ($log->action === 'freeze')
                                    <span class="rounded-full bg-red-100 px-2 py-0.5 text-xs text-red-700">凍結</span>
                                @elseif ($log->action === 'unfreeze')
                                    <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs text-emerald-700">解除</span>
                                @elseif ($log->action === 'delete_event')
                                    <span class="rounded-full bg-orange-100 px-2 py-0.5 text-xs text-orange-700">イベント削除</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-slate-500">{{ $log->target_type }} #{{ $log->target_id }}</td>
                            <td class="px-4 py-3 text-slate-600 dark:text-slate-300">{{ $log->reason }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $logs->links() }}</div>
    </div>
</x-app-layout>
