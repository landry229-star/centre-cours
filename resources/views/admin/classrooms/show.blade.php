@php
    $subjectOptions = $availableSubjects->pluck('name', 'id')->all();
    $teacherOptions = $activeTeachers->mapWithKeys(fn ($t) => [$t->id => $t->full_name.' · '.$t->specialty])->all();
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ $classroom->name }}
                <x-badge class="ml-2" :color="$classroom->status->color()">{{ $classroom->status->label() }}</x-badge>
            </h2>
            <a href="{{ route('admin.classrooms.edit', $classroom) }}"
               class="inline-flex items-center px-4 py-2 bg-gray-800 rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                Modifier
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-4">
            <x-flash />

            <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                <h3 class="text-sm font-semibold uppercase tracking-wider text-gray-500">Informations</h3>
                <dl class="mt-3 grid grid-cols-2 gap-x-6 gap-y-3 text-sm sm:grid-cols-4">
                    <div><dt class="text-gray-500">Niveau</dt><dd>{{ $classroom->level->name }}</dd></div>
                    <div><dt class="text-gray-500">Année scolaire</dt><dd>{{ $classroom->schoolYear->label }}</dd></div>
                    <div><dt class="text-gray-500">Capacité</dt><dd>{{ $classroom->capacity }} places</dd></div>
                    <div><dt class="text-gray-500">Salle</dt><dd>{{ $classroom->room?->name ?? '—' }}</dd></div>
                    <div class="col-span-2"><dt class="text-gray-500">Professeur principal</dt><dd>{{ $classroom->headTeacher?->full_name ?? '—' }}</dd></div>
                </dl>
                <p class="mt-4 text-sm text-gray-600">Effectif : <strong>{{ $enrollments->count() }}</strong> / {{ $classroom->capacity }} places</p>
            </div>

            <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                <h3 class="text-sm font-semibold uppercase tracking-wider text-gray-500">Élèves inscrits ({{ $enrollments->count() }})</h3>
                <ul class="mt-3 divide-y divide-gray-100 text-sm">
                    @forelse ($enrollments as $enrollment)
                        <li class="flex items-center justify-between py-2">
                            <a href="{{ route('admin.students.show', $enrollment->student_id) }}" class="text-indigo-700 hover:underline">{{ $enrollment->student->full_name }}</a>
                            <span class="font-mono text-xs text-gray-500">{{ $enrollment->student->matricule }}</span>
                        </li>
                    @empty
                        <li class="py-2 text-gray-500">Aucun élève inscrit pour le moment.</li>
                    @endforelse
                </ul>
            </div>

            <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                <h3 class="text-sm font-semibold uppercase tracking-wider text-gray-500">Matières et enseignants ({{ $classroom->assignments->count() }})</h3>

                <div class="mt-3 overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
                            <tr>
                                <th class="py-2 pr-4">Matière</th>
                                <th class="py-2 pr-4">Enseignant</th>
                                <th class="py-2 pr-4">Coef.</th>
                                <th class="py-2 pr-4">h/sem.</th>
                                <th class="py-2"><span class="sr-only">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($classroom->assignments->sortBy(fn ($a) => $a->subject->name) as $assignment)
                                <tr>
                                    <td class="py-2 pr-4 font-medium">{{ $assignment->subject->name }}</td>
                                    <td class="py-2 pr-4">
                                        <a href="{{ route('admin.teachers.show', $assignment->teacher_id) }}" class="text-indigo-700 hover:underline">{{ $assignment->teacher->full_name }}</a>
                                    </td>
                                    <td class="py-2 pr-4">{{ $assignment->coefficient }}</td>
                                    <td class="py-2 pr-4">{{ $assignment->weekly_hours ?? '—' }}</td>
                                    <td class="py-2 text-right whitespace-nowrap space-x-3">
                                        <a href="{{ route('admin.assignments.edit', $assignment) }}" class="text-gray-600 hover:underline">Modifier</a>
                                        <form method="POST" action="{{ route('admin.assignments.destroy', $assignment) }}" class="inline"
                                              onsubmit="return confirm('Retirer cette matière de la classe ?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-red-600 hover:underline">Retirer</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="py-6 text-center text-gray-500">Aucune matière affectée.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <form method="POST" action="{{ route('admin.classrooms.assignments.store', $classroom) }}"
                      class="mt-6 border-t border-gray-100 pt-4 grid gap-3 sm:grid-cols-6 items-start">
                    @csrf
                    <div class="sm:col-span-2">
                        <x-select name="subject_id" :options="$subjectOptions" :selected="old('subject_id')" placeholder="Matière…" required />
                        <x-input-error :messages="$errors->get('subject_id')" class="mt-1" />
                    </div>
                    <div class="sm:col-span-2">
                        <x-select name="teacher_id" :options="$teacherOptions" :selected="old('teacher_id')" placeholder="Enseignant…" required />
                        <x-input-error :messages="$errors->get('teacher_id')" class="mt-1" />
                    </div>
                    <div>
                        <x-text-input name="coefficient" type="number" min="1" max="10" class="block w-full" :value="old('coefficient', 1)" placeholder="Coef." required />
                        <x-input-error :messages="$errors->get('coefficient')" class="mt-1" />
                    </div>
                    <div>
                        <x-text-input name="weekly_hours" type="number" min="1" max="40" class="block w-full" :value="old('weekly_hours')" placeholder="h/sem." />
                        <x-input-error :messages="$errors->get('weekly_hours')" class="mt-1" />
                    </div>
                    <div class="sm:col-span-6"><x-primary-button>Affecter la matière</x-primary-button></div>
                </form>
            </div>

            <form method="POST" action="{{ route('admin.classrooms.destroy', $classroom) }}"
                  onsubmit="return confirm('Supprimer cette classe ?');">
                @csrf
                @method('DELETE')
                <x-danger-button>Supprimer cette classe</x-danger-button>
            </form>
        </div>
    </div>
</x-app-layout>
