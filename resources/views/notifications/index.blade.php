<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Notifications</h2>
                <p class="mt-1 text-sm text-gray-500">{{ $unreadCount }} notification{{ $unreadCount === 1 ? ' non lue' : 's non lues' }}</p>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                @if (\Illuminate\Support\Facades\Route::has('notifications.preferences.edit'))
                    <a href="{{ route('notifications.preferences.edit') }}" class="text-sm font-medium text-indigo-700 hover:underline">
                        Préférences
                    </a>
                @endif
                @if ($unreadCount > 0)
                    <form method="POST" action="{{ route('notifications.read-all') }}">
                        @csrf
                        @method('PATCH')
                        <x-secondary-button>Tout marquer comme lu</x-secondary-button>
                    </form>
                @endif
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-4xl space-y-4 px-4 sm:px-6 lg:px-8">
            <x-flash />

            <div class="space-y-3">
                @forelse ($notifications as $notification)
                    <article @class([
                        'flex flex-col gap-4 rounded-lg border p-4 shadow-sm sm:flex-row sm:items-start sm:justify-between',
                        'border-indigo-200 bg-indigo-50' => $notification->read_at === null,
                        'border-gray-200 bg-white' => $notification->read_at !== null,
                    ])>
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 class="font-semibold text-gray-900">{{ $notification->data['title'] ?? 'Notification' }}</h3>
                                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-700">
                                    {{ \App\Enums\NotificationCategory::displayLabel($notification->data['category'] ?? null) }}
                                </span>
                                @if ($notification->read_at === null)
                                    <span class="rounded-full bg-indigo-100 px-2 py-0.5 text-xs font-medium text-indigo-800">Non lue</span>
                                @endif
                            </div>
                            <p class="mt-1 text-sm text-gray-700">{{ $notification->data['message'] ?? '' }}</p>
                            <p class="mt-2 text-xs text-gray-500">{{ \App\Support\Format::dateTime($notification->created_at) }}</p>
                        </div>

                        <div class="flex shrink-0 flex-wrap items-center gap-3">
                            @if (! empty($notification->data['url']))
                                <a href="{{ $notification->data['url'] }}" class="text-sm font-medium text-indigo-700 hover:underline">
                                    Consulter
                                </a>
                            @endif
                            @if ($notification->read_at === null)
                                <form method="POST" action="{{ route('notifications.read', $notification->id) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="text-sm text-gray-600 hover:underline">Marquer comme lue</button>
                                </form>
                            @endif
                        </div>
                    </article>
                @empty
                    <div class="rounded-lg border border-dashed border-gray-300 bg-white px-6 py-12 text-center text-sm text-gray-500">
                        Vous n'avez aucune notification pour le moment.
                    </div>
                @endforelse
            </div>

            {{ $notifications->links() }}
        </div>
    </div>
</x-app-layout>
