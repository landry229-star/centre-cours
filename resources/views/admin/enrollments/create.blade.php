<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Inscrire : {{ $student->full_name }}</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-xl mx-auto sm:px-6 lg:px-8">
            <form method="POST" action="{{ route('admin.students.enrollments.store', $student) }}" class="bg-white p-6 shadow-sm sm:rounded-lg space-y-4">
                @csrf

                <p class="text-sm text-gray-600">
                    Matricule <span class="font-mono">{{ $student->matricule }}</span>.
                    Pour une nouvelle année scolaire, choisissez une classe de cette année (réinscription).
                </p>

                <div>
                    <x-input-label for="classroom_id" value="Classe *" />
                    <select id="classroom_id" name="classroom_id" required
                            class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                        <option value="">Choisir une classe…</option>
                        @foreach ($classroomsByYear as $yearLabel => $classrooms)
                            <optgroup label="Année {{ $yearLabel }}">
                                @foreach ($classrooms as $classroom)
                                    @php
                                        $full = $classroom->active_enrollments_count >= $classroom->capacity;
                                        $busy = in_array($classroom->school_year_id, $busyYearIds, true);
                                    @endphp
                                    <option value="{{ $classroom->id }}" @disabled($full || $busy) @selected((string) old('classroom_id') === (string) $classroom->id)>
                                        {{ $classroom->name }} ({{ $classroom->level->name }}) · {{ $classroom->active_enrollments_count }}/{{ $classroom->capacity }} places
                                        @if ($busy) — déjà inscrit cette année @elseif ($full) — complète @endif
                                    </option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('classroom_id')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="date" value="Date d'inscription *" />
                    <x-text-input id="date" name="date" type="date" class="mt-1 block w-full" :value="old('date', now()->toDateString())" max="{{ now()->toDateString() }}" required />
                    <x-input-error :messages="$errors->get('date')" class="mt-2" />
                </div>

                <div class="flex items-center gap-3">
                    <x-primary-button>Inscrire</x-primary-button>
                    <a href="{{ route('admin.students.show', $student) }}" class="text-sm text-gray-600 hover:underline">Annuler</a>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
