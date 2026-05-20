<x-app-layout>
    <x-slot:title>イベント作成 - AI Connpass</x-slot:title>

    <div class="max-w-2xl mx-auto">
        <div class="mb-6">
            <h1 class="text-2xl font-bold tracking-tight">イベント作成</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">新しいイベントを作成して参加者を募集しましょう</p>
        </div>

        <div class="rounded-2xl bg-white dark:bg-[#161615] border border-slate-200 dark:border-[#3E3E3A] shadow-sm p-6 sm:p-8">
            <form method="POST" action="{{ route('events.store') }}" class="space-y-5">
                @csrf

                <!-- タイトル -->
                <div>
                    <label for="title" class="block text-sm font-medium mb-1.5 text-slate-700 dark:text-slate-300">
                        タイトル <span class="text-red-500">*</span>
                    </label>
                    <input
                        type="text"
                        id="title"
                        name="title"
                        value="{{ old('title') }}"
                        placeholder="例：Laravel × AI 勉強会 Vol.1"
                        class="w-full px-4 py-2.5 rounded-lg border border-slate-300 dark:border-[#3E3E3A] bg-white dark:bg-[#0a0a0a] text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition @error('title') border-red-500 focus:ring-red-500 @enderror"
                        required
                        autofocus
                    />
                    @error('title')
                        <p class="text-red-500 text-sm mt-1.5">{{ $message }}</p>
                    @enderror
                </div>

                <!-- カテゴリ -->
                <div>
                    <label for="category" class="block text-sm font-medium mb-1.5 text-slate-700 dark:text-slate-300">
                        カテゴリ <span class="text-red-500">*</span>
                    </label>
                    <select
                        id="category"
                        name="category"
                        class="w-full px-4 py-2.5 rounded-lg border border-slate-300 dark:border-[#3E3E3A] bg-white dark:bg-[#0a0a0a] text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition @error('category') border-red-500 focus:ring-red-500 @enderror"
                        required
                    >
                        <option value="">選択してください</option>
                        @foreach ($categories as $cat)
                            @php
                                $labels = [
                                    'Frontend' => 'フロントエンド',
                                    'Backend' => 'バックエンド',
                                    'Database' => 'データベース',
                                    'Mobile' => 'モバイル',
                                    'Ai' => 'AI',
                                    'Other' => 'その他',
                                ];
                            @endphp
                            <option value="{{ $cat->value }}" {{ old('category') == $cat->value ? 'selected' : '' }}>
                                {{ $labels[$cat->name] ?? $cat->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('category')
                        <p class="text-red-500 text-sm mt-1.5">{{ $message }}</p>
                    @enderror
                </div>

                <!-- 都道府県 -->
                <div>
                    <label for="prefecture" class="block text-sm font-medium mb-1.5 text-slate-700 dark:text-slate-300">
                        都道府県 <span class="text-red-500">*</span>
                    </label>
                    <select
                        id="prefecture"
                        name="prefecture"
                        class="w-full px-4 py-2.5 rounded-lg border border-slate-300 dark:border-[#3E3E3A] bg-white dark:bg-[#0a0a0a] text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition @error('prefecture') border-red-500 focus:ring-red-500 @enderror"
                        required
                    >
                        <option value="">選択してください</option>
                        @foreach (['北海道','青森県','岩手県','宮城県','秋田県','山形県','福島県','茨城県','栃木県','群馬県','埼玉県','千葉県','東京都','神奈川県','新潟県','富山県','石川県','福井県','山梨県','長野県','岐阜県','静岡県','愛知県','三重県','滋賀県','京都府','大阪府','兵庫県','奈良県','和歌山県','鳥取県','島根県','岡山県','広島県','山口県','徳島県','香川県','愛媛県','高知県','福岡県','佐賀県','長崎県','熊本県','大分県','宮崎県','鹿児島県','沖縄県','オンライン'] as $pref)
                            <option value="{{ $pref }}" {{ old('prefecture') === $pref ? 'selected' : '' }}>{{ $pref }}</option>
                        @endforeach
                    </select>
                    @error('prefecture')
                        <p class="text-red-500 text-sm mt-1.5">{{ $message }}</p>
                    @enderror
                </div>

                <!-- 会場 -->
                <div>
                    <label for="location" class="block text-sm font-medium mb-1.5 text-slate-700 dark:text-slate-300">
                        会場 <span class="text-red-500">*</span>
                    </label>
                    <input
                        type="text"
                        id="location"
                        name="location"
                        value="{{ old('location') }}"
                        placeholder="例：渋谷ヒカリエ 8F"
                        class="w-full px-4 py-2.5 rounded-lg border border-slate-300 dark:border-[#3E3E3A] bg-white dark:bg-[#0a0a0a] text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition @error('location') border-red-500 focus:ring-red-500 @enderror"
                        required
                    />
                    @error('location')
                        <p class="text-red-500 text-sm mt-1.5">{{ $message }}</p>
                    @enderror
                </div>

                <!-- 開催日時 -->
                <div class="min-w-0">
                    <label for="event_date" class="block text-sm font-medium mb-1.5 text-slate-700 dark:text-slate-300">
                        開催日時 <span class="text-red-500">*</span>
                    </label>
                    <div class="flex">
                        <input
                            type="datetime-local"
                            id="event_date"
                            name="event_date"
                            value="{{ old('event_date') }}"
                            class="flex-1 min-w-0 px-4 py-2.5 rounded-lg border border-slate-300 dark:border-[#3E3E3A] bg-white dark:bg-[#0a0a0a] text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition [color-scheme:light_dark] @error('event_date') border-red-500 focus:ring-red-500 @enderror"
                            required
                        />
                    </div>
                    @error('event_date')
                        <p class="text-red-500 text-sm mt-1.5">{{ $message }}</p>
                    @enderror
                </div>

                <!-- 定員 -->
                <div>
                    <label for="capacity" class="block text-sm font-medium mb-1.5 text-slate-700 dark:text-slate-300">
                        定員 <span class="text-red-500">*</span>
                    </label>
                    <input
                        type="number"
                        id="capacity"
                        name="capacity"
                        value="{{ old('capacity', 30) }}"
                        min="1"
                        class="w-full px-4 py-2.5 rounded-lg border border-slate-300 dark:border-[#3E3E3A] bg-white dark:bg-[#0a0a0a] text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition @error('capacity') border-red-500 focus:ring-red-500 @enderror"
                        required
                    />
                    @error('capacity')
                        <p class="text-red-500 text-sm mt-1.5">{{ $message }}</p>
                    @enderror
                </div>

                <!-- 公開設定 -->
                <div>
                    <label for="status" class="block text-sm font-medium mb-1.5 text-slate-700 dark:text-slate-300">
                        公開設定
                    </label>
                    <select
                        id="status"
                        name="status"
                        class="w-full px-4 py-2.5 rounded-lg border border-slate-300 dark:border-[#3E3E3A] bg-white dark:bg-[#0a0a0a] text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition"
                    >
                        @foreach ($statuses as $s)
                            @php
                                $statusLabels = [
                                    'Draft' => '下書き',
                                    'Published' => '公開',
                                    'Private' => '非公開',
                                ];
                            @endphp
                            <option value="{{ $s->value }}" {{ old('status', 0) == $s->value ? 'selected' : '' }}>
                                {{ $statusLabels[$s->name] ?? $s->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- 概要 -->
                <div>
                    <label for="description" class="block text-sm font-medium mb-1.5 text-slate-700 dark:text-slate-300">
                        概要
                    </label>
                    <textarea
                        id="description"
                        name="description"
                        rows="6"
                        placeholder="イベントの詳細・内容・対象者などを記入してください"
                        class="w-full px-4 py-2.5 rounded-lg border border-slate-300 dark:border-[#3E3E3A] bg-white dark:bg-[#0a0a0a] text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition resize-none @error('description') border-red-500 focus:ring-red-500 @enderror"
                    >{{ old('description') }}</textarea>
                    @error('description')
                        <p class="text-red-500 text-sm mt-1.5">{{ $message }}</p>
                    @enderror
                </div>

                <!-- ボタン -->
                <div class="flex items-center gap-3 pt-2">
                    <button
                        type="submit"
                        class="flex-1 px-4 py-2.5 rounded-lg bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-700 hover:to-purple-700 text-white font-semibold shadow-sm transition"
                    >
                        作成する
                    </button>
                    <a
                        href="{{ route('events.index') }}"
                        class="px-4 py-2.5 rounded-lg border border-slate-300 dark:border-[#3E3E3A] text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-[#1a1a18] text-sm font-medium transition"
                    >
                        キャンセル
                    </a>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
