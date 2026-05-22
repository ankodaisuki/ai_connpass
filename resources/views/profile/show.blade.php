@php
    use App\Enums\EventCategory;
    use App\Enums\EventStatus;

    $categoryStyles = [
        EventCategory::Frontend->value => ['label' => 'フロントエンド', 'class' => 'bg-sky-100 text-sky-700 dark:bg-sky-900/40 dark:text-sky-300'],
        EventCategory::Backend->value => ['label' => 'バックエンド', 'class' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300'],
        EventCategory::Database->value => ['label' => 'データベース', 'class' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300'],
        EventCategory::Mobile->value => ['label' => 'モバイル', 'class' => 'bg-pink-100 text-pink-700 dark:bg-pink-900/40 dark:text-pink-300'],
        EventCategory::Ai->value => ['label' => 'AI', 'class' => 'bg-violet-100 text-violet-700 dark:bg-violet-900/40 dark:text-violet-300'],
        EventCategory::Other->value => ['label' => 'その他', 'class' => 'bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-300'],
    ];

    $statusStyles = [
        EventStatus::Draft->value => 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300',
        EventStatus::Published->value => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300',
        EventStatus::Private->value => 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300',
    ];
    $statusLabels = [
        EventStatus::Draft->value => '下書き',
        EventStatus::Published->value => '公開中',
        EventStatus::Private->value => '非公開',
    ];
@endphp

<x-app-layout>
    <x-slot:title>プロフィール - AI Connpass</x-slot:title>

    <div class="max-w-3xl mx-auto space-y-8">

        <!-- ユーザー情報カード -->
        <div class="rounded-2xl bg-white dark:bg-[#161615] border border-slate-200 dark:border-[#3E3E3A] shadow-sm p-6 sm:p-8">
            <div class="flex items-center gap-5">
                <div class="flex h-16 w-16 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-indigo-500 to-purple-600 text-white text-2xl font-bold">
                    {{ mb_substr($user->name, 0, 1) }}
                </div>
                <div class="min-w-0">
                    <h1 class="text-xl font-bold tracking-tight truncate">{{ $user->name }}</h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">{{ $user->email }}</p>
                    <p class="text-xs text-slate-400 dark:text-slate-500 mt-1">
                        登録日：{{ $user->created_at->format('Y年n月j日') }}
                    </p>
                </div>
            </div>

            <!-- 統計 -->
            <div class="mt-6 grid grid-cols-2 gap-4">
                <div class="rounded-xl bg-slate-50 dark:bg-[#0a0a0a] px-5 py-4 text-center">
                    <p class="text-2xl font-bold text-indigo-600 dark:text-indigo-400">{{ $events->count() }}</p>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">作成したイベント</p>
                </div>
                <div class="rounded-xl bg-slate-50 dark:bg-[#0a0a0a] px-5 py-4 text-center">
                    <p class="text-2xl font-bold text-purple-600 dark:text-purple-400">{{ $attendanceCount }}</p>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">参加申し込み数</p>
                </div>
            </div>
        </div>

        <!-- 作成したイベント -->
        <div>
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-bold tracking-tight">作成したイベント</h2>
                <a href="{{ route('events.create') }}"
                    class="inline-flex items-center px-3 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-medium shadow-sm transition-colors">
                    ＋ 新規作成
                </a>
            </div>

            @if ($events->isEmpty())
                <div class="flex flex-col items-center justify-center py-16 rounded-2xl bg-white dark:bg-[#161615] border border-slate-200 dark:border-[#3E3E3A] text-center">
                    <div class="flex h-12 w-12 items-center justify-center rounded-full bg-slate-100 dark:bg-[#0a0a0a] mb-3">
                        <svg class="h-6 w-6 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                        </svg>
                    </div>
                    <p class="text-sm font-medium text-slate-700 dark:text-slate-300">まだイベントを作成していません</p>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">最初のイベントを作成してみましょう。</p>
                </div>
            @else
                <div class="space-y-3">
                    @foreach ($events as $event)
                        @php
                            $cat = $categoryStyles[$event->category->value] ?? ['label' => $event->category->name, 'class' => 'bg-slate-100 text-slate-600'];
                            $st = $statusStyles[$event->status->value] ?? 'bg-slate-100 text-slate-600';
                            $stLabel = $statusLabels[$event->status->value] ?? $event->status->name;
                            $isFull = $event->attendances_count >= $event->capacity;
                        @endphp
                        <a href="{{ route('events.show', $event) }}"
                            class="flex items-start gap-4 rounded-xl bg-white dark:bg-[#161615] border border-slate-200 dark:border-[#3E3E3A] p-4 hover:border-indigo-300 dark:hover:border-indigo-700 transition-colors group">
                            <div class="flex-1 min-w-0">
                                <div class="flex flex-wrap items-center gap-2 mb-1.5">
                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $cat['class'] }}">
                                        {{ $cat['label'] }}
                                    </span>
                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $st }}">
                                        {{ $stLabel }}
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
                                        {{ $event->event_date->format('Y/m/d H:i') }}〜{{ $event->end_date->isSameDay($event->event_date) ? $event->end_date->format('H:i') : $event->end_date->format('m/d H:i') }}
                                    </span>
                                    <span class="inline-flex items-center gap-1">
                                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                                        </svg>
                                        {{ $event->attendances_count }} / {{ $event->capacity }}名
                                        @if ($isFull)
                                            <span class="text-red-500 font-medium">（満員）</span>
                                        @endif
                                    </span>
                                    <span class="inline-flex items-center gap-1">
                                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z" />
                                        </svg>
                                        {{ $event->prefecture }}
                                    </span>
                                </div>
                            </div>
                            <svg class="h-4 w-4 text-slate-400 group-hover:text-indigo-500 transition-colors shrink-0 mt-1" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                            </svg>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- 退会セクション --}}
        <div class="mt-8 rounded-xl border border-red-200 dark:border-red-900/50 bg-red-50 dark:bg-red-950/20 p-6">
            <h2 class="text-base font-semibold text-red-700 dark:text-red-400 mb-1">退会する</h2>
            <p class="text-sm text-red-600/80 dark:text-red-400/70 mb-4">退会するとアカウントが無効になります。この操作は取り消せません。</p>
            <form method="POST" action="{{ route('profile.destroy') }}"
                onsubmit="return confirm('本当に退会しますか？この操作は取り消せません。')">
                @csrf
                @method('DELETE')
                <button type="submit"
                    class="inline-flex items-center px-4 py-2 rounded-lg bg-red-600 hover:bg-red-700 text-white text-sm font-medium shadow-sm transition-colors">
                    退会する
                </button>
            </form>
        </div>

    </div>
</x-app-layout>
