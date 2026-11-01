<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Emplois du temps</h2>
            <a href="{{ route('admin.schedules.create') }}" class="inline-flex items-center rounded-md bg-gray-800 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-gray-700">
                Ajouter une séance
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl space-y-4 sm:px-6 lg:px-8">
            <x-flash />
            <form method="GET" class="grid gap-3 rounded-lg bg-white p-4 shadow-sm sm:grid-cols-2 xl:grid-cols-4">
                <x-select name="school_year_id" :options="$schoolYears" :selected="request('school_year_id', $schoolYearId)" placeholder="Choisir une année" />
                <x-select name="classroom_id" :options="$classrooms" :selected="request('classroom_id')" placeholder="Toutes les classes" />
                <x-select name="teacher_id" :options="$teachers" :selected="request('teacher_id')" placeholder="Tous les enseignants" />
                <div class="flex gap-2">
                    <x-primary-button>Filtrer</x-primary-button>
                    <a href="{{ route('admin.schedules.index') }}" class="inline-flex items-center px-3 text-sm text-gray-600 hover:underline">Réinitialiser</a>
                </div>
            </form>

            <div class="overflow-x-auto rounded-lg bg-white shadow-sm">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
                        <tr>
                            <th class="px-4 py-3">Jour</th>
                            <th class="px-4 py-3">Horaire</th>
                            <th class="px-4 py-3">Classe</th>
                            <th class="px-4 py-3">Matière</th>
                            <th class="px-4 py-3">Enseignant</th>
                            <th class="px-4 py-3">Salle</th>
                            <th class="px-4 py-3"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($schedules as $schedule)
                            <tr>
                                <td class="whitespace-nowrap px-4 py-3 font-medium">{{ $schedule->day_of_week->label() }}</td>
                                <td class="whitespace-nowrap px-4 py-3">{{ $schedule->startTime() }}–{{ $schedule->endTime() }}</td>
                                <td class="px-4 py-3">{{ $schedule->classroom->name }}</td>
                                <td class="px-4 py-3">{{ $schedule->assignment->subject->name }}</td>
                                <td class="px-4 py-3">{{ $schedule->assignment->teacher->full_name }}</td>
                                <td class="px-4 py-3">{{ $schedule->room->name }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-right">
                                    @if ($canManage)
                                        <a href="{{ route('admin.schedules.edit', $schedule) }}" class="text-indigo-700 hover:underline">Modifier</a>
                                    @endif
                                    <form method="POST" action="{{ route('admin.schedules.destroy', $schedule) }}" class="inline" onsubmit="return confirm('Retirer cette séance ?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="ml-3 text-red-600 hover:underline">Retirer</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-4 py-8 text-center text-gray-500">Aucune séance planifiée pour cette année et ces filtres.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $schedules->links() }}
            <p class="text-sm text-gray-500">Chaque séance se répète chaque semaine pendant l’année scolaire active.</p>
        </div>
    </div>
</x-app-layout>
