<x-app-layout title="運営ダッシュボード">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <h1 class="text-2xl font-bold mb-6">運営ダッシュボード</h1>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-6 mb-8">
            <div class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
                <p class="text-sm font-medium text-slate-500">総ユーザー数</p>
                <p class="mt-2 text-3xl font-bold text-slate-900">{{ number_format($userCount) }}</p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
                <p class="text-sm font-medium text-slate-500">総イベント数</p>
                <p class="mt-2 text-3xl font-bold text-slate-900">{{ number_format($eventCount) }}</p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
                <p class="text-sm font-medium text-slate-500">総申込数（参加中）</p>
                <p class="mt-2 text-3xl font-bold text-slate-900">{{ number_format($attendanceCount) }}</p>
            </div>
        </div>

        <div class="flex gap-4">
            <a href="{{ route('admin.users.index') }}" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">ユーザー管理</a>
            <a href="{{ route('admin.events.index') }}" class="rounded-md bg-purple-600 px-4 py-2 text-sm font-medium text-white hover:bg-purple-700">イベント管理</a>
            <a href="{{ route('admin.audit-logs.index') }}" class="rounded-md bg-slate-600 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">操作履歴</a>
        </div>
    </div>
</x-app-layout>
