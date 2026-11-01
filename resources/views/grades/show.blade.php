<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $evaluation->title }}</h2>
                <p class="mt-1 text-sm text-gray-600">
                    {{ $evaluation->classroom->name }} · {{ $evaluation->assignment->subject->name }} ·
                    {{ \App\Support\Format::date($evaluation->evaluation_date) }} · {{ $evaluation->type->label() }}
                </p>
            </div>
            <a href="{{ route('grades.index', ['school_year_id' => $evaluation->classroom->school_year_id]) }}" class="text-sm text-indigo-700 hover:underline">Retour aux évaluations</a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
            <x-flash />

            @if ($evaluation->published_at)
                <section class="space-y-4 rounded-lg border border-emerald-200 bg-emerald-50 p-5">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h3 class="font-semibold text-emerald-900">Résultats publiés</h3>
                            <p class="text-sm text-emerald-800">
                                Le {{ \App\Support\Format::dateTime($evaluation->published_at) }}
                                @if ($evaluation->publisher) par {{ $evaluation->publisher->name }} @endif
                            </p>
                        </div>
                        <span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-800">Visible par les familles et les élèves</span>
                    </div>
                </section>
            @else
                @can('publish', $evaluation)
                    <form method="POST" action="{{ route('grades.publish', $evaluation) }}" class="flex flex-wrap items-center justify-between gap-4 rounded-lg border border-blue-200 bg-blue-50 p-4">
                        @csrf
                        <p class="text-sm text-blue-900">Les notes ne sont visibles par les familles et les élèves qu’après publication.</p>
                        <x-primary-button>Publier les résultats</x-primary-button>
                    </form>
                @endcan
            @endif

            @if (! $evaluation->published_at)
                <form method="POST" action="{{ route('grades.update', $evaluation) }}" class="space-y-4">
                    @csrf
                    @method('PUT')
                    <div class="overflow-x-auto rounded-lg bg-white shadow-sm">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
                            <tr>
                                <th class="px-4 py-3">Élève</th>
                                <th class="w-36 px-4 py-3">Note / 20</th>
                                <th class="px-4 py-3">Commentaire</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($roster as $enrollment)
                                @php($grade = $grades->get($enrollment->id))
                                <tr>
                                    <td class="px-4 py-3 font-medium text-gray-900">{{ $enrollment->student->full_name }}</td>
                                    <td class="px-4 py-3">
                                        <input name="entries[{{ $enrollment->id }}][score]" type="number" min="0" max="20" step="0.01" value="{{ old("entries.{$enrollment->id}.score", $grade?->score) }}" aria-label="Note de {{ $enrollment->student->full_name }}" class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        @error("entries.{$enrollment->id}.score")<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                                    </td>
                                    <td class="px-4 py-3">
                                        <input name="entries[{{ $enrollment->id }}][comment]" type="text" maxlength="255" value="{{ old("entries.{$enrollment->id}.comment", $grade?->comment) }}" aria-label="Commentaire de {{ $enrollment->student->full_name }}" class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="px-4 py-8 text-center text-gray-500">Aucun élève inscrit à cette date.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                    </div>
                    @if ($roster->isNotEmpty())
                        <div class="flex justify-end"><x-primary-button>Enregistrer les notes</x-primary-button></div>
                    @endif
                </form>
            @else
                <section class="overflow-x-auto rounded-lg bg-white shadow-sm">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
                            <tr><th class="px-4 py-3">Élève</th><th class="px-4 py-3">Note / 20</th><th class="px-4 py-3">Appréciation</th><th class="px-4 py-3">Historique des corrections</th></tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($roster as $enrollment)
                                @php($grade = $grades->get($enrollment->id))
                                <tr class="align-top">
                                    <td class="px-4 py-3 font-medium text-gray-900">{{ $enrollment->student->full_name }}</td>
                                    <td class="px-4 py-3">{{ $grade?->score === null ? '—' : number_format((float) $grade->score, 2, ',', ' ').' / 20' }}</td>
                                    <td class="px-4 py-3">{{ $grade?->comment ?: '—' }}</td>
                                    <td class="min-w-72 space-y-3 px-4 py-3">
                                        @if ($grade)
                                            @forelse ($grade->corrections as $correction)
                                                <div class="rounded-lg bg-slate-50 p-3 text-xs text-slate-700">
                                                    <p class="font-semibold">{{ $correction->previous_score ?? '—' }} → {{ $correction->new_score }} / 20</p>
                                                    <p>{{ $correction->reason }}</p>
                                                    <p class="mt-1 text-slate-500">{{ $correction->correctedBy->name }} · {{ \App\Support\Format::dateTime($correction->created_at) }}</p>
                                                </div>
                                            @empty
                                                <p class="text-xs text-slate-500">Aucune correction.</p>
                                            @endforelse

                                            @can('correct', $grade)
                                                <form method="POST" action="{{ route('grades.corrections.store', $grade) }}" class="space-y-2 rounded-lg border border-slate-200 p-3">
                                                    @csrf
                                                    @method('PATCH')
                                                    <label class="block space-y-1 text-xs font-medium text-slate-700">
                                                        <span>Nouvelle note / 20</span>
                                                        <input name="score" type="number" min="0" max="20" step="0.01" value="{{ old('score', $grade->score) }}" required class="w-full rounded-md border-slate-300 text-sm shadow-sm">
                                                    </label>
                                                    <label class="block space-y-1 text-xs font-medium text-slate-700">
                                                        <span>Motif obligatoire</span>
                                                        <textarea name="reason" rows="2" minlength="8" maxlength="2000" required class="w-full rounded-md border-slate-300 text-sm shadow-sm">{{ old('reason') }}</textarea>
                                                    </label>
                                                    <button type="submit" class="rounded-md bg-amber-700 px-3 py-2 text-xs font-semibold text-white hover:bg-amber-800">Corriger la note</button>
                                                </form>
                                            @endcan
                                        @else
                                            <span class="text-xs text-slate-500">Aucune note enregistrée.</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="px-4 py-8 text-center text-gray-500">Aucun élève inscrit à cette date.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </section>
            @endif

            <section class="space-y-3 rounded-lg bg-white p-5 shadow-sm">
                <div>
                    <h3 class="font-semibold text-gray-900">Classement annuel de la classe</h3>
                    <p class="text-sm text-gray-600">Moyenne pondérée sur les notes disponibles de l'année scolaire. Les notes non saisies sont exclues.</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
                            <tr><th class="px-4 py-3">Rang</th><th class="px-4 py-3">Élève</th><th class="px-4 py-3">Moyenne générale</th></tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($ranking as $row)
                                <tr>
                                    <td class="px-4 py-3">{{ $row['rank'] }}</td>
                                    <td class="px-4 py-3">{{ $row['student']->full_name }}</td>
                                    <td class="px-4 py-3 font-semibold">{{ number_format($row['average'], 2, ',', ' ') }} / 20</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="px-4 py-5 text-center text-gray-500">Le classement apparaîtra après la saisie de notes.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
