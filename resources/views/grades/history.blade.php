<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $title }}</h2>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
            @if ($summaries->isNotEmpty())
                <section class="space-y-3">
                    <h3 class="font-semibold text-gray-900">Moyennes par classe et année</h3>
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($summaries as $summary)
                            <article class="space-y-2 rounded-lg bg-white p-5 shadow-sm">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        @if (isset($summary['student']))
                                            <p class="text-sm text-gray-500">{{ $summary['student']->full_name }}</p>
                                        @endif
                                        <h4 class="font-semibold text-gray-900">{{ $summary['classroom']->name }}</h4>
                                        <p class="text-sm text-gray-500">{{ $summary['school_year']->label }}</p>
                                    </div>
                                    <span class="font-semibold text-indigo-700">{{ number_format($summary['average'], 2, ',', ' ') }} / 20</span>
                                </div>
                                <ul class="space-y-1 text-sm text-gray-600">
                                    @foreach ($summary['subjects'] as $subject => $average)
                                        <li class="flex justify-between gap-3"><span>{{ $subject }}</span><span>{{ number_format($average, 2, ',', ' ') }} / 20</span></li>
                                    @endforeach
                                </ul>
                            </article>
                        @endforeach
                    </div>
                </section>
            @endif

            <section class="overflow-x-auto rounded-lg bg-white shadow-sm">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
                        <tr>
                            @if ($showStudent)<th class="px-4 py-3">Élève</th>@endif
                            <th class="px-4 py-3">Date</th>
                            <th class="px-4 py-3">Classe / année</th>
                            <th class="px-4 py-3">Matière</th>
                            <th class="px-4 py-3">Évaluation</th>
                            <th class="px-4 py-3">Note</th>
                            <th class="px-4 py-3">Commentaire</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($grades as $grade)
                            <tr>
                                @if ($showStudent)<td class="px-4 py-3">{{ $grade->enrollment->student->full_name }}</td>@endif
                                <td class="whitespace-nowrap px-4 py-3">{{ \App\Support\Format::date($grade->evaluation->evaluation_date) }}</td>
                                <td class="px-4 py-3">{{ $grade->evaluation->classroom->name }} / {{ $grade->evaluation->classroom->schoolYear->label }}</td>
                                <td class="px-4 py-3">{{ $grade->evaluation->assignment->subject->name }}</td>
                                <td class="px-4 py-3">{{ $grade->evaluation->title }}</td>
                                <td class="whitespace-nowrap px-4 py-3 font-semibold">{{ number_format((float) $grade->score, 2, ',', ' ') }} / 20</td>
                                <td class="px-4 py-3">{{ $grade->comment ?: '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="{{ $showStudent ? 7 : 6 }}" class="px-4 py-8 text-center text-gray-500">Aucune note publiée pour le moment.</td></tr>
                        @endforelse
                    </tbody>
                </table>
                {{ $grades->links() }}
            </section>
        </div>
    </div>
</x-app-layout>
