<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Classes</h2>
            <a href="{{ route('admin.classrooms.create') }}"
               class="inline-flex items-center px-4 py-2 bg-gray-800 rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                Nouvelle classe
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            <x-flash />

            <form method="GET" class="bg-white p-4 shadow-sm sm:rounded-lg grid gap-3 sm:grid-cols-5">
                <input type="search" name="search" value="{{ request('search') }}" placeholder="Nom de classe ou niveau (CI, CP…)…"
                       class="border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <x-select name="school_year_id" :options="$schoolYears" :selected="request('school_year_id')" placeholder="Toutes les années" />
                <x-select name="level_id" :options="$levels" :selected="request('level_id')" placeholder="Tous les niveaux" />
                <x-select name="status" :options="\App\Enums\ClassroomStatus::options()" :selected="request('status')" placeholder="Tous les statuts" />
                <div class="flex gap-2">
                    <x-primary-button>Filtrer</x-primary-button>
                    <a href="{{ route('admin.classrooms.index') }}" class="inline-flex items-center px-3 text-sm text-gray-600 hover:underline">Réinitialiser</a>
                </div>
            </form>

            <div class="bg-white shadow-sm sm:rounded-lg overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
                        <tr>
                            <th class="px-4 py-3">Classe</th>
                            <th class="px-4 py-3">Niveau</th>
                            <th class="px-4 py-3 hidden sm:table-cell">Année</th>
                            <th class="px-4 py-3 hidden md:table-cell">Salle</th>
                            <th class="px-4 py-3 hidden md:table-cell">Prof. principal</th>
                            <th class="px-4 py-3">Statut</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($classrooms as $classroom)
                            <tr>
                                <td class="px-4 py-3">
                                    <a href="{{ route('admin.classrooms.show', $classroom) }}" class="font-medium text-indigo-700 hover:underline">{{ $classroom->name }}</a>
                                    <span class="text-gray-500">({{ $classroom->capacity }} places)</span>
                                </td>
                                <td class="px-4 py-3">{{ $classroom->level->name }}</td>
                                <td class="px-4 py-3 hidden sm:table-cell">{{ $classroom->schoolYear->label }}</td>
                                <td class="px-4 py-3 hidden md:table-cell">{{ $classroom->room?->name ?? '—' }}</td>
                                <td class="px-4 py-3 hidden md:table-cell">{{ $classroom->headTeacher?->full_name ?? '—' }}</td>
                                <td class="px-4 py-3"><x-badge :color="$classroom->status->color()">{{ $classroom->status->label() }}</x-badge></td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-8 text-center text-gray-500">Aucune classe trouvée.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $classrooms->links() }}
        </div>
    </div>
</x-app-layout>
