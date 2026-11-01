<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wider text-teal-700">Communications</p>
                <h2 class="mt-1 text-2xl font-bold text-slate-900">
                    {{ auth()->user()->isAdmin() ? 'Messages des familles' : 'Messages avec l’administration' }}
                </h2>
                <p class="mt-1 text-sm text-slate-500">Les échanges restent disponibles dans votre historique.</p>
            </div>
            <a href="{{ route('communications.messages.create') }}" class="rounded-lg bg-teal-700 px-4 py-2 text-sm font-semibold text-white hover:bg-teal-800">
                {{ auth()->user()->isAdmin() ? 'Écrire à une famille' : 'Nouveau message' }}
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-4xl space-y-3 px-4 sm:px-6 lg:px-8">
            @forelse ($conversations as $conversation)
                @php($lastMessage = $conversation->latestMessage->first())
                <a href="{{ route('communications.messages.show', $conversation) }}" @class([
                    'flex flex-col gap-3 rounded-2xl border p-5 shadow-sm transition sm:flex-row sm:items-center sm:justify-between',
                    'border-teal-200 bg-teal-50' => $conversation->unread_messages_count > 0,
                    'border-slate-200 bg-white hover:border-teal-300' => $conversation->unread_messages_count === 0,
                ])>
                    <div class="min-w-0">
                        <h3 class="font-semibold text-slate-900">
                            {{ auth()->user()->isAdmin() ? $conversation->guardian->full_name : 'Administration du centre' }}
                        </h3>
                        <p class="mt-1 truncate text-sm text-slate-600">{{ $lastMessage?->body ?? 'Aucun message' }}</p>
                        @if ($lastMessage)
                            <p class="mt-1 text-xs text-slate-500">{{ \App\Support\Format::dateTime($lastMessage->created_at) }}</p>
                        @endif
                    </div>
                    @if ($conversation->unread_messages_count > 0)
                        <span class="shrink-0 rounded-full bg-teal-100 px-3 py-1 text-xs font-semibold text-teal-800">
                            {{ $conversation->unread_messages_count }} non lu{{ $conversation->unread_messages_count === 1 ? '' : 's' }}
                        </span>
                    @endif
                </a>
            @empty
                <div class="rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-12 text-center">
                    <p class="text-sm text-slate-500">Aucune conversation pour le moment.</p>
                    <a href="{{ route('communications.messages.create') }}" class="mt-3 inline-flex text-sm font-semibold text-teal-800 hover:underline">
                        {{ auth()->user()->isAdmin() ? 'Commencer un échange' : 'Écrire à l’administration' }}
                    </a>
                </div>
            @endforelse

            {{ $conversations->links() }}
        </div>
    </div>
</x-app-layout>
