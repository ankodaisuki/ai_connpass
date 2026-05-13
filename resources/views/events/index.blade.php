@php
    use App\Enums\EventCategory;

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
            <div class="relative max-w-3xl">
                <h1 class="text-3xl sm:text-4xl lg:text-5xl font-bold text-white tracking-tight">
                    エンジニアのための<br class="sm:hidden">イベントを見つけよう
                </h1>
                <p class="mt-4 text-base sm:text-lg text-indigo-100 max-w-2xl">
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

    <!-- イベントカードグリッド -->
    @if ($events->isEmpty())
        <div class="flex flex-col items-center justify-center py-20 text-center">
            <div class="flex h-16 w-16 items-center justify-center rounded-full bg-slate-100 dark:bg-[#161615] mb-4">
                <svg class="h-8 w-8 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                </svg>
            </div>
            <p class="text-lg font-medium text-slate-700 dark:text-slate-300">公開中のイベントはまだありません</p>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">しばらくしてから再度ご確認ください。</p>
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
                                    <span>{{ $event->event_date->format('Y/m/d (D) H:i') }}</span>
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
