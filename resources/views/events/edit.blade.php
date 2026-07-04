<x-app-layout>
    <x-slot:title>イベント編集 - AI Connpass</x-slot:title>

    <div class="max-w-2xl mx-auto">
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold tracking-tight">イベント編集</h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">イベント情報を更新します</p>
            </div>
            <a href="{{ route('events.show', $event) }}"
                class="inline-flex items-center gap-1 text-sm text-slate-500 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
                </svg>
                詳細に戻る
            </a>
        </div>

        <div class="rounded-2xl bg-white dark:bg-[#161615] border border-slate-200 dark:border-[#3E3E3A] shadow-sm p-6 sm:p-8">
            <form method="POST" action="{{ route('events.update', $event) }}" enctype="multipart/form-data" class="space-y-5">
                @csrf
                @method('PUT')
                <input type="hidden" name="expected_updated_at" value="{{ $event->updated_at->timestamp }}">

                <!-- タイトル -->
                <div>
                    <label for="title" class="block text-sm font-medium mb-1.5 text-slate-700 dark:text-slate-300">
                        タイトル <span class="text-red-500">*</span>
                    </label>
                    <input
                        type="text"
                        id="title"
                        name="title"
                        value="{{ old('title', $event->title) }}"
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
                            <option value="{{ $cat->value }}" {{ old('category', $event->category->value) == $cat->value ? 'selected' : '' }}>
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
                        @foreach (['北海道','青森県','岩手県','宮城県','秋田県','山形県','福島県','茨城県','栃木県','群馬県','埼玉県','千葉県','東京都','神奈川県','新潟県','富山県','石川県','福井県','山梨県','長野県','岐阜県','静岡県','愛知県','三重県','滋賀県','京都府','大阪府','兵庫県','奈良県','和歌山県','鳥取県','島根県','岡山県','広島県','山口県','徳島県','香川県','愛媛県','高知県','福岡県','佐賀県','長崎県','熊本県','大分県','宮崎県','鹿児島県','沖縄県','オンライン','ハイブリッド'] as $pref)
                            <option value="{{ $pref }}" {{ old('prefecture', $event->prefecture) === $pref ? 'selected' : '' }}>{{ $pref }}</option>
                        @endforeach
                    </select>
                    @error('prefecture')
                        <p class="text-red-500 text-sm mt-1.5">{{ $message }}</p>
                    @enderror
                </div>

                <!-- 会場 -->
                <div id="location-field">
                    <label for="location" class="block text-sm font-medium mb-1.5 text-slate-700 dark:text-slate-300">
                        会場 <span class="text-red-500">*</span>
                    </label>
                    <input
                        type="text"
                        id="location"
                        name="location"
                        value="{{ old('location', $event->location) }}"
                        class="w-full px-4 py-2.5 rounded-lg border border-slate-300 dark:border-[#3E3E3A] bg-white dark:bg-[#0a0a0a] text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition @error('location') border-red-500 focus:ring-red-500 @enderror"
                        required
                    />
                    @error('location')
                        <p class="text-red-500 text-sm mt-1.5">{{ $message }}</p>
                    @enderror
                </div>

                <!-- オンラインURL・パスワード -->
                <div id="online-fields" class="space-y-4" style="display: none;">
                    <div>
                        <label for="online_url" class="block text-sm font-medium mb-1.5 text-slate-700 dark:text-slate-300">
                            オンラインURL <span class="text-red-500">*</span>
                        </label>
                        <input
                            type="url"
                            id="online_url"
                            name="online_url"
                            value="{{ old('online_url', $event->online_url) }}"
                            placeholder="例：https://zoom.us/j/123456789"
                            class="w-full px-4 py-2.5 rounded-lg border border-slate-300 dark:border-[#3E3E3A] bg-white dark:bg-[#0a0a0a] text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition @error('online_url') border-red-500 focus:ring-red-500 @enderror"
                        />
                        @error('online_url')
                            <p class="text-red-500 text-sm mt-1.5">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label for="online_password" class="block text-sm font-medium mb-1.5 text-slate-700 dark:text-slate-300">
                            パスワード <span class="text-slate-400 font-normal text-xs">（任意）</span>
                        </label>
                        <input
                            type="text"
                            id="online_password"
                            name="online_password"
                            value="{{ old('online_password', $event->online_password) }}"
                            placeholder="例：abc123"
                            class="w-full px-4 py-2.5 rounded-lg border border-slate-300 dark:border-[#3E3E3A] bg-white dark:bg-[#0a0a0a] text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition @error('online_password') border-red-500 focus:ring-red-500 @enderror"
                        />
                        @error('online_password')
                            <p class="text-red-500 text-sm mt-1.5">{{ $message }}</p>
                        @enderror
                    </div>
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
                            value="{{ old('event_date', $event->event_date->format('Y-m-d\TH:i')) }}"
                            class="flex-1 min-w-0 px-4 py-2.5 rounded-lg border border-slate-300 dark:border-[#3E3E3A] bg-white dark:bg-[#0a0a0a] text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition [color-scheme:light_dark] @error('event_date') border-red-500 focus:ring-red-500 @enderror"
                            required
                        />
                    </div>
                    @error('event_date')
                        <p class="text-red-500 text-sm mt-1.5">{{ $message }}</p>
                    @enderror
                </div>

                <!-- 終了日時 -->
                <div class="min-w-0">
                    <label for="end_date" class="block text-sm font-medium mb-1.5 text-slate-700 dark:text-slate-300">
                        終了日時 <span class="text-red-500">*</span>
                    </label>
                    <div class="flex">
                        <input
                            type="datetime-local"
                            id="end_date"
                            name="end_date"
                            value="{{ old('end_date', $event->end_date->format('Y-m-d\TH:i')) }}"
                            class="flex-1 min-w-0 px-4 py-2.5 rounded-lg border border-slate-300 dark:border-[#3E3E3A] bg-white dark:bg-[#0a0a0a] text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition [color-scheme:light_dark] @error('end_date') border-red-500 focus:ring-red-500 @enderror"
                            required
                        />
                    </div>
                    @error('end_date')
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
                        value="{{ old('capacity', $event->capacity) }}"
                        min="1"
                        class="w-full px-4 py-2.5 rounded-lg border border-slate-300 dark:border-[#3E3E3A] bg-white dark:bg-[#0a0a0a] text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition @error('capacity') border-red-500 focus:ring-red-500 @enderror"
                        required
                    />
                    @error('capacity')
                        <p class="text-red-500 text-sm mt-1.5">{{ $message }}</p>
                    @enderror
                </div>

                <!-- カバー画像 -->
                <div>
                    <label for="cover_image" class="block text-sm font-medium mb-1.5 text-slate-700 dark:text-slate-300">
                        カバー画像 <span class="text-slate-400 font-normal text-xs">（任意・JPEG/PNG/WebP・5MBまで）</span>
                    </label>
                    @if ($event->cover_image_path)
                        <img src="{{ $event->cover_image_url }}"
                             alt="現在のカバー画像"
                             class="mb-2 h-32 w-auto rounded-lg object-cover border border-slate-200 dark:border-[#3E3E3A]" />
                        <label class="mt-2 inline-flex items-center gap-2 text-sm text-slate-600 dark:text-slate-400">
                            <input type="checkbox" name="remove_cover_image" value="1"
                                   class="rounded border-slate-300 dark:border-[#3E3E3A] text-indigo-600 focus:ring-indigo-500">
                            現在の画像を削除する
                        </label>
                    @endif
                    <input
                        type="file"
                        id="cover_image"
                        name="cover_image"
                        accept="image/jpeg,image/png,image/webp"
                        class="w-full text-sm text-slate-700 dark:text-slate-300 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 @error('cover_image') border-red-500 @enderror"
                    />
                    @error('cover_image')
                        <p class="text-red-500 text-sm mt-1.5">{{ $message }}</p>
                    @enderror
                </div>

                <!-- 公開設定 -->
                <div>
                    <label for="status" class="block text-sm font-medium mb-1.5 text-slate-700 dark:text-slate-300">
                        公開設定 <span class="text-red-500">*</span>
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
                            <option value="{{ $s->value }}" {{ old('status', $event->status->value) == $s->value ? 'selected' : '' }}>
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
                        class="w-full px-4 py-2.5 rounded-lg border border-slate-300 dark:border-[#3E3E3A] bg-white dark:bg-[#0a0a0a] text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition resize-none @error('description') border-red-500 focus:ring-red-500 @enderror"
                    >{{ old('description', $event->description) }}</textarea>
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
                        更新する
                    </button>
                    <a
                        href="{{ route('events.show', $event) }}"
                        class="px-4 py-2.5 rounded-lg border border-slate-300 dark:border-[#3E3E3A] text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-[#1a1a18] text-sm font-medium transition"
                    >
                        キャンセル
                    </a>
                </div>
            </form>

            <script>
(function () {
    const prefectureSelect = document.getElementById('prefecture');
    const locationField = document.getElementById('location-field');
    const onlineFields = document.getElementById('online-fields');

    if (!prefectureSelect || !locationField || !onlineFields) { return; }

    applyVisibility(prefectureSelect.value);

    prefectureSelect.addEventListener('change', function () {
        applyVisibility(this.value);
    });

    function applyVisibility(pref) {
        const isOnline = pref === 'オンライン';
        const isHybrid = pref === 'ハイブリッド';
        locationField.style.display = isOnline ? 'none' : '';
        onlineFields.style.display = (isOnline || isHybrid) ? '' : 'none';
    }
}());
</script>

            @can('delete', $event)
            <!-- 削除フォーム（更新フォームの外） -->
            <div class="mt-6 pt-6 border-t border-slate-200 dark:border-[#3E3E3A]">
                <p class="text-xs text-slate-500 dark:text-slate-400 mb-3">危険な操作</p>
                <form method="POST" action="{{ route('events.destroy', $event) }}"
                    onsubmit="return confirm('このイベントを削除してもよいですか？この操作は取り消せません。')">
                    @csrf
                    @method('DELETE')
                    <button type="submit"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-red-200 dark:border-red-900/50 text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 text-sm font-medium transition-colors">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                        </svg>
                        このイベントを削除する
                    </button>
                </form>
            </div>
            @endcan

        </div>
    </div>
</x-app-layout>
