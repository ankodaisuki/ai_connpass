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
    $hasStarted = $event->event_date->isPast();
    $hasEnded = $event->end_date->isPast();
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
                @if (! $hasEnded)
                    <a href="{{ route('events.edit', $event) }}"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-slate-300 dark:border-[#3E3E3A] text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-[#1a1a18] text-xs font-medium transition-colors">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125" />
                        </svg>
                        編集
                    </a>
                @endif
                @can('delete', $event)
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
                @endcan
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
                @if ($hasEnded)
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
                @foreach ($event->acceptedCoOrganizers as $coOrganizer)
                    <span class="text-sm text-white/80">/ {{ $coOrganizer->name }}</span>
                @endforeach
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

            <!-- 参加者一覧 -->
            <section class="rounded-2xl bg-white dark:bg-[#161615] border border-slate-200 dark:border-[#3E3E3A] p-6 sm:p-8 shadow-sm">
                <h2 class="text-xl font-bold mb-4 flex items-center gap-2">
                    <span class="h-1 w-6 rounded-full bg-gradient-to-r {{ $style['gradient'] }}"></span>
                    参加者
                    <span class="ml-1 text-base font-normal text-slate-500 dark:text-slate-400">{{ $attendeeCount }}人 / {{ $event->capacity }}人</span>
                </h2>

                @if ($event->attendances->isEmpty())
                    <p class="text-sm text-slate-500 dark:text-slate-400 italic">まだ参加者がいません。</p>
                @else
                    <ul class="space-y-2">
                        @foreach ($event->attendances as $attendance)
                            <li class="flex items-center gap-3">
                                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-indigo-100 dark:bg-indigo-900/50 text-indigo-700 dark:text-indigo-300 text-xs font-semibold">
                                    {{ mb_substr($attendance->user->name, 0, 1) }}
                                </span>
                                <span class="text-sm font-medium text-slate-800 dark:text-slate-200">{{ $attendance->user->name }}</span>
                                <span class="ml-auto text-xs text-slate-400 dark:text-slate-500 shrink-0">
                                    {{ $attendance->applied_at->format('Y/m/d') }} 申し込み
                                </span>
                            </li>
                        @endforeach
                    </ul>
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
                        {{ $event->event_date->format('Y年m月d日') }}（{{ $event->event_date->locale('ja')->isoFormat('ddd') }}）
                    </p>
                    <p class="text-sm text-slate-600 dark:text-slate-400">
                        {{ $event->event_date->format('H:i') }}
                        〜
                        @if ($event->end_date->isSameDay($event->event_date))
                            {{ $event->end_date->format('H:i') }}
                        @else
                            {{ $event->end_date->format('Y年m月d日') }}（{{ $event->end_date->locale('ja')->isoFormat('ddd') }}）{{ $event->end_date->format('H:i') }}
                        @endif
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
                    @if ($event->location)
                        <p class="text-sm text-slate-600 dark:text-slate-400">{{ $event->location }}</p>
                    @endif
                    @if (in_array($event->prefecture, ['オンライン', 'ハイブリッド']) && $myAttendance !== null)
                        <div class="mt-3 rounded-lg bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 px-4 py-3 space-y-1">
                            <p class="text-xs font-semibold text-blue-700 dark:text-blue-400">オンライン参加情報</p>
                            @if ($event->online_url)
                                <p class="text-sm text-blue-700 dark:text-blue-300 break-all">
                                    URL: <a href="{{ $event->online_url }}" target="_blank" rel="noopener noreferrer" class="underline">{{ $event->online_url }}</a>
                                </p>
                            @endif
                            @if ($event->online_password)
                                <p class="text-sm text-blue-700 dark:text-blue-300">パスワード: {{ $event->online_password }}</p>
                            @endif
                        </div>
                    @endif
                    @can('update', $event)
                        @if (in_array($event->prefecture, ['オンライン', 'ハイブリッド']))
                            @if ($event->online_url)
                                <div class="mt-3 rounded-lg bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 px-4 py-3 space-y-1">
                                    <p class="text-xs font-semibold text-blue-700 dark:text-blue-400">オンライン参加情報（主催者確認用）</p>
                                    <p class="text-sm text-blue-700 dark:text-blue-300 break-all">
                                        URL: <a href="{{ $event->online_url }}" target="_blank" rel="noopener noreferrer" class="underline">{{ $event->online_url }}</a>
                                    </p>
                                    @if ($event->online_password)
                                        <p class="text-sm text-blue-700 dark:text-blue-300">パスワード: {{ $event->online_password }}</p>
                                    @endif
                                </div>
                            @endif
                            <div class="mt-3 rounded-lg bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 px-4 py-3 text-sm text-amber-700 dark:text-amber-400">
                                ⚠ 参加者の入室承認機能（待機室・ロビー等）を有効にすることを推奨します。
                            </div>
                        @endif
                    @endcan
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
                        @if ($event->isOrganizer(auth()->user()) && ! $hasEnded)
                            <a href="{{ route('events.edit', $event) }}"
                                class="w-full inline-flex items-center justify-center px-4 py-3 rounded-xl border border-slate-300 dark:border-[#3E3E3A] text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-[#1a1a18] text-sm font-semibold transition">
                                イベントを編集する
                            </a>
                        @elseif ($myAttendance !== null)
                            <div class="space-y-2">
                                <div class="w-full inline-flex items-center justify-center gap-2 px-4 py-3 rounded-xl bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 text-emerald-700 dark:text-emerald-400 text-sm font-semibold">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                    </svg>
                                    参加申し込み済み@if ($myAttendance->attendance_mode)（{{ $myAttendance->attendance_mode->label() }}）@endif
                                </div>
                                @if ($hasEnded)
                                    <button disabled
                                        class="w-full inline-flex items-center justify-center px-4 py-2 rounded-xl border border-slate-200 dark:border-[#3E3E3A] text-slate-400 dark:text-slate-600 text-sm cursor-not-allowed">
                                        キャンセル不可（終了済み）
                                    </button>
                                @elseif ($myAttendance->attended_at !== null)
                                    <button disabled
                                        class="w-full inline-flex items-center justify-center px-4 py-2 rounded-xl border border-slate-200 dark:border-[#3E3E3A] text-slate-400 dark:text-slate-600 text-sm cursor-not-allowed">
                                        キャンセル不可（出席済み）
                                    </button>
                                @else
                                    <form method="POST" action="{{ route('events.attendances.destroy', $event) }}"
                                        onsubmit="return confirm('参加をキャンセルしてもよいですか？')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                            class="w-full inline-flex items-center justify-center px-4 py-2 rounded-xl border border-slate-300 dark:border-[#3E3E3A] text-slate-500 dark:text-slate-400 hover:text-red-600 dark:hover:text-red-400 hover:border-red-200 dark:hover:border-red-800 text-sm transition">
                                            キャンセルする
                                        </button>
                                    </form>
                                @endif
                            </div>
                        @elseif ($myWaitlist !== null)
                            <div class="space-y-2">
                                <div class="w-full inline-flex items-center justify-center gap-2 px-4 py-3 rounded-xl bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 text-amber-700 dark:text-amber-400 text-sm font-semibold">
                                    キャンセル待ち中（{{ $myWaitlistPosition }}番目）
                                </div>
                                @if (! $hasEnded)
                                    <form method="POST" action="{{ route('events.attendances.destroy', $event) }}"
                                        onsubmit="return confirm('キャンセル待ちを取り消してもよいですか？')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                            class="w-full inline-flex items-center justify-center px-4 py-2 rounded-xl border border-slate-300 dark:border-[#3E3E3A] text-slate-500 dark:text-slate-400 hover:text-red-600 dark:hover:text-red-400 hover:border-red-200 dark:hover:border-red-800 text-sm transition">
                                            キャンセルする
                                        </button>
                                    </form>
                                @endif
                            </div>
                        @elseif ($hasEnded)
                            <button disabled class="w-full inline-flex items-center justify-center px-4 py-3 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-400 dark:text-slate-500 text-sm font-semibold cursor-not-allowed">
                                終了しました
                            </button>
                        @elseif ($isFull && $isWaitlistFull)
                            <button disabled class="w-full inline-flex items-center justify-center px-4 py-3 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-400 dark:text-slate-500 text-sm font-semibold cursor-not-allowed">
                                満員です（キャンセル待ちも満員）
                            </button>
                        @elseif ($isFull)
                            <form method="POST" action="{{ route('events.attendances.store', $event) }}">
                                @csrf
                                @if ($event->prefecture === 'ハイブリッド')
                                    <div class="mb-3 space-y-2">
                                        <p class="text-sm font-medium text-slate-700 dark:text-slate-300">参加方法を選択</p>
                                        <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300 cursor-pointer">
                                            <input type="radio" name="attendance_mode" value="in_person" required class="text-indigo-600"> 対面で参加
                                        </label>
                                        <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300 cursor-pointer">
                                            <input type="radio" name="attendance_mode" value="online" required> オンラインで参加
                                        </label>
                                    </div>
                                @elseif ($event->prefecture === 'オンライン')
                                    <input type="hidden" name="attendance_mode" value="online">
                                @else
                                    <input type="hidden" name="attendance_mode" value="in_person">
                                @endif
                                <button type="submit"
                                    class="w-full inline-flex items-center justify-center px-4 py-3 rounded-xl bg-gradient-to-r from-amber-500 to-orange-600 text-white text-sm font-semibold shadow-sm hover:opacity-90 transition-opacity">
                                    キャンセル待ちに登録する
                                </button>
                            </form>
                        @else
                            <form method="POST" action="{{ route('events.attendances.store', $event) }}">
                                @csrf
                                @if ($event->prefecture === 'ハイブリッド')
                                    <div class="mb-3 space-y-2">
                                        <p class="text-sm font-medium text-slate-700 dark:text-slate-300">参加方法を選択</p>
                                        <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300 cursor-pointer">
                                            <input type="radio" name="attendance_mode" value="in_person" required class="text-indigo-600"> 対面で参加
                                        </label>
                                        <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300 cursor-pointer">
                                            <input type="radio" name="attendance_mode" value="online" required> オンラインで参加
                                        </label>
                                    </div>
                                @elseif ($event->prefecture === 'オンライン')
                                    <input type="hidden" name="attendance_mode" value="online">
                                @else
                                    <input type="hidden" name="attendance_mode" value="in_person">
                                @endif
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

    {{-- 出欠管理（主催者のみ） --}}
    @can('updateAttendance', $event)
        @php $activeTab = request('tab', 'attendees'); @endphp
        <div class="mt-8 space-y-6">
            <section class="rounded-2xl bg-white dark:bg-[#161615] border border-slate-200 dark:border-[#3E3E3A] p-6 sm:p-8 shadow-sm">
                <h2 class="text-xl font-bold mb-4 flex items-center gap-2">
                    <span class="h-1 w-6 rounded-full bg-gradient-to-r {{ $style['gradient'] }}"></span>
                    出欠管理
                </h2>

                {{-- タブナビゲーション --}}
                <div class="flex gap-1 mb-6 border-b border-slate-200 dark:border-[#3E3E3A]">
                    <a href="{{ route('events.show', $event) }}?tab=attendees"
                        class="px-4 py-2 text-sm font-medium rounded-t-lg border-b-2 transition-colors
                            {{ $activeTab === 'attendees'
                                ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400'
                                : 'border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-300' }}">
                        参加者
                        <span class="ml-1 text-xs bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400 px-1.5 py-0.5 rounded-full">
                            {{ $event->attendances()->where('status', \App\Enums\AttendanceStatus::Applied)->count() }}
                        </span>
                    </a>
                    <a href="{{ route('events.show', $event) }}?tab=waitlist"
                        class="px-4 py-2 text-sm font-medium rounded-t-lg border-b-2 transition-colors
                            {{ $activeTab === 'waitlist'
                                ? 'border-amber-500 text-amber-600 dark:text-amber-400'
                                : 'border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-300' }}">
                        キャンセル待ち
                        <span class="ml-1 text-xs bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400 px-1.5 py-0.5 rounded-full">
                            {{ $event->waitlistAttendances()->count() }}
                        </span>
                    </a>
                </div>

                @if ($activeTab === 'attendees')
                    @if (! $hasStarted)
                        <p class="mb-4 rounded-xl bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 px-4 py-3 text-sm text-amber-700 dark:text-amber-400">
                            出欠の記録はイベント開始時刻（{{ $event->event_date->format('Y/m/d H:i') }}）以降に可能になります。
                        </p>
                    @elseif ($hasEnded)
                        <p class="mb-4 rounded-xl bg-slate-50 dark:bg-slate-900/20 border border-slate-200 dark:border-slate-700 px-4 py-3 text-sm text-slate-500 dark:text-slate-400">
                            イベントが終了したため出欠を記録できません。
                        </p>
                    @endif

                    <div class="space-y-2">
                        @forelse($event->attendances()->where('status', \App\Enums\AttendanceStatus::Applied)->with('user')->get() as $attendance)
                            <div class="flex items-center gap-3 rounded-xl border border-slate-100 dark:border-[#3E3E3A] bg-slate-50 dark:bg-[#1a1a18] p-3">
                                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-indigo-100 dark:bg-indigo-900/50 text-indigo-700 dark:text-indigo-300 text-xs font-semibold">
                                    {{ mb_substr($attendance->user->name, 0, 1) }}
                                </span>
                                <span class="flex-1 text-sm font-medium text-slate-800 dark:text-slate-200">
                                    {{ $attendance->user->name }}
                                    @if ($attendance->attendance_mode)
                                        <span class="ml-1 text-xs text-slate-500 dark:text-slate-400">（{{ $attendance->attendance_mode->label() }}）</span>
                                    @endif
                                </span>
                                <div class="flex gap-2 shrink-0">
                                    <form method="POST" action="{{ route('events.attendances.update', [$event, $attendance]) }}" class="inline">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="attended_at" value="{{ now()->format('Y-m-d H:i:s') }}">
                                        <button
                                            type="submit"
                                            @disabled(! $hasStarted || $hasEnded)
                                            class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors {{ (! $hasStarted || $hasEnded) ? 'opacity-50 cursor-not-allowed ' : '' }}{{ $attendance->attended_at ? 'bg-emerald-500 dark:bg-emerald-600 text-white hover:bg-emerald-600 dark:hover:bg-emerald-700' : 'bg-slate-200 dark:bg-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-300 dark:hover:bg-slate-600' }}"
                                        >
                                            {{ $attendance->attended_at ? '✓ 参加' : '参加' }}
                                        </button>
                                    </form>
                                    <form method="POST" action="{{ route('events.attendances.update', [$event, $attendance]) }}" class="inline">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="attended_at" value="null">
                                        <button
                                            type="submit"
                                            @disabled(! $hasStarted || $hasEnded)
                                            class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors {{ (! $hasStarted || $hasEnded) ? 'opacity-50 cursor-not-allowed ' : '' }}{{ ! $attendance->attended_at ? 'bg-slate-500 dark:bg-slate-600 text-white hover:bg-slate-600 dark:hover:bg-slate-700' : 'bg-slate-200 dark:bg-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-300 dark:hover:bg-slate-600' }}"
                                        >
                                            {{ ! $attendance->attended_at ? '✓ 未参加' : '未参加' }}
                                        </button>
                                    </form>
                                </div>
                            </div>
                        @empty
                            <p class="text-sm text-slate-500 dark:text-slate-400 italic">参加者がいません</p>
                        @endforelse
                    </div>

                    @if ($event->attendances()->where('status', \App\Enums\AttendanceStatus::Cancelled)->exists())
                        <div class="mt-6">
                            <h3 class="text-sm font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-3">キャンセル一覧</h3>
                            <div class="space-y-2">
                                @foreach($event->attendances()->where('status', \App\Enums\AttendanceStatus::Cancelled)->with('user')->get() as $attendance)
                                    <div class="flex items-center gap-3 rounded-xl border border-slate-100 dark:border-[#3E3E3A] bg-slate-50 dark:bg-[#1a1a18] p-3 opacity-60">
                                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-slate-200 dark:bg-slate-700 text-slate-500 dark:text-slate-400 text-xs font-semibold">
                                            {{ mb_substr($attendance->user->name, 0, 1) }}
                                        </span>
                                        <span class="flex-1 text-sm text-slate-600 dark:text-slate-400">{{ $attendance->user->name }}</span>
                                        <span class="text-xs text-slate-400 dark:text-slate-500 shrink-0">キャンセル済み</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                @else
                    {{-- キャンセル待ちタブ --}}
                    @php
                        $waitlistUsers = $event->waitlistAttendances()->with('user')->orderBy('waitlisted_at', 'asc')->get();
                    @endphp

                    @if ($waitlistUsers->isEmpty())
                        <p class="text-sm text-slate-500 dark:text-slate-400 italic">キャンセル待ちのユーザーはいません。</p>
                    @else
                        <div class="space-y-2">
                            @foreach($waitlistUsers as $index => $attendance)
                                <div class="flex items-center gap-3 rounded-xl border border-amber-100 dark:border-amber-900/30 bg-amber-50 dark:bg-amber-900/10 p-3">
                                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-amber-100 dark:bg-amber-900/50 text-amber-700 dark:text-amber-300 text-xs font-semibold">
                                        {{ $index + 1 }}
                                    </span>
                                    <span class="flex-1 text-sm font-medium text-slate-800 dark:text-slate-200">{{ $attendance->user->name }}</span>
                                    <span class="text-xs text-slate-400 dark:text-slate-500 shrink-0">
                                        {{ $attendance->waitlisted_at->format('Y/m/d H:i') }} 登録
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    @endif
                @endif
            </section>
        </div>
    @endcan

    {{-- 合同主催者管理（オーナーのみ） --}}
    @can('inviteOrganizer', $event)
        <div class="mt-6">
            <section class="rounded-2xl bg-white dark:bg-[#161615] border border-slate-200 dark:border-[#3E3E3A] p-6 sm:p-8 shadow-sm">
                <h2 class="text-xl font-bold mb-4 flex items-center gap-2">
                    <span class="h-1 w-6 rounded-full bg-gradient-to-r {{ $style['gradient'] }}"></span>
                    合同主催者
                </h2>

                @foreach ($event->eventOrganizers()->with('user')->get() as $organizer)
                    <div class="mb-2 flex items-center justify-between text-sm">
                        <span class="text-slate-700 dark:text-slate-200">
                            {{ $organizer->user->name }}
                            <span class="ml-1 text-xs text-slate-400">
                                @switch($organizer->status)
                                    @case(\App\Enums\OrganizerInvitationStatus::Pending) （招待中） @break
                                    @case(\App\Enums\OrganizerInvitationStatus::Accepted) （承諾済み） @break
                                    @case(\App\Enums\OrganizerInvitationStatus::Declined) （辞退） @break
                                @endswitch
                            </span>
                        </span>
                        <form method="POST" action="{{ route('events.organizers.destroy', [$event, $organizer]) }}"
                            onsubmit="return confirm('この合同主催者を外しますか？')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-xs text-red-600 hover:underline dark:text-red-400">外す</button>
                        </form>
                    </div>
                @endforeach

                <form method="POST" action="{{ route('events.organizers.store', $event) }}" class="mt-4 flex gap-2">
                    @csrf
                    <input type="email" name="email" required placeholder="招待する人のメールアドレス"
                        class="flex-1 rounded-md border border-slate-300 px-3 py-1.5 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100" />
                    <button type="submit" class="rounded-md bg-blue-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-blue-700">
                        合同主催者を招待
                    </button>
                </form>
                @error('email')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror

                @php($acceptedCoOrganizers = $event->acceptedCoOrganizers)
                @if ($acceptedCoOrganizers->isNotEmpty())
                    <form method="POST" action="{{ route('events.ownership.update', $event) }}" class="mt-4 border-t border-slate-200 pt-4 dark:border-slate-700"
                        onsubmit="return confirm('オーナーを移譲すると、あなたは合同主催者になります。よろしいですか？')">
                        @csrf
                        @method('PATCH')
                        <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1">オーナーを移譲する</label>
                        <div class="flex gap-2">
                            <select name="user_id" required class="flex-1 rounded-md border border-slate-300 px-3 py-1.5 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100">
                                @foreach ($acceptedCoOrganizers as $candidate)
                                    <option value="{{ $candidate->id }}">{{ $candidate->name }}</option>
                                @endforeach
                            </select>
                            <button type="submit" class="rounded-md border border-amber-500 px-3 py-1.5 text-sm font-medium text-amber-700 hover:bg-amber-50 dark:text-amber-400 dark:hover:bg-amber-900/20">
                                移譲する
                            </button>
                        </div>
                        @error('user_id')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </form>
                @endif
            </section>
        </div>
    @endcan
</x-app-layout>
