<div class="bg-white p-6 shadow-sm sm:rounded-lg">
    <div class="flex items-center justify-between">
        <h3 class="text-sm font-semibold uppercase tracking-wider text-gray-500">Scolarité ({{ $student->enrollments->count() }})</h3>
        @if ($student->status === \App\Enums\StudentStatus::Active)
            <a href="{{ route('admin.students.enrollments.create', $student) }}" class="text-sm text-indigo-700 hover:underline">Inscrire / réinscrire</a>
        @endif
    </div>

    <div class="mt-3 overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
                <tr>
                    <th class="py-2 pr-4">Année</th>
                    <th class="py-2 pr-4">Classe</th>
                    <th class="py-2 pr-4">Période</th>
                    <th class="py-2 pr-4">Statut</th>
                    <th class="py-2"><span class="sr-only">Actions</span></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($student->enrollments as $enrollment)
                    <tr>
                        <td class="py-2 pr-4">{{ $enrollment->schoolYear->label }}</td>
                        <td class="py-2 pr-4">
                            <a href="{{ route('admin.classrooms.show', $enrollment->classroom_id) }}" class="text-indigo-700 hover:underline">{{ $enrollment->classroom->name }}</a>
                            <span class="text-gray-500">({{ $enrollment->classroom->level->name }})</span>
                        </td>
                        <td class="py-2 pr-4 whitespace-nowrap">
                            {{ \App\Support\Format::date($enrollment->enrolled_at) }} → {{ $enrollment->ended_at ? \App\Support\Format::date($enrollment->ended_at) : 'en cours' }}
                        </td>
                        <td class="py-2 pr-4"><x-badge :color="$enrollment->status->color()">{{ $enrollment->status->label() }}</x-badge></td>
                        <td class="py-2 text-right">
                            @if ($enrollment->isActive())
                                <div class="flex flex-wrap items-center justify-end gap-3">
                                    <a href="{{ route('admin.enrollments.transfer.create', $enrollment) }}" class="text-gray-600 hover:underline">Changer de classe</a>
                                    <form method="POST" action="{{ route('admin.enrollments.withdraw', $enrollment) }}" class="inline-flex items-center gap-2"
                                          onsubmit="return confirm('Retirer cet élève de la classe ? L\'historique sera conservé.');">
                                        @csrf
                                        <input type="date" name="date" value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}" required
                                               class="rounded-md border-gray-300 py-1 text-xs shadow-sm" aria-label="Date du retrait">
                                        <button type="submit" class="text-red-600 hover:underline">Retirer</button>
                                    </form>
                                </div>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-6 text-center text-gray-500">Cet élève n'est inscrit dans aucune classe.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
