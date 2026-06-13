<x-app-layout>
    <div class="mx-auto max-w-3xl px-4 py-8">
        <h1 class="mb-6 text-xl font-bold text-slate-800 dark:text-slate-100">合同主催しているイベント</h1>

        @forelse ($events as $event)
            <div class="mb-3 flex items-center justify-between rounded-lg border border-slate-200 bg-white px-4 py-3 dark:border-slate-700 dark:bg-slate-800">
                <div>
                    <a href="{{ route('events.show', $event) }}" class="font-medium text-slate-800 hover:underline dark:text-slate-100">
                        {{ $event->title }}
                    </a>
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        オーナー: {{ $event->user->name }}
                    </p>
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        {{ $event->event_date->format('Y/m/d H:i') }}
                    </p>
                </div>
                @if (!$event->end_date->isPast())
                    <a href="{{ route('events.edit', $event) }}"
                        class="rounded-md border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-600 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-700">
                        編集
                    </a>
                @else
                    <span class="rounded-md border border-slate-200 px-3 py-1.5 text-sm text-slate-400 dark:border-slate-700 dark:text-slate-600">
                        終了済み
                    </span>
                @endif
            </div>
        @empty
            <p class="text-sm text-slate-500 dark:text-slate-400">合同主催しているイベントはありません。</p>
        @endforelse
    </div>
</x-app-layout>
