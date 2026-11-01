<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wider text-teal-700">Communications</p>
                <h2 class="mt-1 text-2xl font-bold text-slate-900">Annonces</h2>
                <p class="mt-1 text-sm text-slate-500">Informations du centre pour les familles.</p>
            </div>
            @if (auth()->user()->isAdmin())
                <a href="{{ route('admin.communications.announcements.create') }}" class="rounded-lg bg-teal-700 px-4 py-2 text-sm font-semibold text-white hover:bg-teal-800">
                    Nouvelle annonce
                </a>
            @endif
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-4xl space-y-4 px-4 sm:px-6 lg:px-8">
            <x-flash />
            @forelse ($announcements as $announcement)
                <article @class([
                    'rounded-2xl border p-5 shadow-sm',
                    'border-slate-200 bg-white' => auth()->user()->isAdmin() || $announcement->is_read,
                    'border-teal-200 bg-teal-50' => auth()->user()->isGuardian() && ! $announcement->is_read,
                ])>
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h3 class="font-semibold text-slate-900">{{ $announcement->title }}</h3>
                            <p class="mt-1 text-sm text-slate-500">
                                {{ \App\Support\Format::dateTime($announcement->published_at) }}
                                · {{ $announcement->classroom?->name ?? 'Toutes les familles' }}
                            </p>
                        </div>
                        @if (auth()->user()->isGuardian() && ! $announcement->is_read)
                            <span class="rounded-full bg-teal-100 px-2.5 py-1 text-xs font-semibold text-teal-800">Nouvelle</span>
                        @endif
                    </div>
                    <p class="mt-3 whitespace-pre-line text-sm text-slate-700">{{ \Illuminate\Support\Str::limit($announcement->body, 220) }}</p>
                    <a href="{{ route('communications.announcements.show', $announcement) }}" class="mt-4 inline-flex text-sm font-semibold text-teal-800 hover:underline">
                        Lire l’annonce
                    </a>
                </article>
            @empty
                <div class="rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-12 text-center text-sm text-slate-500">
                    Aucune annonce n’a été publiée.
                </div>
            @endforelse

            {{ $announcements->links() }}
        </div>
    </div>
</x-app-layout>
