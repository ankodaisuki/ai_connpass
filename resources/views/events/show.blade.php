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

    $style = $categoryStyles[$event->category->value] ?? $categoryStyles[EventCategory::Other->value];
    $isPast = $event->event_date->isPast();
    $attendeeCount = $event->attendances_count ?? 0;
    $remaining = max(0, $event->capacity - $attendeeCount);
    $isFull = $remaining === 0;
    $progressPct = $event->capacity > 0 ? min(100, ($attendeeCount / $event->capacity) * 100) : 0;
@endphp

<x-app-layout>
    <x-slot:title>{{ $event->title }} - AI Connpass</x-slot:title>

    <!-- パンくず -->
    <nav class="mb-6 flex items-center justify-between text-sm">
        <a href="{{ route('events.index') }}" class="inline-flex items-center gap-1 text-slate-500 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
            </svg>
            イベント一覧に戻る
        </a>
        @can('update', $event)
            <div class="flex items-center gap-2">
                <a href="{{ route('events.edit', $event) }}"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-slate-300 dark:border-[#3E3E3A] text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-[#1a1a18] text-xs font-medium transition-colors">
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125" />
                    </svg>
                    編集
                </a>
                <form method="POST" action="{{ route('events.destroy', $event) }}"
                    onsubmit="return confirm('このイベントを削除してもよいですか？この操作は取り消せません。')">
                    @csrf
                    @method('DELETE')
                    <button type="submit"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-red-200 dark:border-red-900/50 text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 text-xs font-medium transition-colors">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                        </svg>
                        削除
                    </button>
                </form>
            </div>
        @endcan
    </nav>

    <!-- ヒーロー（カテゴリーカラー帯） -->
    <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br {{ $style['gradient'] }} p-8 sm:p-10 mb-8 shadow-xl">
        <div class="absolute inset-0 bg-grid-white/10 [mask-image:radial-gradient(white,transparent_70%)]"></div>
        <div class="relative">
            <div class="flex flex-wrap items-center gap-2 mb-4">
                <span class="inline-flex items-center rounded-full bg-white/20 backdrop-blur px-3 py-1 text-xs font-semibold text-white">
                    {{ $style['label'] }}
                </span>
                @if ($isPast)
                    <span class="inline-flex items-center rounded-full bg-slate-900/30 backdrop-blur px-3 py-1 text-xs font-semibold text-white">
                        終了
                    </span>
                @elseif ($isFull)
                    <span class="inline-flex items-center rounded-full bg-red-500/80 backdrop-blur px-3 py-1 text-xs font-semibold text-white">
                        満員
                    </span>
                @endif
            </div>
            <h1 class="text-2xl sm:text-3xl lg:text-4xl font-bold text-white leading-tight">
                {{ $event->title }}
            </h1>
            <div class="mt-4 flex items-center gap-2 text-white/90">
                <span class="flex h-8 w-8 items-center justify-center rounded-full bg-white/20 backdrop-blur text-sm font-semibold">
                    {{ mb_substr($event->user->name, 0, 1) }}
                </span>
                <span class="text-sm">主催: {{ $event->user->name }}</span>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- 左カラム：説明 -->
        <div class="lg:col-span-2 space-y-6">
            <section class="rounded-2xl bg-white dark:bg-[#161615] border border-slate-200 dark:border-[#3E3E3A] p-6 sm:p-8 shadow-sm">
                <h2 class="text-xl font-bold mb-4 flex items-center gap-2">
                    <span class="h-1 w-6 rounded-full bg-gradient-to-r {{ $style['gradient'] }}"></span>
                    イベント詳細
                </h2>
                @if ($event->description)
                    <div class="prose prose-slate dark:prose-invert max-w-none whitespace-pre-wrap text-slate-700 dark:text-slate-300 leading-relaxed">{{ $event->description }}</div>
                @else
                    <p class="text-slate-500 dark:text-slate-400 italic">説明はまだありません。</p>
                @endif
            </section>
        </div>

        <!-- 右カラム：サマリーカード -->
        <aside class="space-y-4">
            <div class="rounded-2xl bg-white dark:bg-[#161615] border border-slate-200 dark:border-[#3E3E3A] p-6 shadow-sm sticky top-20">
                <!-- 開催日時 -->
                <div class="pb-4 border-b border-slate-100 dark:border-[#3E3E3A]">
                    <div class="flex items-center gap-2 text-xs uppercase tracking-wider text-slate-500 dark:text-slate-400 font-semibold mb-2">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                        </svg>
                        開催日時
                    </div>
                    <p class="text-lg font-bold text-slate-900 dark:text-white">
                        {{ $event->event_date->format('Y年m月d日 (D)') }}
                    </p>
                    <p class="text-sm text-slate-600 dark:text-slate-400">
                        {{ $event->event_date->format('H:i') }} 開始
                    </p>
                </div>

                <!-- 開催場所 -->
                <div class="py-4 border-b border-slate-100 dark:border-[#3E3E3A]">
                    <div class="flex items-center gap-2 text-xs uppercase tracking-wider text-slate-500 dark:text-slate-400 font-semibold mb-2">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z" />
                        </svg>
                        開催場所
                    </div>
                    <p class="text-sm font-semibold text-slate-900 dark:text-white">{{ $event->prefecture }}</p>
                    <p class="text-sm text-slate-600 dark:text-slate-400">{{ $event->location }}</p>
                </div>

                <!-- 定員 -->
                <div class="py-4">
                    <div class="flex items-center justify-between mb-2">
                        <div class="flex items-center gap-2 text-xs uppercase tracking-wider text-slate-500 dark:text-slate-400 font-semibold">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                            </svg>
                            参加者
                        </div>
                        <span class="text-sm font-bold text-slate-900 dark:text-white">{{ $attendeeCount }} / {{ $event->capacity }}</span>
                    </div>
                    <div class="h-2 w-full rounded-full bg-slate-100 dark:bg-slate-800 overflow-hidden">
                        <div class="h-full rounded-full bg-gradient-to-r {{ $style['gradient'] }} transition-all"
                            style="width: {{ $progressPct }}%"></div>
                    </div>
                    <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">
                        @if ($isFull)
                            満員です
                        @else
                            残り {{ $remaining }}人
                        @endif
                    </p>
                </div>

                <!-- 参加ボタン -->
                <div class="pt-2">
                    @error('attendance')
                        <p class="text-red-500 text-sm mb-3">{{ $message }}</p>
                    @enderror
                    @if (session('success'))
                        <p class="text-emerald-600 dark:text-emerald-400 text-sm mb-3">{{ session('success') }}</p>
                    @endif

                    @auth
                        @if ($event->user_id === auth()->id())
                            <a href="{{ route('events.edit', $event) }}"
                                class="w-full inline-flex items-center justify-center px-4 py-3 rounded-xl border border-slate-300 dark:border-[#3E3E3A] text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-[#1a1a18] text-sm font-semibold transition">
                                イベントを編集する
                            </a>
                        @elseif ($isPast)
                            <button disabled class="w-full inline-flex items-center justify-center px-4 py-3 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-400 dark:text-slate-500 text-sm font-semibold cursor-not-allowed">
                                終了しました
                            </button>
                        @elseif ($myAttendance !== null)
                            <div class="space-y-2">
                                <div class="w-full inline-flex items-center justify-center gap-2 px-4 py-3 rounded-xl bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 text-emerald-700 dark:text-emerald-400 text-sm font-semibold">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                    </svg>
                                    参加申し込み済み
                                </div>
                                <form method="POST" action="{{ route('events.attendances.destroy', $event) }}"
                                    onsubmit="return confirm('参加をキャンセルしてもよいですか？')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit"
                                        class="w-full inline-flex items-center justify-center px-4 py-2 rounded-xl border border-slate-300 dark:border-[#3E3E3A] text-slate-500 dark:text-slate-400 hover:text-red-600 dark:hover:text-red-400 hover:border-red-200 dark:hover:border-red-800 text-sm transition">
                                        キャンセルする
                                    </button>
                                </form>
                            </div>
                        @elseif ($isFull)
                            <button disabled class="w-full inline-flex items-center justify-center px-4 py-3 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-400 dark:text-slate-500 text-sm font-semibold cursor-not-allowed">
                                満員です
                            </button>
                        @else
                            <form method="POST" action="{{ route('events.attendances.store', $event) }}">
                                @csrf
                                <button type="submit"
                                    class="w-full inline-flex items-center justify-center px-4 py-3 rounded-xl bg-gradient-to-r {{ $style['gradient'] }} text-white text-sm font-semibold shadow-sm hover:opacity-90 transition-opacity">
                                    参加する
                                </button>
                            </form>
                        @endif
                    @else
                        <a href="{{ route('register') }}" class="w-full inline-flex items-center justify-center px-4 py-3 rounded-xl bg-gradient-to-r {{ $style['gradient'] }} text-white text-sm font-semibold shadow-sm hover:opacity-90 transition-opacity">
                            参加するには登録
                        </a>
                    @endauth
                </div>
            </div>
        </aside>
    </div>
</x-app-layout>
