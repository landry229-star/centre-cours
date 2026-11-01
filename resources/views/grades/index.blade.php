<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Notes et évaluations</h2>
            <a href="{{ route('grades.create') }}" class="inline-flex items-center rounded-md bg-gray-800 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-gray-700">
                Nouvelle évaluation
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl space-y-4 sm:px-6 lg:px-8">
            <x-flash />

            <form method="GET" class="grid gap-3 rounded-lg bg-white p-4 shadow-sm sm:grid-cols-3">
                <x-select name="school_year_id" :options="$schoolYears" :selected="request('school_year_id', $schoolYearId)" placeholder="Choisir une année" />
                <x-select name="classroom_id" :options="$classrooms" :selected="request('classroom_id')" placeholder="Toutes les classes" />
                <div class="flex gap-2">
                    <x-primary-button>Filtrer</x-primary-button>
                    <a href="{{ route('grades.index') }}" class="inline-flex items-center px-3 text-sm text-gray-600 hover:underline">Réinitialiser</a>
                </div>
            </form>

            <div class="overflow-x-auto rounded-lg bg-white shadow-sm">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
                        <tr>
                            <th class="px-4 py-3">Date</th>
                            <th class="px-4 py-3">Évaluation</th>
                            <th class="px-4 py-3">Classe / matière</th>
                            <th class="px-4 py-3">Enseignant</th>
                            <th class="px-4 py-3">Coefficient</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($evaluations as $evaluation)
                            <tr>
                                <td class="whitespace-nowrap px-4 py-3">{{ \App\Support\Format::date($evaluation->evaluation_date) }}</td>
                                <td class="px-4 py-3">
                                    <a href="{{ route('grades.show', $evaluation) }}" class="font-medium text-indigo-700 hover:underline">{{ $evaluation->title }}</a>
                                    <span class="block text-xs text-gray-500">{{ $evaluation->type->label() }}</span>
                                </td>
                                <td class="px-4 py-3">{{ $evaluation->classroom->name }} / {{ $evaluation->assignment->subject->name }}</td>
                                <td class="px-4 py-3">{{ $evaluation->teacher->full_name }}</td>
                                <td class="px-4 py-3">{{ $evaluation->coefficient }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-4 py-8 text-center text-gray-500">Aucune évaluation pour ces filtres.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $evaluations->links() }}
        </div>
    </div>
</x-app-layout>
