@php
    use App\Enums\EventCategory;

    $categoryLabels = [
        EventCategory::Frontend->value => 'フロントエンド',
        EventCategory::Backend->value => 'バックエンド',
        EventCategory::Database->value => 'データベース',
        EventCategory::Mobile->value => 'モバイル',
        EventCategory::Ai->value => 'AI',
        EventCategory::Other->value => 'その他',
    ];

    $categoryStyles = [
        EventCategory::Frontend->value => ['label' => 'フロントエンド', 'class' => 'bg-sky-100 text-sky-700 dark:bg-sky-900/40 dark:text-sky-300'],
        EventCategory::Backend->value => ['label' => 'バックエンド', 'class' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300'],
        EventCategory::Database->value => ['label' => 'データベース', 'class' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300'],
        EventCategory::Mobile->value => ['label' => 'モバイル', 'class' => 'bg-pink-100 text-pink-700 dark:bg-pink-900/40 dark:text-pink-300'],
        EventCategory::Ai->value => ['label' => 'AI', 'class' => 'bg-violet-100 text-violet-700 dark:bg-violet-900/40 dark:text-violet-300'],
        EventCategory::Other->value => ['label' => 'その他', 'class' => 'bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-300'],
    ];
@endphp

<x-app-layout>
    <x-slot:title>イベント一覧 - AI Connpass</x-slot:title>

    <!-- ヒーローセクション -->
    <section class="mb-10 lg:mb-12">
        <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-indigo-600 via-purple-600 to-pink-600 px-6 py-12 sm:px-12 sm:py-16 shadow-xl">
            <div class="absolute inset-0 bg-grid-white/10 [mask-image:radial-gradient(white,transparent_70%)]"></div>
            <div class="relative">
                <h1 class="text-3xl sm:text-4xl lg:text-5xl font-bold text-white tracking-tight">
                    エンジニアのための<br class="sm:hidden">イベントを見つけよう
                </h1>
                <p class="mt-4 text-base sm:text-lg text-indigo-100">
                    フロントエンド、バックエンド、AI など、興味のある分野の勉強会・ハンズオンに参加できます。
                </p>
                <div class="mt-6 flex flex-wrap items-center gap-3 text-sm text-white/90">
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-white/20 px-3 py-1 backdrop-blur">
                        <span class="h-2 w-2 rounded-full bg-emerald-400"></span>
                        {{ $events->total() }}件のイベント
                    </span>
                </div>
            </div>
        </div>
    </section>

    <!-- 検索・フィルターフォーム -->
    @php
        $hasFilters = collect($filters ?? [])->filter()->isNotEmpty();
        $prefectures = ['北海道','青森県','岩手県','宮城県','秋田県','山形県','福島県','茨城県','栃木県','群馬県','埼玉県','千葉県','東京都','神奈川県','新潟県','富山県','石川県','福井県','山梨県','長野県','岐阜県','静岡県','愛知県','三重県','滋賀県','京都府','大阪府','兵庫県','奈良県','和歌山県','鳥取県','島根県','岡山県','広島県','山口県','徳島県','香川県','愛媛県','高知県','福岡県','佐賀県','長崎県','熊本県','大分県','宮崎県','鹿児島県','沖縄県','オンライン'];
    @endphp
    <section class="mb-8">
        <form method="GET" action="{{ route('events.index') }}" class="rounded-2xl bg-white dark:bg-[#161615] border border-slate-200 dark:border-[#3E3E3A] shadow-sm p-4 sm:p-5">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                <!-- キーワード -->
                <div class="lg:col-span-2">
                    <label for="q" class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">キーワード</label>
                    <div class="relative">
                        <svg class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 15.803a7.5 7.5 0 0 0 10.607 0Z" />
                        </svg>
                        <input type="text" id="q" name="q" value="{{ $filters['q'] ?? '' }}"
                            placeholder="タイトル・内容で検索"
                            class="w-full pl-9 pr-4 py-2 rounded-lg border border-slate-300 dark:border-[#3E3E3A] bg-white dark:bg-[#0a0a0a] text-slate-900 dark:text-white placeholder-slate-400 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition" />
                    </div>
                </div>

                <!-- カテゴリ -->
                <div>
                    <label for="category" class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">カテゴリ</label>
                    <select id="category" name="category"
                        class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-[#3E3E3A] bg-white dark:bg-[#0a0a0a] text-slate-900 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition">
                        <option value="">すべて</option>
                        @foreach (EventCategory::cases() as $cat)
                            <option value="{{ $cat->value }}" {{ ($filters['category'] ?? '') == $cat->value ? 'selected' : '' }}>
                                {{ $categoryLabels[$cat->value] }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- 都道府県 -->
                <div>
                    <label for="prefecture" class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">都道府県</label>
                    <select id="prefecture" name="prefecture"
                        class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-[#3E3E3A] bg-white dark:bg-[#0a0a0a] text-slate-900 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition">
                        <option value="">すべて</option>
                        @foreach ($prefectures as $pref)
                            <option value="{{ $pref }}" {{ ($filters['prefecture'] ?? '') === $pref ? 'selected' : '' }}>{{ $pref }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- 開催日（from） -->
                <div class="min-w-0">
                    <label for="from" class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">開催日（以降）</label>
                    <div class="flex">
                        <input type="date" id="from" name="from" value="{{ $filters['from'] ?? '' }}"
                            class="flex-1 min-w-0 px-3 py-2 rounded-lg border border-slate-300 dark:border-[#3E3E3A] bg-white dark:bg-[#0a0a0a] text-slate-900 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition [color-scheme:light_dark]" />
                    </div>
                </div>

                <!-- 開催日（to） -->
                <div class="min-w-0">
                    <label for="to" class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">開催日（以前）</label>
                    <div class="flex">
                        <input type="date" id="to" name="to" value="{{ $filters['to'] ?? '' }}"
                            class="flex-1 min-w-0 px-3 py-2 rounded-lg border border-slate-300 dark:border-[#3E3E3A] bg-white dark:bg-[#0a0a0a] text-slate-900 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition [color-scheme:light_dark]" />
                    </div>
                </div>

                <!-- ボタン -->
                <div class="flex items-end gap-2 lg:col-span-2">
                    <button type="submit"
                        class="flex-1 px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium shadow-sm transition-colors">
                        検索
                    </button>
                    @if ($hasFilters)
                        <a href="{{ route('events.index') }}"
                            class="px-4 py-2 rounded-lg border border-slate-300 dark:border-[#3E3E3A] text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-[#1a1a18] text-sm font-medium transition-colors whitespace-nowrap">
                            クリア
                        </a>
                    @endif
                </div>
            </div>

            <!-- アクティブフィルターの概要 -->
            @if ($hasFilters)
                <div class="mt-3 flex flex-wrap items-center gap-2 pt-3 border-t border-slate-100 dark:border-[#3E3E3A]">
                    <span class="text-xs text-slate-500 dark:text-slate-400">絞り込み中：</span>
                    @if (!empty($filters['q']))
                        <span class="inline-flex items-center gap-1 rounded-full bg-indigo-50 dark:bg-indigo-900/30 px-2.5 py-0.5 text-xs font-medium text-indigo-700 dark:text-indigo-300">
                            「{{ $filters['q'] }}」
                        </span>
                    @endif
                    @if (!empty($filters['category']))
                        <span class="inline-flex items-center gap-1 rounded-full bg-indigo-50 dark:bg-indigo-900/30 px-2.5 py-0.5 text-xs font-medium text-indigo-700 dark:text-indigo-300">
                            {{ $categoryLabels[(int)$filters['category']] ?? '' }}
                        </span>
                    @endif
                    @if (!empty($filters['prefecture']))
                        <span class="inline-flex items-center gap-1 rounded-full bg-indigo-50 dark:bg-indigo-900/30 px-2.5 py-0.5 text-xs font-medium text-indigo-700 dark:text-indigo-300">
                            {{ $filters['prefecture'] }}
                        </span>
                    @endif
                    @if (!empty($filters['from']))
                        <span class="inline-flex items-center gap-1 rounded-full bg-indigo-50 dark:bg-indigo-900/30 px-2.5 py-0.5 text-xs font-medium text-indigo-700 dark:text-indigo-300">
                            {{ $filters['from'] }} 以降
                        </span>
                    @endif
                    @if (!empty($filters['to']))
                        <span class="inline-flex items-center gap-1 rounded-full bg-indigo-50 dark:bg-indigo-900/30 px-2.5 py-0.5 text-xs font-medium text-indigo-700 dark:text-indigo-300">
                            {{ $filters['to'] }} 以前
                        </span>
                    @endif
                </div>
            @endif
        </form>
    </section>

    <!-- イベントカードグリッド -->
    @if ($events->isEmpty())
        <div class="flex flex-col items-center justify-center py-20 text-center">
            <div class="flex h-16 w-16 items-center justify-center rounded-full bg-slate-100 dark:bg-[#161615] mb-4">
                <svg class="h-8 w-8 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                </svg>
            </div>
            @if ($hasFilters)
                <p class="text-lg font-medium text-slate-700 dark:text-slate-300">条件に一致するイベントが見つかりませんでした</p>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">検索条件を変えてお試しください。</p>
                <a href="{{ route('events.index') }}" class="mt-4 inline-flex items-center px-4 py-2 rounded-lg border border-slate-300 dark:border-[#3E3E3A] text-slate-600 dark:text-slate-400 text-sm font-medium hover:bg-slate-50 dark:hover:bg-[#1a1a18] transition-colors">
                    フィルターをクリア
                </a>
            @else
                <p class="text-lg font-medium text-slate-700 dark:text-slate-300">公開中のイベントはまだありません</p>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">しばらくしてから再度ご確認ください。</p>
            @endif
        </div>
    @else
        <section>
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-xl font-bold tracking-tight">公開中のイベント</h2>
                <span class="text-sm text-slate-500 dark:text-slate-400">
                    {{ $events->firstItem() }}-{{ $events->lastItem() }} / {{ $events->total() }}件
                </span>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                @foreach ($events as $event)
                    @php
                        $style = $categoryStyles[$event->category->value] ?? $categoryStyles[EventCategory::Other->value];
                        $isPast = $event->event_date->isPast();
                        $attendeeCount = $event->attendances_count ?? 0;
                        $isFull = $attendeeCount >= $event->capacity;
                    @endphp
                    <a href="{{ route('events.show', $event) }}"
                        class="group flex flex-col rounded-xl bg-white dark:bg-[#161615] border border-slate-200 dark:border-[#3E3E3A] overflow-hidden shadow-sm hover:shadow-lg hover:-translate-y-0.5 transition-all duration-200">
                        <!-- カードヘッダー（カテゴリーストライプ） -->
                        <div class="h-1.5 bg-gradient-to-r {{ $event->category === EventCategory::Frontend ? 'from-sky-400 to-blue-500' : '' }}{{ $event->category === EventCategory::Backend ? 'from-emerald-400 to-green-500' : '' }}{{ $event->category === EventCategory::Database ? 'from-amber-400 to-orange-500' : '' }}{{ $event->category === EventCategory::Mobile ? 'from-pink-400 to-rose-500' : '' }}{{ $event->category === EventCategory::Ai ? 'from-violet-400 to-purple-500' : '' }}{{ $event->category === EventCategory::Other ? 'from-slate-300 to-slate-400' : '' }}"></div>

                        <div class="flex flex-1 flex-col p-5">
                            <!-- バッジ群 -->
                            <div class="flex flex-wrap items-center gap-2 mb-3">
                                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $style['class'] }}">
                                    {{ $style['label'] }}
                                </span>
                                @if ($isPast)
                                    <span class="inline-flex items-center rounded-full bg-slate-100 dark:bg-slate-800 px-2.5 py-0.5 text-xs font-medium text-slate-600 dark:text-slate-400">
                                        終了
                                    </span>
                                @elseif ($isFull)
                                    <span class="inline-flex items-center rounded-full bg-red-100 dark:bg-red-900/40 px-2.5 py-0.5 text-xs font-medium text-red-700 dark:text-red-300">
                                        満員
                                    </span>
                                @endif
                            </div>

                            <!-- タイトル -->
                            <h3 class="text-lg font-bold leading-tight text-slate-900 dark:text-white mb-2 group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors line-clamp-2">
                                {{ $event->title }}
                            </h3>

                            <!-- 詳細メタ -->
                            <div class="mt-auto space-y-2 text-sm text-slate-600 dark:text-slate-400">
                                <div class="flex items-center gap-2">
                                    <svg class="h-4 w-4 flex-shrink-0 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                                    </svg>
                                    <span>{{ $event->event_date->format('Y/m/d') }}（{{ $event->event_date->locale('ja')->isoFormat('ddd') }}）{{ $event->event_date->format('H:i') }}</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <svg class="h-4 w-4 flex-shrink-0 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z" />
                                    </svg>
                                    <span class="truncate">{{ $event->prefecture }} / {{ $event->location }}</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <svg class="h-4 w-4 flex-shrink-0 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                                    </svg>
                                    <span>{{ $attendeeCount }} / {{ $event->capacity }}人</span>
                                </div>
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>

            <!-- ページネーション -->
            <div class="mt-10">
                {{ $events->onEachSide(1)->links() }}
            </div>
        </section>
    @endif
</x-app-layout>
