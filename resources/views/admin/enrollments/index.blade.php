<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Inscriptions</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            <x-flash />

            <form method="GET" class="bg-white p-4 shadow-sm sm:rounded-lg grid gap-3 sm:grid-cols-5">
                <input type="search" name="search" value="{{ request('search') }}" placeholder="Nom, matricule de l'élève…"
                       class="border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <x-select name="school_year_id" :options="$schoolYears" :selected="$yearId" placeholder="Toutes les années" />
                <x-select name="classroom_id" :options="$classrooms" :selected="request('classroom_id')" placeholder="Toutes les classes" />
                <x-select name="status" :options="\App\Enums\EnrollmentStatus::options()" :selected="request('status')" placeholder="Tous les statuts" />
                <div class="flex gap-2">
                    <x-primary-button>Filtrer</x-primary-button>
                    <a href="{{ route('admin.enrollments.index') }}" class="inline-flex items-center px-3 text-sm text-gray-600 hover:underline">Réinitialiser</a>
                </div>
            </form>

            <p class="text-sm text-gray-600">
                Pour inscrire un élève, ouvrez sa fiche puis cliquez sur « Inscrire ».
                <a href="{{ route('admin.students.index') }}" class="text-indigo-700 hover:underline">Liste des élèves</a>
            </p>

            <div class="bg-white shadow-sm sm:rounded-lg overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
                        <tr>
                            <th class="px-4 py-3">Élève</th>
                            <th class="px-4 py-3">Classe</th>
                            <th class="px-4 py-3 hidden sm:table-cell">Année</th>
                            <th class="px-4 py-3 hidden md:table-cell">Période</th>
                            <th class="px-4 py-3">Statut</th>
                            <th class="px-4 py-3"><span class="sr-only">Paiements</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($enrollments as $enrollment)
                            <tr>
                                <td class="px-4 py-3">
                                    <a href="{{ route('admin.students.show', $enrollment->student_id) }}" class="font-medium text-indigo-700 hover:underline">{{ $enrollment->student->full_name }}</a>
                                    <span class="block font-mono text-xs text-gray-500">{{ $enrollment->student->matricule }}</span>
                                </td>
                                <td class="px-4 py-3">
                                    <a href="{{ route('admin.classrooms.show', $enrollment->classroom_id) }}" class="text-indigo-700 hover:underline">{{ $enrollment->classroom->name }}</a>
                                    <span class="text-gray-500">({{ $enrollment->classroom->level->name }})</span>
                                </td>
                                <td class="px-4 py-3 hidden sm:table-cell">{{ $enrollment->schoolYear->label }}</td>
                                <td class="px-4 py-3 hidden md:table-cell whitespace-nowrap">
                                    {{ \App\Support\Format::date($enrollment->enrolled_at) }} → {{ $enrollment->ended_at ? \App\Support\Format::date($enrollment->ended_at) : 'en cours' }}
                                </td>
                                <td class="px-4 py-3"><x-badge :color="$enrollment->status->color()">{{ $enrollment->status->label() }}</x-badge></td>
                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                    <a href="{{ route('admin.enrollments.statement', $enrollment) }}" class="text-indigo-700 hover:underline">Paiements</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-8 text-center text-gray-500">Aucune inscription trouvée.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $enrollments->links() }}
        </div>
    </div>
</x-app-layout>
