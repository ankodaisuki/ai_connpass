@php
    use App\Enums\EventCategory;

    $categoryStyles = [
        EventCategory::Frontend->value => ['label' => 'フロントエンド', 'class' => 'bg-sky-100 text-sky-700 dark:bg-sky-900/40 dark:text-sky-300', 'gradient' => 'from-sky-500 to-blue-600'],
        EventCategory::Backend->value => ['label' => 'バックエンド', 'class' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300', 'gradient' => 'from-emerald-500 to-green-600'],
        EventCategory::Database->value => ['label' => 'データベース', 'class' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300', 'gradient' => 'from-amber-500 to-orange-600'],
        EventCategory::Mobile->value => ['label' => 'モバイル', 'class' => 'bg-pink-100 text-pink-700 dark:bg-pink-900/40 dark:text-pink-300', 'gradient' => 'from-pink-500 to-rose-600'],
        EventCategory::Ai->value => ['label' => 'AI', 'class' => 'bg-violet-100 text-violet-700 dark:bg-violet-900/40 dark:text-violet-300', 'gradient' => 'from-violet-500 to-purple-600'],
        EventCategory::Other->value => ['label' => 'その他', 'class' => 'bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-300', 'gradient' => 'from-slate-500 to-slate-600'],
    ];
@endphp

<x-app-layout>
    <x-slot:title>過去に参加したイベント - AI Connpass</x-slot:title>

    <div class="max-w-3xl mx-auto">
        <div class="mb-6">
            <h1 class="text-2xl font-bold tracking-tight">過去に参加したイベント</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">主催者により出席が記録されたイベントの一覧です</p>
        </div>

        @if ($attendances->isEmpty())
            <div class="flex flex-col items-center justify-center py-20 rounded-2xl bg-white dark:bg-[#161615] border border-slate-200 dark:border-[#3E3E3A] text-center">
                <div class="flex h-14 w-14 items-center justify-center rounded-full bg-slate-100 dark:bg-[#0a0a0a] mb-4">
                    <svg class="h-7 w-7 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                </div>
                <p class="text-base font-medium text-slate-700 dark:text-slate-300">参加したイベントはまだありません</p>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">イベントに参加すると、ここに履歴が表示されます。</p>
                <a href="{{ route('events.index') }}"
                    class="mt-5 inline-flex items-center px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium shadow-sm transition-colors">
                    イベントを探す
                </a>
            </div>
        @else
            <div class="space-y-3">
                @foreach ($attendances as $attendance)
                    @php
                        $event = $attendance->event;
                        if ($event === null) { continue; }
                        $cat = $categoryStyles[$event->category->value] ?? ['label' => $event->category->name, 'class' => 'bg-slate-100 text-slate-600', 'gradient' => 'from-slate-500 to-slate-600'];
                    @endphp
                    <a href="{{ route('events.show', $event) }}"
                        class="flex items-start gap-4 rounded-xl bg-white dark:bg-[#161615] border border-slate-200 dark:border-[#3E3E3A] hover:border-indigo-300 dark:hover:border-indigo-700 p-4 transition-colors group">

                        <!-- カテゴリカラーバー -->
                        <div class="w-1 self-stretch rounded-full bg-gradient-to-b {{ $cat['gradient'] }} shrink-0"></div>

                        <div class="flex-1 min-w-0">
                            <div class="flex flex-wrap items-center gap-2 mb-1.5">
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $cat['class'] }}">
                                    {{ $cat['label'] }}
                                </span>
                                <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300">
                                    <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                    </svg>
                                    参加済み
                                </span>
                            </div>
                            <p class="font-semibold text-slate-900 dark:text-white group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors truncate">
                                {{ $event->title }}
                            </p>
                            <div class="mt-1.5 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-slate-500 dark:text-slate-400">
                                <span class="inline-flex items-center gap-1">
                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                                    </svg>
                                    {{ $event->event_date->format('Y/m/d H:i') }}
                                </span>
                                <span class="inline-flex items-center gap-1">
                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z" />
                                    </svg>
                                    {{ $event->prefecture }} · {{ $event->location }}
                                </span>
                            </div>
                        </div>

                        <div class="shrink-0 text-right">
                            <p class="text-xs text-slate-400 dark:text-slate-500">参加日</p>
                            <p class="text-xs font-medium text-slate-600 dark:text-slate-400 mt-0.5">
                                {{ $attendance->attended_at->format('Y/m/d') }}
                            </p>
                            <svg class="h-4 w-4 text-slate-400 group-hover:text-indigo-500 transition-colors mt-2 ml-auto" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                            </svg>
                        </div>
                    </a>
                @endforeach
            </div>

            <!-- ページネーション -->
            @if ($attendances->hasPages())
                <div class="mt-8">
                    {{ $attendances->links() }}
                </div>
            @endif
        @endif
    </div>
</x-app-layout>
