<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wider text-teal-700">Suivi scolaire</p>
            <h2 class="mt-1 text-2xl font-bold text-slate-900">Bulletins</h2>
            <p class="mt-1 text-sm text-slate-500">Consultez les bulletins trimestriels, semestriels et annuels.</p>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl space-y-4 px-4 sm:px-6 lg:px-8">
            <section class="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                        <tr>
                            <th class="px-4 py-3">Élève</th>
                            <th class="px-4 py-3">Classe</th>
                            <th class="px-4 py-3">Année scolaire</th>
                            <th class="px-4 py-3">Inscription</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($enrollments as $enrollment)
                            <tr>
                                <td class="px-4 py-3 font-medium text-slate-900">{{ $enrollment->student->full_name }}</td>
                                <td class="px-4 py-3 text-slate-700">
                                    {{ $enrollment->classroom->name }}
                                    <span class="text-slate-500">· {{ $enrollment->classroom->level->name }}</span>
                                </td>
                                <td class="px-4 py-3 text-slate-700">{{ $enrollment->schoolYear->label }}</td>
                                <td class="px-4 py-3 text-slate-700">
                                    {{ \App\Support\Format::date($enrollment->enrolled_at) }}
                                    <span class="text-slate-500">→ {{ $enrollment->ended_at ? \App\Support\Format::date($enrollment->ended_at) : 'en cours' }}</span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <a href="{{ route('report-cards.show', $enrollment) }}" class="font-semibold text-teal-800 hover:underline">
                                        Choisir une période
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-10 text-center text-slate-500">
                                    Aucune inscription ne permet encore de générer un bulletin.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </section>

            {{ $enrollments->links() }}
        </div>
    </div>
</x-app-layout>
