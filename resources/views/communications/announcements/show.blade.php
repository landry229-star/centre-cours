<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wider text-teal-700">Communications</p>
            <h2 class="mt-1 text-2xl font-bold text-slate-900">{{ $announcement->title }}</h2>
            <p class="mt-1 text-sm text-slate-500">
                Publiée le {{ \App\Support\Format::dateTime($announcement->published_at) }}
                · {{ $announcement->classroom?->name ?? 'Toutes les familles' }}
            </p>
        </div>
    </x-slot>

    <div class="py-8">
        <article class="mx-auto max-w-3xl space-y-5 px-4 sm:px-6 lg:px-8">
            <a href="{{ route('communications.announcements.index') }}" class="text-sm font-semibold text-teal-800 hover:underline">← Retour aux annonces</a>
            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <p class="whitespace-pre-line text-slate-700">{{ $announcement->body }}</p>
                <p class="mt-6 border-t border-slate-100 pt-4 text-xs text-slate-500">
                    Publiée par {{ $announcement->author->name }}
                    @if ($announcement->schoolYear)
                        · Année scolaire {{ $announcement->schoolYear->label }}
                    @endif
                </p>
            </section>
        </article>
    </div>
</x-app-layout>
