<x-app-layout>
    <div class="mx-auto max-w-3xl px-4 py-8">
        <h1 class="mb-6 text-xl font-bold text-slate-800 dark:text-slate-100">合同主催の招待</h1>

        @if (session('success'))
            <div class="mb-4 rounded-lg bg-green-50 px-4 py-3 text-sm text-green-700 dark:bg-green-900/30 dark:text-green-300">
                {{ session('success') }}
            </div>
        @endif

        @forelse ($invitations as $invitation)
            <div class="mb-3 flex items-center justify-between rounded-lg border border-slate-200 bg-white px-4 py-3 dark:border-slate-700 dark:bg-slate-800">
                <div>
                    <a href="{{ route('events.show', $invitation->event) }}" class="font-medium text-slate-800 hover:underline dark:text-slate-100">
                        {{ $invitation->event->title }}
                    </a>
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        {{ $invitation->event->user->name }} さんからの招待
                    </p>
                </div>
                <div class="flex gap-2">
                    <form method="POST" action="{{ route('organizer-invitations.accept', $invitation) }}">
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="rounded-md bg-blue-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-blue-700">
                            承諾
                        </button>
                    </form>
                    <form method="POST" action="{{ route('organizer-invitations.decline', $invitation) }}">
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="rounded-md border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-600 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-300">
                            辞退
                        </button>
                    </form>
                </div>
            </div>
        @empty
            <p class="text-sm text-slate-500 dark:text-slate-400">現在、保留中の招待はありません。</p>
        @endforelse
    </div>
</x-app-layout>
