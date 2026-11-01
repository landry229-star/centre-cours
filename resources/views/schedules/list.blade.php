<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $title }}</h2>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl space-y-4 sm:px-6 lg:px-8">
            <div class="overflow-x-auto rounded-lg bg-white shadow-sm">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
                        <tr>
                            <th class="px-4 py-3">Jour</th>
                            <th class="px-4 py-3">Horaire</th>
                            <th class="px-4 py-3">Classe</th>
                            <th class="px-4 py-3">Matière</th>
                            @if ($showTeacher)<th class="px-4 py-3">Enseignant</th>@endif
                            <th class="px-4 py-3">Salle</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($schedules as $schedule)
                            <tr>
                                <td class="whitespace-nowrap px-4 py-3 font-medium">{{ $schedule->day_of_week->label() }}</td>
                                <td class="whitespace-nowrap px-4 py-3">{{ $schedule->startTime() }}–{{ $schedule->endTime() }}</td>
                                <td class="px-4 py-3">{{ $schedule->classroom->name }}</td>
                                <td class="px-4 py-3">{{ $schedule->assignment->subject->name }}</td>
                                @if ($showTeacher)<td class="px-4 py-3">{{ $schedule->assignment->teacher->full_name }}</td>@endif
                                <td class="px-4 py-3">{{ $schedule->room->name }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="{{ $showTeacher ? 6 : 5 }}" class="px-4 py-8 text-center text-gray-500">Aucun cours planifié pour le moment.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <p class="text-sm text-gray-500">Cet emploi du temps est valable chaque semaine pendant l’année scolaire active.</p>
        </div>
    </div>
</x-app-layout>
